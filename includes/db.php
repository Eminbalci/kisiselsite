<?php
// Prevent direct access to includes
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$db_dir = __DIR__ . '/../data';
$db_file = $db_dir . '/site.db';

// Ensure data folder exists
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0755, true);
}

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");
    
    // Create Tables if not exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        percentage INTEGER NOT NULL CHECK (percentage >= 0 AND percentage <= 100),
        category TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        content TEXT,
        file_path TEXT,
        image_path TEXT NOT NULL,
        project_link TEXT,
        slug TEXT UNIQUE,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration to support existing databases
    try {
        @$pdo->exec("ALTER TABLE portfolio ADD COLUMN content TEXT;");
    } catch (PDOException $e) {
        // Column already exists, safe to ignore
    }
    try {
        @$pdo->exec("ALTER TABLE portfolio ADD COLUMN file_path TEXT;");
    } catch (PDOException $e) {
        // Column already exists, safe to ignore
    }
    try {
        @$pdo->exec("ALTER TABLE portfolio ADD COLUMN slug TEXT;");
    } catch (PDOException $e) {
        // Column already exists, safe to ignore
    }
    try {
        @$pdo->exec("ALTER TABLE portfolio ADD COLUMN category TEXT;");
    } catch (PDOException $e) {
        // Column already exists, safe to ignore
    }

    // Populate empty slugs for existing projects
    $stmt = $pdo->query("SELECT id, title, slug FROM portfolio");
    $projects_to_update = $stmt->fetchAll();
    foreach ($projects_to_update as $p_item) {
        if (empty($p_item['slug'])) {
            // Simple slug generation
            $slug_val = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $p_item['title'])));
            $slug_val = trim($slug_val, '-');
            if (empty($slug_val)) {
                $slug_val = 'project-' . $p_item['id'];
            }
            $up_stmt = $pdo->prepare("UPDATE portfolio SET slug = :slug WHERE id = :id");
            $up_stmt->execute(['slug' => $slug_val, 'id' => $p_item['id']]);
        }
    }

    // Seed default admin if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    if ($user_count == 0) {
        $default_username = 'admin';
        $default_password = 'AdminPassword123!';
        $hash = password_hash($default_password, PASSWORD_BCRYPT);
        
        $insert_stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :password)");
        $insert_stmt->execute([
            'username' => $default_username,
            'password' => $hash
        ]);
    }

    // Seed default settings if empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings");
    $settings_count = $stmt->fetch()['count'];
    if ($settings_count == 0) {
        $default_settings = [
            'site_title' => 'My Personal Portfolio',
            'admin_name' => 'Alex Morgan',
            'admin_title' => 'Full Stack Developer & UI/UX Designer',
            'logo_text' => 'Portfolio',
            'about_text' => 'Hello! I am a passionate developer dedicated to building beautiful, responsive, and secure web applications. I love solving complex problems and turning ideas into clean, functional code.',
            'profile_image' => '',
            'site_favicon' => '',
            'theme_color' => '#d97706',
            'theme_secondary_color' => '#fbbf24',
            'theme_color_light' => '#2563eb',
            'theme_secondary_color_light' => '#60a5fa',
            'github_link' => 'https://github.com',
            'linkedin_link' => 'https://linkedin.com',
            'twitter_link' => 'https://twitter.com',
            'email' => 'alex@example.com'
        ];

        $insert_setting = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
        foreach ($default_settings as $key => $val) {
            $insert_setting->execute(['key' => $key, 'value' => $val]);
        }
    } else {
        // Ensure site_favicon exists for existing databases
        $check_favicon = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'site_favicon'");
        $check_favicon->execute();
        if ($check_favicon->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('site_favicon', '')");
        }
        
        // Ensure theme_color exists
        $check_theme = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'theme_color'");
        $check_theme->execute();
        if ($check_theme->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_color', '#d97706')");
        }
        
        // Ensure theme_secondary_color exists
        $check_theme_sec = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'theme_secondary_color'");
        $check_theme_sec->execute();
        if ($check_theme_sec->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_secondary_color', '#fbbf24')");
        }
        
        // Ensure theme_color_light exists
        $check_theme_l = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'theme_color_light'");
        $check_theme_l->execute();
        if ($check_theme_l->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_color_light', '#2563eb')");
        }
        
        // Ensure theme_secondary_color_light exists
        $check_theme_sec_l = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'theme_secondary_color_light'");
        $check_theme_sec_l->execute();
        if ($check_theme_sec_l->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('theme_secondary_color_light', '#60a5fa')");
        }
        
        // Ensure logo_text exists
        $check_logo = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = 'logo_text'");
        $check_logo->execute();
        if ($check_logo->fetch()['count'] == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('logo_text', 'Portfolio')");
        }
    }

    // Create skill_categories table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS skill_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    )");

    // Seed default categories if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM skill_categories");
    if ($stmt->fetch()['count'] == 0) {
        $default_cats = ['Frontend', 'Backend', 'UI/UX Tasarım', 'Diğer Araçlar'];
        $ins_cat = $pdo->prepare("INSERT INTO skill_categories (name) VALUES (:name)");
        foreach ($default_cats as $cat) {
            $ins_cat->execute(['name' => $cat]);
        }
    }

    // Helper function for SQLite to add column if not exists
    $addColumn = function($table, $column, $definition) use ($pdo) {
        $stmt = $pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    };

    // Alter existing tables for multi-language and stats
    $addColumn('portfolio', 'title_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'description_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'content_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'views', 'INTEGER DEFAULT 0');

    $addColumn('skills', 'name_en', 'TEXT DEFAULT NULL');
    $addColumn('skill_categories', 'name_en', 'TEXT DEFAULT NULL');

    // Create New Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        title_en TEXT,
        slug TEXT UNIQUE NOT NULL,
        content TEXT NOT NULL,
        content_en TEXT,
        image_path TEXT,
        views INTEGER DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        date_sent DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS timeline (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        title TEXT NOT NULL,
        title_en TEXT,
        institution TEXT NOT NULL,
        institution_en TEXT,
        date_range TEXT,
        description TEXT,
        description_en TEXT,
        display_order INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        title_en TEXT,
        issuer TEXT NOT NULL,
        date_issued TEXT,
        image_path TEXT,
        link TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visit_date DATE UNIQUE NOT NULL,
        page_views INTEGER DEFAULT 0,
        unique_visitors INTEGER DEFAULT 0
    )");

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
