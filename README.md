# Image Manipulation – kogniflow

An interactive web app to upload an image, convert it to black & white, and adjust individual RGB channel brightness using an intuitive triangular colour-balance control.

## Technology Stack

| Layer | Technology |
|---|---|
| Frontend | PHP 8 + HTML5 / CSS3 / vanilla JS |
| Image processing | Python 3 / Flask + Pillow |

## Features

* **Drag-and-drop upload** – or use the file picker; supports PNG, JPG, GIF, WebP, BMP (max 16 MB).
* **Side-by-side preview** – original and processed image shown simultaneously.
* **Black-and-white toggle** – converts the image to greyscale before applying channel adjustments.
* **Triangle colour-balance control** – an HTML5 Canvas triangle with vertices **R** (top), **G** (bottom-left) and **B** (bottom-right). Drag the white handle:
  * Centre of triangle = all channels neutral (1.0×).
  * Towards **R** vertex = increase red brightness, reduce green & blue.
  * Towards **G** vertex = increase green brightness, reduce red & blue.
  * Towards **B** vertex = increase blue brightness, reduce red & green.
  * Multiplier range 0–3× per channel (barycentric mapping).
* **Download button** – save the processed image as JPEG.

## Setup

### 1. Install Python dependencies

```bash
python3 -m venv venv
source venv/bin/activate      # Windows: venv\Scripts\activate
pip install -r requirements.txt
```

### 2. Start the Flask API (port 5000)

```bash
python app.py
```

### 3. Start the PHP development server (port 8080)

```bash
php -S localhost:8080
```

Then open **http://localhost:8080/index.php** in your browser.

### Environment variable

Set `API_URL` to override the default Flask endpoint (`http://localhost:5000/process`):

```bash
API_URL=http://my-flask-host:5000/process php -S localhost:8080
```
