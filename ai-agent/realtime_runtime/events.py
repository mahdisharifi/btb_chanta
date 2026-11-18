from __future__ import annotations

import asyncio
import time
from collections import deque
from typing import Any


class SessionEventBus:
    """Bounded, ordered outbound event queue for one WebSocket session.

    Partial hypotheses with the same session/turn replace an older pending
    hypothesis. All other events apply backpressure instead of being dropped.
    """

    def __init__(self, *, capacity: int = 32) -> None:
        if capacity < 2:
            raise ValueError("Event bus capacity must be at least 2")
        self.capacity = capacity
        self._queue: deque[dict[str, Any]] = deque()
        self._condition = asyncio.Condition()
        self._closed = False

    async def publish(self, payload: dict[str, Any]) -> bool:
        event = dict(payload)
        event.setdefault("timestamp", time.perf_counter())
        event_type = event.get("type", "")
        async with self._condition:
            if self._closed:
                return False

            if event_type in {"partial", "asr.partial"}:
                key = (event.get("session_id"), event.get("utterance_id", event.get("turn_id")))
                for index, pending in enumerate(self._queue):
                    if pending.get("type") not in {"partial", "asr.partial"}:
                        continue
                    pending_key = (
                        pending.get("session_id"),
                        pending.get("utterance_id", pending.get("turn_id")),
                    )
                    if pending_key == key:
                        self._queue[index] = event
                        self._condition.notify_all()
                        return True

            await self._condition.wait_for(lambda: self._closed or len(self._queue) < self.capacity)
            if self._closed:
                return False
            self._queue.append(event)
            self._condition.notify_all()
            return True

    async def get(self) -> dict[str, Any] | None:
        async with self._condition:
            await self._condition.wait_for(lambda: self._closed or self._queue)
            if self._queue:
                event = self._queue.popleft()
                self._condition.notify_all()
                return event
            return None

    async def close(self, *, discard_pending: bool = False) -> None:
        async with self._condition:
            self._closed = True
            if discard_pending:
                self._queue.clear()
            self._condition.notify_all()

    @property
    def depth(self) -> int:
        return len(self._queue)
