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
$edit_name = '';
$edit_title = '';
$edit_title_en = '';
$edit_company = '';
$edit_contact_info = '';
$edit_display_order = 0;

$token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
    } else {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name']);
            $title = trim($_POST['title'] ?? '');
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $company = trim($_POST['company'] ?? '');
            $contact_info = trim($_POST['contact_info'] ?? '');
            $display_order = intval($_POST['display_order']);
            
            if (empty($name)) {
                $error_message = 'Ad Soyad alanı zorunludur.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO `references` (name, title, title_en, company, contact_info, display_order) VALUES (:name, :title, :title_en, :company, :contact_info, :display_order)");
                        $stmt->execute([
                            'name' => $name,
                            'title' => $title,
                            'title_en' => $title_en,
                            'company' => $company,
                            'contact_info' => $contact_info,
                            'display_order' => $display_order
                        ]);
                        $success_message = 'Referans başarıyla eklendi.';
                    } else {
                        $id = intval($_POST['id']);
                        $stmt = $pdo->prepare("UPDATE `references` SET name = :name, title = :title, title_en = :title_en, company = :company, contact_info = :contact_info, display_order = :display_order WHERE id = :id");
                        $stmt->execute([
                            'name' => $name,
                            'title' => $title,
                            'title_en' => $title_en,
                            'company' => $company,
                            'contact_info' => $contact_info,
                            'display_order' => $display_order,
                            'id' => $id
                        ]);
                        $success_message = 'Referans başarıyla güncellendi.';
                    }
                } catch (Exception $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM `references` WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Referans başarıyla silindi.';
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        }
    }
}

// Check if edit mode
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM `references` WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $ref = $stmt->fetch();
    
    if ($ref) {
        $edit_mode = true;
        $edit_name = $ref['name'];
        $edit_title = $ref['title'];
        $edit_title_en = $ref['title_en'] ?? '';
        $edit_company = $ref['company'];
        $edit_contact_info = $ref['contact_info'];
        $edit_display_order = $ref['display_order'];
    }
}

// Get all items
$stmt = $pdo->query("SELECT * FROM `references` ORDER BY display_order ASC, id DESC");
$references = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referanslar Yönetimi - Admin Paneli</title>
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
            <span>Hoş geldiniz, <strong><?php echo escape($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
            <a href="logout.php" class="btn btn-sm btn-danger">Çıkış Yap</a>
        </div>
    </header>

    <div class="admin-container">
        <!-- Sidebar Navigation -->
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
                <a href="comments.php" class="nav-item">
                    <span class="icon">💬</span> Yorumlar
                </a>
                <a href="timeline.php" class="nav-item">
                    <span class="icon">⏳</span> Zaman Çizelgesi
                </a>
                <a href="certificates.php" class="nav-item">
                    <span class="icon">🏆</span> Sertifikalar
                </a>
                <a href="references.php" class="nav-item active">
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
            <h2>Referanslar (Sadece CV'de görünür)</h2>
            <p class="main-subtitle">Buraya ekleyeceğiniz referanslar sitenin ana sayfasında görüntülenmez, sadece CV (PDF/Yazdırılabilir) sayfasında çıkar.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <div class="card">
                    <h3><?php echo $edit_mode ? 'Referansı Düzenle' : 'Yeni Referans Ekle'; ?></h3>
                    <form action="references.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Ad Soyad</label>
                            <input type="text" id="name" name="name" value="<?php echo escape($edit_name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Ünvan / Meslek (TR)</label>
                            <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="title_en">Ünvan / Meslek (EN)</label>
                            <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company">Kurum / Şirket</label>
                            <input type="text" id="company" name="company" value="<?php echo escape($edit_company); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_info">İletişim Bilgisi (E-posta veya Tel)</label>
                            <input type="text" id="contact_info" name="contact_info" value="<?php echo escape($edit_contact_info); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="display_order">Sıralama</label>
                            <input type="number" id="display_order" name="display_order" value="<?php echo escape($edit_display_order); ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Ekle'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="references.php" class="btn btn-outline">İptal</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="card" style="grid-column: span 2;">
                    <h3>Mevcut Referanslar</h3>
                    <?php if (count($references) > 0): ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Sıra</th>
                                        <th>Ad Soyad</th>
                                        <th>Ünvan & Şirket</th>
                                        <th>İletişim</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($references as $ref): ?>
                                        <tr>
                                            <td><?php echo escape($ref['display_order']); ?></td>
                                            <td><strong><?php echo escape($ref['name']); ?></strong></td>
                                            <td>
                                                <?php echo escape($ref['title']); ?>
                                                <?php if(!empty($ref['company'])) echo '<br><small class="text-muted">' . escape($ref['company']) . '</small>'; ?>
                                            </td>
                                            <td><?php echo escape($ref['contact_info']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="references.php?edit=<?php echo $ref['id']; ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                                    
                                                    <form action="references.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu referansı silmek istediğinize emin misiniz?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $ref['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Henüz hiç referans eklenmemiş.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
