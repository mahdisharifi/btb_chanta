from __future__ import annotations

from collections import defaultdict, deque
import os
import resource
from threading import Lock
import time


class RealtimeMetrics:
    """Bounded in-memory latency samples; accepts durations only, never user data."""

    def __init__(self, *, samples_per_metric: int = 512) -> None:
        self._samples: dict[str, deque[float]] = defaultdict(
            lambda: deque(maxlen=samples_per_metric)
        )
        self._counters: dict[str, int] = defaultdict(int)
        self._lock = Lock()

    def record(self, name: str, duration_ms: float | int | None) -> None:
        if duration_ms is None:
            return
        try:
            value = float(duration_ms)
        except (TypeError, ValueError):
            return
        if value < 0 or value != value or value in (float("inf"), float("-inf")):
            return
        with self._lock:
            self._samples[name].append(value)

    def increment(self, name: str, amount: int = 1) -> None:
        if not name or amount < 1:
            return
        with self._lock:
            self._counters[name] += amount

    def snapshot(self) -> dict[str, dict[str, float | int]]:
        with self._lock:
            rows = {name: tuple(values) for name, values in self._samples.items() if values}
        result: dict[str, dict[str, float | int]] = {}
        for name, values in rows.items():
            ordered = sorted(values)

            def percentile(percent: float) -> float:
                index = (len(ordered) - 1) * percent
                low = int(index)
                high = min(low + 1, len(ordered) - 1)
                fraction = index - low
                return round(ordered[low] * (1 - fraction) + ordered[high] * fraction, 1)

            result[name] = {
                "p50_ms": percentile(0.50),
                "p95_ms": percentile(0.95),
                "max_ms": round(ordered[-1], 1),
                "sample_count": len(ordered),
            }
        return result

    def counter_snapshot(self) -> dict[str, int]:
        with self._lock:
            return dict(self._counters)


class ProcessResourceMetrics:
    """Dependency-free process CPU and resident-memory readings for status."""

    def __init__(self) -> None:
        self._lock = Lock()
        self._last_wall: float | None = None
        self._last_cpu: float | None = None

    def snapshot(self) -> dict[str, float | int | None]:
        wall_now = time.monotonic()
        cpu_now = time.process_time()
        with self._lock:
            cpu_percent: float | None = None
            if self._last_wall is not None and self._last_cpu is not None:
                elapsed = wall_now - self._last_wall
                if elapsed > 0:
                    # 100% means one fully occupied CPU core; multi-threaded
                    # processes may report more than 100% on multicore hosts.
                    cpu_percent = round(max(0.0, (cpu_now - self._last_cpu) / elapsed * 100.0), 1)
            self._last_wall = wall_now
            self._last_cpu = cpu_now

        rss_mib = None
        try:
            with open("/proc/self/statm", "r", encoding="ascii") as statm:
                resident_pages = int(statm.read().split()[1])
            rss_mib = round(resident_pages * os.sysconf("SC_PAGE_SIZE") / (1024 * 1024), 1)
        except (OSError, IndexError, ValueError):
            pass

        peak_rss = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss
        peak_rss_mib = round(peak_rss / (1024 if os.name == "posix" else 1024 * 1024), 1)
        usage = resource.getrusage(resource.RUSAGE_SELF)
        return {
            "process_cpu_percent_since_last_status": cpu_percent,
            "cpu_percent_basis": "100_percent_equals_one_core",
            "process_cpu_seconds": round(usage.ru_utime + usage.ru_stime, 3),
            "process_rss_mib": rss_mib,
            "process_peak_rss_mib": peak_rss_mib,
        }
