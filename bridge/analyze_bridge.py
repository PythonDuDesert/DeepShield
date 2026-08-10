#!/usr/bin/env python3
"""
DeepShield - Bridge d'analyse
==============================

Ce script est le point d'intégration UNIQUE entre le front-end PHP et le
moteur d'analyse Python (voir cahier des charges, section 4.2 "Moteur
d'analyse").

L'audio dispose désormais d'un mode simulation (comme la vidéo) et d'un
mode réel basé sur des descripteurs spectraux (librosa) : variance des
MFCC, platitude spectrale et taux de passage par zéro. C'est une base
heuristique explicable en attendant un modèle entraîné sur ASVspoof
(exigence 4.2 : "gérer les cas où une seule modalité est disponible").
"""

import contextlib
import argparse
import hashlib
import json
import os
import sys
import time
import traceback
from datetime import datetime, timezone


if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="strict")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


def env_str(name, default):
    val = os.environ.get(name)
    return val if val not in (None, "") else default


def env_int(name, default):
    try:
        return int(os.environ.get(name, default))
    except (TypeError, ValueError):
        return default


def env_float(name, default):
    try:
        return float(os.environ.get(name, default))
    except (TypeError, ValueError):
        return default


def env_bool(name, default):
    val = os.environ.get(name)
    if val is None:
        return default
    return val.strip() in ("1", "true", "True", "yes", "on")


# ---------------------------------------------------------------------------
# Configuration (uniquement via variables d'environnement, exigence 5.3)
# ---------------------------------------------------------------------------
MAX_FRAMES_DEFAULT = env_int("DEEPSHIELD_MAX_FRAMES", 30)
THRESHOLD_DEFAULT = env_float("DEEPSHIELD_THRESHOLD", 50)
ML_DIR = env_str("DEEPSHIELD_ML_SRC_DIR", os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "src"))
MODEL_CACHE_DIR = env_str("DEEPSHIELD_MODEL_CACHE_DIR", "./storage")
FACE_MODEL_PATH = env_str("DEEPSHIELD_FACE_MODEL_PATH", os.path.join(MODEL_CACHE_DIR, "det_10g.onnx"),)
GENAI_MODEL_NAME = env_str("DEEPSHIELD_VIDEO_MODEL", "prithivMLmods/Deep-Fake-Detector-v2-Model")
AUDIO_THRESHOLD_DEFAULT = env_float("DEEPSHIELD_AUDIO_THRESHOLD", 50)
TEMP_DIR = env_str("DEEPSHIELD_TEMP_DIR", "./storage/temp")

def log(msg):
    """Tous les logs de diagnostic partent sur stderr, jamais sur stdout."""
    print(f"[analyze_bridge] {msg}", file=sys.stderr, flush=True)



def real_analyze_video(video_path, max_frames, threshold):
    import sys

    ml_dir = os.path.abspath(ML_DIR)
    if ml_dir not in sys.path:
        sys.path.insert(0, ml_dir)

    from gend_model import load_model, predict_image
    from gend_video import extract_and_align

    if not os.path.isfile(video_path):
        raise FileNotFoundError(f"Fichier vidéo introuvable : {video_path}")

    log("Chargement du modèle GenD...")
    model, device = load_model()

    log(f"Extraction et alignement des visages ({max_frames} frames max)...")
    frame_paths = extract_and_align(video_path, num_frames=max_frames)

    if not frame_paths:
        raise RuntimeError("Aucune frame analysable (aucun visage détecté).")

    frames_result = []
    for i, fp in enumerate(frame_paths):
        result = predict_image(fp, model, device)
        score_real = result["Realism"]
        frames_result.append({
            "index": i,
            "file": os.path.basename(fp),
            "score_real": score_real,
            "score_deepfake": result["Deepfake"],
            "suspect": score_real < threshold,
        })

    avg_real = sum(f["score_real"] for f in frames_result) / len(frames_result)
    return {
        "avg_real": round(avg_real, 2),
        "avg_fake": round(100.0 - avg_real, 2),
        "n_frames_analyzed": len(frames_result),
        "n_frames_skipped": 0,
        "frames": frames_result,
        "engine": "real",
    }


