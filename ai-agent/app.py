from __future__ import annotations

import asyncio
import io
import base64
import hashlib
import importlib.util
import json
import logging
import math
import mimetypes
import os
import re
import secrets
import sys
import tempfile
import threading
import time
import urllib.error
import urllib.request
import wave
from collections import OrderedDict, deque
from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile, WebSocket, WebSocketDisconnect
from fastapi.responses import FileResponse, Response, StreamingResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field
from starlette.background import BackgroundTask
from starlette.concurrency import run_in_threadpool

from realtime_runtime.asr_scheduler import RealtimeAsrQueue, run_asr_with_timeout
from realtime_runtime.metrics import ProcessResourceMetrics, RealtimeMetrics
from realtime_runtime.piper_scheduler import PiperQueueFull, PiperStreamScheduler
from realtime_runtime.session import ConversationSession
from realtime_runtime.telemetry import TurnTelemetry
from tts.persian_text import has_speakable_content, normalize_persian_for_piper
from tts.piper_presets import (
    PIPER_DELIVERY_PROFILES,
    PIPER_PRESETS,
    apply_piper_delivery_profile,
    piper_synthesis_config,
)


ROOT = Path(__file__).resolve().parent
WEB_DIR = ROOT / "web"
STT_MODEL_REQUIRED_FILES = ("config.json", "model.bin", "tokenizer.json", "vocabulary.txt")
STT_MODEL_CANDIDATES = (
    ROOT / "models" / "stt" / "faster-whisper-small",
    ROOT / "models" / "stt" / "faster-whisper-base",
)
TARANEH_ROOT = ROOT / "taraneh-asr"
TARANEH_MODEL_DIR = ROOT / "models" / "stt" / "taraneh-wbase"
TARANEH_REQUIRED_FILES = (
    "added_tokens.json",
    "config.json",
    "generation_config.json",
    "merges.txt",
    "model.safetensors",
    "normalizer.json",
    "preprocessor_config.json",
    "special_tokens_map.json",
    "tokenizer_config.json",
    "vocab.json",
)
TARANEH_REQUIRED_MODULES = ("torch", "transformers", "pandas", "soundfile")
STT_MODEL_DIR = next(
    (
        model_dir
        for model_dir in STT_MODEL_CANDIDATES
        if all((model_dir / name).is_file() for name in STT_MODEL_REQUIRED_FILES)
    ),
    STT_MODEL_CANDIDATES[-1],
)
TTS_MODEL = ROOT / "models" / "tts" / "piper-fa" / "fa_IR-mana-medium.onnx"
MOSS_TTS_URL = os.environ.get("MOSS_TTS_URL", "http://127.0.0.1:18083").rstrip("/")
MOSS_REALTIME_TTS_URL = os.environ.get("MOSS_REALTIME_TTS_URL", "http://127.0.0.1:18084").rstrip("/")
_moss_realtime_tts_lock = threading.Lock()
MOSS_REFERENCE_AUDIO = (
    ROOT / "emotional-tts" / "moss-tts-nano" / "assets" / "audio" / "fa_mana_reference.wav"
)

_realtime_asr_queue = RealtimeAsrQueue(max_pending_finals=8, max_pending_finals_per_session=2)
_realtime_metrics = RealtimeMetrics()
_process_resources = ProcessResourceMetrics()
_piper_scheduler: PiperStreamScheduler | None = None


def _bounded_env_int(name: str, default: int, low: int, high: int) -> int:
    try:
        return min(high, max(low, int(os.environ.get(name, str(default)))))
    except (TypeError, ValueError):
        return default


MOSS_REALTIME_TTS_TIMEOUT_SECONDS = _bounded_env_int("MOSS_REALTIME_TTS_TIMEOUT_SECONDS", 900, 10, 900)
ENABLE_SPECULATIVE_LLM = os.environ.get("ENABLE_SPECULATIVE_LLM", "false").strip().lower() in {"1", "true", "yes", "on"}
VOICE_ASR_PARTIAL_INTERVAL_MS = _bounded_env_int("VOICE_ASR_PARTIAL_INTERVAL_MS", 2000, 500, 2000)
VOICE_ASR_REQUEST_TIMEOUT_MS = _bounded_env_int("VOICE_ASR_REQUEST_TIMEOUT_MS", 60000, 5000, 180000)
VOICE_ASR_QUEUE_TIMEOUT_MS = _bounded_env_int("VOICE_ASR_QUEUE_TIMEOUT_MS", 30000, 1000, 120000)
VOICE_ENDPOINT_SILENCE_MS = _bounded_env_int("VOICE_ENDPOINT_SILENCE_MS", 420, 300, 1500)
VOICE_TTS_PENDING_LIMIT = _bounded_env_int("VOICE_TTS_PENDING_LIMIT", 2, 0, 8)
VOICE_TTS_REQUEST_TIMEOUT_MS = _bounded_env_int("VOICE_TTS_REQUEST_TIMEOUT_MS", 60000, 5000, 180000)
VOICE_TTS_CHUNK_TIMEOUT_MS = _bounded_env_int("VOICE_TTS_CHUNK_TIMEOUT_MS", 30000, 1000, 90000)
VOICE_TTS_BACKPRESSURE_TIMEOUT_MS = _bounded_env_int("VOICE_TTS_BACKPRESSURE_TIMEOUT_MS", 10000, 500, 60000)


@asynccontextmanager
async def app_lifespan(_app: FastAPI):
    global _realtime_asr_queue, _piper_scheduler
    _realtime_asr_queue = RealtimeAsrQueue(max_pending_finals=8, max_pending_finals_per_session=2)
    _piper_scheduler = PiperStreamScheduler(
        _iter_piper_pcm,
        max_pending=VOICE_TTS_PENDING_LIMIT,
        max_pending_per_session=1,
        output_chunks=2,
        request_timeout_seconds=VOICE_TTS_REQUEST_TIMEOUT_MS / 1000,
        chunk_timeout_seconds=VOICE_TTS_CHUNK_TIMEOUT_MS / 1000,
        backpressure_timeout_seconds=VOICE_TTS_BACKPRESSURE_TIMEOUT_MS / 1000,
        on_finished=_record_piper_job,
    )
    _piper_scheduler.start()
    worker = asyncio.create_task(_run_realtime_asr_worker(), name="realtime-asr-worker")
    try:
        yield
    finally:
        if _piper_scheduler is not None:
            await _piper_scheduler.close()
            _piper_scheduler = None
        await _realtime_asr_queue.close()
        worker.cancel()
        try:
            await worker
        except asyncio.CancelledError:
            pass


app = FastAPI(
    title="AI Agent Voice Bridge",
    docs_url="/api/docs",
    redoc_url=None,
    lifespan=app_lifespan,
)

_whisper_model = None
_piper_voice = None
_piper_load_lock = threading.Lock()
_piper_synthesis_lock = threading.Lock()
_stt_inference_lock = threading.Lock()
_reference_wav_cache: OrderedDict[str, bytes] = OrderedDict()
_reference_wav_cache_lock = threading.Lock()
_realtime_logger = logging.getLogger("ai_agent.realtime")
_VOICE_STAGE_EVENTS = {
    "audio_capture_started",
    "speech_started",
    "asr_partial_created",
    "asr_partial_stabilized",
    "asr_partial_invalidated",
    "asr_request_timed_out",
    "speech_end_detected",
    "asr_final_committed",
    "llm_request_started",
    "llm_first_token",
    "llm_response_completed",
    "tts_request_started",
    "tts_first_pcm",
    "audio_first_playable",
    "audio_playback_started",
    "audio_playback_completed",
    "barge_in_detected",
    "playback_cancelled",
    "llm_speculative_start",
    "llm_speculative_cancelled",
}


