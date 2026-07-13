#!/usr/bin/env python3
"""
DeepShield - Bridge d'analyse
==============================

Ce script est le point d'intégration UNIQUE entre le front-end PHP et le
moteur d'analyse Python (voir cahier des charges, section 4.2 "Moteur
d'analyse"). Il est volontairement autonome et sans dépendance au front :

    python3 analyze_bridge.py --video /chemin/vers/video.mp4

Il écrit EXCLUSIVEMENT du JSON sur stdout (les logs/diagnostics vont sur
stderr) afin que PHP puisse json_decode() la sortie sans ambiguïté. Il ne
lève jamais d'exception non gérée : toute erreur est capturée et renvoyée
sous forme d'objet JSON structuré avec un code de sortie != 0
(exigence 5.3 : "jamais d'échec silencieux").

Deux modes :
- DEEPSHIELD_MOCK_MODE=1 (par défaut) : simulation déterministe, aucune
  dépendance ML requise. Utile pour la démo (exigence 5.4) et pour
  développer le front sans GPU/modèle.
- DEEPSHIELD_MOCK_MODE=0 : pipeline réel, réutilise la même logique que
  src/prithivMLmods/process_video.py + face_utils.py + test_model.py,
  mais SANS aucun chemin codé en dur : tout vient des variables
  d'environnement (voir .env.example), conformément à l'exigence 5.3.

L'audio n'est pas encore implémenté (planning : phase 2 "À faire"). Le
bridge le signale explicitement plutôt que d'échouer silencieusement,
et le score global se recalcule sur la seule modalité disponible
(exigence 4.2 : "gérer les cas où une seule modalité est disponible").
"""

import argparse
import hashlib
import json
import os
import sys
import time
import traceback
from datetime import datetime, timezone


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
MOCK_MODE = env_bool("DEEPSHIELD_MOCK_MODE", True)
MAX_FRAMES_DEFAULT = env_int("DEEPSHIELD_MAX_FRAMES", 30)
THRESHOLD_DEFAULT = env_float("DEEPSHIELD_THRESHOLD", 50)
MODEL_CACHE_DIR = env_str("DEEPSHIELD_MODEL_CACHE_DIR", "./storage/models")
TEMP_DIR = env_str("DEEPSHIELD_TEMP_DIR", "./storage/tmp")
FACE_MODEL_PATH = env_str(
    "DEEPSHIELD_FACE_MODEL_PATH",
    os.path.join(MODEL_CACHE_DIR, "face_detection_yunet_2023mar.onnx"),
)
GENAI_MODEL_NAME = env_str("DEEPSHIELD_VIDEO_MODEL", "prithivMLmods/Deep-Fake-Detector-v2-Model")


def log(msg):
    """Tous les logs de diagnostic partent sur stderr, jamais sur stdout."""
    print(f"[analyze_bridge] {msg}", file=sys.stderr, flush=True)


# ---------------------------------------------------------------------------
# Mode simulation (démo reproductible, exigence 5.4)
# ---------------------------------------------------------------------------
def mock_analyze_video(video_path, max_frames, threshold):
    """
    Simulation déterministe : le résultat dépend du nom de fichier (hash),
    pas d'un tirage aléatoire, pour que la démo soit reproductible
    (exigence 9 : "même entrée -> même sortie").
    """
    if not os.path.isfile(video_path):
        raise FileNotFoundError(f"Fichier vidéo introuvable : {video_path}")

    seed = int(hashlib.sha256(os.path.basename(video_path).encode("utf-8")).hexdigest(), 16)
    is_fake_like = (seed % 2 == 0)
    base_score = 22.0 if is_fake_like else 78.0
    jitter_source = seed

    frames = []
    n_frames = max(5, min(max_frames, 30))
    skipped = 0
    for i in range(n_frames):
        jitter_source = (jitter_source * 1103515245 + 12345) & 0x7FFFFFFF
        jitter = (jitter_source % 2000) / 100.0 - 10.0  # +/-10
        score_real = max(0.0, min(100.0, base_score + jitter))
        # 1 frame sur ~12 simule une frame sans visage détecté (rejet propre, exigence 4.1)
        if jitter_source % 12 == 0:
            skipped += 1
            continue
        frames.append({
            "index": i,
            "file": f"frame_{i:03d}.jpg",
            "score_real": round(score_real, 2),
            "score_deepfake": round(100.0 - score_real, 2),
            "suspect": score_real < threshold,
        })

    if not frames:
        raise RuntimeError("Aucune frame analysable (aucun visage détecté sur l'échantillon).")

    avg_real = sum(f["score_real"] for f in frames) / len(frames)
    avg_fake = 100.0 - avg_real

    time.sleep(0.4)  # simule un temps de traitement perceptible pour la démo UX

    return {
        "avg_real": round(avg_real, 2),
        "avg_fake": round(avg_fake, 2),
        "n_frames_analyzed": len(frames),
        "n_frames_skipped": skipped,
        "frames": frames,
        "engine": "mock",
    }


