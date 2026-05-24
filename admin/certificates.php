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
$edit_issuer = '';
$edit_date_issued = '';
$edit_link = '';
$edit_image_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for upload max size issue
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
                $issuer = trim($_POST['issuer']);
                $date_issued = trim($_POST['date_issued']);
                $link = trim($_POST['link'] ?? '');
                
                if (empty($title) || empty($issuer)) {
                    $error_message = 'Sertifika adı ve veren kurum zorunludur.';
                } else {
                    try {
                        if ($action === 'add') {
                            $image_path = '';
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $image_path = handle_image_upload($_FILES['image']);
                            }
                            
                            $stmt = $pdo->prepare("INSERT INTO certificates (title, title_en, issuer, date_issued, image_path, link) VALUES (:title, :title_en, :issuer, :date_issued, :image_path, :link)");
                            $stmt->execute([
                                'title' => $title,
                                'title_en' => $title_en,
                                'issuer' => $issuer,
                                'date_issued' => $date_issued,
                                'image_path' => $image_path,
                                'link' => $link
                            ]);
                            $success_message = 'Sertifika başarıyla eklendi.';
                        } else {
                            $id = intval($_POST['id']);
                            
                            $stmt = $pdo->prepare("SELECT image_path FROM certificates WHERE id = :id");
                            $stmt->execute(['id' => $id]);
                            $cert = $stmt->fetch();
                            $image_path = $cert['image_path'];
                            
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $new_image = handle_image_upload($_FILES['image']);
                                if (!empty($image_path)) {
                                    $old_image = '../uploads/' . $image_path;
                                    if (file_exists($old_image)) @unlink($old_image);
                                }
                                $image_path = $new_image;
                            }
                            
                            $stmt = $pdo->prepare("UPDATE certificates SET title = :title, title_en = :title_en, issuer = :issuer, date_issued = :date_issued, image_path = :image_path, link = :link WHERE id = :id");
                            $stmt->execute([
                                'title' => $title,
                                'title_en' => $title_en,
                                'issuer' => $issuer,
                                'date_issued' => $date_issued,
                                'image_path' => $image_path,
                                'link' => $link,
                                'id' => $id
                            ]);
                            $success_message = 'Sertifika başarıyla güncellendi.';
                        }
                    } catch (Exception $e) {
                        $error_message = 'Hata: ' . $e->getMessage();
                    }
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['id']);
                try {
                    $stmt = $pdo->prepare("SELECT image_path FROM certificates WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $cert = $stmt->fetch();
                    
                    if ($cert && !empty($cert['image_path'])) {
                        $img = '../uploads/' . $cert['image_path'];
                        if (file_exists($img)) @unlink($img);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $success_message = 'Sertifika başarıyla silindi.';
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
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $cert = $stmt->fetch();
    
    if ($cert) {
        $edit_mode = true;
        $edit_title = $cert['title'];
        $edit_title_en = $cert['title_en'] ?? '';
        $edit_issuer = $cert['issuer'];
        $edit_date_issued = $cert['date_issued'];
        $edit_link = $cert['link'];
        $edit_image_path = $cert['image_path'];
    }
}

// Get all items
$stmt = $pdo->query("SELECT * FROM certificates ORDER BY id DESC");
$certificates = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Sertifikalar</title>
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
                <a href="blog.php" class="nav-item">
                    <span class="icon">📝</span> Blog
                </a>
                <a href="timeline.php" class="nav-item">
                    <span class="icon">⏳</span> Zaman Çizelgesi
                </a>
                <a href="certificates.php" class="nav-item active">
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
            <h2>Sertifikalar Yönetimi</h2>
            <p class="main-subtitle">Sitenizde sergilenecek sertifikalarınızı buradan yönetebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <div class="card">
                    <h3><?php echo $edit_mode ? 'Sertifika Düzenle' : 'Yeni Sertifika Ekle'; ?></h3>
                    <form action="certificates.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="title">Sertifika Adı (TR)</label>
                            <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="title_en">Sertifika Adı (EN)</label>
                            <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>">
                        </div>

                        <div class="form-group">
                            <label for="issuer">Veren Kurum</label>
                            <input type="text" id="issuer" name="issuer" value="<?php echo escape($edit_issuer); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="date_issued">Tarih</label>
                            <input type="text" id="date_issued" name="date_issued" value="<?php echo escape($edit_date_issued); ?>" placeholder="Örn: Ekim 2023">
                        </div>

                        <div class="form-group">
                            <label for="link">Doğrulama / Detay Linki (Opsiyonel)</label>
                            <input type="url" id="link" name="link" value="<?php echo escape($edit_link); ?>">
                        </div>

                        <div class="form-group">
                            <label for="image">Sertifika Görseli (Opsiyonel)</label>
                            <?php if ($edit_mode && !empty($edit_image_path)): ?>
                                <div class="thumbnail-preview mb-1">
                                    <img src="../uploads/<?php echo escape($edit_image_path); ?>" alt="Önizleme" style="max-width: 100px; height: auto;">
                                </div>
                            <?php endif; ?>
                            <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/webp">
                        </div>

                        <div class="flex gap-1">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Ekle'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="certificates.php" class="btn btn-secondary">İptal Et</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Mevcut Sertifikalar</h3>
                    </div>

                    <?php if (count($certificates) === 0): ?>
                        <div class="alert alert-info">Henüz hiç sertifika eklenmemiş.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Görsel</th>
                                        <th>Sertifika</th>
                                        <th>Kurum</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certificates as $cert): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($cert['image_path'])): ?>
                                                    <img src="../uploads/<?php echo escape($cert['image_path']); ?>" alt="Görsel" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">Yok</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo escape($cert['title']); ?></div>
                                            </td>
                                            <td><?php echo escape($cert['issuer']); ?></td>
                                            <td><?php echo escape($cert['date_issued']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="certificates.php?edit=<?php echo $cert['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">✏️</a>
                                                    
                                                    <form action="certificates.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu sertifikayı silmek istediğinize emin misiniz?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $cert['id']; ?>">
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
