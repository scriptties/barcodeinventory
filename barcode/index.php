<?php require_once 'config/db.php'; getDB(); // init DB/table ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barcode Inventory</title>
  <meta name="description" content="Manage your inventory with barcode scanning, photo upload, and Google Sheets sync.">
  <!-- Google Fonts: Inter & Outfit for premium look -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- AI & Barcode Engines -->
  <script src="https://unpkg.com/@zxing/library@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode"></script> <!-- Keep as fallback -->
  
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<div class="page-wrapper">

  <!-- ── Header ── -->
  <header>
    <div class="logo">
      <div class="logo-icon">📦</div>
      <h1>Barcode <span>Inventory</span></h1>
    </div>
    <button id="addItemBtn" class="btn btn-primary">
      <span>＋</span> Add Item
    </button>
  </header>

  <!-- ── Item Grid ── -->
  <div class="section-header">
    <span class="section-title">All Items</span>
  </div>
  <div id="itemsGrid" class="items-grid"></div>

</div><!-- /page-wrapper -->

<!-- ════════════════════════════════════════════
     ADD ITEM MODAL
═════════════════════════════════════════════ -->
<div id="addModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New Item</span>
      <button class="modal-close" id="closeAddModal">✕</button>
    </div>

    <form id="addItemForm" enctype="multipart/form-data">
    <div class="modal-body">

      <!-- Step 1: Barcode -->
      <div class="form-group">
        <label class="form-label">📷 Barcode</label>
        <div class="barcode-scanner-box">
          <button type="button" id="startScanBtn" class="btn btn-ghost" style="width:100%;justify-content:center;margin-bottom:12px">
            📷 Click to Scan Barcode
          </button>
          <div id="scannerPreview" class="scanner-preview" style="display:none;min-height:240px">
            <div id="scannerVideo" style="width:100%;height:100%"></div>
          </div>
          <!-- Result -->
          <div id="barcodeResult" class="barcode-result-box">
            <img class="barcode-result-img" src="" alt="Barcode" style="display:none">
            <div class="barcode-number-display"></div>
            <p style="color:var(--text-muted);font-size:0.8rem;margin-top:4px">Barcode scanned ✓</p>
          </div>
          <input type="hidden" id="addBarcodeNumber" name="barcode_number">
        </div>
      </div>

      <!-- Step 2: Item Name -->
      <div class="form-group">
        <label class="form-label" for="addItemName">Item Name</label>
        <input type="text" id="addItemName" name="name" class="form-control" placeholder="e.g. Blue Denim Jacket" required>
      </div>

      <!-- Step 3: Item Photo -->
      <div class="form-group">
        <label class="form-label">Item Photo</label>

        <div class="photo-upload-tabs">
          <button type="button" class="photo-tab active" id="tab-drop" data-tab="drop">📁 Upload</button>
          <button type="button" class="photo-tab" id="tab-camera" data-tab="camera">📷 Camera</button>
          <button type="button" class="photo-tab" id="tab-url" data-tab="url">🔗 Link</button>
        </div>

        <!-- Drag & Drop / Browse -->
        <div class="photo-panel active" id="panel-drop">
          <div id="dropZone" class="drop-zone">
            <div class="drop-icon">🖼️</div>
            <p>Drag & drop an image here, or <strong id="browseBtn">browse files</strong></p>
          </div>
          <input type="file" id="fileInput" name="photo" accept="image/*" style="display:none">
        </div>

        <!-- Camera -->
        <div class="photo-panel" id="panel-camera">
          <div class="camera-container">
            <video id="cameraVideo" autoplay playsinline muted style="max-height:280px"></video>
          </div>
          <div class="camera-controls">
            <button type="button" id="captureBtn" class="btn btn-primary btn-sm">📸 Capture Photo</button>
          </div>
        </div>

        <!-- URL -->
        <div class="photo-panel" id="panel-url">
          <input type="text" id="addPhotoUrl" class="form-control" placeholder="https://example.com/photo.jpg">
        </div>

        <!-- Preview (shared) -->
        <div id="addPhotoPreview" class="photo-preview-wrap">
          <img id="addPhotoPreviewImg" src="" alt="Preview">
          <button type="button" id="clearPhotoBtn" class="btn btn-ghost btn-sm photo-preview-clear">✕ Clear</button>
        </div>
      </div>

      <!-- Step 4: Color -->
      <div class="form-group">
        <label class="form-label">Color</label>
        <div id="addColorSwatches" class="color-swatches"></div>
        <div class="color-custom-row">
          <input type="color" id="addColorPicker" title="Custom color">
          <input type="text" id="addColor" name="color" class="form-control" placeholder="e.g. Red, Navy Blue…" style="flex:1">
        </div>
      </div>

      <!-- Step 5: Size -->
      <div class="form-group">
        <label class="form-label" for="addSize">Size</label>
        <input type="text" id="addSize" name="size" class="form-control" placeholder="e.g. XL, 42, 30×32…">
      </div>

      <!-- Step 6: Quantity -->
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label" for="addQuantity">Quantity</label>
        <input type="number" id="addQuantity" name="quantity" class="form-control" placeholder="0" min="0" value="1">
      </div>

    </div><!-- /modal-body -->
    <div class="modal-footer">
      <button type="button" id="cancelAddBtn" class="btn btn-ghost">Cancel</button>
      <button type="submit" id="addSubmitBtn" class="btn btn-primary">＋ Add Item</button>
    </div>
    </form>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script src="assets/js/scanner.js"></script>
<script src="assets/js/app.js?v=<?= time() ?>"></script>

<script>
// Custom color picker → fills text input
document.getElementById('addColorPicker').addEventListener('input', function () {
  document.getElementById('addColor').value = this.value;
  document.querySelectorAll('#addColorSwatches .color-swatch').forEach(s => s.classList.remove('selected'));
});
</script>
</body>
</html>
