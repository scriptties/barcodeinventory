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
    $stmt = $db->prepare("DELETE FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Item deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
