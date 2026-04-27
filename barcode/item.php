<?php
require_once 'config/db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();
if (!$item) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($item['name']) ?> – Barcode Inventory</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- AI & Barcode Engines -->
  <script src="https://unpkg.com/@zxing/library@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2/dist/quagga.min.js"></script>
  
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    .rescan-btn { margin-top: 10px; }
    .barcode-missing { color: var(--text-muted); font-size: 0.9rem; padding: 20px; }
    .drive-link { color: var(--accent2); text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; }
    .drive-link:hover { text-decoration: underline; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media(max-width:500px) { .field-row { grid-template-columns: 1fr; } }
    .save-indicator { display:none; font-size:0.85rem; color:var(--success); margin-left:8px; }
  </style>
</head>
<body>

<div class="page-wrapper">
  <br><br>

  <!-- ── Header ── -->
  <div class="detail-header">
    <a href="index.php" class="detail-back" title="Back">←</a>
    <div>
      <div style="color:var(--text-muted);font-size:0.8rem;margin-bottom:4px">ITEM #<?= $item['id'] ?></div>
      <h1 id="pageTitle" style="font-size:1.4rem;font-weight:700"><?= htmlspecialchars($item['name']) ?></h1>
    </div>
    
    <div style="margin-left:auto;display:flex;gap:10px">
      <button id="saveBtn" class="btn btn-primary">💾 Save Changes</button>
      <button id="deleteBtn" class="btn btn-danger">🗑 Delete</button>
    </div>
  </div>

  <div class="detail-grid">

    <!-- LEFT: Images -->
    <div class="detail-images">

      <!-- Barcode -->
      <div>
        <div class="form-label" style="margin-bottom:10px">📊 Barcode</div>
        <?php if ($item['barcode_image']): ?>
          <div class="barcode-display">
            <img id="barcodeDisplayImg" src="<?= htmlspecialchars($item['barcode_image']) ?>" alt="Barcode">
            <div class="barcode-num" id="barcodeDisplayNum"><?= htmlspecialchars($item['barcode_number']) ?></div>
          </div>
        <?php else: ?>
          <div class="barcode-display">
            <div id="barcodeDisplayImg" style="position:relative">
              <div class="barcode-missing">No barcode image yet</div>
            </div>
            <div class="barcode-num" id="barcodeDisplayNum"><?= htmlspecialchars($item['barcode_number']) ?></div>
          </div>
        <?php endif; ?>

        <!-- Rescan barcode -->
        <button type="button" id="rescanBtn" class="btn btn-ghost btn-sm rescan-btn">🔄 Re-scan Barcode</button>
        <div id="rescanPreview" class="scanner-preview" style="display:none;margin-top:12px;min-height:240px">
          <div id="rescanVideo" style="width:100%;height:100%"></div>
        </div>
        <input type="hidden" id="newBarcodeNumber" value="<?= htmlspecialchars($item['barcode_number']) ?>">
        <input type="hidden" id="newBarcodeBase64" value="">
      </div>

      <!-- Item Photo -->
      <div>
        <div class="form-label" style="margin-bottom:10px">🖼️ Item Photo</div>
        <div class="item-photo-display" id="itemPhotoDisplay">
          <?php if (!empty($item['photo'])): ?>
            <img id="itemPhotoImg" src="<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>
            <span id="itemPhotoImg">📦</span>
          <?php endif; ?>
        </div>

        <!-- Photo update options -->
        <div style="margin-top:14px">
          <div class="photo-upload-tabs">
            <button type="button" class="photo-tab active" id="tab-edit-drop" data-tab="edit-drop">📁 Upload</button>
            <button type="button" class="photo-tab" id="tab-edit-camera" data-tab="edit-camera">📷 Camera</button>
            <button type="button" class="photo-tab" id="tab-edit-url" data-tab="edit-url">🔗 Link</button>
          </div>

          <div class="photo-panel active" id="panel-edit-drop">
            <div id="editDropZone" class="drop-zone" style="padding:20px">
              <p>Drag & drop or <strong id="editBrowseBtn">browse files</strong></p>
            </div>
            <input type="file" id="editFileInput" accept="image/*" style="display:none">
          </div>

          <div class="photo-panel" id="panel-edit-camera">
            <div class="camera-container">
              <video id="editCameraVideo" autoplay playsinline muted style="max-height:220px"></video>
            </div>
            <div class="camera-controls">
              <button type="button" id="editCaptureBtn" class="btn btn-primary btn-sm">📸 Capture</button>
            </div>
          </div>

          <div class="photo-panel" id="panel-edit-url">
            <input type="text" id="editPhotoUrl" class="form-control" placeholder="https://…">
          </div>

        <div id="editPhotoPreview" class="photo-preview-wrap">
            <img id="editPhotoPreviewImg" src="" alt="New photo preview">
            <button type="button" id="editClearPhotoBtn" class="btn btn-ghost btn-sm photo-preview-clear">✕ Clear</button>
          </div>

          <?php if ($item['drive_photo_link']): ?>
            <a href="<?= htmlspecialchars($item['drive_photo_link']) ?>" target="_blank" class="drive-link">
              <span>📂</span> View on Google Drive
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /detail-images -->

    <!-- RIGHT: Edit Form -->
    <div class="detail-form">
      <div class="form-group">
        <label class="form-label" for="editName">Item Name</label>
        <input type="text" id="editName" class="form-control" value="<?= htmlspecialchars($item['name']) ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="editBarcodeNumber">Barcode Number</label>
        <input type="text" id="editBarcodeNumber" class="form-control" value="<?= htmlspecialchars($item['barcode_number']) ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Color</label>
        <div id="editColorSwatches" class="color-swatches"></div>
        <div class="color-custom-row">
          <input type="color" id="editColorPicker" title="Custom color">
          <input type="text" id="editColor" class="form-control"
                 value="<?= htmlspecialchars($item['color'] ?? '') ?>"
                 placeholder="e.g. Red, Navy Blue…" style="flex:1">
        </div>
      </div>

      <div class="field-row">
        <div class="form-group">
          <label class="form-label" for="editSize">Size</label>
          <input type="text" id="editSize" class="form-control" value="<?= htmlspecialchars($item['size'] ?? '') ?>" placeholder="e.g. XL">
        </div>
        <div class="form-group">
          <label class="form-label" for="editQuantity">Quantity</label>
          <input type="number" id="editQuantity" class="form-control" value="<?= (int)$item['quantity'] ?>" min="0">
        </div>
      </div>

      <div class="form-group" style="margin-top:8px">
      </div>
    </div><!-- /detail-form -->
  </div><!-- /detail-grid -->
</div><!-- /page-wrapper -->

<div id="toastContainer" class="toast-container"></div>

<script src="assets/js/scanner.js?v=<?= time() ?>"></script>
<script>
const ITEM_ID = <?= $item['id'] ?>;

const COLORS = [
  { name:'Red',hex:'#ef4444'},{ name:'Orange',hex:'#f97316'},{ name:'Yellow',hex:'#eab308'},
  { name:'Green',hex:'#22c55e'},{ name:'Teal',hex:'#14b8a6'},{ name:'Blue',hex:'#3b82f6'},
  { name:'Indigo',hex:'#6366f1'},{ name:'Purple',hex:'#a855f7'},{ name:'Pink',hex:'#ec4899'},
  { name:'White',hex:'#f8fafc'},{ name:'Gray',hex:'#94a3b8'},{ name:'Black',hex:'#1e293b'},
];

function toast(msg, type='success') {
  const icon = type==='success'?'✅':'❌';
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icon}</span><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity 0.4s'; },3000);
  setTimeout(()=>el.remove(),3500);
}