def mock_analyze_audio(audio_path, threshold):
    """
    Simulation déterministe pour l'audio, même logique que
    mock_analyze_video : le résultat dépend du hash du nom de fichier,
    pas d'un tirage aléatoire (exigence 9 : "même entrée -> même sortie").
    """
    if not os.path.isfile(audio_path):
        raise FileNotFoundError(f"Fichier audio introuvable : {audio_path}")

    seed = int(hashlib.sha256(("audio::" + os.path.basename(audio_path)).encode("utf-8")).hexdigest(), 16)
    is_fake_like = (seed % 2 == 0)
    base_score = 24.0 if is_fake_like else 76.0
    jitter = (seed % 1600) / 100.0 - 8.0  # +/-8
    score_real = max(0.0, min(100.0, base_score + jitter))

    time.sleep(0.2)  # simule un temps de traitement perceptible pour la démo UX

    return {
        "avg_real": round(score_real, 2),
        "avg_fake": round(100.0 - score_real, 2),
        "features": {
            "mfcc_variance": round((seed % 500) / 100.0, 2),
            "spectral_flatness": round((seed % 100) / 100.0, 3),
            "zero_crossing_rate": round((seed % 300) / 1000.0, 3),
        },
        "engine": "mock",
    }


def real_analyze_audio(audio_path, threshold):
    """
    Mode réel : extraction de descripteurs spectraux via librosa, puis
    un score heuristique explicable (pas de réseau de neurones entraîné
    à ce stade — voir cahier des charges, planning phase 2). L'audio
    synthétique tend à présenter une platitude spectrale plus élevée et
    une variance de MFCC plus faible que la voix naturelle ; ce n'est
    qu'une base de référence, à remplacer par un modèle ASVspoof entraîné
    dès qu'il sera disponible, sans changer l'interface de cette fonction.
    """
    import numpy as np
    import librosa  # noqa: WPS433 (import local volontaire : évite la dépendance en mode mock)

    if not os.path.isfile(audio_path):
        raise FileNotFoundError(f"Fichier audio introuvable : {audio_path}")

    y, sr = librosa.load(audio_path, sr=None, mono=True)
    if y.size == 0:
        raise RuntimeError("Fichier audio vide ou illisible.")

    mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=20)
    mfcc_variance = float(np.mean(np.var(mfcc, axis=1)))

    flatness = float(np.mean(librosa.feature.spectral_flatness(y=y)))
    zcr = float(np.mean(librosa.feature.zero_crossing_rate(y)))

    # Score heuristique 0-100 (0 = probablement synthétique, 100 = probablement réel).
    # Normalisation empirique : variance MFCC élevée + platitude spectrale basse
    # => voix naturelle. Bornes calibrées sur des échantillons de parole courants,
    # à ajuster une fois qu'un vrai jeu de données ASVspoof est disponible.
    variance_component = max(0.0, min(1.0, mfcc_variance / 400.0))
    flatness_component = max(0.0, min(1.0, 1.0 - (flatness / 0.35)))
    score_real = round(((variance_component * 0.6) + (flatness_component * 0.4)) * 100.0, 2)

    return {
        "avg_real": score_real,
        "avg_fake": round(100.0 - score_real, 2),
        "features": {
            "mfcc_variance": round(mfcc_variance, 2),
            "spectral_flatness": round(flatness, 3),
            "zero_crossing_rate": round(zcr, 3),
        },
        "engine": "real",
        "engine_note": "Score heuristique (descripteurs spectraux), pas encore un modèle ASVspoof entraîné.",
    }



