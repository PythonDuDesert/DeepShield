from pathlib import Path
import os
import sys
import subprocess

PROJECT_ROOT = Path(__file__).resolve().parent.parent
MODELS_GEND_DIR = PROJECT_ROOT / "models_gend"
TEMP_FRAMES_DIR = Path(os.environ.get("DEEPSHIELD_TEMP_DIR", str(PROJECT_ROOT / "storage" / "tmp")))
DETECTOR_SCRIPT = MODELS_GEND_DIR / "detector.py"


# ==========================================================
# CUDA / cuDNN fournis par PyTorch
# ==========================================================

TORCH_LIB_DIR = (
    PROJECT_ROOT
    / "gend-env"
    / "Lib"
    / "site-packages"
    / "torch"
    / "lib"
)

if not TORCH_LIB_DIR.is_dir():
    raise RuntimeError(
        f"Dossier torch/lib introuvable : {TORCH_LIB_DIR}"
    )

# conserver le handle pour maintenir le dossier DLL actif
TORCH_DLL_DIR_HANDLE = os.add_dll_directory(
    str(TORCH_LIB_DIR)
)

# PyTorch doit être importé avant ONNX Runtime
import torch
import onnxruntime as ort

# ONNX Runtime 1.26.x = CUDA 12.x + cuDNN 9.x
# On lui demande explicitement d'utiliser les DLL de PyTorch.
ort.preload_dlls(cuda=True, cudnn=True, msvc=True, directory=str(TORCH_LIB_DIR),)
print(
    f"[CUDA] Torch : {torch.__version__}",
    file=sys.stderr,
    flush=True
)
print(
    f"[CUDA] Torch CUDA : {torch.version.cuda}",
    file=sys.stderr,
    flush=True
)
print(
    f"[CUDA] GPU : {torch.cuda.get_device_name(0)}",
    file=sys.stderr,
    flush=True
)
print(
    f"[CUDA] ONNX Runtime : {ort.__version__}",
    file=sys.stderr,
    flush=True
)
print(
    f"[CUDA] Providers : {ort.get_available_providers()}",
    file=sys.stderr,
    flush=True
)


# ==========================================================
# Import du modèle
# ==========================================================

if str(PROJECT_ROOT / "src") not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT / "src"))

from gend_model import load_model, predict_image


# ==========================================================
# Extraction et alignement
# ==========================================================
def extract_and_align(video_path, num_frames=30):
    video_path = Path(video_path)
    if not video_path.is_file():
        raise FileNotFoundError(
            f"Vidéo introuvable : {video_path}"
        )

    if not DETECTOR_SCRIPT.is_file():
        raise FileNotFoundError(
            f"Script detector.py introuvable : {DETECTOR_SCRIPT}"
        )

    TEMP_FRAMES_DIR.mkdir(parents=True, exist_ok=True)

    print(
        f"[gend_video] Extraction de {num_frames} frames...",
        file=sys.stderr,
        flush=True
    )

    # Le nom du dossier de sortie sera celui de la vidéo
    video_name = video_path.stem
    output_dir = TEMP_FRAMES_DIR

    # Utilise exactement le Python qui exécute DeepShield/le bridge.
    python_executable = sys.executable

    command = [
        python_executable,
        str(DETECTOR_SCRIPT),

        "-i",
        str(video_path),

        "-o",
        str(output_dir),

        "-m",
        "fixed_num_frames",

        "-n",
        str(num_frames),

        "--target_size",
        "256,256",

        "-s",
        "1.3",

        "--det_thres",
        "0.4",
    ]

    env = os.environ.copy()
    # Les DLL CUDA/cuDNN utilisées par ONNX Runtime sont fournies par PyTorch dans torch/lib.
    env["PATH"] = (str(TORCH_LIB_DIR) + os.pathsep + env.get("PATH", ""))

    subprocess.run(
        command,
        check=True,
        cwd=str(MODELS_GEND_DIR),
        env=env,
    )

    frames_folder = output_dir / video_name

    if not frames_folder.exists():
        print(
            f"[gend_video] Dossier introuvable : {frames_folder}",
            file=sys.stderr,
            flush=True
        )
        return []

    frame_files = sorted(
        str(path)
        for path in frames_folder.iterdir()
        if path.is_file()
        and path.suffix.lower() == ".png"
    )

    print(
        f"[gend_video] {len(frame_files)} frames alignées trouvées",
        file=sys.stderr,
        flush=True
    )

    return frame_files


# ==========================================================
# Analyse vidéo complète
# ==========================================================

def analyze_video(video_path, num_frames=30, threshold=50):
    model, device = load_model()
    print(
        "[gend_video] Extraction et alignement des visages...",
        file=sys.stderr,
        flush=True
    )

    frame_paths = extract_and_align(video_path, num_frames=num_frames)

    if not frame_paths:
        return {
            "verdict": "INDÉTERMINÉ",
            "reason": "Aucun visage détecté dans la vidéo",
            "n_frames": 0
        }

    scores_real = []

    for frame_path in frame_paths:
        result = predict_image(frame_path, model, device)
        scores_real.append(
            result["Realism"]
        )

    avg_real = sum(scores_real) / len(scores_real)
    verdict = (
        "RÉELLE"
        if avg_real > threshold
        else "DEEPFAKE"
    )

    return {
        "verdict": verdict,
        "avg_real": round(avg_real, 2),
        "avg_fake": round(100.0 - avg_real, 2),
        "n_frames": len(scores_real),
    }