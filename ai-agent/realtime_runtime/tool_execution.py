from __future__ import annotations

import asyncio
from dataclasses import dataclass
from enum import StrEnum
from typing import Any, Awaitable, Callable


class ToolKind(StrEnum):
    READ_ONLY = "read_only"
    IDEMPOTENT = "idempotent"
    SIDE_EFFECTING = "side_effecting"
    IRREVERSIBLE = "irreversible"


@dataclass(slots=True)
class ToolExecution:
    session_id: str
    task_id: str
    idempotency_key: str
    kind: ToolKind
    task: asyncio.Task[Any]
    cancel_requested: bool = False


class ToolExecutionRegistry:
    """Small in-process policy interface; it does not register product tools."""

    def __init__(self) -> None:
        self._by_key: dict[tuple[str, str], ToolExecution] = {}
        self._by_task: dict[tuple[str, str], ToolExecution] = {}
        self._lock = asyncio.Lock()

    async def execute(
        self,
        *,
        session_id: str,
        task_id: str,
        idempotency_key: str,
        kind: ToolKind,
        operation: Callable[[], Awaitable[Any]],
    ) -> Any:
        identity = (session_id, idempotency_key)
        async with self._lock:
            existing = self._by_key.get(identity)
            if existing is not None:
                if existing.task.cancelled():
                    raise asyncio.CancelledError
                task = existing.task
            else:
                task = asyncio.create_task(operation(), name=f"tool-{task_id}")
                execution = ToolExecution(session_id, task_id, idempotency_key, kind, task)
                self._by_key[identity] = execution
                self._by_task[(session_id, task_id)] = execution
        return await asyncio.shield(task)

    async def cancel(self, session_id: str, task_id: str) -> bool:
        async with self._lock:
            execution = self._by_task.get((session_id, task_id))
            if execution is None or execution.task.done():
                return False
            if execution.kind in {ToolKind.READ_ONLY, ToolKind.IDEMPOTENT}:
                execution.task.cancel()
                return True
            execution.cancel_requested = True
            return False

    def active_task_ids(self, session_id: str) -> set[str]:
        return {
            task_id
            for (owner, task_id), execution in self._by_task.items()
            if owner == session_id and not execution.task.done()
        }

    def get_status(self, session_id: str, task_id: str) -> dict[str, str | bool] | None:
        execution = self._by_task.get((session_id, task_id))
        if execution is None:
            return None
        if execution.task.cancelled():
            state = "cancelled"
        elif execution.task.done() and execution.task.exception() is None:
            state = "completed"
        elif execution.task.done():
            state = "failed"
        else:
            state = "running"
        return {
            "task_id": execution.task_id,
            "kind": execution.kind.value,
            "state": state,
            "cancel_requested": execution.cancel_requested,
        }
