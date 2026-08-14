#!/usr/bin/env python3
"""
DeepShield - Audio Deepfake Detector V1

Model:
    Vansh180/deepfake-audio-wav2vec2

Contract:
    real_probability = probability of BONAFIDE / REAL speech.
    fake_probability = 1 - real_probability.

Preprocessing:
    FFmpeg -> PCM WAV, mono, 16 kHz
    1.0 second windows, 0.5 second stride
"""

from __future__ import annotations

import os
import shutil
import subprocess
import tempfile
import wave
from pathlib import Path
from typing import Any
import sys

import numpy as np
import torch
from transformers import AutoFeatureExtractor, AutoModelForAudioClassification


MODEL_ID = os.environ.get(
    "DEEPSHIELD_AUDIO_MODEL",
    "Gustking/wav2vec2-large-xlsr-deepfake-audio-classification",
)
MODEL_CACHE_DIR = os.environ.get(
    "DEEPSHIELD_AUDIO_MODEL_CACHE",
    os.environ.get("DEEPSHIELD_MODEL_CACHE_DIR", "./storage"),
)

SAMPLE_RATE = 16_000
WINDOW_SAMPLES = 16_000
STRIDE_SAMPLES = 8_000

_model = None
_processor = None
_device = None


def _find_ffmpeg() -> str:
    configured = os.environ.get("DEEPSHIELD_FFMPEG")
    if configured:
        if not os.path.isfile(configured):
            raise FileNotFoundError(
                f"DEEPSHIELD_FFMPEG pointe vers un fichier introuvable : {configured}"
            )
        return configured

    found = shutil.which("ffmpeg")
    if found:
        return found

    candidates = [
        Path(__file__).resolve().parent / "ffmpeg" / "bin" / "ffmpeg.exe",
        Path(__file__).resolve().parent.parent / "ffmpeg" / "bin" / "ffmpeg.exe",
        Path("./ffmpeg/bin/ffmpeg.exe").resolve(),
    ]
    for candidate in candidates:
        if candidate.is_file():
            return str(candidate)

    raise FileNotFoundError(
        "FFmpeg introuvable. Ajoutez FFmpeg au PATH ou définissez DEEPSHIELD_FFMPEG."
    )


def _load_model():
    global _model, _processor, _device

    if _model is not None:
        return _model, _processor, _device

    _device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(
        f"[AUDIO] Model      : {MODEL_ID}",
        file=sys.stderr,
        flush=True,
    )
    print(
        f"[AUDIO] Device     : {_device}",
        file=sys.stderr,
        flush=True,
    )
    print(
        f"[AUDIO] CUDA avail. : {torch.cuda.is_available()}",
        file=sys.stderr,
        flush=True,
    )

    if torch.cuda.is_available():
        print(
            f"[AUDIO] GPU        : {torch.cuda.get_device_name(0)}",
            file=sys.stderr,
            flush=True,
        )
        print(
            f"[AUDIO] CUDA       : {torch.version.cuda}",
            file=sys.stderr,
            flush=True,
        )

    print(
        f"[AUDIO] Loading model...",
        file=sys.stderr,
        flush=True,
    )

    kwargs = {"cache_dir": MODEL_CACHE_DIR}
    _processor = AutoFeatureExtractor.from_pretrained(MODEL_ID, **kwargs)
    _model = AutoModelForAudioClassification.from_pretrained(MODEL_ID, **kwargs)
    _model.to(_device)
    _model.eval()

    labels = getattr(_model.config, "id2label", {}) or {}
    print(f"[audio_detector] Labels modèle: {labels}", flush=True)
    print(f"[audio_detector] Modèle chargé sur {_device}.", flush=True)
    print(
        f"[AUDIO] Labels     : {labels}",
        file=sys.stderr,
        flush=True,
    )
    print(
        f"[AUDIO] Model loaded successfully",
        file=sys.stderr,
        flush=True,
    )

    return _model, _processor, _device


def _convert_to_wav(input_path: str, output_path: str) -> None:
    ffmpeg = _find_ffmpeg()

    command = [
        ffmpeg, "-y", "-hide_banner", "-loglevel", "error",
        "-i", input_path,
        "-vn", "-ac", "1", "-ar", str(SAMPLE_RATE),
        "-acodec", "pcm_s16le",
        output_path,
    ]

    completed = subprocess.run(
        command,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
    )

    if completed.returncode != 0 or not os.path.isfile(output_path):
        details = completed.stderr.strip()
        raise RuntimeError(
            "Échec FFmpeg lors de la conversion audio."
            + (f" {details}" if details else "")
        )


