<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'barcode_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Create DB if not exists
        $init = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `items` (
                `id`                INT AUTO_INCREMENT PRIMARY KEY,
                `barcode_number`    VARCHAR(255) NOT NULL,
                `barcode_image`     VARCHAR(512) DEFAULT NULL,
                `name`              VARCHAR(255) NOT NULL,
                `photo`             VARCHAR(512) DEFAULT NULL,
                `drive_photo_link`  VARCHAR(1024) DEFAULT NULL,
                `color`             VARCHAR(64)  DEFAULT NULL,
                `size`              VARCHAR(64)  DEFAULT NULL,
                `quantity`          INT DEFAULT 0,
                `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Force column check/fix
        $cols = $pdo->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('photo', $cols)) {
            if (in_array('item_image', $cols)) {
                $pdo->exec("ALTER TABLE items CHANGE item_image photo VARCHAR(512) DEFAULT NULL");
            } else {
                $pdo->exec("ALTER TABLE items ADD photo VARCHAR(512) DEFAULT NULL AFTER name");
            }
        }
        if (!in_array('drive_photo_link', $cols)) {
            $pdo->exec("ALTER TABLE items ADD drive_photo_link VARCHAR(1024) DEFAULT NULL AFTER photo");
        }
    }
    return $pdo;
}
