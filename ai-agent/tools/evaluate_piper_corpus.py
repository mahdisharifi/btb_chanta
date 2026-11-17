#!/usr/bin/env python3
"""Generate Persian corpus WAVs and blank human-listening score sheets."""

from __future__ import annotations

import argparse
import csv
import json
import resource
import sys
import time
import wave
from datetime import datetime
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT_ROOT))

import app
from tts.piper_presets import PIPER_PRESETS, piper_synthesis_config


CORPUS_PATH = Path(__file__).resolve().parents[1] / "tests" / "data" / "persian_tts_corpus.json"
SCORE_FIELDS = (
    "pronunciation", "naturalness", "friendliness", "prosody", "intelligibility", "overall", "notes", "listener",
)
PLAYBACK_BUFFER_SECONDS = 0.12


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--preset", choices=tuple(PIPER_PRESETS), action="append")
    parser.add_argument("--output-dir", type=Path)
    args = parser.parse_args()
    presets = args.preset or ["default"]
    corpus = json.loads(CORPUS_PATH.read_text(encoding="utf-8"))
    if len(corpus) < 100:
        raise SystemExit(f"Corpus must contain at least 100 sentences; found {len(corpus)}")

    output_dir = args.output_dir or Path("/tmp") / f"piper-corpus-{datetime.now():%Y%m%d-%H%M%S}"
    output_dir.mkdir(parents=True, exist_ok=True)
    voice = app._get_piper_voice()
    metric_rows = []
    score_rows = []

    for preset in presets:
        for entry in corpus:
            text = entry["text"]
            metrics: dict[str, object] = {"request_started_at": time.perf_counter()}
            cpu_start = time.process_time()
            wall_start = time.perf_counter()
            chunks = list(app._iter_piper_pcm(text, piper_synthesis_config(preset), metrics))
            wall_seconds = time.perf_counter() - wall_start
            cpu_seconds = time.process_time() - cpu_start
            pcm_bytes = sum(map(len, chunks))
            frames = pcm_bytes // 2
            duration = frames / voice.config.sample_rate
            chunk_metrics = metrics.get("chunks", [])
            accumulated = 0
            first_playable_ms = None
            for chunk in chunk_metrics if isinstance(chunk_metrics, list) else []:
                accumulated += int(chunk["bytes"])
                if accumulated >= voice.config.sample_rate * 2 * PLAYBACK_BUFFER_SECONDS:
                    first_playable_ms = round(float(chunk["ready_seconds"]) * 1000, 2)
                    break

            wav_path = output_dir / preset / f"{entry['id']}.wav"
            wav_path.parent.mkdir(parents=True, exist_ok=True)
            with wave.open(str(wav_path), "wb") as wav:
                wav.setnchannels(1)
                wav.setsampwidth(2)
                wav.setframerate(voice.config.sample_rate)
                wav.writeframes(b"".join(chunks))

            row = {
                "id": entry["id"],
                "category": entry["category"],
                "preset": preset,
                "text": text,
                "wav_path": str(wav_path),
                "time_to_first_pcm_ms": round(float(metrics.get("time_to_first_pcm_seconds", 0)) * 1000, 2),
                "time_to_first_playable_ms_estimate": first_playable_ms,
                "total_synthesis_ms": round(wall_seconds * 1000, 2),
                "audio_duration_seconds": round(duration, 3),
                "rtf": round(wall_seconds / duration, 3) if duration else None,
                "cpu_seconds": round(cpu_seconds, 3),
                "peak_process_rss_mib": round(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024, 1),
                "queue_wait_ms": round(float(metrics.get("queue_wait_seconds", 0)) * 1000, 2),
                "pcm_bytes": pcm_bytes,
                "pcm_frames": frames,
                "chunk_count": len(chunks),
                **{field: "" for field in SCORE_FIELDS},
            }
            metric_rows.append(row)
            score_rows.append({
                "id": entry["id"],
                "category": entry["category"],
                "preset": preset,
                "text": text,
                "wav_path": str(wav_path),
                **{field: "" for field in SCORE_FIELDS},
            })

    for filename, rows in (("metrics.csv", metric_rows), ("human_scores.csv", score_rows)):
        with (output_dir / filename).open("w", newline="", encoding="utf-8-sig") as csv_file:
            writer = csv.DictWriter(csv_file, fieldnames=list(rows[0]))
            writer.writeheader()
            writer.writerows(rows)

    print(json.dumps({
        "corpus_path": str(CORPUS_PATH),
        "sentence_count": len(corpus),
        "presets": presets,
        "generated_wavs": len(metric_rows),
        "output_dir": str(output_dir),
        "metrics_csv": str(output_dir / "metrics.csv"),
        "human_score_template": str(output_dir / "human_scores.csv"),
        "scores_are_blank_for_human_review": True,
    }, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
