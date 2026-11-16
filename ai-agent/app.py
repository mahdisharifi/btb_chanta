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
from starlette.concurrency import run_in_threadpool

from realtime_runtime.asr_scheduler import RealtimeAsrQueue
from realtime_runtime.telemetry import TurnTelemetry


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
MOSS_REFERENCE_AUDIO = (
    ROOT / "emotional-tts" / "moss-tts-nano" / "assets" / "audio" / "fa_mana_reference.wav"
)

_realtime_asr_queue = RealtimeAsrQueue(max_pending_finals=8)


@asynccontextmanager
async def app_lifespan(_app: FastAPI):
    worker = asyncio.create_task(_run_realtime_asr_worker(), name="realtime-asr-worker")
    try:
        yield
    finally:
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
_stt_inference_lock = threading.Lock()
_reference_wav_cache: OrderedDict[str, bytes] = OrderedDict()
_reference_wav_cache_lock = threading.Lock()
_realtime_logger = logging.getLogger("ai_agent.realtime")


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
        "moss": _moss_status(),
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
            if job.kind == "partial":
                trace.start("stt_partial")
                partial_started_at = time.perf_counter()
                partial_text = await run_in_threadpool(_transcribe_partial_samples, payload["samples"])
                partial_stt_ms = trace.finish("stt_partial")
                if payload["is_current"]() and partial_text:
                    sent = await send_event({
                        "type": "partial",
                        "session_id": job.session_id,
                        "utterance_id": job.turn_id,
                        "text": partial_text,
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

            base_measurements = payload["base_measurements"]
            trace.start("stt_final")
            result = await run_in_threadpool(_transcribe_live_samples, payload["samples"])
            stt_final_ms = trace.finish("stt_final")
            timing = trace.snapshot(
                **base_measurements,
                stt_final_ms=stt_final_ms,
                stt_final_queue_ms=queue_wait_ms,
            )
            result["timing"] = timing
            _realtime_logger.info("voice_turn_metrics %s", json.dumps(timing, ensure_ascii=False))
            sent = await send_event({
                "type": "final",
                "session_id": job.session_id,
                "utterance_id": job.turn_id,
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


def _iter_piper_pcm(text: str):
    """Yield Piper's sentence-sized PCM chunks without building a whole WAV."""
    voice = _get_piper_voice()
    for audio_chunk in voice.synthesize(text):
        yield audio_chunk.audio_int16_bytes


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
    session_id = secrets.token_hex(8)
    send_lock = asyncio.Lock()
    active_utterance_id: int | None = None

    async def send_event(payload: dict[str, object]) -> bool:
        async with send_lock:
            try:
                await websocket.send_json(payload)
            except (WebSocketDisconnect, RuntimeError):
                return False
        return True

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
    end_silence_samples = int(0.42 * sample_rate)
    trailing_context_samples = int(0.12 * sample_rate)
    noise_floor = 0.002
    in_speech = False
    utterance_id = 0
    turn_trace: TurnTelemetry | None = None

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
            base_measurements = {
                "utterance_audio_ms": round(final_samples.size * 1000.0 / sample_rate, 1),
                "endpoint_wait_ms": endpoint_wait_ms,
            }
            trace = turn_trace or TurnTelemetry(session_id, utterance_id)
            await send_event({
                "type": "finalizing",
                "session_id": session_id,
                "utterance_id": utterance_id,
                "timing": trace.snapshot(**base_measurements),
            })
            accepted = await _realtime_asr_queue.submit_final(session_id, utterance_id, {
                "samples": final_samples.copy(),
                "trace": trace,
                "base_measurements": base_measurements,
                "queued_at": time.perf_counter(),
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
        in_speech = False

    try:
        while True:
            message = await websocket.receive()
            if message.get("type") == "websocket.disconnect":
                break

            packet = message.get("bytes")
            if packet:
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
                        utterance_id += 1
                        active_utterance_id = utterance_id
                        turn_trace = TurnTelemetry(session_id, utterance_id)
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
                    continue

                utterance_frames.append(samples)
                utterance_samples += int(samples.size)
                if rms < silence_threshold:
                    consecutive_silence_samples += int(samples.size)
                else:
                    consecutive_silence_samples = 0
                    voiced_samples += int(samples.size)

                if utterance_samples - partial_at_samples >= 2 * 16000 and utterance_samples >= 32000:
                    partial_samples = np.concatenate(utterance_frames).astype(np.float32, copy=False)
                    await _realtime_asr_queue.submit_partial(session_id, utterance_id, {
                        "samples": partial_samples,
                        "trace": turn_trace,
                        "partial_audio_ms": round(utterance_samples * 1000.0 / sample_rate, 1),
                        "queued_at": time.perf_counter(),
                        "send_event": send_event,
                        "is_current": lambda turn_id=utterance_id: active_utterance_id == turn_id,
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
    except WebSocketDisconnect:
        pass
    finally:
        await _realtime_asr_queue.discard_session(session_id)
        try:
            await websocket.close()
        except RuntimeError:
            pass


def _get_piper_voice():
    global _piper_voice
    if _piper_voice is None:
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


def _synthesize(text: str) -> bytes:
    voice = _get_piper_voice()
    audio = io.BytesIO()
    with wave.open(audio, "wb") as wav_file:
        # Set the header before synthesis so an earlier phonemizer error isn't
        # replaced by wave.Error("# channels not specified") during close.
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(voice.config.sample_rate)
        voice.synthesize_wav(text, wav_file, set_wav_format=False)
        if wav_file.tell() == 0:
            raise RuntimeError("Piper returned no audio chunks for the response text.")
    return audio.getvalue()


@app.post("/api/synthesize")
def synthesize(request: SpeakRequest) -> Response:
    try:
        audio = _synthesize(request.text)
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


@app.post("/api/synthesize/stream")
async def synthesize_stream(
    text: str = Form(..., min_length=1, max_length=1200),
    engine: str = Form("piper"),
    prompt_audio: UploadFile | None = File(None),
):
    """Return raw PCM as it is generated by Piper or MOSS."""
    selected_engine = engine.strip().lower()
    if selected_engine == "piper":
        try:
            voice = await run_in_threadpool(_get_piper_voice)
        except RuntimeError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc
        except Exception as exc:
            raise HTTPException(status_code=500, detail=f"آماده‌سازی Piper ناموفق بود: {exc}") from exc
        return StreamingResponse(
            _iter_piper_pcm(text),
            media_type="application/octet-stream",
            headers={
                "X-Audio-Codec": "pcm_s16le",
                "X-Audio-Sample-Rate": str(voice.config.sample_rate),
                "X-Audio-Channels": "1",
                "Cache-Control": "no-cache, no-store",
                "X-Accel-Buffering": "no",
            },
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