# ---------------------------------------------------------------------------
# Mode réel : reprend process_video.py / face_utils.py / test_model.py
# en supprimant tous les chemins codés en dur (exigence 5.3)
# ---------------------------------------------------------------------------
def real_analyze_video(video_path, max_frames, threshold):
    import cv2  # noqa: WPS433 (import local volontaire : évite la dépendance en mode mock)
    import torch
    from PIL import Image
    from transformers import AutoImageProcessor, AutoModelForImageClassification

    if not os.path.isfile(video_path):
        raise FileNotFoundError(f"Fichier vidéo introuvable : {video_path}")
    if not os.path.isfile(FACE_MODEL_PATH):
        raise FileNotFoundError(
            f"Modèle de détection de visage introuvable : {FACE_MODEL_PATH}. "
            "Vérifiez DEEPSHIELD_FACE_MODEL_PATH / DEEPSHIELD_MODEL_CACHE_DIR."
        )

    os.makedirs(TEMP_DIR, exist_ok=True)
    frames_dir = os.path.join(TEMP_DIR, "frames_" + os.path.splitext(os.path.basename(video_path))[0])
    os.makedirs(frames_dir, exist_ok=True)

    # --- 1. Extraction des frames (extract_frames.py, sans chemin en dur) ---
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Impossible d'ouvrir la vidéo : {video_path}")
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
    step = max(total_frames // max_frames, 1) if total_frames > 0 else 1

    frame_paths = []
    frame_idx, saved = 0, 0
    while cap.isOpened() and saved < max_frames:
        ret, frame = cap.read()
        if not ret:
            break
        if frame_idx % step == 0:
            fp = os.path.join(frames_dir, f"frame_{saved:03d}.jpg")
            cv2.imwrite(fp, frame)
            frame_paths.append(fp)
            saved += 1
        frame_idx += 1
    cap.release()

    # --- 2. Détection + recadrage du visage (face_utils.py) ---
    detector = cv2.FaceDetectorYN.create(FACE_MODEL_PATH, "", (320, 320),
                                          score_threshold=0.5, nms_threshold=0.3, top_k=5000)

    def extract_face(image_path, margin=0.3, min_face_size=160, confidence_threshold=0.6):
        img = cv2.imread(image_path)
        if img is None:
            return None
        h, w = img.shape[:2]
        detector.setInputSize((w, h))
        _, faces = detector.detect(img)
        if faces is None or len(faces) == 0:
            return None
        best = max(faces, key=lambda f: f[-1])
        if best[-1] < confidence_threshold:
            return None
        x, y, bw, bh = best[0:4].astype(int)
        x1, y1 = max(x, 0), max(y, 0)
        x2, y2 = min(x + bw, w), min(y + bh, h)
        mx, my = int((x2 - x1) * margin), int((y2 - y1) * margin)
        x1, y1 = max(x1 - mx, 0), max(y1 - my, 0)
        x2, y2 = min(x2 + mx, w), min(y2 + my, h)
        crop = img[y1:y2, x1:x2]
        if crop.size == 0:
            return None
        ch, cw = crop.shape[:2]
        smallest = min(ch, cw)
        if smallest < min_face_size:
            scale = min_face_size / smallest
            crop = cv2.resize(crop, (int(cw * scale), int(ch * scale)), interpolation=cv2.INTER_CUBIC)
        base, ext = os.path.splitext(image_path)
        out_path = f"{base}_face{ext}"
        cv2.imwrite(out_path, crop)
        return out_path

    # --- 3. Modèle de classification (test_model.py, cache dir configurable) ---
    processor = AutoImageProcessor.from_pretrained(GENAI_MODEL_NAME, cache_dir=MODEL_CACHE_DIR)
    model = AutoModelForImageClassification.from_pretrained(GENAI_MODEL_NAME, cache_dir=MODEL_CACHE_DIR)
    device = "cuda" if torch.cuda.is_available() else "cpu"
    model = model.to(device).eval()

    frames_result = []
    skipped = 0
    for i, frame_path in enumerate(frame_paths):
        face_path = extract_face(frame_path)
        if face_path is None:
            skipped += 1
            continue
        image = Image.open(face_path).convert("RGB")
        inputs = processor(images=image, return_tensors="pt").to(device)
        with torch.no_grad():
            outputs = model(**inputs)
            probs = torch.nn.functional.softmax(outputs.logits, dim=1).squeeze().tolist()
        labels = model.config.id2label
        result = {labels[j]: round(probs[j] * 100, 2) for j in range(len(probs))}
        score_real = result.get("Realism", result.get("real", 0))
        frames_result.append({
            "index": i,
            "file": os.path.basename(frame_path),
            "score_real": round(float(score_real), 2),
            "score_deepfake": round(100.0 - float(score_real), 2),
            "suspect": float(score_real) < threshold,
        })

    if not frames_result:
        raise RuntimeError("Aucune frame analysable (aucun visage détecté sur l'échantillon).")

    avg_real = sum(f["score_real"] for f in frames_result) / len(frames_result)
    return {
        "avg_real": round(avg_real, 2),
        "avg_fake": round(100.0 - avg_real, 2),
        "n_frames_analyzed": len(frames_result),
        "n_frames_skipped": skipped,
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
    engine_fn = mock_analyze_video if MOCK_MODE else real_analyze_video

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
            "message": "Le moteur audio (librosa + ASVspoof) est en cours de développement "
                       "(voir planning, phase 2). Le score global ci-dessous ne repose que sur "
                       "la modalité vidéo.",
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
            "engine": "mock" if MOCK_MODE else "real",
        },
        "video": video_block,
        "audio": audio_block,
        "global": {
            "verdict": global_verdict,
            "confidence_real_percent": global_score,
            "modalities_used": modalities_used,
            "disclaimer": "Ce score est une aide à la décision destinée à une équipe de "
                           "conformité. Il ne constitue jamais une décision automatique "
                           "de refus (voir cahier des charges, 3.2 et 5.2).",
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
        report = build_report(args.video, args.audio, args.max_frames, args.threshold)
        print(json.dumps(report, ensure_ascii=False))
        sys.exit(0)
    except Exception as exc:  # jamais d'échec silencieux (exigence 5.3)
        log(traceback.format_exc())
        print(json.dumps({
            "status": "error",
            "error": str(exc),
            "generated_at": datetime.now(timezone.utc).isoformat(),
        }, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
