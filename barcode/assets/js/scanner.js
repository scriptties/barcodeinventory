/**
 * scanner.js – Powered by Html5Qrcode for Maximum Reliability
 * Optimized for "instant" detection and high-quality frame capture.
 */
const BarcodeScanner = (() => {
  let _isScanning = false;
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

    // 1. Create Video Container
    const scannerWrapper = document.createElement('div');
    scannerWrapper.id = "quagga-container";
    Object.assign(scannerWrapper.style, { width: "100%", height: "100%", position: "relative" });
    target.appendChild(scannerWrapper);

    // 2. Add UI Overlay
    const overlay = document.createElement('div');
    overlay.className = 'ai-overlay';
    overlay.innerHTML = `
      <div class="ai-scan-line"></div>
      <div class="ai-corner tl"></div>
      <div class="ai-corner tr"></div>
      <div class="ai-corner bl"></div>
      <div class="ai-corner br"></div>
      <div class="ai-status">INITIALIZING...</div>
      <button type="button" id="torchBtn" class="torch-btn" title="Toggle Flashlight">🔦</button>
    `;
    target.appendChild(overlay);

    overlay.querySelector('#torchBtn').addEventListener('click', toggleTorch);

    // 3. Initialize Quagga2
    const config = {
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: scannerWrapper,
            constraints: {
                width: { min: 1280 },
                height: { min: 720 },
                facingMode: "environment"
            },
        },
        decoder: {
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "upc_reader",
                "upc_e_reader",
                "codabar_reader"
            ]
        },
        locate: true,
        halfSample: true,
        patchSize: "medium", // Try medium or large for better results
        frequency: 10
    };

    Quagga.init(config, (err) => {
        if (err) {
            console.error("Quagga Init Error:", err);
            let msg = "CAMERA ERROR";
            if (err.name === "NotAllowedError") msg = "PERMISSION DENIED";
            if (err.name === "NotFoundError") msg = "NO CAMERA FOUND";
            _updateStatus(msg);
            return;
        }
        _updateStatus("SEARCHING...");
        setTimeout(() => {
            if (_isScanning) Quagga.start();
        }, 300);
    });

    Quagga.onDetected(_handleDetected);
  }

  function _handleDetected(result) {
    if (!_isScanning) return;
    
    const code = result.codeResult.code;
    if (!code) return;

    _isScanning = false;
    _beep.play().catch(() => {});
    _updateStatus("DETECTED ✓", true);

    // Capture Image from Video
    let dataUrl = null;
    try {
      const video = document.querySelector("#quagga-container video");
      if (video) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        dataUrl = canvas.toDataURL('image/jpeg', 0.8);
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
    try {
      _torchEnabled = !_torchEnabled;
      const track = Quagga.CameraAccess.getActiveTrack();
      if (track && track.applyConstraints) {
          track.applyConstraints({ advanced: [{ torch: _torchEnabled }] });
      } else {
          alert("Flashlight not supported on this device/browser.");
      }
    } catch (e) {
      console.warn("Torch error:", e);
    }
  }

  async function stop() {
    _isScanning = false;
    Quagga.offDetected(_handleDetected);
    Quagga.stop();
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