def _log_voice_stage(
    name: str,
    *,
    session_id: str,
    turn_id: str | int | None = None,
    generation_id: str | None = None,
    duration_ms: float | int | None = None,
) -> None:
    if name not in _VOICE_STAGE_EVENTS:
        return
    counter_names = {
        "llm_speculative_start": "speculative_request_count",
        "barge_in_detected": "barge_in_count",
        "playback_cancelled": "playback_cancel_count",
        "asr_partial_invalidated": "asr_partial_invalidation_count",
        "asr_request_timed_out": "asr_request_timeout_count",
    }
    counter_name = counter_names.get(name)
    if counter_name:
        _realtime_metrics.increment(counter_name)
    duration_metrics = {
        "llm_first_token": "client_llm_first_token",
        "llm_response_completed": "client_llm_response_total",
        "audio_first_playable": "client_finalizing_to_first_playable",
    }
    metric_name = duration_metrics.get(name)
    if metric_name and isinstance(duration_ms, (float, int)) and duration_ms >= 0:
        _realtime_metrics.record(metric_name, duration_ms)
    event: dict[str, object] = {
        "event": name,
        "session_id": session_id,
        "turn_id": turn_id,
        "generation_id": generation_id,
        "timestamp": time.perf_counter(),
    }
    if duration_ms is not None and isinstance(duration_ms, (float, int)) and duration_ms >= 0:
        event["duration_ms"] = round(float(duration_ms), 1)
    _realtime_logger.info("voice_stage_event %s", json.dumps(event, ensure_ascii=False))


def _record_piper_job(job) -> None:
    metrics = job.metrics
    if "scheduler_started_at" not in metrics:
        return
    started_at = float(metrics.get("request_started_at", job.queued_at))
    finished_at = float(metrics.get("finished_at", time.perf_counter()))
    queue_wait = float(metrics.get("scheduler_queue_wait_seconds", 0.0))
    lock_wait = metrics.get("queue_wait_seconds")
    first_pcm = metrics.get("time_to_first_pcm_seconds")
    if isinstance(first_pcm, (float, int)):
        _realtime_metrics.record("tts_first_pcm", float(first_pcm) * 1000.0)
    _realtime_metrics.record("tts_scheduler_queue_wait", queue_wait * 1000.0)
    if isinstance(lock_wait, (float, int)):
        _realtime_metrics.record("tts_runtime_lock_wait", float(lock_wait) * 1000.0)
    _realtime_metrics.record("tts_generation_total", (finished_at - started_at) * 1000.0)


def _taraneh_files_present() -> bool:
    return all((TARANEH_MODEL_DIR / name).is_file() for name in TARANEH_REQUIRED_FILES)


def _taraneh_dependencies_present() -> bool:
    return all(importlib.util.find_spec(name) is not None for name in TARANEH_REQUIRED_MODULES)


def _selected_stt() -> tuple[str, str]:
    if TARANEH_ROOT.is_dir() and _taraneh_files_present() and _taraneh_dependencies_present():
        return "Taraneh-WBase", "taraneh"
    return STT_MODEL_DIR.name, "faster-whisper"


def _audio_metrics(samples) -> dict[str, float | int | bool]:
    import numpy as np

    samples = np.asarray(samples, dtype=np.float32).reshape(-1)
    if not samples.size:
        return {
            "duration_seconds": 0.0,
            "active_level_dbfs": -120.0,
            "quiet_percent": 100,
            "clipped": False,
        }

    peak = float(np.max(np.abs(samples)))
    frame_size = 320  # 20 ms at the 16 kHz rate used by Whisper.
    frame_count = int(math.ceil(samples.size / frame_size))
    padded = np.pad(samples, (0, frame_count * frame_size - samples.size))
    frame_rms = np.sqrt(np.mean(padded.reshape(frame_count, frame_size) ** 2, axis=1))
    quiet_frames = frame_rms < 0.004
    active_frames = frame_rms[~quiet_frames]
    active_level = float(np.percentile(active_frames, 75)) if active_frames.size else 0.0
    active_dbfs = 20.0 * math.log10(max(active_level, 1e-6))

    return {
        "duration_seconds": round(samples.size / 16000, 2),
        "active_level_dbfs": round(active_dbfs, 1),
        "quiet_percent": round(float(np.mean(quiet_frames)) * 100),
        "clipped": peak >= 0.999,
    }


def _moss_status() -> dict[str, object]:
    request = urllib.request.Request(f"{MOSS_TTS_URL}/api/warmup-status", method="GET")
    try:
        with urllib.request.urlopen(request, timeout=1.0) as response:
            payload = json.loads(response.read().decode("utf-8"))
        return {
            "available": True,
            "ready": bool(payload.get("ready")),
            "status": str(payload.get("status_text") or payload.get("message") or "MOSS فعال است."),
        }
    except Exception:
        return {
            "available": False,
            "ready": False,
            "status": "سرویس MOSS اجرا نیست؛ آن را روی پورت ۱۸۰۸۳ اجرا کن.",
        }


def _moss_realtime_status() -> dict[str, object]:
    request = urllib.request.Request(f"{MOSS_REALTIME_TTS_URL}/v1/audio/voices", method="GET")
    try:
        with urllib.request.urlopen(request, timeout=1.0) as response:
            response.read(1024)
        return {
            "available": True,
            "ready": True,
            "status": "MOSS Realtime GGUF آماده است؛ PCM هم‌زمان با تولید به سرویس وب منتقل می‌شود.",
        }
    except Exception:
        return {
            "available": False,
            "ready": False,
            "status": f"سرویس MOSS Realtime روی {MOSS_REALTIME_TTS_URL} در دسترس نیست.",
        }


def _synthesize_with_moss_realtime_gguf(text: str) -> tuple[bytes, int, int, int]:
    if not _moss_realtime_tts_lock.acquire(timeout=2.0):
        raise RuntimeError("موتور MOSS Realtime در حال تولید صدای درخواست دیگری است؛ کمی بعد دوباره تلاش کن.")

    try:
        request_body = json.dumps(
            {"model": "tts", "input": text, "response_format": "wav"},
            ensure_ascii=False,
        ).encode("utf-8")
        request = urllib.request.Request(
            f"{MOSS_REALTIME_TTS_URL}/v1/audio/speech",
            data=request_body,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=MOSS_REALTIME_TTS_TIMEOUT_SECONDS) as response:
                wav_bytes = response.read()
        except urllib.error.HTTPError as exc:
            details = exc.read().decode("utf-8", errors="replace")
            raise RuntimeError(f"MOSS Realtime GGUF خطا داد: {details[:1000]}") from exc
        except urllib.error.URLError as exc:
            raise RuntimeError(
                f"اتصال به MOSS Realtime برقرار نشد ({MOSS_REALTIME_TTS_URL}). سرویس tts-server را اجرا کن."
            ) from exc

        try:
            with wave.open(io.BytesIO(wav_bytes), "rb") as wav_file:
                channels = wav_file.getnchannels()
                sample_width = wav_file.getsampwidth()
                sample_rate = wav_file.getframerate()
                pcm = wav_file.readframes(wav_file.getnframes())
        except (wave.Error, EOFError) as exc:
            raise RuntimeError("MOSS Realtime پاسخ WAV معتبر برنگرداند.") from exc

        if sample_width != 2 or channels not in (1, 2) or not pcm:
            raise RuntimeError("قالب صوت MOSS Realtime پشتیبانی نمی‌شود یا پاسخ خالی است.")
        return wav_bytes, pcm, sample_rate, channels
    finally:
        _moss_realtime_tts_lock.release()


