import os
import sys
import time

sys.path.append("D:/DeepShield/models_gend")
from gend_video import extract_and_align
from gend_model import load_model, predict_image

DATASET_DIR = "D:/DeepShield/dataset"


def percentile(sorted_scores, p):
    n = len(sorted_scores)
    idx = p * (n - 1)
    lo, hi = int(idx), min(int(idx) + 1, n - 1)
    frac = idx - lo
    return sorted_scores[lo] * (1 - frac) + sorted_scores[hi] * frac


def analyze_with_loaded_model(video_path, model, device, num_frames=30, threshold=50):
    t0 = time.time()
    frame_paths = extract_and_align(video_path, num_frames=num_frames)

    if not frame_paths:
        return {"verdict": "INDÉTERMINÉ", "n_frames": 0}

    scores_real = []
    for frame_path in frame_paths:
        result = predict_image(frame_path, model, device)
        scores_real.append(result["Realism"])

    scores_real.sort()
    n = len(scores_real)
    avg_real = sum(scores_real) / n
    elapsed = time.time() - t0
    verdict = "RÉELLE" if avg_real > threshold else "DEEPFAKE"

    return {
        "verdict": verdict,
        "avg_real": avg_real,
        "median": percentile(scores_real, 0.5),
        "q1": percentile(scores_real, 0.25),
        "q3": percentile(scores_real, 0.75),
        "min": scores_real[0],
        "max": scores_real[-1],
        "n_frames": n,
        "elapsed": elapsed
    }


if __name__ == "__main__":
    print("Chargement du modèle GenD...")
    model, device = load_model()
    print(f"Device : {device}\n")

    results = []

    for label, subdir in [("REAL", "real"), ("FAKE", "fake")]:
        folder = os.path.join(DATASET_DIR, subdir)
        if not os.path.exists(folder):
            continue

        for filename in sorted(os.listdir(folder)):
            if not filename.lower().endswith((".mp4", ".mov", ".avi")):
                continue

            video_path = os.path.join(folder, filename)
            print(f"--- Analyse : {filename} (vérité: {label}) ---")

            report = analyze_with_loaded_model(video_path, model, device)

            if report["n_frames"] == 0:
                print(f"{filename}: aucun visage détecté, exclu\n")
                continue

            predicted = "REAL" if report["verdict"] == "RÉELLE" else "FAKE"
            correct = predicted == label
            print(f"-> moyenne={report['avg_real']:.1f}%  médiane={report['median']:.1f}%  "
                  f"prédit={predicted}, {'✓' if correct else '✗'}\n")

            results.append({
                "filename": filename, "truth": label, "predicted": predicted,
                "correct": correct, "avg_real": report["avg_real"],
                "median": report["median"], "q1": report["q1"], "q3": report["q3"],
                "min": report["min"], "max": report["max"],
                "n_frames": report["n_frames"], "elapsed": report["elapsed"]
            })

    if not results:
        print("Aucune vidéo analysée.")
        exit()

    print("\n========== RÉCAPITULATIF (pipeline GenD unifié) ==========")
    print(f"{'Fichier':16s} {'Vérité':7s} {'Prédit':7s} {'OK?':4s} {'Min':6s} {'Q1':6s} "
          f"{'Médiane':8s} {'Q3':6s} {'Max':6s} {'Moyenne':8s} {'Temps':7s}")
    for r in results:
        ok = "✓" if r["correct"] else "✗"
        print(f"{r['filename']:16s} {r['truth']:7s} {r['predicted']:7s} {ok:4s} "
              f"{r['min']:5.1f}% {r['q1']:5.1f}% {r['median']:7.1f}% {r['q3']:5.1f}% {r['max']:5.1f}% "
              f"{r['avg_real']:7.1f}% {r['elapsed']:5.1f}s")

    total = len(results)
    correct_count = sum(1 for r in results if r["correct"])
    accuracy = correct_count / total * 100

    real_results = [r for r in results if r["truth"] == "REAL"]
    fake_results = [r for r in results if r["truth"] == "FAKE"]
    fpr = (sum(1 for r in real_results if not r["correct"]) / len(real_results) * 100) if real_results else 0
    fnr = (sum(1 for r in fake_results if not r["correct"]) / len(fake_results) * 100) if fake_results else 0
    total_time = sum(r["elapsed"] for r in results)

    print(f"\nAccuracy globale : {accuracy:.1f}% ({correct_count}/{total})")
    print(f"Faux positifs (réel classé fake) : {fpr:.1f}% sur {len(real_results)} vidéos réelles")
    print(f"Faux négatifs (fake classé réel) : {fnr:.1f}% sur {len(fake_results)} vidéos fake")

    print(f"\nTemps total : {total_time:.1f}s ({total_time/total:.1f}s en moyenne par vidéo)")