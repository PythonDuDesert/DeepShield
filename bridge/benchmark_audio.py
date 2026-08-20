#!/usr/bin/env python3
"""
DeepShield - Benchmark du moteur audio
=======================================
Le moteur utilisé est exactement celui du bridge :
    audio_detector.analyze_audio()

Pour chaque fichier :
- récupère les scores REAL de tous les segments ;
- calcule Min / Q1 / médiane / Q3 / Max / Moyenne ;
- mesure le temps d'analyse ;
- applique le seuil binaire REAL/FAKE pour le benchmark ;
- conserve également le verdict DeepShield (RÉEL / SUSPECT / DEEPFAKE).
"""

from __future__ import annotations

import argparse
import os
import statistics
import sys
import time
from pathlib import Path

import numpy as np

# Permet d'utiliser audio_detector.py placé dans le même dossier que ce script.
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from audio_detector import analyze_audio


AUDIO_EXTENSIONS = {".wav", ".mp3"}


def verdict_from_score(score: float, threshold: float) -> str:
    """Verdict DeepShield officiel, identique au bridge."""
    margin = 8.0
    if score >= threshold + margin:
        return "RÉEL"
    if score <= threshold - margin:
        return "DEEPFAKE"
    return "SUSPECT"


def collect_audio_files(directory: Path) -> list[Path]:
    if not directory.is_dir():
        raise FileNotFoundError(f"Dossier introuvable : {directory}")

    return sorted(
        p for p in directory.iterdir()
        if p.is_file() and p.suffix.lower() in AUDIO_EXTENSIONS
    )


def quartile(values: list[float], q: float) -> float:
    return float(np.percentile(values, q))


def analyze_one(path: Path, truth: str, threshold: float) -> dict:
    t0 = time.perf_counter()

    result = analyze_audio(str(path))

    elapsed = time.perf_counter() - t0

    segments = result.get("segments", [])
    scores = [
        float(segment["real_probability"]) * 100.0
        for segment in segments
        if "real_probability" in segment
    ]

    if not scores:
        raise RuntimeError("Le moteur n'a retourné aucun score de segment.")

    mean_score = statistics.fmean(scores)
    median_score = statistics.median(scores)

    # Benchmark binaire : même principe que le benchmark vidéo.
    # >= seuil => REAL, < seuil => FAKE.
    predicted = "REAL" if mean_score >= threshold else "FAKE"
    ok = predicted == truth

    engine_verdict = verdict_from_score(mean_score, threshold)

    return {
        "file": path.name,
        "truth": truth,
        "predicted": predicted,
        "ok": ok,
        "min": min(scores),
        "q1": quartile(scores, 25),
        "median": median_score,
        "q3": quartile(scores, 75),
        "max": max(scores),
        "mean": mean_score,
        "time": elapsed,
        "n_segments": len(scores),
        "engine_verdict": engine_verdict,
        "real_probability": float(result["real_probability"]),
    }


