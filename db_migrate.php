<?php
require_once 'includes/db.php';

try {
    // 1. Create project_images table
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        image_path TEXT NOT NULL,
        display_order INTEGER DEFAULT 0
    )");
    echo "project_images tablosu oluşturuldu.\n";

    // 2. Create blog_comments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        comment TEXT NOT NULL,
        is_approved INTEGER DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "blog_comments tablosu oluşturuldu.\n";

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
