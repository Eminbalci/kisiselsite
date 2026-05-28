<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Force login
require_login();

// Handle database backup before anything else
if (isset($_GET['action']) && $_GET['action'] === 'backup_db') {
    $file = '../data/site.db';
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="site_backup_' . date('Y-m-d_H-i-s') . '.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        $error_message = 'Veritabanı dosyası bulunamadı.';
    }
}

$success_message = '';
$error_message = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['db_success_msg'])) {
    $success_message = $_SESSION['db_success_msg'];
    unset($_SESSION['db_success_msg']);
}

// Load current settings
$settings = get_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the post data was discarded because it exceeded the post_max_size limit
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        $error_message = "Yüklemeye çalıştığınız dosya sunucu limitlerini aşıyor (Maksimum limit: {$max_size}). Lütfen daha küçük bir dosya yükleyin.";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        
        if (!verify_csrf_token($csrf_token)) {
            $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
        } else {
            if ($action === 'update_settings') {
            $site_title = trim($_POST['site_title']);
            $admin_name = trim($_POST['admin_name']);
            $logo_text = trim($_POST['logo_text'] ?? '');
            $admin_title = trim($_POST['admin_title']);
            $admin_title_en = trim($_POST['admin_title_en'] ?? '');
            if (empty($admin_title_en) && !empty($admin_title)) {
                $admin_title_en = auto_translate($admin_title, 'tr', 'en');
            }
            $about_text = trim($_POST['about_text']);
            $about_text_en = trim($_POST['about_text_en'] ?? '');
            if (empty($about_text_en) && !empty($about_text)) {
                $about_text_en = auto_translate($about_text, 'tr', 'en');
            }
            $github_link = trim($_POST['github_link']);
            $linkedin_link = trim($_POST['linkedin_link']);
            $instagram_link = trim($_POST['instagram_link']);
            $email = trim($_POST['email']);
            $theme_color = trim($_POST['theme_color']);
            $theme_secondary_color = trim($_POST['theme_secondary_color']);
            $theme_color_light = trim($_POST['theme_color_light'] ?? '#2563eb');
            $theme_secondary_color_light = trim($_POST['theme_secondary_color_light'] ?? '#60a5fa');
            
            try {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
                
                $data = [
                    'site_title' => $site_title,
                    'admin_name' => $admin_name,
                    'logo_text' => $logo_text,
                    'admin_title' => $admin_title,
                    'admin_title_en' => $admin_title_en,
                    'about_text' => $about_text,
                    'about_text_en' => $about_text_en,
                    'github_link' => $github_link,
                    'linkedin_link' => $linkedin_link,
                    'instagram_link' => $instagram_link,
                    'email' => $email,
                    'theme_color' => $theme_color,
                    'theme_secondary_color' => $theme_secondary_color,
                    'theme_color_light' => $theme_color_light,
                    'theme_secondary_color_light' => $theme_secondary_color_light
                ];
                
                $checkStmt = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = :key");
                $insertStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
                
                foreach ($data as $key => $value) {
                    $checkStmt->execute(['key' => $key]);
                    if ($checkStmt->rowCount() > 0 || $checkStmt->fetch()) {
                        $stmt->execute(['value' => $value, 'key' => $key]);
                    } else {
                        $insertStmt->execute(['key' => $key, 'value' => $value]);
                    }
                }
                
                $success_message = 'Genel ayarlar başarıyla güncellendi.';
                $settings = get_settings($pdo); // Reload settings
            } catch (PDOException $e) {
                $error_message = 'Veritabanı hatası: ' . $e->getMessage();
            }
        } elseif ($action === 'update_password') {
            $new_username = trim($_POST['new_username']);
            $old_password = $_POST['old_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($new_username) || empty($old_password)) {
                $error_message = 'Kullanıcı adı ve mevcut şifre alanları doldurulmak zorunludur.';
            } else {
                // Get current password hash and username
                $stmt = $pdo->prepare("SELECT username, password_hash FROM users WHERE id = :id");
                $stmt->execute(['id' => $_SESSION['admin_user_id']]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($old_password, $user['password_hash'])) {
                    $username_valid = true;
                    
                    // Check if new username is already taken by another user
                    if ($new_username !== $user['username']) {
                        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :id");
                        $check_stmt->execute(['username' => $new_username, 'id' => $_SESSION['admin_user_id']]);
                        if ($check_stmt->fetch()['count'] > 0) {
                            $error_message = 'Bu kullanıcı adı zaten kullanılmaktadır.';
                            $username_valid = false;
                        }
                    }
                    
                    if ($username_valid) {
                        $update_fields = ["username = :username"];
                        $params = [
                            'username' => $new_username,
                            'id' => $_SESSION['admin_user_id']
                        ];
                        $password_valid = true;
                        
                        // If they want to change password too
                        if (!empty($new_password)) {
                            if ($new_password !== $confirm_password) {
                                $error_message = 'Yeni şifreler eşleşmiyor.';
                                $password_valid = false;
                            } elseif (strlen($new_password) < 6) {
                                $error_message = 'Yeni şifre en az 6 karakter olmalıdır.';
                                $password_valid = false;
                            } else {
                                $update_fields[] = "password_hash = :hash";
                                $params['hash'] = password_hash($new_password, PASSWORD_BCRYPT);
                            }
                        }
                        
                        if ($password_valid) {
                            $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = :id";
                            $update_stmt = $pdo->prepare($sql);
                            $update_stmt->execute($params);
                            
                            // Update session credentials
                            $_SESSION['admin_username'] = $new_username;
                            $success_message = 'Giriş bilgileri başarıyla güncellendi.';
                        }
                    }
                } else {
                    $error_message = 'Mevcut şifre hatalı.';
                }
            }
        } elseif ($action === 'update_profile_image') {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $uploaded_file = handle_image_upload($_FILES['profile_image']);
                    
                    // Delete old profile image if it exists
                    if (!empty($settings['profile_image'])) {
                        $old_image_path = '../uploads/' . $settings['profile_image'];
                        if (file_exists($old_image_path)) {
                            @unlink($old_image_path);
                        }
                    }
                    
                    // Save new profile image
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = 'profile_image'");
                    $stmt->execute(['value' => $uploaded_file]);
                    
                    $success_message = 'Profil resmi başarıyla güncellendi.';
                    $settings = get_settings($pdo); // Reload settings
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            } else {
                $error_message = 'Lütfen geçerli bir dosya seçin.';
            }
        } elseif ($action === 'update_favicon') {
            if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $uploaded_file = handle_favicon_upload($_FILES['site_favicon']);
                    
                    // Save new favicon setting
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = 'site_favicon'");
                    $stmt->execute(['value' => $uploaded_file]);
                    
                    $success_message = 'Site faviconu başarıyla güncellendi.';
                    $settings = get_settings($pdo); // Reload settings
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            } else {
                $error_message = 'Lütfen geçerli bir favicon dosyası seçin.';
            }
        } elseif ($action === 'update_db_config') {
            $db_type = trim($_POST['db_type']);
            $mysql_host = trim($_POST['mysql_host']);
            $mysql_db = trim($_POST['mysql_db']);
            $mysql_user = trim($_POST['mysql_user']);
            $mysql_pass = $_POST['mysql_pass'];
            $migrate_data = isset($_POST['migrate_data']) && $_POST['migrate_data'] == '1';

            $test_failed = false;
            $test_err = '';
            if ($db_type === 'mysql') {
                try {
                    $dsn = "mysql:host={$mysql_host};dbname={$mysql_db};charset=utf8mb4";
                    $test_pdo = new PDO($dsn, $mysql_user, $mysql_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 3,
                    ]);
                } catch (PDOException $e) {
                    $test_failed = true;
                    $test_err = $e->getMessage();
                }
            }

            if ($test_failed) {
                $error_message = 'MySQL sunucusuna bağlanılamadı. Ayarlar kaydedilmedi. Hata: ' . $test_err;
            } else {
                $new_config = [
                    'db_type' => $db_type,
                    'mysql_host' => $mysql_host,
                    'mysql_db' => $mysql_db,
                    'mysql_user' => $mysql_user,
                    'mysql_pass' => $mysql_pass,
                ];

                $config_path = '../includes/config.php';
                $content = "<?php\nreturn " . var_export($new_config, true) . ";\n";
                if (file_put_contents($config_path, $content) === false) {
                    $error_message = 'Yapılandırma dosyası (includes/config.php) yazılırken hata oluştu.';
                } else {
                    $success_message = 'Veritabanı yapılandırması başarıyla kaydedildi.';

                    if ($db_type === 'mysql' && $migrate_data) {
                        try {
                            $sqlite_pdo = new PDO("sqlite:" . __DIR__ . '/../data/site.db');
                            $sqlite_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $sqlite_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                            $pk_auto = 'INT AUTO_INCREMENT PRIMARY KEY';
                            $tables_sql = [
                                "CREATE TABLE IF NOT EXISTS users (id $pk_auto, username VARCHAR(255) UNIQUE NOT NULL, password_hash TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(255) PRIMARY KEY, setting_value TEXT)",
                                "CREATE TABLE IF NOT EXISTS skills (id $pk_auto, name TEXT NOT NULL, percentage INTEGER NOT NULL, category TEXT NOT NULL, name_en TEXT DEFAULT NULL)",
                                "CREATE TABLE IF NOT EXISTS skill_categories (id $pk_auto, name VARCHAR(255) UNIQUE NOT NULL, name_en TEXT DEFAULT NULL)",
                                "CREATE TABLE IF NOT EXISTS portfolio (id $pk_auto, title TEXT NOT NULL, title_en TEXT DEFAULT NULL, description TEXT NOT NULL, description_en TEXT DEFAULT NULL, content TEXT DEFAULT NULL, content_en TEXT DEFAULT NULL, file_path TEXT DEFAULT NULL, image_path TEXT NOT NULL, project_link TEXT DEFAULT NULL, slug VARCHAR(255) UNIQUE, category TEXT DEFAULT NULL, views INTEGER DEFAULT 0, date_added DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS `references` (id $pk_auto, name TEXT NOT NULL, title TEXT DEFAULT NULL, title_en TEXT DEFAULT NULL, company TEXT DEFAULT NULL, contact_info TEXT DEFAULT NULL, display_order INTEGER DEFAULT 0, date_added DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS blog (id $pk_auto, title TEXT NOT NULL, title_en TEXT DEFAULT NULL, slug VARCHAR(255) UNIQUE NOT NULL, content TEXT NOT NULL, content_en TEXT DEFAULT NULL, image_path TEXT DEFAULT NULL, views INTEGER DEFAULT 0, date_added DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS messages (id $pk_auto, name TEXT NOT NULL, email TEXT NOT NULL, message TEXT NOT NULL, is_read INTEGER DEFAULT 0, date_sent DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS timeline (id $pk_auto, type TEXT NOT NULL, title TEXT NOT NULL, title_en TEXT DEFAULT NULL, institution TEXT NOT NULL, institution_en TEXT DEFAULT NULL, date_range TEXT DEFAULT NULL, description TEXT DEFAULT NULL, description_en TEXT DEFAULT NULL, display_order INTEGER DEFAULT 0)",
                                "CREATE TABLE IF NOT EXISTS certificates (id $pk_auto, title TEXT NOT NULL, title_en TEXT DEFAULT NULL, issuer TEXT NOT NULL, date_issued TEXT DEFAULT NULL, image_path TEXT DEFAULT NULL, link TEXT DEFAULT NULL)",
                                "CREATE TABLE IF NOT EXISTS analytics (id $pk_auto, visit_date DATE UNIQUE NOT NULL, page_views INTEGER DEFAULT 0, unique_visitors INTEGER DEFAULT 0)",
                                "CREATE TABLE IF NOT EXISTS project_images (id $pk_auto, project_id INTEGER NOT NULL, image_path TEXT NOT NULL, display_order INTEGER DEFAULT 0)",
                                "CREATE TABLE IF NOT EXISTS blog_comments (id $pk_auto, post_id INTEGER NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL, comment TEXT NOT NULL, is_approved INTEGER DEFAULT 0, date_added DATETIME DEFAULT CURRENT_TIMESTAMP)",
                                "CREATE TABLE IF NOT EXISTS project_files (id $pk_auto, project_id INTEGER NOT NULL, file_path TEXT NOT NULL, file_label TEXT NOT NULL, file_label_en TEXT NOT NULL, display_order INTEGER DEFAULT 0)"
                            ];

                            foreach ($tables_sql as $sql) {
                                $test_pdo->exec($sql);
                            }

                            $tables = [
                                'users', 'settings', 'skills', 'skill_categories', 'portfolio', 
                                '`references`', 'blog', 'messages', 'timeline', 'certificates', 
                                'analytics', 'project_images', 'blog_comments', 'project_files'
                            ];

                            foreach ($tables as $table) {
                                $sqlite_stmt = $sqlite_pdo->query("SELECT * FROM $table");
                                $rows = $sqlite_stmt->fetchAll(PDO::FETCH_ASSOC);

                                $test_pdo->exec("TRUNCATE TABLE $table");

                                if (!empty($rows)) {
                                    $columns = array_keys($rows[0]);
                                    $col_list = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
                                    $param_list = implode(', ', array_map(function($c) { return ":$c"; }, $columns));

                                    $ins_stmt = $test_pdo->prepare("INSERT INTO $table ($col_list) VALUES ($param_list)");
                                    foreach ($rows as $row) {
                                        $ins_stmt->execute($row);
                                    }
                                }
                            }
                            $success_message .= ' SQLite verileriniz başarıyla MySQL veritabanına aktarıldı!';
                        } catch (Exception $ex) {
                            $error_message .= ' Veritabanı kaydedildi ancak veri aktarımı sırasında hata oluştu: ' . $ex->getMessage();
                        }
                    }

                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['db_success_msg'] = $success_message;

                    header("Location: index.php");
                    exit;
                }
            }
        }
    }
}
}

