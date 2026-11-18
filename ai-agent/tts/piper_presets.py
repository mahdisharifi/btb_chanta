"""Validated Piper inference presets; experimental presets are not defaulted."""

from __future__ import annotations

PIPER_PRESETS: dict[str, dict[str, float | str]] = {
    "default": {
        "label": "مدل، مقدار پیش‌فرض",
        "length_scale": 1.0,
        "noise_scale": 0.667,
        "noise_w_scale": 0.8,
    },
    "faster": {
        "label": "آزمایشی، length scale ۰٫۹۴",
        "length_scale": 0.94,
        "noise_scale": 0.667,
        "noise_w_scale": 0.8,
    },
    "steadier": {
        "label": "آزمایشی، noise پایین‌تر",
        "length_scale": 1.0,
        "noise_scale": 0.5,
        "noise_w_scale": 0.6,
    },
}


# A delivery profile shapes pacing around Piper's fixed voice. It is not a
# learned emotion control; keep changes small and leave voice noise settings
# untouched so the profile cannot make synthesis unstable.
PIPER_DELIVERY_PROFILES: dict[str, dict[str, float | str]] = {
    "warm": {"label": "گرم و صمیمی", "length_scale_delta": 0.0, "gap_ms": 90.0},
    "business": {"label": "حرفه‌ای و صمیمی", "length_scale_delta": 0.0, "gap_ms": 90.0},
    "empathetic": {"label": "همدلانه", "length_scale_delta": 0.03, "gap_ms": 110.0},
    "reassuring": {"label": "آرامش‌بخش", "length_scale_delta": 0.02, "gap_ms": 100.0},
    "upbeat": {"label": "پرانرژی", "length_scale_delta": -0.02, "gap_ms": 70.0},
}


def piper_synthesis_config(preset_name: str = "default"):
    try:
        preset = PIPER_PRESETS[preset_name]
    except KeyError as exc:
        raise ValueError(f"تنظیم TTS ناشناخته است: {preset_name}") from exc
    from piper.config import SynthesisConfig

    if preset_name == "default":
        return SynthesisConfig()
    return SynthesisConfig(
        length_scale=float(preset["length_scale"]),
        noise_scale=float(preset["noise_scale"]),
        noise_w_scale=float(preset["noise_w_scale"]),
    )


def apply_piper_delivery_profile(syn_config, voice_config, profile_name: str = "warm"):
    """Apply a conservative pacing adjustment to a Piper request config."""
    try:
        profile = PIPER_DELIVERY_PROFILES[profile_name]
    except KeyError as exc:
        raise ValueError(f"حالت گفتار Piper ناشناخته است: {profile_name}") from exc

    delta = float(profile["length_scale_delta"])
    if delta == 0.0:
        return syn_config

    # Piper resolves unset SynthesisConfig fields from the voice config.
    base_length_scale = syn_config.length_scale
    if base_length_scale is None:
        base_length_scale = voice_config.length_scale
    adjusted = max(0.80, min(1.20, float(base_length_scale) + delta))
    from dataclasses import replace

    return replace(syn_config, length_scale=adjusted)
