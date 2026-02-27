"""
Tests for the Image Manipulation Flask API.
"""

import base64
import io
import json
import pytest
from PIL import Image

from app import app


@pytest.fixture
def client():
    app.config["TESTING"] = True
    with app.test_client() as c:
        yield c


def _make_image_bytes(width=100, height=100, color=(128, 64, 32), fmt="JPEG"):
    """Return raw bytes of a simple RGB image."""
    img = Image.new("RGB", (width, height), color)
    buf = io.BytesIO()
    img.save(buf, format=fmt)
    buf.seek(0)
    return buf.read()


# ── Health endpoint ────────────────────────────────────────────────────────────

def test_health(client):
    resp = client.get("/health")
    assert resp.status_code == 200
    data = json.loads(resp.data)
    assert data["status"] == "ok"


# ── /process – missing / invalid input ────────────────────────────────────────

def test_process_no_image(client):
    resp = client.post("/process")
    assert resp.status_code == 400
    assert "error" in json.loads(resp.data)


def test_process_invalid_filetype(client):
    data = {"image": (io.BytesIO(b"not an image"), "file.txt")}
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 400
    assert "error" in json.loads(resp.data)


# ── /process – successful processing ──────────────────────────────────────────

def test_process_returns_base64_jpeg(client):
    img_bytes = _make_image_bytes()
    data = {"image": (io.BytesIO(img_bytes), "test.jpg")}
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    result = json.loads(resp.data)
    assert "image" in result
    assert result["image"].startswith("data:image/jpeg;base64,")


def test_process_bw_conversion(client):
    """Black-and-white flag should produce a greyscale-like result."""
    img_bytes = _make_image_bytes(color=(200, 100, 50))
    data = {"image": (io.BytesIO(img_bytes), "test.jpg"), "bw": "true"}
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    result = json.loads(resp.data)
    # Decode result and check all channels are equal (greyscale)
    raw = base64.b64decode(result["image"].split(",", 1)[1])
    out_img = Image.open(io.BytesIO(raw)).convert("RGB")
    pixels = list(out_img.getdata())
    for r, g, b in pixels[:50]:
        assert r == g == b, f"Expected greyscale pixel but got ({r},{g},{b})"


def test_process_channel_multipliers(client):
    """Boosting red channel should make the result redder than the original."""
    # White image → all channels equal
    img_bytes = _make_image_bytes(color=(100, 100, 100))
    data = {
        "image": (io.BytesIO(img_bytes), "test.jpg"),
        "r": "2.0",
        "g": "0.5",
        "b": "0.5",
    }
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    result = json.loads(resp.data)
    raw = base64.b64decode(result["image"].split(",", 1)[1])
    out_img = Image.open(io.BytesIO(raw)).convert("RGB")
    r_avg = sum(p[0] for p in out_img.getdata()) / (out_img.width * out_img.height)
    g_avg = sum(p[1] for p in out_img.getdata()) / (out_img.width * out_img.height)
    assert r_avg > g_avg, "Red channel should be brighter than green after boosting"


def test_process_multiplier_clamping(client):
    """Multipliers outside [0, 3] must be clamped without error."""
    img_bytes = _make_image_bytes()
    data = {
        "image": (io.BytesIO(img_bytes), "test.jpg"),
        "r": "10.0",
        "g": "-5.0",
        "b": "1.0",
    }
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    assert "image" in json.loads(resp.data)


def test_process_large_image_resized(client):
    """Images wider than MAX_DIMENSION should be resized without error."""
    img_bytes = _make_image_bytes(width=1500, height=1500)
    data = {"image": (io.BytesIO(img_bytes), "big.jpg")}
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    result = json.loads(resp.data)
    raw = base64.b64decode(result["image"].split(",", 1)[1])
    out_img = Image.open(io.BytesIO(raw))
    assert max(out_img.size) <= 1200


def test_process_png_upload(client):
    """PNG uploads should also be handled correctly."""
    img_bytes = _make_image_bytes(fmt="PNG")
    data = {"image": (io.BytesIO(img_bytes), "test.png")}
    resp = client.post("/process", data=data, content_type="multipart/form-data")
    assert resp.status_code == 200
    assert "image" in json.loads(resp.data)
