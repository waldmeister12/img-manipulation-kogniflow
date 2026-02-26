"""
Image Manipulation API – kogniflow
Python/Flask backend using Pillow for image processing.

Endpoints:
  POST /process  – Accept an image + channel multipliers, return processed image.
  GET  /health   – Liveness check.
"""

import base64
import io

from flask import Flask, jsonify, request
from flask_cors import CORS
from PIL import Image, ImageOps

app = Flask(__name__)
CORS(app)

# Maximum allowed upload size (16 MB)
app.config["MAX_CONTENT_LENGTH"] = 16 * 1024 * 1024

ALLOWED_EXTENSIONS = {"png", "jpg", "jpeg", "gif", "bmp", "webp"}
MAX_DIMENSION = 1200  # px – resize larger images for performance


def _allowed(filename: str) -> bool:
    return "." in filename and filename.rsplit(".", 1)[1].lower() in ALLOWED_EXTENSIONS


@app.route("/process", methods=["POST"])
def process_image():
    """Accept an image file plus optional processing parameters and return
    the result encoded as a base64 data-URI inside a JSON response."""

    if "image" not in request.files:
        return jsonify({"error": "No image provided"}), 400

    file = request.files["image"]
    if not file.filename or not _allowed(file.filename):
        return jsonify({"error": "Invalid file type"}), 400

    try:
        # --- Parse parameters ------------------------------------------------
        r_mult = max(0.0, min(3.0, float(request.form.get("r", 1.0))))
        g_mult = max(0.0, min(3.0, float(request.form.get("g", 1.0))))
        b_mult = max(0.0, min(3.0, float(request.form.get("b", 1.0))))
        bw = request.form.get("bw", "false").lower() == "true"

        # --- Load & optionally resize ----------------------------------------
        img = Image.open(file.stream).convert("RGB")
        if max(img.size) > MAX_DIMENSION:
            img.thumbnail((MAX_DIMENSION, MAX_DIMENSION), Image.LANCZOS)

        # --- Black-and-white conversion --------------------------------------
        if bw:
            img = ImageOps.grayscale(img).convert("RGB")

        # --- Per-channel brightness multipliers ------------------------------
        if r_mult != 1.0 or g_mult != 1.0 or b_mult != 1.0:
            r_ch, g_ch, b_ch = img.split()
            r_ch = r_ch.point(lambda x: min(255, int(x * r_mult)))
            g_ch = g_ch.point(lambda x: min(255, int(x * g_mult)))
            b_ch = b_ch.point(lambda x: min(255, int(x * b_mult)))
            img = Image.merge("RGB", (r_ch, g_ch, b_ch))

        # --- Encode result ---------------------------------------------------
        buf = io.BytesIO()
        img.save(buf, format="JPEG", quality=90)
        encoded = base64.b64encode(buf.getvalue()).decode("utf-8")
        return jsonify({"image": "data:image/jpeg;base64," + encoded})

    except Exception as exc:  # pylint: disable=broad-except
        return jsonify({"error": str(exc)}), 500


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    app.run(debug=False, host="0.0.0.0", port=5000)
