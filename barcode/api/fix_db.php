<?php
/**
 * api/fix_db.php
 * Run this once to ensure your database has the 'photo' and 'drive_photo_link' columns.
 */
require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();
    
    // Check if 'photo' column exists, if not, try to rename 'item_image' or add 'photo'
    $cols = $db->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('photo', $cols)) {
        if (in_array('item_image', $cols)) {
            $db->exec("ALTER TABLE items CHANGE item_image photo VARCHAR(512) DEFAULT NULL");
            echo "Renamed item_image to photo.<br>";
        } else {
            $db->exec("ALTER TABLE items ADD photo VARCHAR(512) DEFAULT NULL AFTER name");
            echo "Added photo column.<br>";
        }
    } else {
        echo "Photo column already exists.<br>";
    }
    
    if (!in_array('drive_photo_link', $cols)) {
        $db->exec("ALTER TABLE items ADD drive_photo_link VARCHAR(1024) DEFAULT NULL AFTER photo");
        echo "Added drive_photo_link column.<br>";
    } else {
        echo "Drive link column already exists.<br>";
    }
    
    echo "<strong>Database fix complete!</strong>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
