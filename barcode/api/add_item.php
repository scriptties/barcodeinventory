<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';
$googleConfig = @include __DIR__ . '/../config/google_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    // ── Required fields ───────────────────────────────────────────────────
    $barcodeNumber = trim($_POST['barcode_number'] ?? '');
    $name          = trim($_POST['name'] ?? '');
    $color         = trim($_POST['color'] ?? '');
    $size          = trim($_POST['size'] ?? '');
    $quantity      = (int)($_POST['quantity'] ?? 0);

    if (empty($barcodeNumber)) {
        echo json_encode(['success' => false, 'error' => 'Barcode number is required']);
        exit;
    }
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Item name is required']);
        exit;
    }

    // ── Save Barcode Image ────────────────────────────────────────────────
    $barcodeImagePath = null;
    if (!empty($_POST['barcode_image_base64'])) {
        $val = $_POST['barcode_image_base64'];
        if (strpos($val, 'uploads/') === 0) {
            // Already a file on server
            $oldPath = __DIR__ . '/../' . $val;
            if (file_exists($oldPath)) {
                $dir = __DIR__ . '/../uploads/barcodes/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'barcode_' . time() . '_' . rand(1000,9999) . '.jpg';
                rename($oldPath, $dir . $filename);
                $barcodeImagePath = 'uploads/barcodes/' . $filename;
            }
        } else {
            $data    = preg_replace('#^data:image/\w+;base64,#i', '', $val);
            $imgData = base64_decode($data);
            if ($imgData !== false) {
                $dir = __DIR__ . '/../uploads/barcodes/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename         = 'barcode_' . time() . '_' . rand(1000,9999) . '.png';
                file_put_contents($dir . $filename, $imgData);
                $barcodeImagePath = 'uploads/barcodes/' . $filename;
            }
        }
    }

    // ── Save Item Photo ───────────────────────────────────────────────────
    $photoPath = null;
    $photoBase64 = null; // We'll keep this for Google Sync
    $dir       = __DIR__ . '/../uploads/photos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Uploaded file
        $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $filename = 'photo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
            $photoPath = 'uploads/photos/' . $filename;
            $photoBase64 = 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($dir . $filename));
        }
    } elseif (!empty($_POST['photo_base64'])) {
        // Camera capture or existing server file
        $val = $_POST['photo_base64'];
        if (strpos($val, 'uploads/') === 0) {
            $oldPath = __DIR__ . '/../' . $val;
            if (file_exists($oldPath)) {
                $filename = 'photo_' . time() . '_' . rand(1000,9999) . '.jpg';
                // If it's already in barcodes or temp, we copy it to photos
                copy($oldPath, $dir . $filename);
                $photoPath = 'uploads/photos/' . $filename;
                $photoBase64 = 'data:image/jpg;base64,' . base64_encode(file_get_contents($dir . $filename));
            }
        } else {
            $photoBase64 = $val;
            $data    = preg_replace('#^data:image/\w+;base64,#i', '', $photoBase64);
            $imgData = base64_decode($data);
            if ($imgData !== false) {
                $filename  = 'photo_' . time() . '_' . rand(1000,9999) . '.jpg';
                file_put_contents($dir . $filename, $imgData);
                $photoPath = 'uploads/photos/' . $filename;
            }
        }
    } elseif (!empty($_POST['photo_url'])) {
        // URL fetch
        $url = filter_var(trim($_POST['photo_url']), FILTER_VALIDATE_URL);
        if ($url) {
            $ctx     = stream_context_create(['http' => ['timeout' => 10]]);
            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false) {
                $filename  = 'photo_' . time() . '_' . rand(1000,9999) . '.jpg';
                file_put_contents($dir . $filename, $content);
                $photoPath = 'uploads/photos/' . $filename;
                $photoBase64 = 'data:image/jpg;base64,' . base64_encode($content);
            }
        }
    }

    // ── Insert into MySQL ─────────────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO items (barcode_number, barcode_image, name, photo, color, size, quantity)
        VALUES (:barcode_number, :barcode_image, :name, :photo, :color, :size, :quantity)
    ");
    $stmt->execute([
        ':barcode_number' => $barcodeNumber,
        ':barcode_image'  => $barcodeImagePath,
        ':name'           => $name,
        ':photo'          => $photoPath,
        ':color'          => $color,
        ':size'           => $size,
        ':quantity'       => $quantity,
    ]);
    $newId = (int)$db->lastInsertId();

    // ── Google Sync (Drive & Sheets) ──────────────────────────────────────
    $driveLink = null;
    if ($googleConfig && $googleConfig['enabled'] && !empty($googleConfig['web_app_url'])) {
        $syncData = [
            'name'         => $name,
            'barcode'      => $barcodeNumber,
            'color'        => $color,
            'size'         => $size,
            'quantity'     => $quantity,
            'imageBase64'  => $photoBase64,
            'imageName'    => $photoPath ? basename($photoPath) : 'item.jpg'
        ];

        $ch = curl_init($googleConfig['web_app_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($syncData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $resData = json_decode($response, true);
            if (!empty($resData['success']) && !empty($resData['driveLink'])) {
                $driveLink = $resData['driveLink'];
                // Update local DB with Drive link
                $upd = $db->prepare("UPDATE items SET drive_photo_link = ? WHERE id = ?");
                $upd->execute([$driveLink, $newId]);
            }
        }
    }

    echo json_encode([
        'success'   => true, 
        'id'        => $newId, 
        'message'   => 'Item added successfully',
        'driveLink' => $driveLink
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'line'    => $e->getLine(),
        'file'    => basename($e->getFile()),
    ]);
}
