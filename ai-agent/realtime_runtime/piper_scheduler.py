from __future__ import annotations

import asyncio
import json
import logging
import time
from collections import deque
from dataclasses import dataclass, field
from typing import Any, Callable, Iterator

from starlette.concurrency import run_in_threadpool


_END = object()
_logger = logging.getLogger("ai_agent.piper")


@dataclass(slots=True)
class PiperJob:
    session_id: str
    turn_id: str
    generation_id: str
    text: str
    syn_config: Any
    queued_at: float
    output: asyncio.Queue[Any] = field(default_factory=lambda: asyncio.Queue(maxsize=2))
    cancelled: asyncio.Event = field(default_factory=asyncio.Event)
    metrics: dict[str, Any] = field(default_factory=dict)
    finished: bool = False


class PiperQueueFull(RuntimeError):
    pass


class PiperStreamScheduler:
    """One CPU Piper worker with bounded admission and per-request PCM backpressure."""

    def __init__(
        self,
        synthesize: Callable[[str, Any, dict[str, Any]], Iterator[bytes]],
        *,
        max_pending: int = 2,
        max_pending_per_session: int = 1,
        output_chunks: int = 2,
        request_timeout_seconds: float = 60.0,
        chunk_timeout_seconds: float = 30.0,
        backpressure_timeout_seconds: float = 10.0,
        on_finished: Callable[[PiperJob], None] | None = None,
    ) -> None:
        self.synthesize = synthesize
        self.max_pending = max_pending
        self.max_pending_per_session = max_pending_per_session
        self.output_chunks = output_chunks
        self.request_timeout_seconds = request_timeout_seconds
        self.chunk_timeout_seconds = chunk_timeout_seconds
        self.backpressure_timeout_seconds = backpressure_timeout_seconds
        self.on_finished = on_finished
        self._condition = asyncio.Condition()
        self._queued: deque[PiperJob] = deque()
        self._active: PiperJob | None = None
        self._by_request: dict[tuple[str, str], PiperJob] = {}
        self._by_session: dict[str, PiperJob] = {}
        self._worker: asyncio.Task[None] | None = None
        self._closed = False

    def start(self) -> None:
        if self._worker is None:
            self._worker = asyncio.create_task(self._run(), name="piper-stream-worker-1")

    async def submit(
        self,
        *,
        session_id: str,
        turn_id: str,
        generation_id: str,
        text: str,
        syn_config: Any,
    ) -> PiperJob:
        key = (session_id, generation_id)
        async with self._condition:
            if self._closed or self._worker is None:
                raise RuntimeError("Piper worker is not available")
            if key in self._by_request:
                raise PiperQueueFull("duplicate_generation")
            existing = self._by_session.get(session_id)
            if existing is not None and not existing.finished:
                raise PiperQueueFull("session_busy")
            outstanding = len(self._queued) + (1 if self._active is not None else 0)
            if outstanding >= 1 + self.max_pending:
                raise PiperQueueFull("global_queue_full")
            session_pending = sum(1 for job in self._queued if job.session_id == session_id)
            if session_pending >= self.max_pending_per_session:
                raise PiperQueueFull("session_queue_full")

            job = PiperJob(
                session_id=session_id,
                turn_id=turn_id,
                generation_id=generation_id,
                text=text,
                syn_config=syn_config,
                queued_at=time.perf_counter(),
                output=asyncio.Queue(maxsize=self.output_chunks),
                metrics={"request_started_at": time.perf_counter()},
            )
            self._queued.append(job)
            self._by_request[key] = job
            self._by_session[session_id] = job
            self._condition.notify_all()
            return job

    async def cancel_generation(self, session_id: str, turn_id: str, generation_id: str) -> bool:
        key = (session_id, generation_id)
        async with self._condition:
            job = self._by_request.get(key)
            if job is None or job.turn_id != turn_id or job.finished:
                return False
            job.cancelled.set()
            if job is not self._active:
                try:
                    self._queued.remove(job)
                except ValueError:
                    pass
                await self._finish_queued_job(job)
            else:
                self._signal_output(job)
                # The native call still owns the sole worker, but a new turn
                # may occupy the bounded per-session pending slot behind it.
                if self._by_session.get(session_id) is job:
                    self._by_session.pop(session_id, None)
            self._condition.notify_all()
            return True

    async def cancel_session(self, session_id: str) -> None:
        async with self._condition:
            jobs = [job for job in self._by_request.values() if job.session_id == session_id]
            for job in jobs:
                job.cancelled.set()
                if job is not self._active:
                    try:
                        self._queued.remove(job)
                    except ValueError:
                        pass
                    await self._finish_queued_job(job)
                else:
                    self._signal_output(job)
                    if self._by_session.get(session_id) is job:
                        self._by_session.pop(session_id, None)
            self._condition.notify_all()

    async def stream(self, job: PiperJob):
        try:
            while True:
                item = await job.output.get()
                if item is _END:
                    return
                if isinstance(item, BaseException):
                    raise item
                yield item
        finally:
            if not job.finished:
                await self.cancel_generation(job.session_id, job.turn_id, job.generation_id)

    async def close(self) -> None:
        async with self._condition:
            self._closed = True
            for job in tuple(self._queued):
                job.cancelled.set()
                self._queued.remove(job)
                await self._finish_queued_job(job)
            if self._active is not None:
                self._active.cancelled.set()
                self._signal_output(self._active)
            self._condition.notify_all()
        if self._worker is not None:
            await self._worker

    def snapshot(self) -> dict[str, int | bool]:
        return {
            "workers": 1,
            "active": 1 if self._active is not None else 0,
            "pending": len(self._queued),
            "pending_limit": self.max_pending,
            "sessions_with_requests": len(self._by_session),
            "closed": self._closed,
        }

    async def _run(self) -> None:
        while True:
            async with self._condition:
                await self._condition.wait_for(lambda: self._closed or self._queued)
                if not self._queued and self._closed:
                    return
                job = self._queued.popleft()
                self._active = job
                job.metrics["scheduler_queue_wait_seconds"] = time.perf_counter() - job.queued_at
                job.metrics["scheduler_started_at"] = time.perf_counter()
            try:
                if not job.cancelled.is_set():
                    await self._synthesize_job(job)
            except asyncio.CancelledError:
                job.cancelled.set()
                raise
            except Exception as exc:
                _logger.warning("piper_request_failed kind=%s", type(exc).__name__)
                await self._terminate(job, RuntimeError("Piper synthesis failed"))
            finally:
                self._finish_job(job)
                async with self._condition:
                    if self._active is job:
                        self._active = None
                    self._condition.notify_all()

    async def _synthesize_job(self, job: PiperJob) -> None:
        iterator = iter(self.synthesize(job.text, job.syn_config, job.metrics))
        deadline = job.queued_at + self.request_timeout_seconds
        while not job.cancelled.is_set():
            remaining = deadline - time.perf_counter()
            if remaining <= 0:
                await self._terminate(job, TimeoutError("Piper request timed out"))
                return
            next_task = asyncio.create_task(run_in_threadpool(_next_pcm, iterator))
            done, _pending = await asyncio.wait(
                {next_task}, timeout=min(self.chunk_timeout_seconds, remaining)
            )
            if not done:
                job.cancelled.set()
                await self._terminate(job, TimeoutError("Piper chunk generation timed out"))
                # A running native inference cannot be killed safely. Keep the
                # single worker occupied until it returns, then discard its PCM.
                try:
                    await next_task
                except Exception:
                    pass
                return
            has_chunk, chunk = next_task.result()
            if not has_chunk:
                await self._terminate(job, None)
                return
            if job.cancelled.is_set():
                return
            if not job.metrics.get("first_pcm_event_logged"):
                job.metrics["first_pcm_event_logged"] = True
                _logger.info(
                    "voice_stage_event %s",
                    json.dumps({
                        "event": "tts_first_pcm",
                        "session_id": job.session_id,
                        "turn_id": job.turn_id,
                        "generation_id": job.generation_id,
                        "timestamp": time.perf_counter(),
                        "duration_ms": round((time.perf_counter() - job.queued_at) * 1000.0, 1),
                    }),
                )
            if not await self._publish_chunk(job, chunk):
                return

    async def _publish_chunk(self, job: PiperJob, chunk: bytes) -> bool:
        put_task = asyncio.create_task(job.output.put(chunk))
        cancel_task = asyncio.create_task(job.cancelled.wait())
        done, pending = await asyncio.wait(
            {put_task, cancel_task},
            timeout=self.backpressure_timeout_seconds,
            return_when=asyncio.FIRST_COMPLETED,
        )
        for task in pending:
            task.cancel()
        if pending:
            await asyncio.gather(*pending, return_exceptions=True)
        if cancel_task in done or job.cancelled.is_set():
            if not put_task.done():
                put_task.cancel()
            return False
        if put_task not in done:
            job.cancelled.set()
            await self._terminate(job, TimeoutError("Piper client backpressure timed out"))
            return False
        await put_task
        return True

    async def _terminate(self, job: PiperJob, error: BaseException | None) -> None:
        if error is not None:
            self._signal_output(job, error)
            return
        end_task = asyncio.create_task(job.output.put(_END))
        cancel_task = asyncio.create_task(job.cancelled.wait())
        done, pending = await asyncio.wait(
            {end_task, cancel_task},
            timeout=self.backpressure_timeout_seconds,
            return_when=asyncio.FIRST_COMPLETED,
        )
        for task in pending:
            task.cancel()
        if pending:
            await asyncio.gather(*pending, return_exceptions=True)
        if cancel_task in done or job.cancelled.is_set():
            if not end_task.done():
                end_task.cancel()
            return
        if end_task not in done:
            job.cancelled.set()
            self._signal_output(job, TimeoutError("Piper client backpressure timed out"))
            return
        await end_task

    def _signal_output(self, job: PiperJob, error: BaseException | None = None) -> None:
        while not job.output.empty():
            try:
                job.output.get_nowait()
            except asyncio.QueueEmpty:
                break
        if error is not None:
            job.output.put_nowait(error)
        else:
            job.output.put_nowait(_END)

    async def _finish_queued_job(self, job: PiperJob) -> None:
        self._signal_output(job)
        self._finish_job(job)

    def _finish_job(self, job: PiperJob) -> None:
        if job.finished:
            return
        job.metrics.setdefault("finished_at", time.perf_counter())
        job.finished = True
        self._by_request.pop((job.session_id, job.generation_id), None)
        if self._by_session.get(job.session_id) is job:
            self._by_session.pop(job.session_id, None)
        if self.on_finished is not None:
            try:
                self.on_finished(job)
            except Exception:
                _logger.warning("piper_metrics_callback_failed")


def _next_pcm(iterator: Iterator[bytes]) -> tuple[bool, bytes | None]:
    try:
        return True, next(iterator)
    except StopIteration:
        return False, None