def _open_moss_realtime_gguf_stream(text: str):
    """Open the upstream raw-PCM stream without blocking the ASGI event loop."""
    if not _moss_realtime_tts_lock.acquire(timeout=2.0):
        raise RuntimeError("موتور MOSS Realtime در حال تولید صدای درخواست دیگری است؛ کمی بعد دوباره تلاش کن.")

    request_body = json.dumps(
        {"model": "tts", "input": text, "response_format": "pcm"},
        ensure_ascii=False,
    ).encode("utf-8")
    request = urllib.request.Request(
        f"{MOSS_REALTIME_TTS_URL}/v1/audio/speech",
        data=request_body,
        headers={"Content-Type": "application/json", "Accept": "application/octet-stream"},
        method="POST",
    )
    try:
        return urllib.request.urlopen(request, timeout=MOSS_REALTIME_TTS_TIMEOUT_SECONDS)
    except urllib.error.HTTPError as exc:
        details = exc.read().decode("utf-8", errors="replace")
        exc.close()
        _moss_realtime_tts_lock.release()
        raise RuntimeError(f"MOSS Realtime GGUF خطا داد: {details[:1000]}") from exc
    except urllib.error.URLError as exc:
        _moss_realtime_tts_lock.release()
        raise RuntimeError(
            f"اتصال به MOSS Realtime برقرار نشد ({MOSS_REALTIME_TTS_URL}). سرویس tts-server را اجرا کن."
        ) from exc
    except Exception:
        _moss_realtime_tts_lock.release()
        raise


def _multipart_body(
    fields: dict[str, str],
    *,
    file_bytes: bytes,
    file_name: str,
    content_type: str,
) -> tuple[bytes, str]:
    boundary = secrets.token_hex(16)
    chunks: list[bytes] = []
    for name, value in fields.items():
        chunks.extend(
            [
                f"--{boundary}\r\n".encode(),
                f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode(),
                value.encode("utf-8"),
                b"\r\n",
            ]
        )
    chunks.extend(
        [
            f"--{boundary}\r\n".encode(),
            f'Content-Disposition: form-data; name="prompt_audio"; filename="{file_name}"\r\n'.encode(),
            f"Content-Type: {content_type}\r\n\r\n".encode(),
            file_bytes,
            b"\r\n",
            f"--{boundary}--\r\n".encode(),
        ]
    )
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


def _reference_audio_to_wav(audio_bytes: bytes, filename: str) -> bytes:
    """Decode browser audio formats (usually WebM/Opus) into MOSS-friendly WAV."""
    cache_key = hashlib.sha256(audio_bytes).hexdigest()
    with _reference_wav_cache_lock:
        cached_wav = _reference_wav_cache.get(cache_key)
        if cached_wav is not None:
            _reference_wav_cache.move_to_end(cache_key)
            return cached_wav

    suffix = Path(filename).suffix.lower()
    if suffix not in {".wav", ".mp3", ".ogg", ".m4a", ".flac", ".webm", ".mp4"}:
        suffix = ".audio"

    try:
        import numpy as np
        from faster_whisper.audio import decode_audio

        with tempfile.TemporaryDirectory(prefix="ai-agent-moss-ref-") as temp_dir:
            source_path = Path(temp_dir) / f"reference{suffix}"
            source_path.write_bytes(audio_bytes)
            samples = decode_audio(str(source_path), sampling_rate=24000)
    except Exception as exc:
        raise RuntimeError(
            "فایل صدای مرجع قابل خواندن نیست. آن را با فرمت WAV، MP3 یا WebM ضبط کن و دوباره انتخاب کن."
        ) from exc

    if samples is None or len(samples) == 0:
        raise RuntimeError("از فایل صدای مرجع هیچ صدایی خوانده نشد.")

    pcm = (np.clip(np.asarray(samples, dtype=np.float32), -1.0, 1.0) * 32767.0).astype("<i2")
    wav_buffer = io.BytesIO()
    with wave.open(wav_buffer, "wb") as wav_file:
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(24000)
        wav_file.writeframes(pcm.tobytes())
    wav_bytes = wav_buffer.getvalue()
    with _reference_wav_cache_lock:
        _reference_wav_cache[cache_key] = wav_bytes
        _reference_wav_cache.move_to_end(cache_key)
        while len(_reference_wav_cache) > 8:
            _reference_wav_cache.popitem(last=False)
    return wav_bytes


def _synthesize_with_moss(text: str, reference_audio: bytes | None, filename: str) -> bytes:
    if reference_audio is None:
        if not MOSS_REFERENCE_AUDIO.is_file():
            raise RuntimeError(
                "صدای مرجع فارسی آماده نیست. یک فایل WAV فارسی انتخاب کن یا نمونه‌ی Mana را بساز."
            )
        reference_audio = MOSS_REFERENCE_AUDIO.read_bytes()
        filename = MOSS_REFERENCE_AUDIO.name
    else:
        reference_audio = _reference_audio_to_wav(reference_audio, filename)
        filename = "reference-audio.wav"

    suffix = Path(filename).suffix.lower()
    if suffix not in {".wav", ".mp3", ".ogg", ".m4a", ".flac", ".webm"}:
        suffix = ".wav"
    safe_filename = f"reference-audio{suffix}"
    safe_content_type = mimetypes.guess_type(safe_filename)[0] or "application/octet-stream"

    body, multipart_content_type = _multipart_body(
        {
            "text": text,
            # Keep more text in each MOSS voice-clone segment so long answers
            # get fewer synthetic joins and fewer inserted pauses.
            "voice_clone_max_text_tokens": "100",
            "enable_text_normalization": "0",  # WeTextProcessing only has zh/en normalizers.
            "enable_normalize_tts_text": "1",
        },
        file_bytes=reference_audio,
        file_name=safe_filename,
        content_type=safe_content_type,
    )
    request = urllib.request.Request(
        f"{MOSS_TTS_URL}/api/generate",
        data=body,
        headers={"Content-Type": multipart_content_type},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=900) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        details = exc.read().decode("utf-8", errors="replace")
        try:
            parsed = json.loads(details)
            details = str(parsed.get("error") or parsed.get("detail") or details)
        except json.JSONDecodeError:
            pass
        raise RuntimeError(f"MOSS خطا داد: {details}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(
            f"اتصال به MOSS برقرار نشد ({MOSS_TTS_URL}). سرویس MOSS را اجرا کن."
        ) from exc

    audio_base64 = str(payload.get("audio_base64") or "")
    if not audio_base64:
        raise RuntimeError(str(payload.get("error") or "MOSS صدایی برنگرداند."))
    try:
        return base64.b64decode(audio_base64, validate=True)
    except ValueError as exc:
        raise RuntimeError("پاسخ صوتی MOSS خراب یا ناقص بود.") from exc


class SpeakRequest(BaseModel):
    text: str = Field(min_length=1, max_length=5000)
    preset: str = "default"
    delivery_profile: str = "warm"


@app.get("/")
def home() -> FileResponse:
    return FileResponse(WEB_DIR / "index.html")


