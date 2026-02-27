<?php
/**
 * Image Manipulation – kogniflow
 *
 * PHP frontend that:
 *   • On GET  – serves the interactive HTML/JS/CSS single-page application.
 *   • On POST – validates the uploaded file, then proxies the request to the
 *               Python/Flask image-processing API and returns the JSON result.
 */

// ─── PHP Proxy ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    // Validate presence of uploaded file
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $code = isset($_FILES['image']) ? $_FILES['image']['error'] : 0;
        echo json_encode(['error' => 'Upload error (code ' . $code . ')']);
        exit;
    }

    // Validate MIME type against actual file content
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($_FILES['image']['tmp_name']);
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    // Validate file size (max 16 MB)
    if ($_FILES['image']['size'] > 16 * 1024 * 1024) {
        echo json_encode(['error' => 'File too large (max 16 MB)']);
        exit;
    }

    // Sanitise numeric parameters
    $r  = max(0.0, min(3.0, (float)($_POST['r']  ?? 1.0)));
    $g  = max(0.0, min(3.0, (float)($_POST['g']  ?? 1.0)));
    $b  = max(0.0, min(3.0, (float)($_POST['b']  ?? 1.0)));
    $bw = (($_POST['bw'] ?? 'false') === 'true') ? 'true' : 'false';

    // Forward to Flask API
    $api_url = getenv('API_URL') ?: 'http://localhost:5000/process';

    $post_data = [
        'image' => new CURLFile(
            $_FILES['image']['tmp_name'],
            $mime,
            basename($_FILES['image']['name'])
        ),
        'r'  => $r,
        'g'  => $g,
        'b'  => $b,
        'bw' => $bw,
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === false) {
        echo json_encode(['error' => 'Bildverarbeitungs-Service nicht erreichbar']);
    } else {
        echo $result;
    }
    exit;
}

