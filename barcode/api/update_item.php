<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    $barcodeNumber = trim($_POST['barcode_number'] ?? $item['barcode_number']);
    $name          = trim($_POST['name']           ?? $item['name']);
    $color         = trim($_POST['color']          ?? $item['color']);
    $size          = trim($_POST['size']           ?? $item['size']);
    $quantity      = isset($_POST['quantity']) ? (int)$_POST['quantity'] : (int)$item['quantity'];
    $barcodeImage  = $item['barcode_image'];
    $photoPath     = $item['photo'];

    // ── Update barcode image ──────────────────────────────────────────────
    if (!empty($_POST['barcode_image_base64'])) {
        $data    = preg_replace('#^data:image/\w+;base64,#i', '', $_POST['barcode_image_base64']);
        $imgData = base64_decode($data);
        if ($imgData !== false) {
            $dir = __DIR__ . '/../uploads/barcodes/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename     = 'barcode_' . time() . '_' . rand(1000,9999) . '.png';
            file_put_contents($dir . $filename, $imgData);
            $barcodeImage = 'uploads/barcodes/' . $filename;
        }
    }

    // ── Update item photo ─────────────────────────────────────────────────
    $dir = __DIR__ . '/../uploads/photos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $filename = 'photo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
            $photoPath = 'uploads/photos/' . $filename;
        }
    } elseif (!empty($_POST['photo_base64'])) {
        $data    = preg_replace('#^data:image/\w+;base64,#i', '', $_POST['photo_base64']);
        $imgData = base64_decode($data);
        if ($imgData !== false) {
            $filename  = 'photo_' . time() . '_' . rand(1000,9999) . '.jpg';
            file_put_contents($dir . $filename, $imgData);
            $photoPath = 'uploads/photos/' . $filename;
        }
    } elseif (!empty($_POST['photo_url'])) {
        $url = filter_var(trim($_POST['photo_url']), FILTER_VALIDATE_URL);
        if ($url) {
            $ctx     = stream_context_create(['http' => ['timeout' => 10]]);
            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false) {
                $filename  = 'photo_' . time() . '_' . rand(1000,9999) . '.jpg';
                file_put_contents($dir . $filename, $content);
                $photoPath = 'uploads/photos/' . $filename;
            }
        }
    }

    // ── Update MySQL ──────────────────────────────────────────────────────
    $stmt = $db->prepare("
        UPDATE items SET
            barcode_number = :barcode_number,
            barcode_image  = :barcode_image,
            name           = :name,
            photo          = :photo,
            color          = :color,
            size           = :size,
            quantity       = :quantity
        WHERE id = :id
    ");
    $stmt->execute([
        ':barcode_number' => $barcodeNumber,
        ':barcode_image'  => $barcodeImage,
        ':name'           => $name,
        ':photo'          => $photoPath,
        ':color'          => $color,
        ':size'           => $size,
        ':quantity'       => $quantity,
        ':id'             => $id,
    ]);

    echo json_encode(['success' => true, 'message' => 'Item updated successfully']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'line'    => $e->getLine(),
        'file'    => basename($e->getFile()),
    ]);
}
