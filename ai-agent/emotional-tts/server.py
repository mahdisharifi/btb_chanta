from __future__ import annotations

import io
import os
import re
import threading
from pathlib import Path
from typing import Literal

import soundfile as sf
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, Response
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field


ROOT = Path(__file__).resolve().parent
WEB_DIR = ROOT / "web"
MODEL_DIR = Path(
    os.environ.get(
        "EMOTION_TTS_MODEL_DIR",
        ROOT / "models" / "voxcpm-fa-emotion" / "best_checkpoint_of_post_training",
    )
).expanduser().resolve()
REQUIRED_MODEL_FILES = (
    "config.json",
    "model.safetensors",
    "audiovae.pth",
    "tokenizer.json",
    "tokenizer_config.json",
    "special_tokens_map.json",
    "tokenization_voxcpm2.py",
)

EMOTIONS = {
    "neutral": None,
    "warm": "warm",
    "happy": "happy",
    "excited": "excited",
    "sad": "sad",
    "fear": "fear",
    "anger": "anger",
}
PARALINGUISTIC_TAGS = {
    "none": None,
    "breath": "[breath]",
    "sighs": "[sighs]",
    "uhm": "[uhm]",
    "chuckles": "[chuckles]",
    "yawns": "[yawns]",
}

app = FastAPI(title="Persian Emotional TTS Lab", docs_url="/api/docs", redoc_url=None)
_model = None
_model_lock = threading.Lock()


class SynthesizeRequest(BaseModel):
    text: str = Field(min_length=1, max_length=1200)
    emotion: Literal["neutral", "warm", "happy", "excited", "sad", "fear", "anger"] = "neutral"
    tag: Literal["none", "breath", "sighs", "uhm", "chuckles", "yawns"] = "none"


def _missing_model_files() -> list[str]:
    return [name for name in REQUIRED_MODEL_FILES if not (MODEL_DIR / name).is_file()]


def _get_model():
    global _model
    if _model is None:
        missing = _missing_model_files()
        if missing:
            raise RuntimeError(
                f"وزن‌های مدل VoxCPM پیدا نشدند. فایل‌های لازم در {MODEL_DIR}: "
                + ", ".join(missing)
            )
        try:
            from voxcpm import VoxCPM
        except ImportError as exc:
            raise RuntimeError(
                "کتابخانه‌ی رسمی VoxCPM در محیط سرویس نصب نیست. دستور نصب در README همین پوشه است."
            ) from exc

        # CPU mode keeps this optional evaluation service usable without Docker
        # or a CUDA GPU. Generation will be slower on a small CPU.
        _model = VoxCPM(
            voxcpm_model_path=str(MODEL_DIR),
            enable_denoiser=False,
            optimize=False,
            device=os.environ.get("EMOTION_TTS_DEVICE", "cpu"),
        )
    return _model


def _clean_for_speech(text: str) -> str:
    """Remove common Markdown/UI notation before it reaches a speech model."""
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = re.sub(r"```[\s\S]*?```", " ", text)
    text = re.sub(r"`([^`]*)`", r"\1", text)
    text = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", text)
    text = re.sub(r"https?://\S+", " ", text)
    text = re.sub(r"^\s{0,3}#{1,6}\s*", "", text, flags=re.MULTILINE)
    text = re.sub(r"^\s*(?:[-*+]\s+|\d+[.)]\s+)", "", text, flags=re.MULTILINE)
    text = re.sub(r"[*_~]{1,3}", "", text)
    text = re.sub(r"<[^>]+>", " ", text)
    lines = [re.sub(r"\s+", " ", line).strip() for line in text.splitlines()]
    speech_lines = []
    for line in (line for line in lines if line):
        if not re.search(r"[.!?؟…؛:]$", line):
            line += "."
        speech_lines.append(line)
    return " ".join(speech_lines)


def _build_tts_text(request: SynthesizeRequest) -> str:
    clean_text = _clean_for_speech(request.text)
    if not clean_text:
        raise ValueError("بعد از پاک‌کردن نشانه‌های قالب‌بندی، متنی برای خواندن باقی نماند.")

    parts: list[str] = []
    emotion = EMOTIONS[request.emotion]
    tag = PARALINGUISTIC_TAGS[request.tag]
    if emotion:
        parts.append(f"({emotion})")
    if tag:
        parts.append(tag)
    parts.append(clean_text)
    return "".join(parts)


def _synthesize(text: str) -> tuple[bytes, int]:
    import numpy as np

    model = _get_model()
    waveform = model.generate(
        text=text,
        cfg_value=2.0,
        inference_timesteps=10,
        normalize=False,
        retry_badcase=True,
        retry_badcase_max_times=2,
    )
    waveform = np.asarray(waveform, dtype=np.float32).reshape(-1)
    if waveform.size == 0:
        raise RuntimeError("موتور VoxCPM صدای خالی برگرداند.")

    sample_rate = int(model.tts_model.sample_rate)
    output = io.BytesIO()
    sf.write(output, waveform, sample_rate, format="WAV", subtype="PCM_16")
    return output.getvalue(), sample_rate


@app.get("/")
def home() -> FileResponse:
    return FileResponse(WEB_DIR / "index.html")


@app.get("/api/status")
def status() -> dict[str, object]:
    missing = _missing_model_files()
    return {
        "service": "ready",
        "engine": "VoxCPM2 Persian emotion fine-tune",
        "model_path": str(MODEL_DIR),
        "model_files_present": not missing,
        "missing_model_files": missing,
        "loaded": _model is not None,
        "device": os.environ.get("EMOTION_TTS_DEVICE", "cpu"),
        "emotions": list(EMOTIONS),
        "tags": list(PARALINGUISTIC_TAGS),
    }


@app.post("/api/synthesize")
def synthesize(request: SynthesizeRequest) -> Response:
    try:
        tts_text = _build_tts_text(request)
        with _model_lock:
            audio, sample_rate = _synthesize(tts_text)
    except (RuntimeError, ValueError) as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"تولید صدا ناموفق بود: {exc}") from exc

    return Response(
        content=audio,
        media_type="audio/wav",
        headers={"X-Audio-Sample-Rate": str(sample_rate)},
    )


app.mount("/", StaticFiles(directory=WEB_DIR), name="web-assets")
