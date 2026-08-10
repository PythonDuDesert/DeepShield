#!/usr/bin/env python3
"""
DeepShield - Bridge d'analyse
==============================

Ce script est le point d'intégration UNIQUE entre le front-end PHP et le
moteur d'analyse Python (voir cahier des charges, section 4.2 "Moteur
d'analyse").

L'audio n'est pas encore implémenté (planning : phase 2 "À faire"). Le
bridge le signale explicitement plutôt que d'échouer silencieusement,
et le score global se recalcule sur la seule modalité disponible
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
        # Pipeline audio non implémenté à ce stade du planning (phase 2 : "À faire").
        # On le signale explicitement plutôt que d'échouer silencieusement ou
        # de renvoyer un faux score (exigence 5.3 / 4.2).
        audio_block = {
            "filename": os.path.basename(audio_path),
            "status": "non_implemente",
            "message": "Le moteur audio (librosa + ASVspoof) est en cours de développement."
                       "Le score global ci-dessous ne repose que sur la modalité vidéo.",
        }

    modalities_used = []
    if video_block:
        modalities_used.append("video")
    if audio_block and audio_block.get("status") != "non_implemente":
        modalities_used.append("audio")

    if video_block:
        global_score = video_block["avg_real"]
        global_verdict = video_block["verdict"]
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