@app.get("/api/status")
def status() -> dict[str, object]:
    try:
        import piper
        from piper import espeakbridge  # noqa: F401

        piper_espeak_data_present = (
            Path(piper.__file__).resolve().parent / "espeak-ng-data" / "phontab"
        ).is_file()
        piper_bridge_present = True
    except ImportError:
        piper_espeak_data_present = False
        piper_bridge_present = False

    stt_model, stt_engine = _selected_stt()
    return {
        "service": "ready",
        "stt_model": stt_model,
        "stt_engine": stt_engine,
        "taraneh_model_present": _taraneh_files_present(),
        "taraneh_dependencies_present": _taraneh_dependencies_present(),
        "stt_model_present": (STT_MODEL_DIR / "model.bin").is_file(),
        "stt_config_present": (STT_MODEL_DIR / "config.json").is_file(),
        "stt_tokenizer_present": (STT_MODEL_DIR / "tokenizer.json").is_file(),
        "stt_vocabulary_present": (STT_MODEL_DIR / "vocabulary.txt").is_file(),
        "tts_model_present": TTS_MODEL.is_file(),
        "tts_config_present": TTS_MODEL.with_suffix(TTS_MODEL.suffix + ".json").is_file(),
        "tts_espeak_bridge_present": piper_bridge_present,
        "tts_espeak_data_present": piper_espeak_data_present,
        "tts_presets": PIPER_PRESETS,
        "realtime": {
            "speculative_llm_enabled": ENABLE_SPECULATIVE_LLM,
            "asr_partial_interval_ms": VOICE_ASR_PARTIAL_INTERVAL_MS,
            "asr_request_timeout_ms": VOICE_ASR_REQUEST_TIMEOUT_MS,
            "asr_queue_timeout_ms": VOICE_ASR_QUEUE_TIMEOUT_MS,
            "endpoint_silence_ms": VOICE_ENDPOINT_SILENCE_MS,
            "metrics": _realtime_metrics.snapshot(),
            "counters": _realtime_metrics.counter_snapshot(),
            "process_resources": _process_resources.snapshot(),
            "event_bus": "per-session in-process; use sticky sessions with multiple workers",
            "asr_queue": _realtime_asr_queue.snapshot(),
            "piper": _piper_scheduler.snapshot() if _piper_scheduler else {"workers": 0, "active": 0, "pending": 0},
            "piper_queue_pending_limit": VOICE_TTS_PENDING_LIMIT,
            "piper_queue_pending_per_session_limit": 1,
            "piper_output_queue_chunks": 2,
            "piper_request_timeout_ms": VOICE_TTS_REQUEST_TIMEOUT_MS,
            "piper_chunk_timeout_ms": VOICE_TTS_CHUNK_TIMEOUT_MS,
            "piper_backpressure_timeout_ms": VOICE_TTS_BACKPRESSURE_TIMEOUT_MS,
        },
        "moss": _moss_status(),
        "moss_realtime": _moss_realtime_status(),
        "moss_reference_audio_present": MOSS_REFERENCE_AUDIO.is_file(),
    }


def _get_whisper_model():
    global _whisper_model
    if _whisper_model is None:
        try:
            from faster_whisper import WhisperModel
        except ImportError as exc:
            raise RuntimeError(
                "faster-whisper is not installed. Install the cloned project in the ai-agent environment."
            ) from exc

        missing_files = [
            name
            for name in STT_MODEL_REQUIRED_FILES
            if not (STT_MODEL_DIR / name).is_file()
        ]
        if missing_files:
            raise RuntimeError(
                f"Whisper model files are missing from {STT_MODEL_DIR}: {', '.join(missing_files)}"
            )

        _whisper_model = WhisperModel(
            str(STT_MODEL_DIR),
            device="cpu",
            compute_type="int8",
            cpu_threads=2,
            num_workers=1,
        )
    return _whisper_model


def _transcribe_taraneh(samples) -> str:
    import numpy as np
    import pandas as pd

    if str(TARANEH_ROOT) not in sys.path:
        sys.path.insert(0, str(TARANEH_ROOT))
    from taraneh.whisper_engine import transcribe

    # The cloned Taraneh engine reads WAV through SoundFile. Browser recordings
    # are WebM/Opus, so decode once with faster-whisper and give it normalized WAV.
    wav_path = None
    try:
        with tempfile.NamedTemporaryFile(prefix="ai-agent-fa-", suffix=".wav", delete=False) as wav_file:
            wav_path = wav_file.name
        pcm = np.clip(samples, -1.0, 1.0)
        pcm = (pcm * 32767.0).astype("<i2")
        with wave.open(wav_path, "wb") as wav_file:
            wav_file.setnchannels(1)
            wav_file.setsampwidth(2)
            wav_file.setframerate(16000)
            wav_file.writeframes(pcm.tobytes())

        rows = pd.DataFrame({"path": [wav_path], "text": [""]})
        with _stt_inference_lock:
            hypotheses = transcribe(
                rows,
                str(TARANEH_MODEL_DIR),
                batch_size=1,
                device="cpu",
                backend="auto",
                compute_type="int8",
            )
        transcript = hypotheses[0].strip()
        if not transcript or transcript == "ERROR":
            raise RuntimeError("موتور Taraneh از صدای ضبط‌شده متنی تشخیص نداد.")
        return transcript
    finally:
        if wav_path:
            Path(wav_path).unlink(missing_ok=True)


def _transcribe_file(audio_path: str) -> dict[str, object]:
    from faster_whisper.audio import decode_audio

    samples = decode_audio(audio_path, sampling_rate=16000)
    if not len(samples):
        raise RuntimeError("از فایل صوتی نمونه‌ای برای تشخیص گفتار دریافت نشد.")
    metrics = _audio_metrics(samples)
    stt_model, stt_engine = _selected_stt()

    if stt_engine == "taraneh" and metrics["duration_seconds"] <= 30:
        transcript = _transcribe_taraneh(samples)
    else:
        if stt_engine == "taraneh":
            stt_model = f"{STT_MODEL_DIR.name} · فایل بلندتر از ۳۰ ثانیه"
        with _stt_inference_lock:
            model = _get_whisper_model()
            segments, _info = model.transcribe(
                samples,
                language="fa",
                beam_size=5,
                vad_filter=False,
                temperature=0,
                condition_on_previous_text=False,
            )
            transcript = "".join(segment.text for segment in segments).strip()
    return {
        "text": transcript,
        "language": "fa",
        "model": stt_model,
        "audio": metrics,
    }


def _transcribe_partial_samples(samples) -> str:
    """Low-latency provisional transcript for the live microphone preview."""
    if not len(samples):
        return ""
    with _stt_inference_lock:
        model = _get_whisper_model()
        segments, _info = model.transcribe(
            samples,
            language="fa",
            beam_size=1,
            vad_filter=False,
            temperature=0,
            condition_on_previous_text=False,
        )
        return "".join(segment.text for segment in segments).strip()


def _transcribe_live_samples(samples) -> dict[str, object]:
    """Finalize one VAD-delimited utterance through the configured Persian STT path."""
    import numpy as np

    samples = np.asarray(samples, dtype=np.float32).reshape(-1)
    if not samples.size:
        raise RuntimeError("گفتاری برای تبدیل نهایی پیدا نشد.")
    metrics = _audio_metrics(samples)
    stt_model, stt_engine = _selected_stt()
    if stt_engine == "taraneh" and metrics["duration_seconds"] <= 30:
        transcript = _transcribe_taraneh(samples)
    else:
        if stt_engine == "taraneh":
            stt_model = f"{STT_MODEL_DIR.name} · جمله‌ی بلندتر از ۳۰ ثانیه"
        with _stt_inference_lock:
            model = _get_whisper_model()
            segments, _info = model.transcribe(
                samples,
                language="fa",
                beam_size=5,
                vad_filter=False,
                temperature=0,
                condition_on_previous_text=False,
            )
            transcript = "".join(segment.text for segment in segments).strip()
    return {"text": transcript, "language": "fa", "model": stt_model, "audio": metrics}


