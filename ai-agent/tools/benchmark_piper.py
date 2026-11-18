#!/usr/bin/env python3
"""Warm/cold Piper benchmark with temporary WAVs and machine-readable metrics."""

from __future__ import annotations

import argparse
import asyncio
import csv
import json
import resource
import statistics
import sys
import time
import wave
from datetime import datetime
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT_ROOT))

import app
from tts.piper_presets import PIPER_PRESETS, piper_synthesis_config
from realtime_runtime.piper_scheduler import PiperStreamScheduler


SAMPLE_TEXTS = (
    "سلام، خوبی؟ من اینجام تا با خیال راحت کمکت کنم.",
    "آها، فهمیدم چی می‌گی؛ بیا مرحله‌به‌مرحله جلو بریم.",
    "قیمت محصول دویست و پنجاه هزار تومان است. اگر سؤال دیگری داری، بپرس.",
)
PLAYBACK_BUFFER_SECONDS = 0.12


def synthesize(text: str, preset: str, output_path: Path | None = None) -> dict[str, object]:
    voice = app._get_piper_voice()
    metrics: dict[str, object] = {"request_started_at": time.perf_counter()}
    cpu_start = time.process_time()
    wall_start = time.perf_counter()
    chunks = list(app._iter_piper_pcm(text, piper_synthesis_config(preset), metrics))
    wall_seconds = time.perf_counter() - wall_start
    cpu_seconds = time.process_time() - cpu_start
    pcm_bytes = sum(map(len, chunks))
    pcm_frames = pcm_bytes // (2 * 1)
    audio_seconds = pcm_frames / voice.config.sample_rate
    chunk_metrics = metrics.get("chunks", [])
    cumulative_bytes = 0
    first_playable_seconds = None
    playable_bytes = int(voice.config.sample_rate * 2 * PLAYBACK_BUFFER_SECONDS)
    for chunk in chunk_metrics if isinstance(chunk_metrics, list) else []:
        cumulative_bytes += int(chunk["bytes"])
        if cumulative_bytes >= playable_bytes:
            first_playable_seconds = float(chunk["ready_seconds"])
            break

    if output_path:
        output_path.parent.mkdir(parents=True, exist_ok=True)
        with wave.open(str(output_path), "wb") as wav:
            wav.setnchannels(1)
            wav.setsampwidth(2)
            wav.setframerate(voice.config.sample_rate)
            wav.writeframes(b"".join(chunks))

    return {
        "preset": preset,
        "text": text,
        "wav_path": str(output_path) if output_path else "",
        "time_to_first_pcm_ms": round(float(metrics.get("time_to_first_pcm_seconds", 0)) * 1000, 2),
        "time_to_first_playable_ms_estimate": round(first_playable_seconds * 1000, 2) if first_playable_seconds is not None else None,
        "total_synthesis_ms": round(wall_seconds * 1000, 2),
        "audio_duration_seconds": round(audio_seconds, 3),
        "rtf": round(wall_seconds / audio_seconds, 3) if audio_seconds else None,
        "cpu_seconds": round(cpu_seconds, 3),
        "cpu_core_percent": round(cpu_seconds / wall_seconds * 100, 1) if wall_seconds else None,
        "peak_process_rss_mib": round(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024, 1),
        "queue_wait_ms": round(float(metrics.get("queue_wait_seconds", 0)) * 1000, 2),
        "pcm_bytes": pcm_bytes,
        "pcm_frames": pcm_frames,
        "chunk_count": len(chunks),
        "chunk_bytes": [len(chunk) for chunk in chunks],
    }