def _read_pcm_wav(path: str) -> np.ndarray:
    with wave.open(path, "rb") as wav:
        channels = wav.getnchannels()
        sample_width = wav.getsampwidth()
        rate = wav.getframerate()
        frames = wav.readframes(wav.getnframes())

    if rate != SAMPLE_RATE:
        raise RuntimeError(f"WAV inattendu : {rate} Hz au lieu de {SAMPLE_RATE} Hz.")
    if sample_width != 2:
        raise RuntimeError(
            f"WAV inattendu : {sample_width * 8} bits au lieu de 16 bits."
        )

    audio = np.frombuffer(frames, dtype=np.int16).astype(np.float32) / 32768.0

    if channels > 1:
        audio = audio.reshape(-1, channels).mean(axis=1)

    if audio.size == 0:
        raise RuntimeError("Fichier audio vide ou illisible.")

    return audio


def _chunk_starts(total_samples: int) -> list[int]:
    if total_samples <= WINDOW_SAMPLES:
        return [0]

    count = int(np.ceil(
        (total_samples - WINDOW_SAMPLES) / STRIDE_SAMPLES
    )) + 1
    return [i * STRIDE_SAMPLES for i in range(count)]


def _real_label_indices(model: Any) -> set[int]:
    id2label = getattr(model.config, "id2label", {}) or {}
    indices = set()

    for raw_index, raw_label in id2label.items():
        label = str(raw_label).strip().lower()
        if any(token in label for token in (
            "bonafide", "bona fide", "real", "genuine", "authentic"
        )):
            indices.add(int(raw_index))

    if not indices:
        raise RuntimeError(
            f"Impossible d'identifier la classe REAL dans id2label={id2label!r}."
        )

    return indices


def _predict_real_probability(
    audio: np.ndarray,
    model: Any,
    processor: Any,
    device: torch.device,
    real_indices: set[int],
) -> float:
    inputs = processor(
        audio,
        sampling_rate=SAMPLE_RATE,
        return_tensors="pt",
    )
    inputs = {
        key: value.to(device) if hasattr(value, "to") else value
        for key, value in inputs.items()
    }

    with torch.inference_mode():
        logits = model(**inputs).logits
        probabilities = torch.softmax(logits, dim=-1)[0]

    real_probability = sum(probabilities[index].item() for index in real_indices)
    return max(0.0, min(1.0, float(real_probability)))


def analyze_audio(audio_path: str) -> dict[str, Any]:
    """Analyze one audio/media file and return a REAL-centric score."""
    input_path = Path(audio_path)
    if not input_path.is_file():
        raise FileNotFoundError(f"Fichier audio introuvable : {audio_path}")

    model, processor, device = _load_model()
    real_indices = _real_label_indices(model)

    with tempfile.TemporaryDirectory(prefix="deepshield_audio_") as temp_dir:
        wav_path = os.path.join(temp_dir, "normalized.wav")
        _convert_to_wav(str(input_path), wav_path)
        audio = _read_pcm_wav(wav_path)

        segments = []
        for index, start in enumerate(_chunk_starts(len(audio))):
            end = start + WINDOW_SAMPLES
            chunk = audio[start:end]

            if len(chunk) < WINDOW_SAMPLES:
                chunk = np.pad(chunk, (0, WINDOW_SAMPLES - len(chunk)), mode="constant")

            real_probability = _predict_real_probability(
                chunk, model, processor, device, real_indices
            )

            segments.append({
                "index": index,
                "start_seconds": round(start / SAMPLE_RATE, 3),
                "end_seconds": round(min(end, len(audio)) / SAMPLE_RATE, 3),
                "real_probability": round(real_probability, 4),
                "fake_probability": round(1.0 - real_probability, 4),
            })

    if not segments:
        raise RuntimeError("Aucun segment audio analysable.")

    final_real = float(np.mean([
        segment["real_probability"] for segment in segments
    ]))

    return {
        "real_probability": round(final_real, 4),
        "fake_probability": round(1.0 - final_real, 4),
        "segments": segments,
        "n_segments": len(segments),
        "duration_seconds": round(len(audio) / SAMPLE_RATE, 3),
        "model": MODEL_ID,
        "device": str(device),
    }


if __name__ == "__main__":
    import argparse
    import json

    parser = argparse.ArgumentParser()
    parser.add_argument("audio")
    args = parser.parse_args()

    print(json.dumps(analyze_audio(args.audio), ensure_ascii=False, indent=2))
