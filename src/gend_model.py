import sys
import os

MODELS_GEND_DIR = "D:/DeepShield/models_gend"
sys.path.insert(0, MODELS_GEND_DIR)
os.chdir(MODELS_GEND_DIR)

import torch
from PIL import Image
from src.hf.modeling_gend import GenD

MODEL_NAME = "yermandy/GenD_CLIP_L_14"

def load_model():
    model = GenD.from_pretrained(MODEL_NAME)
    device = "cuda" if torch.cuda.is_available() else "cpu"
    model = model.to(device)
    model.eval()
    return model, device

def predict_image(image_path, model, device):
    img = Image.open(image_path).convert("RGB")
    tensor = model.feature_extractor.preprocess(img).unsqueeze(0).to(device)
    with torch.no_grad():
        probs = model(tensor).softmax(dim=-1).squeeze().tolist()
    return {"Realism": round(probs[0] * 100, 2), "Deepfake": round(probs[1] * 100, 2)}

if __name__ == "__main__":
    model, device = load_model()
    print(f"Device utilisé : {device}")
    result = predict_image("D:/DeepShield/temp/gend_pipeline_frames/test_video1/frame_0000.png", model, device)
    print(f"Résultat : {result}")