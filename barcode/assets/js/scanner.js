/**
 * scanner.js – Powered by Html5Qrcode for Maximum Reliability
 * Optimized for "instant" detection and high-quality frame capture.
 */
const BarcodeScanner = (() => {
  let _isScanning = false;
  let _html5QrCode = null;
  let _onDetect = null;
  let _torchEnabled = false;

  const _beep = new Audio("data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YV9vT18AZu7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u");

  async function start(containerId, onDetect) {
    if (_isScanning) await stop();
    
    _onDetect = onDetect;
    _isScanning = true;

    const target = document.getElementById(containerId);
    if (!target) return;
    target.innerHTML = "";
    $(target).show();

    // 1. Create Video/Overlay Container
    const scannerWrapper = document.createElement('div');
    scannerWrapper.id = "qr-reader";
    Object.assign(scannerWrapper.style, { width: "100%", height: "100%", position: "relative" });
    target.appendChild(scannerWrapper);

    // 2. Add AI UI Overlay
    const overlay = document.createElement('div');
    overlay.className = 'ai-overlay';
    overlay.innerHTML = `
      <div class="ai-scan-line"></div>
      <div class="ai-corner tl"></div>
      <div class="ai-corner tr"></div>
      <div class="ai-corner bl"></div>
      <div class="ai-corner br"></div>
      <div class="ai-status">AI SEARCHING...</div>
      <button type="button" id="torchBtn" class="torch-btn" title="Toggle Flashlight">🔦</button>
    `;
    target.appendChild(overlay);

    overlay.querySelector('#torchBtn').addEventListener('click', toggleTorch);

    // 3. Initialize Html5Qrcode
    _html5QrCode = new Html5Qrcode("qr-reader");

    const config = {
      fps: 20,
      qrbox: { width: 280, height: 280 },
      aspectRatio: 1.0,
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true
      }
    };

    try {
      await _html5QrCode.start(
        { facingMode: "environment" }, 
        config,
        (decodedText, decodedResult) => {
          _handleSuccess(decodedText);
        },
        (errorMessage) => {
          // parse errors are normal
        }
      );
      _updateStatus("AI SEARCHING...");
    } catch (err) {
      console.error("Scanner Error:", err);
      _updateStatus("CAMERA ERROR");
    }
  }

  async function _handleSuccess(code) {
    if (!_isScanning) return;
    _isScanning = false;
    _beep.play().catch(() => {});

    _updateStatus("VERIFIED ✓", true);

    // Capture Image
    let dataUrl = null;
    try {
      const video = document.querySelector("#qr-reader video");
      if (video) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      }
    } catch (e) {
      console.error("Capture Error:", e);
    }

    setTimeout(async () => {
      await stop();
      if (_onDetect) _onDetect(code, dataUrl);
    }, 400);
  }

  async function toggleTorch() {
    if (!_html5QrCode) return;
    try {
      _torchEnabled = !_torchEnabled;
      await _html5QrCode.applyVideoConstraints({
        advanced: [{ torch: _torchEnabled }]
      });
    } catch (e) {
      alert("Flashlight not supported.");
    }
  }

  async function stop() {
    _isScanning = false;
    if (_html5QrCode && _html5QrCode.isScanning) {
      await _html5QrCode.stop();
      _html5QrCode = null;
    }
  }

  function _updateStatus(text, success = false) {
    const el = document.querySelector('.ai-status');
    if (el) {
      el.innerText = text;
      el.style.background = success ? "var(--success)" : "rgba(0,0,0,0.85)";
    }
  }

  return { start, stop };
})();