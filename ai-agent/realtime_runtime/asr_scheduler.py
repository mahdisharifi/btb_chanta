from __future__ import annotations

import asyncio
from collections import OrderedDict, deque
from dataclasses import dataclass
from typing import Any


@dataclass(slots=True)
class AsrJob:
    kind: str
    session_id: str
    turn_id: int
    payload: Any


class RealtimeAsrQueue:
    """Serialize CPU-bound ASR, prioritize finals, and coalesce session previews."""

    def __init__(self, *, max_pending_finals: int = 8) -> None:
        self._condition = asyncio.Condition()
        self._finals: deque[AsrJob] = deque()
        self._latest_partials: OrderedDict[str, AsrJob] = OrderedDict()
        self._max_pending_finals = max_pending_finals
        self._closed = False

    async def submit_final(self, session_id: str, turn_id: int, payload: Any) -> bool:
        async with self._condition:
            # A preview that has not started is obsolete once its turn ends.
            partial = self._latest_partials.get(session_id)
            if partial and partial.turn_id == turn_id:
                self._latest_partials.pop(session_id, None)
            if self._closed or len(self._finals) >= self._max_pending_finals:
                return False
            self._finals.append(AsrJob("final", session_id, turn_id, payload))
            self._condition.notify()
            return True

    async def submit_partial(self, session_id: str, turn_id: int, payload: Any) -> None:
        async with self._condition:
            if self._closed:
                return
            self._latest_partials[session_id] = AsrJob("partial", session_id, turn_id, payload)
            self._latest_partials.move_to_end(session_id)
            self._condition.notify()

    async def get(self) -> AsrJob | None:
        async with self._condition:
            await self._condition.wait_for(
                lambda: self._closed or self._finals or self._latest_partials
            )
            if self._finals:
                return self._finals.popleft()
            if self._latest_partials:
                _session_id, job = self._latest_partials.popitem(last=False)
                return job
            return None

    async def discard_session(self, session_id: str) -> None:
        async with self._condition:
            self._latest_partials.pop(session_id, None)
            self._finals = deque(job for job in self._finals if job.session_id != session_id)
            self._condition.notify()

    async def close(self) -> None:
        async with self._condition:
            self._closed = True
            self._latest_partials.clear()
            self._condition.notify_all()