async def _run_realtime_asr_worker() -> None:
    """Run one prioritized ASR lane for all active microphone sessions."""
    while True:
        job = await _realtime_asr_queue.get()
        if job is None:
            return
        payload = job.payload
        trace: TurnTelemetry = payload["trace"]
        send_event = payload["send_event"]
        queue_wait_ms = round((time.perf_counter() - payload["queued_at"]) * 1000.0, 1)
        try:
            if queue_wait_ms > VOICE_ASR_QUEUE_TIMEOUT_MS:
                _realtime_metrics.record("asr_queue_timeout", queue_wait_ms)
                await send_event({
                    "type": "error",
                    "session_id": job.session_id,
                    "utterance_id": job.turn_id,
                    "stage": f"stt_{job.kind}_queue",
                    "message": "درخواست تشخیص گفتار در صف بیش از حد منتظر ماند؛ لطفاً جمله را دوباره بگو.",
                })
                continue

            async def report_asr_timeout() -> None:
                _realtime_metrics.record("asr_request_timeout", VOICE_ASR_REQUEST_TIMEOUT_MS)
                _log_voice_stage("asr_request_timed_out", session_id=job.session_id, turn_id=job.turn_id)
                sent = await send_event({
                    "type": "error",
                    "session_id": job.session_id,
                    "utterance_id": job.turn_id,
                    "stage": f"stt_{job.kind}_timeout",
                    "message": "تبدیل گفتار بیش از زمان مجاز طول کشید؛ نتیجه‌ی دیررس نادیده گرفته می‌شود.",
                })
                if not sent:
                    await _realtime_asr_queue.discard_session(job.session_id)

            if job.kind == "partial":
                trace.start("stt_partial")
                partial_started_at = time.perf_counter()
                partial_text, timed_out = await run_asr_with_timeout(
                    _transcribe_partial_samples,
                    payload["samples"],
                    timeout_seconds=VOICE_ASR_REQUEST_TIMEOUT_MS / 1000,
                    on_timeout=report_asr_timeout,
                )
                if timed_out:
                    trace.finish("stt_partial")
                    continue
                partial_stt_ms = trace.finish("stt_partial")
                _realtime_metrics.record("asr_partial_compute", partial_stt_ms)
                if payload["is_current"]() and partial_text:
                    revision = payload["transcript_state"].observe(partial_text)
                    if revision is None:
                        continue
                    if not payload["transcript_state"].first_partial_emitted:
                        _realtime_metrics.record(
                            "capture_to_first_partial",
                            (time.perf_counter() - payload["speech_started_at"]) * 1000.0,
                        )
                        payload["transcript_state"].first_partial_emitted = True
                    sent = await send_event({
                        "type": "partial",
                        "session_id": job.session_id,
                        "utterance_id": job.turn_id,
                        "revision": revision.revision,
                        "text": revision.text,
                        "timing": trace.snapshot(
                            partial_audio_ms=payload["partial_audio_ms"],
                            partial_stt_ms=partial_stt_ms,
                            partial_queue_ms=queue_wait_ms,
                            partial_wall_ms=round((time.perf_counter() - partial_started_at) * 1000.0, 1),
                        ),
                    })
                    if not sent:
                        await _realtime_asr_queue.discard_session(job.session_id)
                        continue
                    _log_voice_stage(
                        "asr_partial_created",
                        session_id=job.session_id,
                        turn_id=job.turn_id,
                        duration_ms=partial_stt_ms,
                    )
                    if revision.stable_changed:
                        await send_event({
                            "type": "asr.stable",
                            "session_id": job.session_id,
                            "utterance_id": job.turn_id,
                            "revision": revision.revision,
                            "text": revision.stable_text,
                            "invalidated": revision.stable_invalidated,
                        })
                        _log_voice_stage(
                            "asr_partial_invalidated" if revision.stable_invalidated else "asr_partial_stabilized",
                            session_id=job.session_id,
                            turn_id=job.turn_id,
                        )
                continue

            base_measurements = payload["base_measurements"]
            trace.start("stt_final")
            result, timed_out = await run_asr_with_timeout(
                _transcribe_live_samples,
                payload["samples"],
                timeout_seconds=VOICE_ASR_REQUEST_TIMEOUT_MS / 1000,
                on_timeout=report_asr_timeout,
            )
            if timed_out:
                trace.finish("stt_final")
                continue
            if payload["session"].closed:
                continue
            stt_final_ms = trace.finish("stt_final")
            _realtime_metrics.record("asr_final_compute", stt_final_ms)
            _realtime_metrics.record("asr_final_queue", queue_wait_ms)
            _realtime_metrics.record(
                "speech_end_to_final_asr",
                (time.perf_counter() - payload["speech_ended_at"]) * 1000.0,
            )
            timing = trace.snapshot(
                **base_measurements,
                stt_final_ms=stt_final_ms,
                stt_final_queue_ms=queue_wait_ms,
            )
            result["timing"] = timing
            revision = payload["session"].finish_utterance(job.turn_id, str(result.get("text", "")))
            _realtime_logger.info("voice_turn_metrics %s", json.dumps(timing, ensure_ascii=False))
            _log_voice_stage("asr_final_committed", session_id=job.session_id, turn_id=job.turn_id, duration_ms=stt_final_ms)
            sent = await send_event({
                "type": "final",
                "session_id": job.session_id,
                "utterance_id": job.turn_id,
                "revision": revision,
                **result,
            })
            if not sent:
                await _realtime_asr_queue.discard_session(job.session_id)
        except asyncio.CancelledError:
            raise
        except Exception as exc:
            trace.finish(f"stt_{job.kind}")
            sent = await send_event({
                "type": "error",
                "session_id": job.session_id,
                "utterance_id": job.turn_id,
                "stage": f"stt_{job.kind}",
                "message": str(exc),
            })
            if not sent:
                await _realtime_asr_queue.discard_session(job.session_id)


def _iter_piper_pcm(text: str, syn_config=None, metrics: dict[str, object] | None = None):
    """Yield Piper's sentence-sized PCM chunks without building a whole WAV."""
    request_started_at = (
        float(metrics.setdefault("request_started_at", time.perf_counter()))
        if metrics is not None
        else time.perf_counter()
    )
    voice = _get_piper_voice()
    normalized_text = normalize_persian_for_piper(text)
    if not has_speakable_content(normalized_text):
        if metrics is not None:
            metrics["finished_at"] = time.perf_counter()
        return
    iterator = iter(voice.synthesize(normalized_text, syn_config=syn_config))
    while True:
        lock_wait_started = time.perf_counter()
        _piper_synthesis_lock.acquire()
        lock_acquired_at = time.perf_counter()
        try:
            if metrics is not None and "queue_wait_seconds" not in metrics:
                metrics["queue_wait_seconds"] = lock_acquired_at - lock_wait_started
            try:
                audio_chunk = next(iterator)
            except StopIteration:
                if metrics is not None:
                    metrics["finished_at"] = time.perf_counter()
                return
        finally:
            _piper_synthesis_lock.release()
        pcm = audio_chunk.audio_int16_bytes
        if pcm:
            if metrics is not None:
                chunks = metrics.setdefault("chunks", [])
                if isinstance(chunks, list):
                    chunks.append({
                        "bytes": len(pcm),
                        "ready_seconds": time.perf_counter() - request_started_at,
                    })
                metrics.setdefault("time_to_first_pcm_seconds", time.perf_counter() - request_started_at)
            yield pcm


def _prepare_moss_reference(reference_audio: bytes | None, filename: str) -> tuple[bytes, str, str]:
    if reference_audio is None:
        if not MOSS_REFERENCE_AUDIO.is_file():
            raise RuntimeError("صدای مرجع فارسی آماده نیست. یک فایل WAV فارسی انتخاب کن یا نمونه‌ی Mana را بساز.")
        reference_audio = MOSS_REFERENCE_AUDIO.read_bytes()
        filename = MOSS_REFERENCE_AUDIO.name
    else:
        reference_audio = _reference_audio_to_wav(reference_audio, filename)
        filename = "reference-audio.wav"

    suffix = Path(filename).suffix.lower()
    if suffix not in {".wav", ".mp3", ".ogg", ".m4a", ".flac", ".webm"}:
        suffix = ".wav"
    safe_filename = f"reference-audio{suffix}"
    content_type = mimetypes.guess_type(safe_filename)[0] or "application/octet-stream"
    return reference_audio, safe_filename, content_type