// ── Build Swatches ──
function buildSwatches(containerId, inputId) {
  const container = document.getElementById(containerId);
  const input     = document.getElementById(inputId);
  const curVal    = input.value;
  container.innerHTML = '';
  COLORS.forEach(c => {
    const div = document.createElement('div');
    div.className = 'color-swatch' + (c.name===curVal?' selected':'');
    div.dataset.color = c.name;
    div.style.background = c.hex;
    div.title = c.name;
    div.innerHTML = '<span class="check">✓</span>';
    div.addEventListener('click', ()=>{
      container.querySelectorAll('.color-swatch').forEach(s=>s.classList.remove('selected'));
      div.classList.add('selected');
      input.value = c.name;
    });
    container.appendChild(div);
  });
}
buildSwatches('editColorSwatches','editColor');

document.getElementById('editColorPicker').addEventListener('input', function(){
  document.getElementById('editColor').value = this.value;
  document.querySelectorAll('#editColorSwatches .color-swatch').forEach(s=>s.classList.remove('selected'));
});

// ── Photo Tabs ──
let editCameraStream = null;
let editPhotoBase64  = null;

document.querySelectorAll('.photo-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    const t = this.dataset.tab;
    document.querySelectorAll('.photo-tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.photo-panel').forEach(x=>x.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('panel-'+t).classList.add('active');
    if (t !== 'edit-camera') stopEditCamera();
    if (t === 'edit-camera') startEditCamera();
  });
});

function startEditCamera() {
  navigator.mediaDevices.getUserMedia({ video: { facingMode:'environment' } })
    .then(stream => {
      editCameraStream = stream;
      document.getElementById('editCameraVideo').srcObject = stream;
      document.getElementById('editCameraVideo').play();
    }).catch(()=> toast('Camera access denied','error'));
}
function stopEditCamera() {
  if (editCameraStream) {
    editCameraStream.getTracks().forEach(t=>t.stop());
    editCameraStream = null;
  }
}

document.getElementById('editCaptureBtn').addEventListener('click', ()=>{
  const video = document.getElementById('editCameraVideo');
  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth||640; canvas.height = video.videoHeight||480;
  canvas.getContext('2d').drawImage(video,0,0);
  editPhotoBase64 = canvas.toDataURL('image/jpeg',0.9);
  showEditPreview(editPhotoBase64);
  stopEditCamera();
});

