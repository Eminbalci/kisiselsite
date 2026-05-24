<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Force login
require_login();

$settings = get_settings($pdo);

$success_message = '';
$error_message = '';

// Edit Mode Variables
$edit_mode = false;
$edit_id = 0;
$edit_title = '';
$edit_title_en = '';
$edit_description = '';
$edit_description_en = '';
$edit_content = '';
$edit_content_en = '';
$edit_file = '';
$edit_link = '';
$edit_image = '';
$edit_slug = '';
$edit_category = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the post data was discarded because it exceeded the post_max_size limit
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        $error_message = "Yüklemeye çalıştığınız dosya sunucu limitlerini aşıyor (Maksimum limit: {$max_size}). Lütfen PHP ayarlarındaki post_max_size ve upload_max_filesize limitlerini artırın veya daha küçük bir dosya yükleyin.";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        
        if (!verify_csrf_token($csrf_token)) {
            $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
        } else {
            if ($action === 'add') {
            $title = trim($_POST['title']);
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $description = trim($_POST['description']);
            $description_en = trim($_POST['description_en'] ?? '');
            if (empty($description_en) && !empty($description)) $description_en = auto_translate($description, 'tr', 'en');
            $content = trim($_POST['content']);
            $content_en = trim($_POST['content_en'] ?? '');
            if (empty($content_en) && !empty($content)) $content_en = auto_translate($content, 'tr', 'en');
            $project_link = trim($_POST['project_link']);
            $slug = trim($_POST['slug']);
            $category = trim($_POST['category']);
            
            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }
            
            // Uniqueness check
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM portfolio WHERE slug = :slug");
            $check_stmt->execute(['slug' => $slug]);
            if ($check_stmt->fetch()['count'] > 0) {
                $slug = $slug . '-' . time();
            }
            
            if (empty($title) || empty($description)) {
                $error_message = 'Başlık ve açıklama alanları zorunludur.';
            } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                $error_message = 'Lütfen proje için bir görsel yükleyin.';
            } else {
                try {
                    // Upload image
                    $uploaded_file = handle_image_upload($_FILES['image']);
                    
                    // Upload file if any
                    $file_path = '';
                    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file_path = handle_file_upload($_FILES['project_file']);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO portfolio (title, title_en, description, description_en, content, content_en, file_path, image_path, project_link, slug, category) VALUES (:title, :title_en, :description, :description_en, :content, :content_en, :file_path, :image_path, :project_link, :slug, :category)");
                    $stmt->execute([
                        'title' => $title,
                        'title_en' => $title_en,
                        'description' => $description,
                        'description_en' => $description_en,
                        'content' => $content,
                        'content_en' => $content_en,
                        'file_path' => $file_path,
                        'image_path' => $uploaded_file,
                        'project_link' => $project_link,
                        'slug' => $slug,
                        'category' => $category
                    ]);
                    $success_message = 'Proje başarıyla eklendi.';
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $description = trim($_POST['description']);
            $description_en = trim($_POST['description_en'] ?? '');
            if (empty($description_en) && !empty($description)) $description_en = auto_translate($description, 'tr', 'en');
            $content = trim($_POST['content']);
            $content_en = trim($_POST['content_en'] ?? '');
            if (empty($content_en) && !empty($content)) $content_en = auto_translate($content, 'tr', 'en');
            $project_link = trim($_POST['project_link']);
            $slug = trim($_POST['slug']);
            $category = trim($_POST['category']);
            
            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }
            
            // Uniqueness check
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM portfolio WHERE slug = :slug AND id != :id");
            $check_stmt->execute(['slug' => $slug, 'id' => $id]);
            if ($check_stmt->fetch()['count'] > 0) {
                $slug = $slug . '-' . time();
            }
            
            if (empty($title) || empty($description)) {
                $error_message = 'Başlık ve açıklama alanları zorunludur.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT image_path, file_path FROM portfolio WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $current_project = $stmt->fetch();
                    
                    if (!$current_project) {
                        throw new Exception("Proje bulunamadı.");
                    }
                    
                    $image_path = $current_project['image_path'];
                    $file_path = $current_project['file_path'];
                    
                    // Check if new image is uploaded
                    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $new_image = handle_image_upload($_FILES['image']);
                        $old_image_path = '../uploads/' . $image_path;
                        if (file_exists($old_image_path)) {
                            @unlink($old_image_path);
                        }
                        $image_path = $new_image;
                    }
                    
                    // Check if new file is uploaded
                    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $new_file = handle_file_upload($_FILES['project_file']);
                        if (!empty($file_path)) {
                            $old_file_path = '../uploads/' . $file_path;
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                        $file_path = $new_file;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE portfolio SET title = :title, title_en = :title_en, description = :description, description_en = :description_en, content = :content, content_en = :content_en, file_path = :file_path, image_path = :image_path, project_link = :project_link, slug = :slug, category = :category WHERE id = :id");
                    $stmt->execute([
                        'title' => $title,
                        'title_en' => $title_en,
                        'description' => $description,
                        'description_en' => $description_en,
                        'content' => $content,
                        'content_en' => $content_en,
                        'file_path' => $file_path,
                        'image_path' => $image_path,
                        'project_link' => $project_link,
                        'slug' => $slug,
                        'category' => $category,
                        'id' => $id
                    ]);
                    
                    $success_message = 'Proje başarıyla güncellendi.';
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                // Fetch image and file to delete from disk
                $stmt = $pdo->prepare("SELECT image_path, file_path FROM portfolio WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $project = $stmt->fetch();
                
                if ($project) {
                    $image_path = '../uploads/' . $project['image_path'];
                    if (file_exists($image_path)) {
                        @unlink($image_path);
                    }
                    
                    if (!empty($project['file_path'])) {
                        $old_file = '../uploads/' . $project['file_path'];
                        if (file_exists($old_file)) {
                            @unlink($old_file);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $success_message = 'Proje başarıyla silindi.';
                } else {
                    $error_message = 'Proje bulunamadı.';
                }
            } catch (PDOException $e) {
                $error_message = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}
}

// Check if we are loading an item for edit mode via GET
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $project = $stmt->fetch();
    
    if ($project) {
        $edit_mode = true;
        $edit_title = $project['title'];
        $edit_title_en = $project['title_en'] ?? '';
        $edit_description = $project['description'];
        $edit_description_en = $project['description_en'] ?? '';
        $edit_content = $project['content'];
        $edit_content_en = $project['content_en'] ?? '';
        $edit_file = $project['file_path'];
        $edit_link = $project['project_link'];
        $edit_image = $project['image_path'];
        $edit_slug = $project['slug'];
        $edit_category = $project['category'] ?? '';
    }
}

// Get all portfolio items
$stmt = $pdo->query("SELECT * FROM portfolio ORDER BY date_added DESC");
$portfolio_items = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Portföy</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <!-- EasyMDE Markdown Editor -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
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
                <a href="index.php" class="nav-item">
                    <span class="icon">⚙️</span> Genel Ayarlar
                </a>
                <a href="skills.php" class="nav-item">
                    <span class="icon">📊</span> Yetenekler
                </a>
                <a href="portfolio.php" class="nav-item active">
                    <span class="icon">💼</span> Portföy
                </a>
                <a href="blog.php" class="nav-item">
                    <span class="icon">📝</span> Blog
                </a>
                <a href="timeline.php" class="nav-item">
                    <span class="icon">⏳</span> Zaman Çizelgesi
                </a>
                <a href="certificates.php" class="nav-item">
                    <span class="icon">🏆</span> Sertifikalar
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="icon">✉️</span> Gelen Kutusu
                </a>
                <div class="nav-divider"></div>
                <a href="../index.php" target="_blank" class="nav-item">
                    <span class="icon">🌐</span> Siteyi Görüntüle
                </a>
            </nav>
        </aside>

        <main class="admin-main">
            <h2>Portföy Yönetimi</h2>
            <p class="main-subtitle">Sitenizde sergilenecek projelerinizi buradan yönetebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <!-- Portfolio Form Card -->
                <div class="card">
                    <h3><?php echo $edit_mode ? 'Projeyi Düzenle' : 'Yeni Proje Ekle'; ?></h3>
                    <form action="portfolio.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="title">Proje Adı (TR)</label>
                            <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>" placeholder="Örn: E-Ticaret Arayüz Tasarımı" required>
                        </div>

                        <div class="form-group">
                            <label for="title_en">Proje Adı (EN)</label>
                            <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>" placeholder="Örn: E-Commerce UI Design">
                        </div>

                        <div class="form-group">
                            <label for="project_link">Proje Linki (Opsiyonel)</label>
                            <input type="url" id="project_link" name="project_link" value="<?php echo escape($edit_link); ?>" placeholder="Örn: https://github.com/kullanici/proje">
                        </div>

                        <div class="form-group">
                            <label for="slug">Proje URL Sloganı / Link İsmi (Boş bırakırsanız başlığa göre otomatik oluşturulur)</label>
                            <input type="text" id="slug" name="slug" value="<?php echo escape($edit_slug); ?>" placeholder="Örn: yemek-siparis-sistemi">
                        </div>

                        <div class="form-group">
                            <label for="category">Proje Kategorisi (Örn: Web, Mobil, Gömülü Sistemler, Mekatronik vb.)</label>
                            <input type="text" id="category" name="category" value="<?php echo escape($edit_category); ?>" placeholder="Örn: Web">
                        </div>

                        <div class="form-group">
                            <label for="description">Proje Kısa Açıklaması (TR)</label>
                            <textarea id="description" name="description" rows="3" placeholder="Projenin amacını, kullanılan teknolojileri vb. kısaca özetleyin..." required><?php echo escape($edit_description); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="description_en">Proje Kısa Açıklaması (EN)</label>
                            <textarea id="description_en" name="description_en" rows="3"><?php echo escape($edit_description_en); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="content">Proje Detaylı İçeriği (TR)</label>
                            <textarea id="content" name="content" rows="8" placeholder="Proje detaylarını, kurulum rehberini, kullanım kılavuzunu veya ek açıklamaları buraya yazın..."><?php echo escape($edit_content); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="content_en">Proje Detaylı İçeriği (EN)</label>
                            <textarea id="content_en" name="content_en" rows="8"><?php echo escape($edit_content_en); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image">Proje Görseli <?php echo $edit_mode ? '(Değiştirmek istemiyorsanız boş bırakın)' : ''; ?></label>
                            <?php if ($edit_mode && !empty($edit_image)): ?>
                                <div class="thumbnail-preview mb-1">
                                    <img src="../uploads/<?php echo escape($edit_image); ?>" alt="Önizleme">
                                </div>
                            <?php endif; ?>
                            <input type="file" id="image" name="image" accept="image/*" <?php echo $edit_mode ? '' : 'required'; ?>>
                        </div>

                        <div class="form-group">
                            <label for="project_file">İndirilebilir Proje Dosyası (İsteğe Bağlı - ZIP, PDF, RAR, TXT vb.)</label>
                            <?php if ($edit_mode && !empty($edit_file)): ?>
                                <p class="mb-1" style="font-size: 0.85rem; color: var(--text-secondary);">Mevcut dosya: <a href="../uploads/<?php echo escape($edit_file); ?>" target="_blank" style="color:var(--accent); font-weight:600; text-decoration:none;"><?php echo escape($edit_file); ?></a></p>
                            <?php endif; ?>
                            <input type="file" id="project_file" name="project_file">
                        </div>

                        <div class="flex gap-1 mt-1">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Kaydet'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="portfolio.php" class="btn btn-secondary">İptal Et</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Projects List Card -->
                <div class="card">
                    <h3>Mevcut Projeler</h3>
                    <?php if (count($portfolio_items) === 0): ?>
                        <p class="empty-text">Henüz eklenmiş bir proje yok.</p>
                    <?php else: ?>
                        <div class="portfolio-list-admin">
                            <?php foreach ($portfolio_items as $item): ?>
                                <div class="portfolio-item-admin">
                                    <?php if (!empty($item['image_path']) && file_exists('../uploads/' . $item['image_path'])): ?>
                                        <img src="../uploads/<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['title']); ?>" class="portfolio-thumb">
                                    <?php else: ?>
                                        <div class="portfolio-thumb-placeholder">
                                            <span><?php echo escape(mb_strtoupper(mb_substr($item['title'], 0, 1))); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="portfolio-info">
                                        <h4><?php echo escape($item['title']); ?></h4>
                                        <p class="portfolio-desc-brief"><?php echo mb_strimwidth(escape($item['description']), 0, 80, '...'); ?></p>
                                        <div class="actions">
                                            <a href="portfolio.php?edit=<?php echo $item['id']; ?>" class="btn btn-xs btn-primary-outline">Düzenle</a>
                                            <form action="portfolio.php" method="POST" onsubmit="return confirm('Bu projeyi silmek istediğinize emin misiniz? Sildiğinizde resmi de kalıcı olarak silinecektir.');" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-danger-outline">Sil</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        const easyMDE = new EasyMDE({ 
            element: document.getElementById('content'),
            forceSync: true,
            spellChecker: false,
            maxHeight: "300px"
        });
    </script>
</body>
</html>