def _start_moss_stream(text: str, reference_audio: bytes | None, filename: str) -> dict[str, object]:
    reference_audio, safe_filename, content_type = _prepare_moss_reference(reference_audio, filename)
    body, multipart_content_type = _multipart_body(
        {
            "text": text,
            "voice_clone_max_text_tokens": "100",
            "enable_text_normalization": "0",
            "enable_normalize_tts_text": "1",
        },
        file_bytes=reference_audio,
        file_name=safe_filename,
        content_type=content_type,
    )
    request = urllib.request.Request(
        f"{MOSS_TTS_URL}/api/generate-stream/start",
        data=body,
        headers={"Content-Type": multipart_content_type},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")
        try:
            detail = str(json.loads(detail).get("error") or detail)
        except json.JSONDecodeError:
            pass
        raise RuntimeError(f"MOSS خطا داد: {detail}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"اتصال به MOSS برقرار نشد ({MOSS_TTS_URL}). سرویس MOSS را اجرا کن.") from exc

    if not payload.get("stream_id") or not payload.get("audio_url"):
        raise RuntimeError(str(payload.get("error") or "MOSS نشانی جریان صوت را برنگرداند."))
    return payload


def _iter_moss_pcm(audio_url: str, stream_id: str):
    request = urllib.request.Request(audio_url, method="GET")
    try:
        with urllib.request.urlopen(request, timeout=900) as response:
            while True:
                audio_bytes = response.read(4 * 1024)
                if not audio_bytes:
                    break
                yield audio_bytes
    finally:
        close_request = urllib.request.Request(
            f"{MOSS_TTS_URL}/api/generate-stream/{stream_id}/close",
            data=b"",
            headers={"Content-Length": "0"},
            method="POST",
        )
        try:
            with urllib.request.urlopen(close_request, timeout=3):
                pass
        except Exception:
            pass


@app.post("/api/transcribe")
async def transcribe(file: UploadFile = File(...)) -> dict[str, object]:
    audio = await file.read()
    if not audio:
        raise HTTPException(status_code=400, detail="فایل صوتی خالی است.")
    if len(audio) > 20 * 1024 * 1024:
        raise HTTPException(status_code=413, detail="حداکثر اندازه‌ی فایل صوتی ۲۰ مگابایت است.")

    suffix = Path(file.filename or "speech.webm").suffix.lower()
    if suffix not in {".webm", ".wav", ".mp3", ".ogg", ".mp4", ".m4a", ".flac"}:
        suffix = ".webm"

    temporary_path = None
    try:
        with tempfile.NamedTemporaryFile(prefix="ai-agent-", suffix=suffix, delete=False) as temp_file:
            temp_file.write(audio)
            temporary_path = temp_file.name
        return await run_in_threadpool(_transcribe_file, temporary_path)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"تبدیل صدا به متن ناموفق بود: {exc}") from exc
    finally:
        if temporary_path:
            Path(temporary_path).unlink(missing_ok=True)


@app.websocket("/api/transcribe/stream")
async def transcribe_stream(websocket: WebSocket) -> None:
    """Stream provisional Persian transcripts from 16 kHz, mono, s16le audio."""
    await websocket.accept()
    await websocket.send_json({"type": "ready", "model": STT_MODEL_DIR.name})
    audio_bytes = bytearray()
    last_partial_samples = 0
    minimum_samples = 3 * 16000
    partial_interval_samples = 2 * 16000
    try:
        while True:
            message = await websocket.receive()
            if message.get("type") == "websocket.disconnect":
                break
            packet = message.get("bytes")
            if packet:
                audio_bytes.extend(packet)
                sample_count = len(audio_bytes) // 2
                if (
                    sample_count >= minimum_samples
                    and sample_count - last_partial_samples >= partial_interval_samples
                ):
                    import numpy as np

                    snapshot = bytes(audio_bytes[: sample_count * 2])
                    samples = np.frombuffer(snapshot, dtype="<i2").astype(np.float32) / 32768.0
                    try:
                        partial_text = await run_in_threadpool(_transcribe_partial_samples, samples)
                    except Exception as exc:
                        await websocket.send_json({"type": "error", "message": str(exc)})
                        break
                    last_partial_samples = sample_count
                    await websocket.send_json({"type": "partial", "text": partial_text})
                continue

            text_message = message.get("text")
            if text_message:
                try:
                    control = json.loads(text_message)
                except json.JSONDecodeError:
                    control = {}
                if control.get("type") == "stop":
                    break
    except WebSocketDisconnect:
        pass
    finally:
        try:
            await websocket.close()
        except RuntimeError:
            pass


@app.websocket("/api/conversation/stream")
async def live_conversation_stream(websocket: WebSocket) -> None:
    """Keep one microphone session open and emit speech-start, partial, and final events."""
    import numpy as np

    await websocket.accept()
    session = ConversationSession()
    session_id = session.session_id
    active_utterance_id: int | None = None

    async def send_event(payload: dict[str, object]) -> bool:
        event = dict(payload)
        event.setdefault("session_id", session_id)
        event.setdefault("turn_id", event.get("utterance_id"))
        event.setdefault("revision", 0)
        return await session.events.publish(event)

    async def write_events() -> None:
        while True:
            event = await session.events.get()
            if event is None:
                return
            try:
                await websocket.send_json(event)
            except (WebSocketDisconnect, RuntimeError):
                await session.events.close(discard_pending=True)
                return

    event_writer = asyncio.create_task(write_events(), name=f"voice-events-{session_id}")
    await send_event({"type": "ready", "session_id": session_id, "sample_rate": 16000, "channels": 1})
    sample_rate = 16000
    pre_roll: deque[np.ndarray] = deque(maxlen=20)  # 400 ms, independent of WebSocket packet size.
    utterance_frames: list[np.ndarray] = []
    utterance_samples = 0
    partial_at_samples = 0
    consecutive_voice_samples = 0
    consecutive_silence_samples = 0
    voiced_samples = 0
    onset_required_samples = int(0.12 * sample_rate)
    minimum_voiced_samples = int(0.22 * sample_rate)
    # 420 ms is a latency/turn-boundary compromise: shorter can split natural
    # Persian pauses, while longer makes the text-model pipeline feel sluggish.
    end_silence_samples = round(VOICE_ENDPOINT_SILENCE_MS * sample_rate / 1000)
    trailing_context_samples = int(0.12 * sample_rate)
    noise_floor = 0.002
    in_speech = False
    utterance_id = 0
    turn_trace: TurnTelemetry | None = None
    speech_started_at = 0.0
    partial_interval_samples = round(VOICE_ASR_PARTIAL_INTERVAL_MS * sample_rate / 1000)

    def final_utterance_audio() -> np.ndarray:
        audio = np.concatenate(utterance_frames).astype(np.float32, copy=False)
        removable_silence = max(0, consecutive_silence_samples - trailing_context_samples)
        end = max(1, audio.size - removable_silence)
        return audio[:end]

    async def finalize_utterance() -> None:
        nonlocal utterance_frames, utterance_samples, partial_at_samples
        nonlocal consecutive_voice_samples, consecutive_silence_samples, voiced_samples
        nonlocal turn_trace, in_speech, active_utterance_id

        if voiced_samples < minimum_voiced_samples:
            await send_event({
                "type": "discarded",
                "utterance_id": utterance_id,
                "reason": "short_or_noisy_audio",
            })
        else:
            final_samples = final_utterance_audio()
            endpoint_wait_ms = round(consecutive_silence_samples * 1000.0 / sample_rate, 1)
            speech_ended_at = time.perf_counter()
            _log_voice_stage(
                "speech_end_detected",
                session_id=session_id,
                turn_id=utterance_id,
                duration_ms=endpoint_wait_ms,
            )
            base_measurements = {
                "utterance_audio_ms": round(final_samples.size * 1000.0 / sample_rate, 1),
                "endpoint_wait_ms": endpoint_wait_ms,
            }
            trace = turn_trace or TurnTelemetry(session_id, utterance_id)
            _realtime_metrics.record("endpoint_silence_wait", endpoint_wait_ms)
            await send_event({
                "type": "finalizing",
                "session_id": session_id,
                "utterance_id": utterance_id,
                "timing": trace.snapshot(**base_measurements),
            })
            accepted = await _realtime_asr_queue.submit_final(session_id, utterance_id, {
                "samples": final_samples.copy(),
                "trace": trace,
                "session": session,
                "base_measurements": base_measurements,
                "queued_at": time.perf_counter(),
                "speech_ended_at": speech_ended_at,
                "send_event": send_event,
            })
            if not accepted:
                await send_event({
                    "type": "error",
                    "session_id": session_id,
                    "utterance_id": utterance_id,
                    "stage": "stt_queue",
                    "message": "صف پردازش گفتار پر است؛ چند لحظه صبر کن و دوباره بگو.",
                })

        # Reset VAD immediately so the next utterance is captured while this
        # utterance's final ASR runs in the independent worker.
        pre_roll.clear()
        utterance_frames = []
        utterance_samples = 0
        partial_at_samples = 0
        consecutive_voice_samples = 0
        consecutive_silence_samples = 0
        voiced_samples = 0
        turn_trace = None
        active_utterance_id = None
        session.capture.active_utterance_id = None
        in_speech = False

    try:
        while True:
            message = await websocket.receive()
            if message.get("type") == "websocket.disconnect":
                break

            packet = message.get("bytes")
            if packet:
                session.capture.received_audio_frames += 1
                even_length = len(packet) - (len(packet) % 2)
                if even_length <= 0:
                    continue
                samples = np.frombuffer(packet[:even_length], dtype="<i2").astype(np.float32) / 32768.0
                rms = float(np.sqrt(np.mean(samples * samples))) if samples.size else 0.0
                onset_threshold = max(0.006, noise_floor * 3.0)
                silence_threshold = onset_threshold * 0.58

                if not in_speech:
                    pre_roll.append(samples)
                    if rms >= onset_threshold:
                        consecutive_voice_samples += int(samples.size)
                    else:
                        consecutive_voice_samples = 0
                        if rms < 0.03:
                            alpha = 1.0 - math.exp(-samples.size / (sample_rate * 1.5))
                            noise_floor = min(0.018, noise_floor * (1.0 - alpha) + rms * alpha)
                    if consecutive_voice_samples >= onset_required_samples:
                        in_speech = True
                        utterance_id = session.begin_utterance()
                        active_utterance_id = utterance_id
                        turn_trace = TurnTelemetry(session_id, utterance_id)
                        speech_started_at = time.perf_counter()
                        utterance_frames = list(pre_roll)
                        utterance_samples = sum(int(frame.size) for frame in utterance_frames)
                        partial_at_samples = utterance_samples
                        consecutive_silence_samples = 0
                        voiced_samples = consecutive_voice_samples
                        await send_event({
                            "type": "speech_start",
                            "session_id": session_id,
                            "utterance_id": utterance_id,
                        })
                        _log_voice_stage("speech_started", session_id=session_id, turn_id=utterance_id)
                    continue

                utterance_frames.append(samples)
                utterance_samples += int(samples.size)
                if rms < silence_threshold:
                    consecutive_silence_samples += int(samples.size)
                else:
                    consecutive_silence_samples = 0
                    voiced_samples += int(samples.size)

                if utterance_samples - partial_at_samples >= partial_interval_samples and utterance_samples >= partial_interval_samples:
                    partial_samples = np.concatenate(utterance_frames).astype(np.float32, copy=False)
                    await _realtime_asr_queue.submit_partial(session_id, utterance_id, {
                        "samples": partial_samples,
                        "trace": turn_trace,
                        "transcript_state": session.transcript(utterance_id),
                        "speech_started_at": speech_started_at,
                        "partial_audio_ms": round(utterance_samples * 1000.0 / sample_rate, 1),
                        "queued_at": time.perf_counter(),
                        "send_event": send_event,
                    "session": session,
                    "is_current": lambda turn_id=utterance_id: not session.closed and active_utterance_id == turn_id,
                    })
                    partial_at_samples = utterance_samples

                if consecutive_silence_samples >= end_silence_samples or utterance_samples >= 30 * sample_rate:
                    await finalize_utterance()
                continue

            text_message = message.get("text")
            if text_message:
                try:
                    control = json.loads(text_message)
                except json.JSONDecodeError:
                    control = {}
                if control.get("type") == "stop":
                    if in_speech and utterance_frames:
                        await finalize_utterance()
                    break
                if control.get("type") == "voice.metrics":
                    measurements = control.get("durations_ms")
                    if isinstance(measurements, dict):
                        allowed = {
                            "llm_first_token_to_tts_pcm",
                            "speech_end_to_first_audio",
                            "barge_in_to_playback_stop",
                        }
                        for name, value in measurements.items():
                            if name in allowed and isinstance(value, (float, int)):
                                _realtime_metrics.record(f"client_{name}", value)
                if control.get("type") == "voice.event":
                    name = str(control.get("event", ""))
                    duration_ms = control.get("duration_ms")
                    _log_voice_stage(
                        name,
                        session_id=session_id,
                        turn_id=str(control.get("turn_id", "")) or None,
                        generation_id=str(control.get("generation_id", "")) or None,
                        duration_ms=duration_ms if isinstance(duration_ms, (float, int)) else None,
                    )
                if control.get("type") == "cancel_tts_generation" and _piper_scheduler is not None:
                    turn_id = str(control.get("turn_id", ""))
                    generation_id = str(control.get("generation_id", ""))
                    await _piper_scheduler.cancel_generation(session_id, turn_id, generation_id)
    except WebSocketDisconnect:
        pass
    finally:
        await _realtime_asr_queue.discard_session(session_id)
        if _piper_scheduler is not None:
            await _piper_scheduler.cancel_session(session_id)
        await session.close()
        event_writer.cancel()
        try:
            await event_writer
        except asyncio.CancelledError:
            pass
        try:
            await websocket.close()
        except RuntimeError:
            pass


def _get_piper_voice():
    global _piper_voice
    if _piper_voice is None:
        with _piper_load_lock:
            if _piper_voice is not None:
                return _piper_voice
            try:
                import piper
                from piper import PiperVoice
                from piper import espeakbridge  # noqa: F401
            except ImportError as exc:
                raise RuntimeError(
                    "Piper's native espeak bridge is missing. From ai-agent, reinstall the cloned project with: "
                    "python -m pip uninstall -y piper-tts && python -m pip install ./piper1-gpl"
                ) from exc

            espeak_data = Path(piper.__file__).resolve().parent / "espeak-ng-data"
            if not (espeak_data / "phontab").is_file():
                raise RuntimeError(
                    f"Piper espeak-ng data is missing at {espeak_data}. Reinstall with: "
                    "python -m pip uninstall -y piper-tts && python -m pip install ./piper1-gpl"
                )

            if not TTS_MODEL.is_file() or not TTS_MODEL.with_suffix(TTS_MODEL.suffix + ".json").is_file():
                raise RuntimeError(f"Piper voice files were not found beside {TTS_MODEL}")
            _piper_voice = PiperVoice.load(str(TTS_MODEL))
    return _piper_voice


def _synthesize(
    text: str,
    preset_name: str = "default",
    delivery_profile: str = "warm",
) -> bytes:
    normalized_text = normalize_persian_for_piper(text)
    if not has_speakable_content(normalized_text):
        raise ValueError("متن برای گفتار فقط شامل نشانه‌گذاری است.")
    voice = _get_piper_voice()
    syn_config = apply_piper_delivery_profile(
        piper_synthesis_config(preset_name),
        voice.config,
        delivery_profile,
    )
    audio = io.BytesIO()
    with wave.open(audio, "wb") as wav_file:
        # Set the header before synthesis so an earlier phonemizer error isn't
        # replaced by wave.Error("# channels not specified") during close.
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(voice.config.sample_rate)
        with _piper_synthesis_lock:
            voice.synthesize_wav(normalized_text, wav_file, syn_config=syn_config, set_wav_format=False)
        if wav_file.tell() == 0:
            raise RuntimeError("Piper returned no audio chunks for the response text.")
    return audio.getvalue()


@app.post("/api/synthesize")
def synthesize(request: SpeakRequest) -> Response:
    try:
        audio = _synthesize(request.text, request.preset, request.delivery_profile)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"تولید صدای پاسخ ناموفق بود: {exc}") from exc
    return Response(content=audio, media_type="audio/wav")


