<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
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
    echo json_encode(['success' => true, 'item' => $item]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
