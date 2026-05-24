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
$edit_slug = '';
$edit_content = '';
$edit_content_en = '';
$edit_image_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        $error_message = "Yüklemeye çalıştığınız dosya sunucu limitlerini aşıyor (Maksimum limit: {$max_size}).";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        
        if (!verify_csrf_token($csrf_token)) {
            $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
        } else {
            if ($action === 'add' || $action === 'edit') {
                $title = trim($_POST['title']);
                $title_en = trim($_POST['title_en'] ?? '');
                if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
                $slug = trim($_POST['slug']);
                $content = trim($_POST['content']);
                $content_en = trim($_POST['content_en'] ?? '');
                if (empty($content_en) && !empty($content)) $content_en = auto_translate($content, 'tr', 'en');
                
                if (empty($slug)) {
                    $slug = slugify($title);
                } else {
                    $slug = slugify($slug);
                }
                
                if (empty($title) || empty($content)) {
                    $error_message = 'Başlık ve içerik alanları zorunludur.';
                } else {
                    try {
                        if ($action === 'add') {
                            // Uniqueness check
                            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog WHERE slug = :slug");
                            $check_stmt->execute(['slug' => $slug]);
                            if ($check_stmt->fetch()['count'] > 0) {
                                $slug = $slug . '-' . time();
                            }
                            
                            $image_path = '';
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $image_path = handle_image_upload($_FILES['image']);
                            }
                            
                            $stmt = $pdo->prepare("INSERT INTO blog (title, title_en, slug, content, content_en, image_path) VALUES (:title, :title_en, :slug, :content, :content_en, :image_path)");
                            $stmt->execute([
                                'title' => $title,
                                'title_en' => $title_en,
                                'slug' => $slug,
                                'content' => $content,
                                'content_en' => $content_en,
                                'image_path' => $image_path
                            ]);
                            $success_message = 'Blog yazısı başarıyla eklendi.';
                        } else {
                            $id = intval($_POST['id']);
                            
                            // Uniqueness check
                            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog WHERE slug = :slug AND id != :id");
                            $check_stmt->execute(['slug' => $slug, 'id' => $id]);
                            if ($check_stmt->fetch()['count'] > 0) {
                                $slug = $slug . '-' . time();
                            }
                            
                            $stmt = $pdo->prepare("SELECT image_path FROM blog WHERE id = :id");
                            $stmt->execute(['id' => $id]);
                            $blog = $stmt->fetch();
                            $image_path = $blog['image_path'];
                            
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $new_image = handle_image_upload($_FILES['image']);
                                if (!empty($image_path)) {
                                    $old_image = '../uploads/' . $image_path;
                                    if (file_exists($old_image)) @unlink($old_image);
                                }
                                $image_path = $new_image;
                            }
                            
                            $stmt = $pdo->prepare("UPDATE blog SET title = :title, title_en = :title_en, slug = :slug, content = :content, content_en = :content_en, image_path = :image_path WHERE id = :id");
                            $stmt->execute([
                                'title' => $title,
                                'title_en' => $title_en,
                                'slug' => $slug,
                                'content' => $content,
                                'content_en' => $content_en,
                                'image_path' => $image_path,
                                'id' => $id
                            ]);
                            $success_message = 'Blog yazısı başarıyla güncellendi.';
                        }
                    } catch (Exception $e) {
                        $error_message = 'Hata: ' . $e->getMessage();
                    }
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['id']);
                try {
                    $stmt = $pdo->prepare("SELECT image_path FROM blog WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $blog = $stmt->fetch();
                    
                    if ($blog && !empty($blog['image_path'])) {
                        $img = '../uploads/' . $blog['image_path'];
                        if (file_exists($img)) @unlink($img);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM blog WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $success_message = 'Blog yazısı başarıyla silindi.';
                } catch (Exception $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        }
    }
}

// Check if edit mode
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM blog WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $blog = $stmt->fetch();
    
    if ($blog) {
        $edit_mode = true;
        $edit_title = $blog['title'];
        $edit_title_en = $blog['title_en'] ?? '';
        $edit_slug = $blog['slug'];
        $edit_content = $blog['content'];
        $edit_content_en = $blog['content_en'] ?? '';
        $edit_image_path = $blog['image_path'];
    }
}

// Get all items
$stmt = $pdo->query("SELECT * FROM blog ORDER BY date_added DESC");
$blog_posts = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Blog</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
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
                <a href="portfolio.php" class="nav-item">
                    <span class="icon">💼</span> Portföy
                </a>
                <a href="blog.php" class="nav-item active">
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
            <h2>Blog Yönetimi</h2>
            <p class="main-subtitle">Sitenizde yayınlanacak blog yazılarını buradan yönetebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <div class="card" style="grid-column: span 3;">
                    <h3><?php echo $edit_mode ? 'Yazıyı Düzenle' : 'Yeni Yazı Ekle'; ?></h3>
                    <form action="blog.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="title">Yazı Başlığı (TR)</label>
                                <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="title_en">Yazı Başlığı (EN)</label>
                                <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="slug">URL Slug (Boş bırakırsanız otomatik oluşur)</label>
                            <input type="text" id="slug" name="slug" value="<?php echo escape($edit_slug); ?>">
                        </div>

                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="content">İçerik (TR)</label>
                                <textarea id="content" name="content" rows="12" required><?php echo escape($edit_content); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="content_en">İçerik (EN)</label>
                                <textarea id="content_en" name="content_en" rows="12"><?php echo escape($edit_content_en); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="image">Kapak Görseli (Opsiyonel)</label>
                            <?php if ($edit_mode && !empty($edit_image_path)): ?>
                                <div class="thumbnail-preview mb-1">
                                    <img src="../uploads/<?php echo escape($edit_image_path); ?>" alt="Önizleme" style="max-width: 150px; height: auto;">
                                </div>
                            <?php endif; ?>
                            <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/webp">
                        </div>

                        <div class="flex gap-1 mt-2">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Ekle'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="blog.php" class="btn btn-secondary">İptal Et</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card" style="grid-column: span 3;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Yayınlanan Yazılar</h3>
                    </div>

                    <?php if (count($blog_posts) === 0): ?>
                        <div class="alert alert-info">Henüz hiç blog yazısı eklenmemiş.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Görsel</th>
                                        <th>Başlık</th>
                                        <th>Görüntülenme</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blog_posts as $post): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($post['image_path'])): ?>
                                                    <img src="../uploads/<?php echo escape($post['image_path']); ?>" alt="Görsel" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">Yok</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo escape($post['title']); ?></div>
                                            </td>
                                            <td><?php echo (int)$post['views']; ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($post['date_added'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="blog.php?edit=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">✏️</a>
                                                    
                                                    <form action="blog.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu yazıyı silmek istediğinize emin misiniz?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Sil">🗑️</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