def print_row(row: dict) -> None:
    print(
        f"{row['file']:<24} "
        f"{row['truth']:<5} "
        f"{row['predicted']:<8} "
        f"{'✓' if row['ok'] else '✗':<3} "
        f"{row['min']:>5.1f}% "
        f"{row['q1']:>5.1f}% "
        f"{row['median']:>8.1f}% "
        f"{row['q3']:>5.1f}% "
        f"{row['max']:>5.1f}% "
        f"{row['mean']:>8.1f}% "
        f"{row['time']:>6.1f}s"
    )


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Benchmark du moteur audio réel DeepShield"
    )

    parser.add_argument(
        "--real-dir",
        default=os.path.join("dataset", "audio", "real"),
        help="Dossier contenant les audios REAL",
    )
    parser.add_argument(
        "--fake-dir",
        default=os.path.join("dataset", "audio", "fake"),
        help="Dossier contenant les audios FAKE",
    )
    parser.add_argument(
        "--threshold",
        type=float,
        default=float(os.environ.get("DEEPSHIELD_AUDIO_THRESHOLD", "50")),
        help="Seuil binaire REAL/FAKE en pourcentage (défaut: 50)",
    )

    args = parser.parse_args()

    real_dir = Path(args.real_dir)
    fake_dir = Path(args.fake_dir)
    threshold = args.threshold

    real_files = collect_audio_files(real_dir)
    fake_files = collect_audio_files(fake_dir)

    if not real_files:
        raise RuntimeError(f"Aucun fichier audio REAL dans : {real_dir}")
    if not fake_files:
        raise RuntimeError(f"Aucun fichier audio FAKE dans : {fake_dir}")

    print("=" * 115)
    print("DEEPSHIELD - BENCHMARK MOTEUR AUDIO")
    print("=" * 115)
    print(f"REAL : {real_dir} ({len(real_files)} fichiers)")
    print(f"FAKE : {fake_dir} ({len(fake_files)} fichiers)")
    print(f"Seuil benchmark : {threshold:.1f}%")
    print()
    print(
        f"{'Fichier':<24} "
        f"{'Vérité':<5} "
        f"{'Prédit':<8} "
        f"{'OK?':<3} "
        f"{'Min':>6} "
        f"{'Q1':>6} "
        f"{'Médiane':>9} "
        f"{'Q3':>6} "
        f"{'Max':>6} "
        f"{'Moyenne':>9} "
        f"{'Temps':>7}"
    )
    print("-" * 115)

    rows: list[dict] = []
    total_t0 = time.perf_counter()

    all_files = [(p, "REAL") for p in real_files] + [
        (p, "FAKE") for p in fake_files
    ]

    for path, truth in all_files:
        try:
            row = analyze_one(path, truth, threshold)
            rows.append(row)
            print_row(row)
        except Exception as exc:
            print(
                f"{path.name:<24} {truth:<5} ERREUR: {exc}",
                file=sys.stderr,
            )

    total_time = time.perf_counter() - total_t0

    if not rows:
        raise RuntimeError("Aucun fichier n'a pu être analysé.")

    real_rows = [r for r in rows if r["truth"] == "REAL"]
    fake_rows = [r for r in rows if r["truth"] == "FAKE"]

    correct = sum(r["ok"] for r in rows)
    total = len(rows)

    false_positive = sum(
        r["truth"] == "REAL" and r["predicted"] == "FAKE"
        for r in rows
    )
    false_negative = sum(
        r["truth"] == "FAKE" and r["predicted"] == "REAL"
        for r in rows
    )

    accuracy = 100.0 * correct / total
    fp_rate = 100.0 * false_positive / len(real_rows) if real_rows else 0.0
    fn_rate = 100.0 * false_negative / len(fake_rows) if fake_rows else 0.0

    suspect_count = sum(r["engine_verdict"] == "SUSPECT" for r in rows)

    print()
    print("=" * 115)
    print("RÉCAPITULATIF")
    print("=" * 115)
    print(f"Accuracy globale : {accuracy:.1f}% ({correct}/{total})")
    print(
        f"Faux positifs (réel classé fake) : "
        f"{fp_rate:.1f}% sur {len(real_rows)} audios réels"
    )
    print(
        f"Faux négatifs (fake classé réel) : "
        f"{fn_rate:.1f}% sur {len(fake_rows)} audios fake"
    )
    print(f"Verdicts SUSPECT DeepShield : {suspect_count}/{total}")
    print()
    print(
        f"Temps total : {total_time:.1f}s "
        f"({total_time / total:.1f}s en moyenne par audio)"
    )

    # Quelques métriques supplémentaires utiles pour évaluer le moteur.
    if real_rows:
        real_mean = statistics.fmean(r["mean"] for r in real_rows)
        print(f"Score REAL moyen des vrais audios : {real_mean:.1f}%")

    if fake_rows:
        fake_mean = statistics.fmean(r["mean"] for r in fake_rows)
        print(f"Score REAL moyen des faux audios  : {fake_mean:.1f}%")

    print("=" * 115)

    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        print("\nBenchmark interrompu.", file=sys.stderr)
        raise SystemExit(130)
    except Exception as exc:
        print(f"\nERREUR: {exc}", file=sys.stderr)
        raise SystemExit(1)
