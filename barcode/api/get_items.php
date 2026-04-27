<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

try {
    $db    = getDB();
    $stmt  = $db->query("SELECT * FROM items ORDER BY id DESC");
    $items = $stmt->fetchAll();
    echo json_encode(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'line'    => $e->getLine(),
        'file'    => basename($e->getFile()),
    ]);
}