@app.post("/api/synthesize/moss")
async def synthesize_moss(
    text: str = Form(..., min_length=1, max_length=1200),
    prompt_audio: UploadFile | None = File(None),
) -> Response:
    reference_audio = None
    filename = ""
    if prompt_audio is not None:
        reference_audio = await prompt_audio.read()
        filename = prompt_audio.filename or "reference-audio.wav"
        if not reference_audio:
            raise HTTPException(status_code=400, detail="فایل صدای مرجع خالی است.")
        if len(reference_audio) > 20 * 1024 * 1024:
            raise HTTPException(status_code=413, detail="حداکثر اندازه‌ی صدای مرجع ۲۰ مگابایت است.")
    try:
        audio = await run_in_threadpool(
            _synthesize_with_moss,
            text,
            reference_audio,
            filename,
        )
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"تولید صدا با MOSS ناموفق بود: {exc}") from exc
    return Response(content=audio, media_type="audio/wav")


@app.post("/api/synthesize/moss-realtime")
async def synthesize_moss_realtime(request: SpeakRequest) -> Response:
    try:
        wav_bytes, _, _, _ = await run_in_threadpool(_synthesize_with_moss_realtime_gguf, request.text)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"تولید صدا با MOSS Realtime ناموفق بود: {exc}") from exc
    return Response(content=wav_bytes, media_type="audio/wav")


