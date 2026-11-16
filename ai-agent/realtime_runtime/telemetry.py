from __future__ import annotations

from time import perf_counter


class TurnTelemetry:
    """Small per-utterance latency trace; intentionally stores no audio or text."""

    def __init__(self, session_id: str, turn_id: int) -> None:
        self.session_id = session_id
        self.turn_id = turn_id
        self._created_at = perf_counter()
        self._open_spans: dict[str, float] = {}
        self._durations_ms: dict[str, float] = {}

    def start(self, stage: str) -> None:
        self._open_spans[stage] = perf_counter()

    def finish(self, stage: str) -> float:
        started_at = self._open_spans.pop(stage, None)
        if started_at is None:
            return 0.0
        elapsed_ms = round((perf_counter() - started_at) * 1000.0, 1)
        self._durations_ms[stage] = round(self._durations_ms.get(stage, 0.0) + elapsed_ms, 1)
        return elapsed_ms

    def snapshot(self, **measurements: float | int | str | None) -> dict[str, object]:
        return {
            "session_id": self.session_id,
            "turn_id": self.turn_id,
            "elapsed_ms": round((perf_counter() - self._created_at) * 1000.0, 1),
            "stages_ms": dict(self._durations_ms),
            **measurements,
        }
