/**
 * app.js – Main application logic (jQuery + AJAX)
 */
$(function () {

  /* ══════════════════════════════════════════════
     COLOR SWATCHES CONFIG
  ══════════════════════════════════════════════ */
  const COLORS = [
    { name: 'Red',     hex: '#ef4444' },
    { name: 'Orange',  hex: '#f97316' },
    { name: 'Yellow',  hex: '#eab308' },
    { name: 'Green',   hex: '#22c55e' },
    { name: 'Teal',    hex: '#14b8a6' },
    { name: 'Blue',    hex: '#3b82f6' },
    { name: 'Indigo',  hex: '#6366f1' },
    { name: 'Purple',  hex: '#a855f7' },
    { name: 'Pink',    hex: '#ec4899' },
    { name: 'White',   hex: '#f8fafc' },
    { name: 'Gray',    hex: '#94a3b8' },
    { name: 'Black',   hex: '#1e293b' },
  ];

  /* ══════════════════════════════════════════════
     TOAST
  ══════════════════════════════════════════════ */
  function toast(msg, type = 'success') {
    const icon = type === 'success' ? '✅' : '❌';
    const el = $(`<div class="toast ${type}">
      <span class="toast-icon">${icon}</span>
      <span>${msg}</span>
    </div>`);
    $('#toastContainer').append(el);
    setTimeout(() => el.css({ opacity: 0, transition: 'opacity 0.4s' }), 3000);
    setTimeout(() => el.remove(), 3500);
  }

  /* ══════════════════════════════════════════════
     BUILD COLOR SWATCHES
  ══════════════════════════════════════════════ */
  function buildSwatches(containerId, inputId) {
    const $container = $(`#${containerId}`);
    $container.empty();
    COLORS.forEach(c => {
      $container.append(`
        <div class="color-swatch" data-color="${c.name}" data-hex="${c.hex}"
             title="${c.name}" style="background:${c.hex}">
          <span class="check">✓</span>
        </div>`);
    });
    $container.on('click', '.color-swatch', function () {
      $container.find('.color-swatch').removeClass('selected');
      $(this).addClass('selected');
      $(`#${inputId}`).val($(this).data('color'));
    });
  }

  /* ══════════════════════════════════════════════
     LOAD & RENDER ITEMS
  ══════════════════════════════════════════════ */
  function loadItems() {
    const $grid = $('#itemsGrid');
    $grid.html('<div class="items-loading"><div class="loading-spinner"></div><p style="margin-top:12px">Loading items…</p></div>');

    $.getJSON('api/get_items.php', function (res) {
      console.log("Items loaded:", res); // Debug log
      $grid.empty();
      if (!res.success || !res.items.length) {
        $grid.html(`<div class="empty-state">
          <div class="empty-icon">📦</div>
          <p>No items yet. Click <strong>Add Item</strong> to get started.</p>
        </div>`);
        return;
      }
      res.items.forEach(item => $grid.append(renderCard(item)));
    }).fail((xhr) => {
      try {
        const res = JSON.parse(xhr.responseText);
        $grid.html(`<div class="empty-state"><p>Error: ${res.error}</p></div>`);
      } catch(e) {
        $grid.html('<div class="empty-state"><p>Failed to load items.</p></div>');
      }
    });
  }

  function renderCard(item) {
    const colorDot = item.color
      ? `<span class="color-dot" style="background:${colorHex(item.color)}"></span>`
      : '';
    
    // Use photo if available, otherwise fallback to barcode image
    return `
      <div class="item-card" data-id="${item.id}" onclick="window.location='item.php?id=${item.id}'">
        <div class="item-card-img">
          ${item.photo ? `<img src="${item.photo}" alt="${item.name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'; console.error('Load failed:', '${item.photo}')">` : ''}
          <div class="image-fallback" style="${item.photo ? 'display:none;' : 'display:flex;'} width:100%; height:100%; align-items:center; justify-content:center; flex-direction:column; gap:8px;" title="Path: ${item.photo || 'None'}">
            <span style="font-size:2rem">📦</span>
            <span style="font-size:0.7rem; color:var(--text-muted)">Image not found</span>
          </div>
          ${item.barcode_image && item.photo ? `<div class="item-card-barcode-thumb"><img src="${item.barcode_image}" alt="Barcode"></div>` : ''}
        </div>
        <div class="item-card-body">
          <div class="item-card-name">${item.name}</div>
          <div class="item-card-barcode-num">${item.barcode_number}</div>
          <div class="item-card-meta">
            ${item.color ? `<span class="badge badge-color">${colorDot} ${item.color}</span>` : ''}
            ${item.size  ? `<span class="badge badge-size">📐 ${item.size}</span>` : ''}
            ${item.quantity !== null ? `<span class="badge badge-qty">× ${item.quantity}</span>` : ''}
          </div>
        </div>
      </div>`;
  }

  function colorHex(name) {
    const c = COLORS.find(x => x.name === name);
    return c ? c.hex : '#888';
  }

  /* ══════════════════════════════════════════════
     ADD ITEM MODAL
  ══════════════════════════════════════════════ */
  let barcodeImageBase64 = null;
  let photoBase64        = null;
  let cameraStream       = null;

  function openAddModal() {
    resetForm();
    buildSwatches('addColorSwatches', 'addColor');
    $('#addModal').addClass('active');
  }

  function closeAddModal() {
    stopCamera();
    BarcodeScanner.stop();
    $('#addModal').removeClass('active');
  }

  function resetForm() {
    $('#addItemForm')[0].reset();
    barcodeImageBase64 = null;
    photoBase64        = null;
    $('#scannerPreview').hide();
    $('#barcodeResult').hide().find('.barcode-result-img').attr('src', '');
    $('#addBarcodeNumber').val('');
    switchPhotoTab('drop');
    $('#addPhotoPreview').hide();
    $('#addPhotoPreviewImg').attr('src', '');
    $('#addColor').val('');
  }

  // ── Barcode Scanner ──
  $('#startScanBtn').on('click', function () {
    if (window.location.protocol === 'http:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
      alert("⚠️ Camera Error: Browsers block camera access on non-secure (HTTP) connections. Please use HTTPS or access via localhost.");
      return;
    }
    const $preview = $('#scannerPreview');
    if ($preview.is(':visible')) {
      BarcodeScanner.stop();
      $preview.hide();
      $(this).html('📷 Click to Scan Barcode');
      return;
    }
    $preview.show();
    $(this).html('⏹ Stop Scanner');
    BarcodeScanner.start('scannerVideo', (code, dataUrl) => {
      $('#scannerPreview').hide();
      $('#startScanBtn').html('🔄 Scan Again').removeClass('btn-ghost').addClass('btn-success');
      $('#addBarcodeNumber').val(code);
      barcodeImageBase64 = dataUrl;
      
      // Auto-populate photo if empty
      if (!photoBase64) {
        photoBase64 = dataUrl;
        showPhotoPreview(dataUrl);
        toast("Barcode & Preview captured! 📸");
      } else {
        toast("Barcode scanned! ✓");
      }

      const $res = $('#barcodeResult').show();
      $res.find('.barcode-number-display').text(code);
      if (dataUrl) $res.find('.barcode-result-img').attr('src', dataUrl).show();
    });
  });

  // ── Photo Tabs ──
  function switchPhotoTab(tab) {
    $('.photo-tab').removeClass('active');
    $('.photo-panel').removeClass('active');
    $(`#tab-${tab}`).addClass('active');
    $(`#panel-${tab}`).addClass('active');
    stopCamera();
    if (tab === 'camera') startCamera();
  }

  $(document).on('click', '.photo-tab', function () {
    switchPhotoTab($(this).data('tab'));
  });

  // ── Drag & Drop ──
  const $dropZone = $('#dropZone');
  $dropZone.on('dragover', e => { e.preventDefault(); $dropZone.addClass('dragover'); });
  $dropZone.on('dragleave', () => $dropZone.removeClass('dragover'));
  $dropZone.on('drop', e => {
    e.preventDefault();
    $dropZone.removeClass('dragover');
    const file = e.originalEvent.dataTransfer.files[0];
    if (file) handlePhotoFile(file);
  });
  $('#browseBtn').on('click', () => $('#fileInput').click());
  $('#fileInput').on('change', function () {
    if (this.files[0]) handlePhotoFile(this.files[0]);
  });

  function handlePhotoFile(file) {
    if (!file.type.startsWith('image/')) { toast('Please select an image file', 'error'); return; }
    const reader = new FileReader();
    reader.onload = e => {
      photoBase64 = e.target.result;
      showPhotoPreview(photoBase64);
    };
    reader.readAsDataURL(file);
  }

  // ── Camera Capture ──
  function startCamera() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        cameraStream = stream;
        document.getElementById('cameraVideo').srcObject = stream;
        document.getElementById('cameraVideo').play();
      })
      .catch(() => toast('Camera access denied', 'error'));
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(t => t.stop());
      cameraStream = null;
    }
  }

  $('#captureBtn').on('click', function () {
    const video  = document.getElementById('cameraVideo');
    const canvas = document.createElement('canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    photoBase64 = canvas.toDataURL('image/jpeg', 0.9);
    showPhotoPreview(photoBase64);
    stopCamera();
  });

  // ── URL Photo ──
  $('#addPhotoUrl').on('input', function () {
    const url = $(this).val().trim();
    if (url) {
      photoBase64 = null;
      showPhotoPreview(url, true);
    }
  });

  function showPhotoPreview(src, isUrl = false) {
    $('#addPhotoPreviewImg').attr('src', src);
    $('#addPhotoPreview').show();
  }

  $('#clearPhotoBtn').on('click', function () {
    photoBase64 = null;
    $('#addPhotoPreview').hide();
    $('#addPhotoPreviewImg').attr('src', '');
    $('#addPhotoUrl').val('');
    $('#fileInput').val('');
  });

  // ── Submit ──
  $('#addItemForm').on('submit', function (e) {
    e.preventDefault();
    const $btn = $('#addSubmitBtn');

    const barcodeNum = $('#addBarcodeNumber').val().trim();
    const name       = $('#addItemName').val().trim();
    if (!barcodeNum) { toast('Please scan a barcode first', 'error'); return; }
    if (!name)       { toast('Item name is required', 'error'); return; }

    $btn.addClass('btn-loading').prop('disabled', true);

    const fd = new FormData();
    fd.append('barcode_number', barcodeNum);
    fd.append('name',           name);
    fd.append('color',          $('#addColor').val());
    fd.append('size',           $('#addSize').val().trim());
    fd.append('quantity',       $('#addQuantity').val() || 0);

    if (barcodeImageBase64) fd.append('barcode_image_base64', barcodeImageBase64);

    // Photo source priority: base64 > file > url
    if (photoBase64) {
      fd.append('photo_base64', photoBase64);
    } else if ($('#fileInput')[0].files[0]) {
      fd.append('photo', $('#fileInput')[0].files[0]);
    } else if ($('#addPhotoUrl').val().trim()) {
      fd.append('photo_url', $('#addPhotoUrl').val().trim());
    }

    $.ajax({
      url: 'api/add_item.php',
      type: 'POST',
      data: fd,
      contentType: false,
      processData: false,
      success: res => {
        if (res.success) {
          toast('Item added successfully! 🎉');
          closeAddModal();
          loadItems();
        } else {
          const detail = res.file ? ` (${res.file}:${res.line})` : '';
          toast((res.error || 'Failed to add item') + detail, 'error');
        }
      },
      error: (xhr) => {
        try {
          const res = JSON.parse(xhr.responseText);
          const detail = res.file ? ` (${res.file}:${res.line})` : '';
          toast((res.error || 'Server error') + detail, 'error');
        } catch(e) {
          toast('Server error — check test.php for details', 'error');
        }
      },
      complete: () => $btn.removeClass('btn-loading').prop('disabled', false),
    });
  });

  // ── Modal controls ──
  $('#addItemBtn').on('click', openAddModal);
  $('#closeAddModal, #cancelAddBtn').on('click', closeAddModal);
  $('#addModal').on('click', function (e) {
    if ($(e.target).is('#addModal')) closeAddModal();
  });

  /* ══════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════ */
  if ($('#itemsGrid').length) loadItems();
});