// Drag & drop
const editDrop = document.getElementById('editDropZone');
editDrop.addEventListener('dragover', e=>{ e.preventDefault(); editDrop.classList.add('dragover'); });
editDrop.addEventListener('dragleave', ()=> editDrop.classList.remove('dragover'));
editDrop.addEventListener('drop', e=>{
  e.preventDefault(); editDrop.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) readEditFile(file);
});
document.getElementById('editBrowseBtn').addEventListener('click',()=> document.getElementById('editFileInput').click());
document.getElementById('editFileInput').addEventListener('change', function(){ if(this.files[0]) readEditFile(this.files[0]); });

function readEditFile(file) {
  const reader = new FileReader();
  reader.onload = e => { editPhotoBase64 = e.target.result; showEditPreview(editPhotoBase64); };
  reader.readAsDataURL(file);
}

document.getElementById('editPhotoUrl').addEventListener('input', function(){
  const url = this.value.trim();
  if (url) { editPhotoBase64 = null; showEditPreview(url, true); }
});

function showEditPreview(src, isUrl=false) {
  document.getElementById('editPhotoPreviewImg').src = src;
  document.getElementById('editPhotoPreview').style.display = 'block';
  // Live update display
  const disp = document.getElementById('itemPhotoDisplay');
  disp.innerHTML = `<img id="itemPhotoImg" src="${src}" alt="preview">`;
}

document.getElementById('editClearPhotoBtn').addEventListener('click',()=>{
  editPhotoBase64 = null;
  document.getElementById('editPhotoPreview').style.display = 'none';
  document.getElementById('editPhotoPreviewImg').src = '';
  document.getElementById('editPhotoUrl').value = '';
  document.getElementById('editFileInput').value = '';
});

// ── Rescan Barcode ──
let newBarcodeBase64 = '';
document.getElementById('rescanBtn').addEventListener('click', function(){
  const preview = document.getElementById('rescanPreview');
  if (preview.style.display !== 'none') {
    BarcodeScanner.stop();
    preview.style.display = 'none';
    this.textContent = '🔄 Re-scan Barcode';
    return;
  }
  preview.style.display = 'block';
  this.textContent = '⏹ Stop Scanner';
  BarcodeScanner.start('rescanVideo', (code, dataUrl) => {
    document.getElementById('rescanPreview').style.display = 'none';
    document.getElementById('rescanBtn').textContent = '🔄 Re-scan Barcode';
    document.getElementById('newBarcodeNumber').value = code;
    document.getElementById('editBarcodeNumber').value = code;
    newBarcodeBase64 = dataUrl || '';
    
    // If no new photo has been selected yet, use the scan capture as the new photo
    if (!editPhotoBase64 && dataUrl) {
      editPhotoBase64 = dataUrl;
      showEditPreview(dataUrl);
    }

    document.getElementById('barcodeDisplayNum').textContent = code;
    if (dataUrl) {
      const bc = document.getElementById('barcodeDisplayImg');
      if (bc.tagName === 'IMG') bc.src = dataUrl;
      else { const img = document.createElement('img'); img.src = dataUrl; img.style.maxHeight='120px'; bc.replaceWith(img); }
    }
    toast('Barcode updated ✓');
  });
});

// ── Save ──
document.getElementById('saveBtn').addEventListener('click', function(){
  const btn = this;
  btn.classList.add('btn-loading');
  btn.disabled = true;

  const fd = new FormData();
  fd.append('id',             ITEM_ID);
  fd.append('name',           document.getElementById('editName').value.trim());
  fd.append('barcode_number', document.getElementById('editBarcodeNumber').value.trim());
  fd.append('color',          document.getElementById('editColor').value.trim());
  fd.append('size',           document.getElementById('editSize').value.trim());
  fd.append('quantity',       document.getElementById('editQuantity').value);
  if (newBarcodeBase64)    fd.append('barcode_image_base64', newBarcodeBase64);
  if (editPhotoBase64)     fd.append('photo_base64', editPhotoBase64);
  const urlVal = document.getElementById('editPhotoUrl').value.trim();
  if (!editPhotoBase64 && urlVal) fd.append('photo_url', urlVal);
  const fileInput = document.getElementById('editFileInput');
  if (!editPhotoBase64 && !urlVal && fileInput.files[0]) fd.append('photo', fileInput.files[0]);

  fetch('api/update_item.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        toast('Item saved successfully! 💾');
        document.getElementById('pageTitle').textContent = document.getElementById('editName').value;
        document.title = document.getElementById('editName').value + ' – Barcode Inventory';
        document.getElementById('lastUpdated').textContent = new Date().toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
      } else {
        toast(res.error || 'Failed to save','error');
      }
    })
    .catch(()=> toast('Server error','error'))
    .finally(()=>{ btn.classList.remove('btn-loading'); btn.disabled=false; });
});

// ── Delete ──
document.getElementById('deleteBtn').addEventListener('click', function(){
  if (!confirm('Delete this item? This cannot be undone.')) return;
  const fd = new FormData();
  fd.append('id', ITEM_ID);
  fetch('api/delete_item.php', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(res=>{
      if (res.success) window.location.href='index.php';
      else toast(res.error||'Delete failed','error');
    });
});
</script>
</body>
</html>