// ─── Serve HTML frontend ─────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Image Manipulation – kogniflow</title>
  <style>
    /* ── Reset & base ───────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #0f0f1a;
      color: #dde1f0;
      min-height: 100vh;
    }

    /* ── Header ─────────────────────────────────────────────────────── */
    header {
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      border-bottom: 1px solid #252545;
      padding: 18px 36px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    header svg  { flex-shrink: 0; }
    header h1   { font-size: 1.35rem; font-weight: 700; color: #8b90fc; }
    header span { font-size: 0.85rem; color: #555; margin-left: 4px; }

    /* ── Main layout ────────────────────────────────────────────────── */
    main { max-width: 1080px; margin: 0 auto; padding: 28px 20px 60px; }

    /* ── Upload zone ────────────────────────────────────────────────── */
    .upload-zone {
      border: 2px dashed #2e2e5a;
      border-radius: 14px;
      padding: 44px 24px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
      background: #151528;
      margin-bottom: 24px;
    }
    .upload-zone:hover,
    .upload-zone.dragover { border-color: #8b90fc; background: #1c1c38; }
    .upload-zone input[type=file] { display: none; }
    .upload-zone .icon { color: #8b90fc; margin-bottom: 10px; }
    .upload-zone p { color: #666; font-size: .9rem; margin-top: 8px; }
    .upload-btn {
      display: inline-block;
      padding: 9px 22px;
      background: #8b90fc;
      color: #fff;
      border-radius: 8px;
      font-weight: 600;
      font-size: .9rem;
      margin-top: 14px;
      cursor: pointer;
      transition: opacity .2s;
    }
    .upload-btn:hover { opacity: .85; }

    /* ── Preview grid ───────────────────────────────────────────────── */
    .preview-grid {
      display: none;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-bottom: 22px;
    }
    .preview-grid.active { display: grid; }
    .preview-card {
      background: #151528;
      border: 1px solid #252545;
      border-radius: 12px;
      overflow: hidden;
    }
    .preview-card header {
      padding: 10px 16px;
      font-size: .75rem;
      font-weight: 600;
      color: #777;
      text-transform: uppercase;
      letter-spacing: .07em;
      border-bottom: 1px solid #252545;
      background: transparent;
    }
    .preview-card img {
      display: block;
      width: 100%;
      height: 300px;
      object-fit: contain;
      background: #0f0f1a;
    }

    /* ── Controls row ───────────────────────────────────────────────── */
    .controls { display: none; gap: 18px; align-items: flex-start; }
    .controls.active { display: flex; }

    /* ── Settings panel ─────────────────────────────────────────────── */
    .settings-panel {
      flex: 1;
      background: #151528;
      border: 1px solid #252545;
      border-radius: 12px;
      padding: 20px;
    }
    .panel-title {
      font-size: .75rem;
      font-weight: 700;
      color: #666;
      text-transform: uppercase;
      letter-spacing: .07em;
      margin-bottom: 18px;
    }

    /* B&W toggle */
    .toggle-row { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .switch { position: relative; width: 46px; height: 24px; }
    .switch input { display: none; }
    .switch-slider {
      position: absolute; inset: 0;
      background: #252545; border-radius: 12px; cursor: pointer;
      transition: background .25s;
    }
    .switch-slider::before {
      content: '';
      position: absolute;
      width: 18px; height: 18px;
      background: #fff; border-radius: 50%;
      top: 3px; left: 3px;
      transition: transform .25s;
    }
    .switch input:checked + .switch-slider { background: #8b90fc; }
    .switch input:checked + .switch-slider::before { transform: translateX(22px); }
    .toggle-label { font-size: .95rem; color: #ccc; }

    /* Process button */
    .process-btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #8b90fc, #a259e6);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: opacity .2s;
    }
    .process-btn:hover { opacity: .9; }
    .process-btn:disabled { opacity: .45; cursor: not-allowed; }

    /* Spinner */
    .spinner {
      display: none;
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Download link */
    .download-btn {
      display: none;
      margin-top: 10px;
      width: 100%;
      padding: 10px;
      background: #0e2b1a;
      color: #5bdb8a;
      border: 1px solid #1e5c36;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 600;
      text-align: center;
      text-decoration: none;
      cursor: pointer;
      transition: background .2s;
    }
    .download-btn:hover { background: #1e5c36; }
    .download-btn.active { display: block; }

    /* ── Triangle panel ─────────────────────────────────────────────── */
    .triangle-panel {
      background: #151528;
      border: 1px solid #252545;
      border-radius: 12px;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-width: 270px;
    }
    .triangle-panel .panel-title { align-self: flex-start; }
    #triangleCanvas { cursor: crosshair; border-radius: 6px; touch-action: none; }

    .channel-values {
      display: flex;
      gap: 20px;
      margin-top: 14px;
      font-size: .82rem;
    }
    .ch { display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .ch .lbl { font-weight: 800; font-size: 1.05rem; }
    .ch .lbl.r { color: #f87272; }
    .ch .lbl.g { color: #6ee786; }
    .ch .lbl.b { color: #70b8f8; }
    .ch .num { color: #888; font-size: .78rem; }

    .reset-btn {
      margin-top: 12px;
      padding: 7px 18px;
      background: transparent;
      border: 1px solid #2e2e5a;
      color: #888;
      border-radius: 7px;
      font-size: .82rem;
      cursor: pointer;
      transition: border-color .2s, color .2s;
    }
    .reset-btn:hover { border-color: #8b90fc; color: #8b90fc; }

    /* ── Hint text ──────────────────────────────────────────────────── */
    .hint {
      font-size: .78rem;
      color: #444;
      text-align: center;
      margin-top: 8px;
    }

    /* ── Responsive ─────────────────────────────────────────────────── */
    @media (max-width: 680px) {
      .preview-grid.active { grid-template-columns: 1fr; }
      .controls.active { flex-direction: column; }
      .triangle-panel { min-width: unset; width: 100%; }
    }
  </style>
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<header>
  <svg class="icon" width="28" height="28" viewBox="0 0 24 24" fill="none"
       stroke="#8b90fc" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <rect x="3" y="3" width="18" height="18" rx="2"/>
    <circle cx="8.5" cy="8.5" r="1.5"/>
    <polyline points="21 15 16 10 5 21"/>
  </svg>
  <h1>Image Manipulation</h1>
  <span>kogniflow</span>
</header>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<main>

  <!-- Upload zone -->
  <div class="upload-zone" id="uploadZone">
    <input type="file" id="fileInput" accept="image/*">
    <div class="icon">
      <svg width="52" height="52" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
    </div>
    <p>Bild hierher ziehen oder</p>
    <span class="upload-btn" onclick="document.getElementById('fileInput').click()">
      Bild auswählen
    </span>
    <p>PNG · JPG · GIF · WebP · BMP &nbsp;–&nbsp; max. 16 MB</p>
  </div>

  <!-- Preview -->
  <div class="preview-grid" id="previewGrid">
    <div class="preview-card">
      <header>Original</header>
      <img id="originalImg" src="" alt="Original">
    </div>
    <div class="preview-card">
      <header>Bearbeitet</header>
      <img id="processedImg" src="" alt="Bearbeitet">
    </div>
  </div>

  <!-- Controls -->
  <div class="controls" id="controls">

    <!-- Left: settings -->
    <div class="settings-panel">
      <div class="panel-title">Einstellungen</div>

      <div class="toggle-row">
        <label class="switch">
          <input type="checkbox" id="bwToggle">
          <span class="switch-slider"></span>
        </label>
        <span class="toggle-label">Schwarz-Weiß</span>
      </div>

      <button class="process-btn" id="processBtn" onclick="processImage()">
        <div class="spinner" id="spinner"></div>
        <span id="processBtnText">Bild verarbeiten</span>
      </button>

      <a class="download-btn" id="downloadBtn" download="bearbeitet.jpg">
        ⬇&nbsp; Bild herunterladen
      </a>
    </div>

    <!-- Right: triangle colour balance control -->
    <div class="triangle-panel">
      <div class="panel-title">Farb-Balance (RGB)</div>
      <canvas id="triangleCanvas" width="240" height="240"></canvas>
      <p class="hint">Ziehen Sie den Punkt, um die Kanalstärke anzupassen</p>
      <div class="channel-values">
        <div class="ch">
          <span class="lbl r">R</span>
          <span class="num" id="rVal">1.00×</span>
        </div>
        <div class="ch">
          <span class="lbl g">G</span>
          <span class="num" id="gVal">1.00×</span>
        </div>
        <div class="ch">
          <span class="lbl b">B</span>
          <span class="num" id="bVal">1.00×</span>
        </div>
      </div>
      <button class="reset-btn" onclick="resetTriangle()">Zurücksetzen</button>
    </div>

  </div><!-- /.controls -->

</main>

<!-- ── JavaScript ──────────────────────────────────────────────────────── -->
<script>
"use strict";

// ── State ──────────────────────────────────────────────────────────────────
let uploadedFile = null;
let isDragging   = false;
let currentPt    = null;   // {x, y} in canvas pixels
let channels     = { r: 1, g: 1, b: 1 };

// Triangle vertices (set in initTriangle)
let vR, vG, vB;

// ── Canvas setup ───────────────────────────────────────────────────────────
const canvas = document.getElementById("triangleCanvas");
const ctx    = canvas.getContext("2d");
const CW = canvas.width, CH = canvas.height;

function initTriangle() {
  const pad  = 28;
  const cx   = CW / 2;
  const triH = (CH - pad * 2) * 0.92;
  const half = triH / Math.sqrt(3);   // half-width of equilateral triangle

  // R = top-centre, G = bottom-left, B = bottom-right
  vR = { x: cx,          y: pad };
  vG = { x: cx - half,   y: pad + triH };
  vB = { x: cx + half,   y: pad + triH };

  // Start at centroid (neutral – all channels × 1.0)
  currentPt = {
    x: (vR.x + vG.x + vB.x) / 3,
    y: (vR.y + vG.y + vB.y) / 3,
  };
  drawTriangle();
}

// ── Draw ───────────────────────────────────────────────────────────────────
function drawTriangle() {
  ctx.clearRect(0, 0, CW, CH);

  // ── Clipped colour fill ─────────────────────────────────────────────
  ctx.save();
  ctx.beginPath();
  ctx.moveTo(vR.x, vR.y);
  ctx.lineTo(vG.x, vG.y);
  ctx.lineTo(vB.x, vB.y);
  ctx.closePath();

  // Dark base
  ctx.fillStyle = "#0a0a18";
  ctx.fill();

  ctx.clip();

  // Red radial gradient from R vertex
  let g = ctx.createRadialGradient(vR.x, vR.y, 0, vR.x, vR.y, CW * 0.88);
  g.addColorStop(0,   "rgba(210, 55, 55, 0.90)");
  g.addColorStop(1,   "rgba(210, 55, 55, 0)");
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, CW, CH);

  // Green radial gradient from G vertex
  g = ctx.createRadialGradient(vG.x, vG.y, 0, vG.x, vG.y, CW * 0.88);
  g.addColorStop(0,   "rgba(50, 195, 70, 0.90)");
  g.addColorStop(1,   "rgba(50, 195, 70, 0)");
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, CW, CH);

  // Blue radial gradient from B vertex
  g = ctx.createRadialGradient(vB.x, vB.y, 0, vB.x, vB.y, CW * 0.88);
  g.addColorStop(0,   "rgba(50, 100, 220, 0.90)");
  g.addColorStop(1,   "rgba(50, 100, 220, 0)");
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, CW, CH);

  ctx.restore();

  // ── Triangle border ─────────────────────────────────────────────────
  ctx.beginPath();
  ctx.moveTo(vR.x, vR.y);
  ctx.lineTo(vG.x, vG.y);
  ctx.lineTo(vB.x, vB.y);
  ctx.closePath();
  ctx.strokeStyle = "rgba(255,255,255,0.25)";
  ctx.lineWidth   = 1.5;
  ctx.stroke();

  // ── Vertex labels ────────────────────────────────────────────────────
  ctx.font        = "bold 13px sans-serif";
  ctx.textAlign   = "center";
  ctx.textBaseline = "middle";

  ctx.fillStyle = "#f87272";
  ctx.fillText("R", vR.x, vR.y - 14);

  ctx.fillStyle = "#6ee786";
  ctx.fillText("G", vG.x - 16, vG.y + 14);

  ctx.fillStyle = "#70b8f8";
  ctx.fillText("B", vB.x + 16, vB.y + 14);

  // ── Centre "neutral" marker ──────────────────────────────────────────
  const cx = (vR.x + vG.x + vB.x) / 3;
  const cy = (vR.y + vG.y + vB.y) / 3;
  ctx.beginPath();
  ctx.arc(cx, cy, 3, 0, Math.PI * 2);
  ctx.fillStyle = "rgba(255,255,255,0.18)";
  ctx.fill();

  // ── Current-position handle ──────────────────────────────────────────
  if (currentPt) {
    // Drop shadow
    ctx.beginPath();
    ctx.arc(currentPt.x + 1, currentPt.y + 1, 9, 0, Math.PI * 2);
    ctx.fillStyle = "rgba(0,0,0,0.45)";
    ctx.fill();

    // White disc
    ctx.beginPath();
    ctx.arc(currentPt.x, currentPt.y, 8, 0, Math.PI * 2);
    ctx.fillStyle = "rgba(255,255,255,0.95)";
    ctx.fill();
    ctx.strokeStyle = "rgba(0,0,0,0.5)";
    ctx.lineWidth = 1.5;
    ctx.stroke();
  }
}

// ── Barycentric coordinates ────────────────────────────────────────────────
function bary(px, py) {
  const denom = (vG.y - vB.y) * (vR.x - vB.x) + (vB.x - vG.x) * (vR.y - vB.y);
  const wr    = ((vG.y - vB.y) * (px - vB.x) + (vB.x - vG.x) * (py - vB.y)) / denom;
  const wg    = ((vB.y - vR.y) * (px - vB.x) + (vR.x - vB.x) * (py - vB.y)) / denom;
  const wb    = 1 - wr - wg;
  return { r: wr, g: wg, b: wb };
}

// Clamp a point to be inside the triangle using barycentric projection
function clampToTriangle(px, py) {
  let b = bary(px, py);
  b.r = Math.max(0, b.r);
  b.g = Math.max(0, b.g);
  b.b = Math.max(0, b.b);
  const s = b.r + b.g + b.b;
  b.r /= s; b.g /= s; b.b /= s;
  return {
    x:    b.r * vR.x + b.g * vG.x + b.b * vB.x,
    y:    b.r * vR.y + b.g * vG.y + b.b * vB.y,
    bary: b,
  };
}

function handleTrianglePointer(e) {
  e.preventDefault();
  const rect = canvas.getBoundingClientRect();
  const src  = e.touches ? e.touches[0] : e;
  const scX  = CW / rect.width;
  const scY  = CH / rect.height;
  const px   = (src.clientX - rect.left) * scX;
  const py   = (src.clientY - rect.top)  * scY;

  const clamped = clampToTriangle(px, py);
  currentPt = { x: clamped.x, y: clamped.y };

  // Scale barycentric coord to multiplier: centre (⅓,⅓,⅓) → 1.0×
  channels.r = parseFloat((clamped.bary.r * 3).toFixed(3));
  channels.g = parseFloat((clamped.bary.g * 3).toFixed(3));
  channels.b = parseFloat((clamped.bary.b * 3).toFixed(3));

  document.getElementById("rVal").textContent = channels.r.toFixed(2) + "×";
  document.getElementById("gVal").textContent = channels.g.toFixed(2) + "×";
  document.getElementById("bVal").textContent = channels.b.toFixed(2) + "×";

  drawTriangle();
}

// Mouse events
canvas.addEventListener("mousedown",  (e) => { isDragging = true;  handleTrianglePointer(e); });
canvas.addEventListener("mousemove",  (e) => { if (isDragging) handleTrianglePointer(e); });
canvas.addEventListener("mouseup",    ()  => { isDragging = false; });
document.addEventListener("mouseup",  ()  => { isDragging = false; });

// Touch events
canvas.addEventListener("touchstart", (e) => { isDragging = true;  handleTrianglePointer(e); }, { passive: false });
canvas.addEventListener("touchmove",  (e) => { if (isDragging) handleTrianglePointer(e); },     { passive: false });
canvas.addEventListener("touchend",   ()  => { isDragging = false; });

function resetTriangle() {
  currentPt  = { x: (vR.x + vG.x + vB.x) / 3, y: (vR.y + vG.y + vB.y) / 3 };
  channels   = { r: 1, g: 1, b: 1 };
  ["r","g","b"].forEach(c =>
    document.getElementById(c + "Val").textContent = "1.00×"
  );
  drawTriangle();
}

// ── File upload ────────────────────────────────────────────────────────────
const uploadZone = document.getElementById("uploadZone");
const fileInput  = document.getElementById("fileInput");

uploadZone.addEventListener("dragover",  (e) => { e.preventDefault(); uploadZone.classList.add("dragover"); });
uploadZone.addEventListener("dragleave", ()  => uploadZone.classList.remove("dragover"));
uploadZone.addEventListener("drop", (e) => {
  e.preventDefault();
  uploadZone.classList.remove("dragover");
  const f = e.dataTransfer.files[0];
  if (f && f.type.startsWith("image/")) loadFile(f);
});
fileInput.addEventListener("change", (e) => { if (e.target.files[0]) loadFile(e.target.files[0]); });

function loadFile(file) {
  uploadedFile = file;
  const reader = new FileReader();
  reader.onload = (ev) => {
    document.getElementById("originalImg").src  = ev.target.result;
    document.getElementById("processedImg").src = ev.target.result;
    document.getElementById("previewGrid").classList.add("active");
    document.getElementById("controls").classList.add("active");
    // Reset triangle and hide previous download
    resetTriangle();
    document.getElementById("downloadBtn").classList.remove("active");
  };
  reader.readAsDataURL(file);
}

// ── Image processing (via PHP proxy) ──────────────────────────────────────
function processImage() {
  if (!uploadedFile) return;

  const btn     = document.getElementById("processBtn");
  const btnText = document.getElementById("processBtnText");
  const spinner = document.getElementById("spinner");

  btn.disabled          = true;
  btnText.style.display = "none";
  spinner.style.display = "block";

  const fd = new FormData();
  fd.append("image", uploadedFile);
  fd.append("r",  channels.r);
  fd.append("g",  channels.g);
  fd.append("b",  channels.b);
  fd.append("bw", document.getElementById("bwToggle").checked ? "true" : "false");

  fetch("index.php", { method: "POST", body: fd })
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        alert("Fehler: " + data.error);
        return;
      }
      document.getElementById("processedImg").src = data.image;
      const dl  = document.getElementById("downloadBtn");
      dl.href   = data.image;
      dl.classList.add("active");
    })
    .catch((err) => alert("Verbindungsfehler: " + err.message))
    .finally(() => {
      btn.disabled          = false;
      btnText.style.display = "";
      spinner.style.display = "none";
    });
}

// ── Init ───────────────────────────────────────────────────────────────────
initTriangle();
</script>

</body>
</html>