def concurrent_queue_check() -> list[dict[str, object]]:
    async def run() -> list[dict[str, object]]:
        scheduler = PiperStreamScheduler(app._iter_piper_pcm, max_pending=2)
        scheduler.start()
        try:
            jobs = await asyncio.gather(*(
                scheduler.submit(
                    session_id=f"benchmark-{index}",
                    turn_id=f"turn-{index}",
                    generation_id=f"generation-{index}",
                    text=text,
                    syn_config=piper_synthesis_config("default"),
                )
                for index, text in enumerate(SAMPLE_TEXTS[:2])
            ))

            async def collect(job):
                byte_count = 0
                chunk_count = 0
                async for pcm in scheduler.stream(job):
                    byte_count += len(pcm)
                    chunk_count += 1
                metrics = job.metrics
                return {
                    "session_id": job.session_id,
                    "turn_id": job.turn_id,
                    "generation_id": job.generation_id,
                    "queue_wait_ms": round(float(metrics.get("scheduler_queue_wait_seconds", 0)) * 1000, 2),
                    "time_to_first_pcm_ms": round(float(metrics.get("time_to_first_pcm_seconds", 0)) * 1000, 2),
                    "total_generation_ms": round((float(metrics.get("finished_at", time.perf_counter())) - job.queued_at) * 1000, 2),
                    "pcm_bytes": byte_count,
                    "chunks": chunk_count,
                }

            return await asyncio.gather(*(collect(job) for job in jobs))
        finally:
            await scheduler.close()

    return asyncio.run(run())


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repeats", type=int, default=3)
    parser.add_argument("--output-dir", type=Path)
    parser.add_argument("--skip-concurrency", action="store_true")
    args = parser.parse_args()
    if args.repeats < 1:
        parser.error("--repeats must be at least 1")

    output_dir = args.output_dir or Path("/tmp") / f"piper-benchmark-{datetime.now():%Y%m%d-%H%M%S}"
    output_dir.mkdir(parents=True, exist_ok=True)
    cold_started = time.perf_counter()
    app._get_piper_voice()
    cold_model_load_seconds = time.perf_counter() - cold_started
    voice = app._get_piper_voice()

    rows = []
    for preset in PIPER_PRESETS:
        for sentence_index, text in enumerate(SAMPLE_TEXTS, start=1):
            for repeat in range(1, args.repeats + 1):
                wav_path = output_dir / f"{preset}-{sentence_index:02d}-run-{repeat:02d}.wav"
                rows.append(synthesize(text, preset, wav_path))

    columns = list(rows[0])
    with (output_dir / "metrics.csv").open("w", newline="", encoding="utf-8-sig") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=columns)
        writer.writeheader()
        writer.writerows(rows)

    summary = {
        "model_path": str(app.TTS_MODEL),
        "piper_package_version": __import__("importlib.metadata", fromlist=["version"]).version("piper-tts"),
        "sample_rate": voice.config.sample_rate,
        "channels": 1,
        "sample_width_bits": 16,
        "model_defaults": {
            "length_scale": voice.config.length_scale,
            "noise_scale": voice.config.noise_scale,
            "noise_w_scale": voice.config.noise_w_scale,
        },
        "cold_model_load_seconds": round(cold_model_load_seconds, 3),
        "peak_process_rss_mib": round(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024, 1),
        "runs": len(rows),
        "by_preset": {},
        "rows": rows,
    }
    for preset in PIPER_PRESETS:
        selected = [row for row in rows if row["preset"] == preset]
        summary["by_preset"][preset] = {
            metric: round(statistics.median(float(row[metric]) for row in selected if row[metric] is not None), 3)
            for metric in ("time_to_first_pcm_ms", "time_to_first_playable_ms_estimate", "total_synthesis_ms", "audio_duration_seconds", "rtf")
        }
    if not args.skip_concurrency:
        summary["two_request_queue_check"] = concurrent_queue_check()

    summary_path = output_dir / "summary.json"
    summary_path.write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"output_dir": str(output_dir), "summary": str(summary_path), "by_preset": summary["by_preset"], "cold_model_load_seconds": summary["cold_model_load_seconds"], "concurrent_queue_check": summary.get("two_request_queue_check")}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
