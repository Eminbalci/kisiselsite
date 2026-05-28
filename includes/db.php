<?php
// Prevent direct access to includes
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Load config file or create default one
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    $default_config = [
        'db_type' => 'sqlite',
        'mysql_host' => 'localhost',
        'mysql_db' => 'kisiselsite',
        'mysql_user' => 'root',
        'mysql_pass' => '',
    ];
    file_put_contents($config_file, "<?php\nreturn " . var_export($default_config, true) . ";\n");
}
$config = require $config_file;

$db_dir = __DIR__ . '/../data';
$db_file = $db_dir . '/site.db';

// Ensure data folder exists
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0755, true);
}

$pdo = null;
$db_error = '';
$is_fallback = false;

// 1. Try MySQL if selected
if (isset($config['db_type']) && $config['db_type'] === 'mysql') {
    try {
        $dsn = "mysql:host=" . $config['mysql_host'] . ";dbname=" . $config['mysql_db'] . ";charset=utf8mb4";
        // Connect to MySQL with a 3 second timeout limit
        $pdo = new PDO($dsn, $config['mysql_user'], $config['mysql_pass'], [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        $db_error = "MySQL veritabanına bağlanılamadı (" . $e->getMessage() . "). SQLite yedek veritabanı üzerinden çalışmaya devam ediliyor.";
        $is_fallback = true;
    }
}

// 2. Fallback to SQLite if MySQL failed or SQLite is selected
if ($pdo === null) {
    try {
        $pdo = new PDO("sqlite:" . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } catch (PDOException $e) {
        die("Veritabanı bağlantı hatası: SQLite ve MySQL bağlantıları kurulamadı. Detay: " . $e->getMessage());
    }
}

// Store fallback warning in session
if ($is_fallback) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['db_fallback_warning'] = $db_error;
}

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pk_auto = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    
    // Create Tables if not exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk_auto,
        username VARCHAR(255) UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(255) PRIMARY KEY,
        setting_value TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS skills (
        id $pk_auto,
        name TEXT NOT NULL,
        percentage INTEGER NOT NULL,
        category TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
        id $pk_auto,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        image_path TEXT NOT NULL,
        project_link TEXT,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `references` (
        id $pk_auto,
        name TEXT NOT NULL,
        title TEXT,
        title_en TEXT,
        company TEXT,
        contact_info TEXT,
        display_order INTEGER DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS skill_categories (
        id $pk_auto,
        name VARCHAR(255) UNIQUE NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS blog (
        id $pk_auto,
        title TEXT NOT NULL,
        title_en TEXT,
        slug VARCHAR(255) UNIQUE NOT NULL,
        content TEXT NOT NULL,
        content_en TEXT,
        image_path TEXT,
        views INTEGER DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id $pk_auto,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        date_sent DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS timeline (
        id $pk_auto,
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
        id $pk_auto,
        title TEXT NOT NULL,
        title_en TEXT,
        issuer TEXT NOT NULL,
        date_issued TEXT,
        image_path TEXT,
        link TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics (
        id $pk_auto,
        visit_date DATE UNIQUE NOT NULL,
        page_views INTEGER DEFAULT 0,
        unique_visitors INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_images (
        id $pk_auto,
        project_id INTEGER NOT NULL,
        image_path TEXT NOT NULL,
        display_order INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id $pk_auto,
        post_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        comment TEXT NOT NULL,
        is_approved INTEGER DEFAULT 0,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_files (
        id $pk_auto,
        project_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,
        file_label TEXT NOT NULL,
        file_label_en TEXT NOT NULL,
        display_order INTEGER DEFAULT 0
    )");

    // Helper function to add column if not exists (driver-agnostic)
    $addColumn = function($table, $column, $definition) use ($pdo, $driver) {
        if ($driver === 'mysql') {
            $clean_table = str_replace('`', '', $table);
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$clean_table` LIKE :column");
            $stmt->execute(['column' => $column]);
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$clean_table` ADD COLUMN `$column` $definition");
            }
        } else {
            $stmt = $pdo->prepare("PRAGMA table_info($table)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array($column, $columns)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
    };

    // Alter existing tables for multi-language and stats
    $addColumn('portfolio', 'title_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'description_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'content_en', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'views', 'INTEGER DEFAULT 0');
    $addColumn('portfolio', 'content', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'file_path', 'TEXT DEFAULT NULL');
    $addColumn('portfolio', 'slug', 'VARCHAR(255) DEFAULT NULL');
    $addColumn('portfolio', 'category', 'TEXT DEFAULT NULL');

    $addColumn('skills', 'name_en', 'TEXT DEFAULT NULL');
    $addColumn('skill_categories', 'name_en', 'TEXT DEFAULT NULL');

    // Populate empty slugs for existing projects
    $stmt = $pdo->query("SELECT id, title, slug FROM portfolio");
    $projects_to_update = $stmt->fetchAll();
    foreach ($projects_to_update as $p_item) {
        if (empty($p_item['slug'])) {
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
        // Ensure keys exist
        $ensure_setting = function($key, $default_val) use ($pdo) {
            $check = $pdo->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = :key");
            $check->execute(['key' => $key]);
            if ($check->fetch()['count'] == 0) {
                $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)");
                $ins->execute(['key' => $key, 'val' => $default_val]);
            }
        };

        $ensure_setting('site_favicon', '');
        $ensure_setting('theme_color', '#d97706');
        $ensure_setting('theme_secondary_color', '#fbbf24');
        $ensure_setting('theme_color_light', '#2563eb');
        $ensure_setting('theme_secondary_color_light', '#60a5fa');
        $ensure_setting('logo_text', 'Portfolio');
    }

    // Seed default categories if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM skill_categories");
    if ($stmt->fetch()['count'] == 0) {
        $default_cats = ['Frontend', 'Backend', 'UI/UX Tasarım', 'Diğer Araçlar'];
        $ins_cat = $pdo->prepare("INSERT INTO skill_categories (name) VALUES (:name)");
        foreach ($default_cats as $cat) {
            $ins_cat->execute(['name' => $cat]);
        }
    }

} catch (PDOException $e) {
    die("Database Schema Initialization Error: " . $e->getMessage());
}