def verdict_from_score(avg_real, threshold):
    margin = 8.0  # zone grise autour du seuil -> verdict "SUSPECT" (exigence 4.3)
    if avg_real >= threshold + margin:
        return "RÉEL"
    if avg_real <= threshold - margin:
        return "DEEPFAKE"
    return "SUSPECT"


def build_report(video_path, audio_path, max_frames, threshold):
    t0 = time.time()
    engine_fn = real_analyze_video

    video_block = None
    if video_path:
        v = engine_fn(video_path, max_frames, threshold)
        v_verdict = verdict_from_score(v["avg_real"], threshold)
        # frames les plus suspectes en tête, pour l'explicabilité (exigence 4.3)
        v["frames_sorted_by_suspicion"] = sorted(v["frames"], key=lambda f: f["score_real"])[:5]
        video_block = {
            "filename": os.path.basename(video_path),
            "verdict": v_verdict,
            **v,
        }

    audio_block = None
    if audio_path:
        audio_engine_fn = mock_analyze_audio if MOCK_MODE else real_analyze_audio
        a = audio_engine_fn(audio_path, threshold)
        a_verdict = verdict_from_score(a["avg_real"], threshold)
        audio_block = {
            "filename": os.path.basename(audio_path),
            "status": "non_implemente",
            "message": "Le moteur audio (librosa + ASVspoof) est en cours de développement."
                       "Le score global ci-dessous ne repose que sur la modalité vidéo.",
        }

    modalities_used = []
    if video_block:
        modalities_used.append("video")
    if audio_block:
        modalities_used.append("audio")

    if video_block and audio_block:
        # Pondération simple : la vidéo (analyse multi-frames) pèse un peu plus
        # que l'audio (mode heuristique) tant que ce dernier n'est pas un modèle
        # entraîné (exigence 4.2 : gérer plusieurs modalités disponibles).
        global_score = round((video_block["avg_real"] * 0.6) + (audio_block["avg_real"] * 0.4), 2)
        global_verdict = verdict_from_score(global_score, threshold)
    elif video_block:
        global_score = video_block["avg_real"]
        global_verdict = video_block["verdict"]
    elif audio_block:
        global_score = audio_block["avg_real"]
        global_verdict = audio_block["verdict"]
    else:
        global_score = None
        global_verdict = "INDÉTERMINÉ"

    elapsed = round(time.time() - t0, 2)

    return {
        "status": "ok",
        "error": None,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "elapsed_seconds": elapsed,
        "params": {
            "max_frames": max_frames,
            "threshold": threshold,
            "engine": "real",
        },
        "video": video_block,
        "audio": audio_block,
        "global": {
            "verdict": global_verdict,
            "confidence_real_percent": global_score,
            "modalities_used": modalities_used,
        },
    }


def main():
    parser = argparse.ArgumentParser(description="DeepShield analyze bridge")
    parser.add_argument("--video", default=None, help="Chemin du fichier vidéo (.mp4/.mov)")
    parser.add_argument("--audio", default=None, help="Chemin du fichier audio (.wav/.mp3)")
    parser.add_argument("--max-frames", type=int, default=MAX_FRAMES_DEFAULT)
    parser.add_argument("--threshold", type=float, default=THRESHOLD_DEFAULT)
    args = parser.parse_args()

    if not args.video and not args.audio:
        print(json.dumps({
            "status": "error",
            "error": "Aucune modalité fournie (ni vidéo ni audio).",
        }))
        sys.exit(1)

    try:
        with contextlib.redirect_stdout(sys.stderr):
            report = build_report(
                args.video,
                args.audio,
                args.max_frames,
                args.threshold
            )
        print(json.dumps(report, ensure_ascii=False).encode("utf-8").decode("utf-8"))
        sys.exit(0)

    except Exception as exc:
        log(traceback.format_exc())
        print(json.dumps({
            "status": "error",
            "error": str(exc),
            "generated_at": datetime.now(timezone.utc).isoformat(),
        }, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