// Generate new CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Genel Ayarlar</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <!-- Favicon -->
    <?php if (!empty($settings['site_favicon']) && file_exists('../uploads/' . $settings['site_favicon'])): ?>
        <link rel="icon" type="<?php 
            $fav_ext = pathinfo($settings['site_favicon'], PATHINFO_EXTENSION);
            if ($fav_ext === 'svg') echo 'image/svg+xml';
            elseif ($fav_ext === 'ico') echo 'image/x-icon';
            else echo 'image/' . $fav_ext;
        ?>" href="../uploads/<?php echo escape($settings['site_favicon']); ?>">
    <?php endif; ?>
</head>
<body>
    <header class="admin-header">
        <div class="header-logo">
            <h1>Portföy Admin</h1>
        </div>
        <div class="user-info">
            <span>Hoş geldiniz, <strong><?php echo escape($_SESSION['admin_username']); ?></strong></span>
            <a href="logout.php" class="btn btn-sm btn-danger">Çıkış Yap</a>
        </div>
    </header>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <a href="index.php" class="nav-item active">
                    <span class="icon">⚙️</span> Genel Ayarlar
                </a>
                <a href="skills.php" class="nav-item">
                    <span class="icon">📊</span> Yetenekler
                </a>
                <a href="portfolio.php" class="nav-item">
                    <span class="icon">💼</span> Portföy
                </a>
                <a href="blog.php" class="nav-item">
                    <span class="icon">📝</span> Blog
                </a>
                <a href="comments.php" class="nav-item">
                    <span class="icon">💬</span> Yorumlar
                </a>
                <a href="timeline.php" class="nav-item">
                    <span class="icon">⏳</span> Zaman Çizelgesi
                </a>
                <a href="certificates.php" class="nav-item">
                    <span class="icon">🏆</span> Sertifikalar
                </a>
                <a href="references.php" class="nav-item">
                    <span class="icon">🤝</span> Referanslar
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="icon">✉️</span> Gelen Kutusu
                </a>
                <div class="nav-divider"></div>
                <a href="../index.php" target="_blank" class="nav-item">
                    <span class="icon">🌐</span> Siteyi Görüntüle
                </a>
                <a href="../cv.php?lang=tr" target="_blank" class="nav-item" style="color: var(--brand-secondary);">
                    <span class="icon">📄</span> CV İndir (TR)
                </a>
                <a href="../cv.php?lang=en" target="_blank" class="nav-item" style="color: var(--brand-secondary);">
                    <span class="icon">📄</span> CV İndir (EN)
                </a>
            </nav>
        </aside>

        <main class="admin-main">
            <h2>Genel Ayarlar & İstatistikler</h2>
            <p class="main-subtitle">Sitenizin temel bilgilerini, ayarlarını ve anlık ziyaretçi istatistiklerini buradan yönetebilirsiniz.</p>

            <?php
            // Fetch basic stats
            $today = date('Y-m-d');
            $stats_today = $pdo->query("SELECT page_views, unique_visitors FROM analytics WHERE visit_date = '$today'")->fetch();
            $total_views = $pdo->query("SELECT SUM(page_views) as total FROM analytics")->fetch()['total'] ?? 0;
            $unread_msg = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE is_read = 0")->fetch()['count'] ?? 0;
            ?>
            <div class="grid grid-3" style="margin-bottom: 30px; gap: 20px;">
                <div class="card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">👁️</div>
                    <h3 style="margin-bottom: 5px;">Bugün (Gösterim)</h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo (int)($stats_today['page_views'] ?? 0); ?></div>
                </div>
                <div class="card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📈</div>
                    <h3 style="margin-bottom: 5px;">Toplam Gösterim</h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo (int)$total_views; ?></div>
                </div>
                <div class="card" style="text-align: center; padding: 20px; <?php echo $unread_msg > 0 ? 'border: 2px solid var(--primary);' : ''; ?>">
                    <div style="font-size: 2rem; margin-bottom: 10px;">✉️</div>
                    <h3 style="margin-bottom: 5px;">Okunmamış Mesajlar</h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo (int)$unread_msg; ?></div>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['db_fallback_warning'])): ?>
                <div class="alert alert-warning" style="border-left: 5px solid #d97706; background: rgba(217, 119, 6, 0.05); color: #d97706; margin-bottom: 20px; padding: 15px; border-radius: 8px;">
                    ⚠️ <?php echo escape($_SESSION['db_fallback_warning']); ?>
                </div>
                <?php unset($_SESSION['db_fallback_warning']); ?>
            <?php endif; ?>

            <div class="grid grid-2">
                <div class="card">
                    <h3>Kişisel Bilgiler & Sosyal Bağlantılar</h3>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="update_settings">

                        <div class="form-group">
                            <label for="site_title">Site Başlığı</label>
                            <input type="text" id="site_title" name="site_title" value="<?php echo escape($settings['site_title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_name">Adınız Soyadınız</label>
                            <input type="text" id="admin_name" name="admin_name" value="<?php echo escape($settings['admin_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="logo_text">Sol Üst Logo Metni (Kaldırmak için boş bırakın)</label>
                            <input type="text" id="logo_text" name="logo_text" value="<?php echo escape($settings['logo_text'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="admin_title">Unvanınız (TR)</label>
                            <input type="text" id="admin_title" name="admin_title" value="<?php echo escape($settings['admin_title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_title_en">Unvanınız (EN)</label>
                            <input type="text" id="admin_title_en" name="admin_title_en" value="<?php echo escape($settings['admin_title_en'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">E-posta Adresi</label>
                            <input type="email" id="email" name="email" value="<?php echo escape($settings['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="about_text">Hakkımda Yazısı (TR)</label>
                            <textarea id="about_text" name="about_text" rows="5" required><?php echo escape($settings['about_text']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="about_text_en">Hakkımda Yazısı (EN)</label>
                            <textarea id="about_text_en" name="about_text_en" rows="5"><?php echo escape($settings['about_text_en'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="github_link">GitHub Linki</label>
                            <input type="url" id="github_link" name="github_link" value="<?php echo escape($settings['github_link']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="linkedin_link">LinkedIn Linki</label>
                            <input type="url" id="linkedin_link" name="linkedin_link" value="<?php echo escape($settings['linkedin_link']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="instagram_link">Instagram Linki</label>
                            <input type="url" id="instagram_link" name="instagram_link" value="<?php echo escape($settings['instagram_link']); ?>">
                        </div>

                        <div class="grid grid-2" style="margin-bottom:1.5rem; gap:15px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="theme_color">Karanlık Mod Birincil Renk</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" id="theme_color" name="theme_color" value="<?php echo escape($settings['theme_color'] ?? '#d97706'); ?>" style="width:50px; height:40px; padding:0; border:none; cursor:pointer; border-radius:4px;">
                                    <span style="font-family:monospace; font-weight:600;"><?php echo escape($settings['theme_color'] ?? '#d97706'); ?></span>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="theme_secondary_color">Karanlık Mod İkincil Renk</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" id="theme_secondary_color" name="theme_secondary_color" value="<?php echo escape($settings['theme_secondary_color'] ?? '#fbbf24'); ?>" style="width:50px; height:40px; padding:0; border:none; cursor:pointer; border-radius:4px;">
                                    <span style="font-family:monospace; font-weight:600;"><?php echo escape($settings['theme_secondary_color'] ?? '#fbbf24'); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="theme_color_light">Aydınlık Mod Birincil Renk</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" id="theme_color_light" name="theme_color_light" value="<?php echo escape($settings['theme_color_light'] ?? '#2563eb'); ?>" style="width:50px; height:40px; padding:0; border:none; cursor:pointer; border-radius:4px;">
                                    <span style="font-family:monospace; font-weight:600;"><?php echo escape($settings['theme_color_light'] ?? '#2563eb'); ?></span>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="theme_secondary_color_light">Aydınlık Mod İkincil Renk</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" id="theme_secondary_color_light" name="theme_secondary_color_light" value="<?php echo escape($settings['theme_secondary_color_light'] ?? '#60a5fa'); ?>" style="width:50px; height:40px; padding:0; border:none; cursor:pointer; border-radius:4px;">
                                    <span style="font-family:monospace; font-weight:600;"><?php echo escape($settings['theme_secondary_color_light'] ?? '#60a5fa'); ?></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </form>
                </div>

                <div class="flex-column gap-2">
                    <div class="card">
                        <h3>Profil Resmi Güncelle</h3>
                        <div class="profile-preview-wrapper">
                            <?php if (!empty($settings['profile_image'])): ?>
                                <img src="../uploads/<?php echo escape($settings['profile_image']); ?>" alt="Profil Resmi" class="admin-profile-preview">
                            <?php else: ?>
                                <div class="admin-profile-placeholder">Resim Yok</div>
                            <?php endif; ?>
                        </div>
                        <form action="index.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="update_profile_image">
                            
                            <div class="form-group">
                                <label for="profile_image">Yeni Profil Resmi Seç (JPG, PNG, WEBP)</label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn btn-secondary">Yükle</button>
                        </form>
                    </div>

                    <div class="card">
                        <h3>Favicon Güncelle</h3>
                        <div class="profile-preview-wrapper" style="display:flex; justify-content:center; align-items:center; min-height:80px; margin-bottom:1rem;">
                            <?php if (!empty($settings['site_favicon']) && file_exists('../uploads/' . $settings['site_favicon'])): ?>
                                <img src="../uploads/<?php echo escape($settings['site_favicon']); ?>" alt="Favicon" style="max-width: 48px; max-height: 48px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); background: var(--bg-secondary); padding: 8px;">
                            <?php else: ?>
                                <div class="admin-profile-placeholder" style="width: 48px; height: 48px; line-height: 48px;">Yok</div>
                            <?php endif; ?>
                        </div>
                        <form action="index.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="update_favicon">
                            
                            <div class="form-group">
                                <label for="site_favicon">Favicon Seç (ICO, PNG, SVG)</label>
                                <input type="file" id="site_favicon" name="site_favicon" accept=".ico,.png,.svg,image/png,image/x-icon,image/svg+xml" required>
                            </div>
                            <button type="submit" class="btn btn-secondary">Yükle</button>
                        </form>
                    </div>

                    <div class="card">
                        <h3>Giriş Bilgilerini Güncelle</h3>
                        <form action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="update_password">

                            <div class="form-group">
                                <label for="new_username">Kullanıcı Adı</label>
                                <input type="text" id="new_username" name="new_username" value="<?php echo escape($_SESSION['admin_username']); ?>" required autocomplete="username">
                            </div>

                            <div class="form-group">
                                <label for="old_password">Mevcut Şifre (Değişiklikleri kaydetmek için gereklidir)</label>
                                <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
                            </div>

                            <div class="form-group">
                                <label for="new_password">Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                                <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn btn-secondary">Bilgileri Güncelle</button>
                        </form>
                    </div>

                    <?php
                    $driver_name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $active_db_label = ($driver_name === 'mysql') ? 'MySQL (Sunucu Tabanlı)' : 'SQLite (Dosya Tabanlı)';
                    ?>
                    <div class="card">
                        <h3>Veritabanı Yapılandırması</h3>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
                            Aktif Bağlantı: <strong style="color: var(--accent);"><?php echo $active_db_label; ?></strong>
                        </p>
                        
                        <form action="index.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="update_db_config">
                            
                            <div class="form-group">
                                <label for="db_type">Veritabanı Türü</label>
                                <select id="db_type" name="db_type" onchange="toggleMysqlFields(this.value)" style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-primary); font-family: inherit;">
                                    <option value="sqlite" <?php echo $config['db_type'] === 'sqlite' ? 'selected' : ''; ?>>SQLite (Dosya Tabanlı)</option>
                                    <option value="mysql" <?php echo $config['db_type'] === 'mysql' ? 'selected' : ''; ?>>MySQL (Sunucu Tabanlı)</option>
                                </select>
                            </div>

                            <div id="mysql-fields" style="display: <?php echo $config['db_type'] === 'mysql' ? 'block' : 'none'; ?>; border-top: 1px dashed var(--border); padding-top: 15px; margin-top: 15px;">
                                <div class="form-group">
                                    <label for="mysql_host">MySQL Sunucu Adresi (Host)</label>
                                    <input type="text" id="mysql_host" name="mysql_host" value="<?php echo escape($config['mysql_host'] ?? 'localhost'); ?>" placeholder="örn: localhost veya IP adresi">
                                </div>

                                <div class="form-group">
                                    <label for="mysql_db">MySQL Veritabanı Adı (Database)</label>
                                    <input type="text" id="mysql_db" name="mysql_db" value="<?php echo escape($config['mysql_db'] ?? 'kisiselsite'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="mysql_user">MySQL Kullanıcı Adı (Username)</label>
                                    <input type="text" id="mysql_user" name="mysql_user" value="<?php echo escape($config['mysql_user'] ?? 'root'); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="mysql_pass">MySQL Şifresi (Password)</label>
                                    <input type="password" id="mysql_pass" name="mysql_pass" value="<?php echo escape($config['mysql_pass'] ?? ''); ?>" placeholder="Boş bırakmak için temizleyin">
                                </div>

                                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 15px;">
                                    <input type="checkbox" id="migrate_data" name="migrate_data" value="1" style="width: auto; margin: 0; cursor: pointer;">
                                    <label for="migrate_data" style="margin: 0; font-size: 0.85rem; color: var(--accent); cursor: pointer; user-select: none;">
                                        <strong>SQLite Verilerini MySQL'e Aktar</strong> (MySQL temizlenir ve SQLite verileri kopyalanır)
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-secondary" style="margin-top: 15px; width: 100%;">Yapılandırmayı Kaydet</button>
                        </form>
                    </div>

                    <script>
                    function toggleMysqlFields(val) {
                        const fields = document.getElementById('mysql-fields');
                        if (val === 'mysql') {
                            fields.style.display = 'block';
                        } else {
                            fields.style.display = 'none';
                        }
                    }
                    </script>

                    <div class="card">
                        <h3>Sistem Bakımı & Yedekleme</h3>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.2rem; line-height: 1.5;">Web sitenizin SQLite veritabanı yedeğini tek tıkla indirebilirsiniz. Dosyayı yedekleyerek verilerinizi güvende tutun.</p>
                        <a href="index.php?action=backup_db" class="btn btn-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; width: 100%;">
                            💾 Veritabanını Yedekle (.db)
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