@app.post("/api/synthesize/stream")
async def synthesize_stream(
    text: str = Form(..., min_length=1, max_length=1200),
    engine: str = Form("piper"),
    preset: str = Form("default"),
    delivery_profile: str = Form("warm"),
    session_id: str = Form("anonymous", min_length=1, max_length=96),
    turn_id: str = Form("manual", min_length=1, max_length=96),
    generation_id: str = Form("0", min_length=1, max_length=96),
    prompt_audio: UploadFile | None = File(None),
):
    """Return raw PCM from Piper, MOSS-Nano, or the streamed MOSS Realtime CPU server."""
    selected_engine = engine.strip().lower()
    if selected_engine == "piper":
        normalized_text = normalize_persian_for_piper(text)
        if not has_speakable_content(normalized_text):
            raise HTTPException(status_code=422, detail="متن برای گفتار فقط شامل نشانه‌گذاری است.")
        if preset not in PIPER_PRESETS:
            raise HTTPException(status_code=422, detail=f"تنظیم TTS ناشناخته است: {preset}")
        if delivery_profile not in PIPER_DELIVERY_PROFILES:
            raise HTTPException(status_code=422, detail=f"حالت گفتار Piper ناشناخته است: {delivery_profile}")
        if not all(re.fullmatch(r"[A-Za-z0-9._:-]{1,96}", value) for value in (session_id, turn_id, generation_id)):
            raise HTTPException(status_code=422, detail="شناسه‌ی نشست یا نسل صوت معتبر نیست.")
        scheduler = _piper_scheduler
        if scheduler is None:
            raise HTTPException(status_code=503, detail="Piper worker هنوز آماده نیست.")
        try:
            voice = await run_in_threadpool(_get_piper_voice)
        except RuntimeError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc
        except Exception as exc:
            raise HTTPException(status_code=500, detail=f"آماده‌سازی Piper ناموفق بود: {exc}") from exc
        try:
            syn_config = apply_piper_delivery_profile(
                piper_synthesis_config(preset),
                voice.config,
                delivery_profile,
            )
        except ValueError as exc:
            raise HTTPException(status_code=422, detail=str(exc)) from exc
        try:
            job = await scheduler.submit(
                session_id=session_id,
                turn_id=turn_id,
                generation_id=generation_id,
                text=normalized_text,
                syn_config=syn_config,
            )
        except PiperQueueFull as exc:
            raise HTTPException(status_code=429, detail="صف Piper پر است یا برای این نشست یک تولید دیگر فعال است.") from exc
        except RuntimeError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc

        return StreamingResponse(
            scheduler.stream(job),
            media_type="application/octet-stream",
            headers={
                "X-Audio-Codec": "pcm_s16le",
                "X-Audio-Sample-Rate": str(voice.config.sample_rate),
                "X-Audio-Channels": "1",
                "X-Session-ID": session_id,
                "X-Turn-ID": turn_id,
                "X-Generation-ID": generation_id,
                "X-Delivery-Profile": delivery_profile,
                "X-Delivery-Gap-Ms": str(round(float(PIPER_DELIVERY_PROFILES[delivery_profile]["gap_ms"]))),
                "Cache-Control": "no-cache, no-store",
                "X-Accel-Buffering": "no",
            },
        )

    if selected_engine == "moss-realtime-cpu":
        try:
            upstream = await run_in_threadpool(_open_moss_realtime_gguf_stream, text)
        except RuntimeError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc
        except Exception as exc:
            raise HTTPException(status_code=500, detail=f"شروع جریان MOSS Realtime ناموفق بود: {exc}") from exc

        cleanup_guard = threading.Lock()
        cleaned_up = False

        async def cleanup_upstream() -> None:
            nonlocal cleaned_up
            with cleanup_guard:
                if cleaned_up:
                    return
                cleaned_up = True
            try:
                await run_in_threadpool(upstream.close)
            finally:
                _moss_realtime_tts_lock.release()

        async def relay_pcm():
            try:
                while True:
                    read_audio = getattr(upstream, "read1", upstream.read)
                    chunk = await run_in_threadpool(read_audio, 8192)
                    if not chunk:
                        break
                    yield chunk
            finally:
                await cleanup_upstream()

        return StreamingResponse(
            relay_pcm(),
            media_type="application/octet-stream",
            headers={
                "X-Audio-Codec": "pcm_s16le",
                "X-Audio-Sample-Rate": "24000",
                "X-Audio-Channels": "1",
                "X-Audio-Buffered": "false",
                "Cache-Control": "no-cache, no-store",
                "X-Accel-Buffering": "no",
            },
            background=BackgroundTask(cleanup_upstream),
        )

    if selected_engine != "moss":
        raise HTTPException(status_code=400, detail="موتور صدای انتخاب‌شده پشتیبانی نمی‌شود.")

    reference_audio = None
    filename = ""
    if prompt_audio is not None:
        reference_audio = await prompt_audio.read()
        filename = prompt_audio.filename or "reference-audio.wav"
        if not reference_audio:
            raise HTTPException(status_code=400, detail="فایل صدای مرجع خالی است.")
        if len(reference_audio) > 20 * 1024 * 1024:
            raise HTTPException(status_code=413, detail="حداکثر اندازه‌ی صدای مرجع ۲۰ مگابایت است.")
    try:
        payload = await run_in_threadpool(_start_moss_stream, text, reference_audio, filename)
    except RuntimeError as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"شروع جریان صدای MOSS ناموفق بود: {exc}") from exc

    raw_audio_url = str(payload["audio_url"])
    audio_url = raw_audio_url if raw_audio_url.startswith(("http://", "https://")) else f"{MOSS_TTS_URL}/{raw_audio_url.lstrip('/')}"
    headers = {
        "X-Audio-Codec": "pcm_s16le",
        "X-Audio-Sample-Rate": str(payload.get("sample_rate") or 48000),
        "X-Audio-Channels": str(payload.get("channels") or 2),
        "Cache-Control": "no-cache, no-store",
        "X-Accel-Buffering": "no",
    }
    return StreamingResponse(
        _iter_moss_pcm(audio_url, str(payload["stream_id"])),
        media_type="application/octet-stream",
        headers=headers,
    )


app.mount("/", StaticFiles(directory=WEB_DIR), name="web-assets")
