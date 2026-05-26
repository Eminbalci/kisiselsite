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
$edit_type = 'edu';
$edit_title = '';
$edit_title_en = '';
$edit_institution = '';
$edit_institution_en = '';
$edit_date_range = '';
$edit_description = '';
$edit_description_en = '';
$edit_display_order = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
    } else {
        if ($action === 'add' || $action === 'edit') {
            $type = trim($_POST['type']);
            $title = trim($_POST['title']);
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $institution = trim($_POST['institution']);
            $institution_en = trim($_POST['institution_en'] ?? '');
            if (empty($institution_en) && !empty($institution)) $institution_en = auto_translate($institution, 'tr', 'en');
            $date_range = trim($_POST['date_range']);
            $description = trim($_POST['description'] ?? '');
            $description_en = trim($_POST['description_en'] ?? '');
            if (empty($description_en) && !empty($description)) $description_en = auto_translate($description, 'tr', 'en');
            $display_order = intval($_POST['display_order']);
            
            if (empty($title) || empty($institution) || empty($date_range)) {
                $error_message = 'Başlık, kurum ve tarih alanları zorunludur.';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO timeline (type, title, title_en, institution, institution_en, date_range, description, description_en, display_order) VALUES (:type, :title, :title_en, :institution, :institution_en, :date_range, :description, :description_en, :display_order)");
                        $stmt->execute([
                            'type' => $type,
                            'title' => $title,
                            'title_en' => $title_en,
                            'institution' => $institution,
                            'institution_en' => $institution_en,
                            'date_range' => $date_range,
                            'description' => $description,
                            'description_en' => $description_en,
                            'display_order' => $display_order
                        ]);
                        $success_message = 'Zaman çizelgesi öğesi başarıyla eklendi.';
                    } else {
                        $id = intval($_POST['id']);
                        $stmt = $pdo->prepare("UPDATE timeline SET type = :type, title = :title, title_en = :title_en, institution = :institution, institution_en = :institution_en, date_range = :date_range, description = :description, description_en = :description_en, display_order = :display_order WHERE id = :id");
                        $stmt->execute([
                            'type' => $type,
                            'title' => $title,
                            'title_en' => $title_en,
                            'institution' => $institution,
                            'institution_en' => $institution_en,
                            'date_range' => $date_range,
                            'description' => $description,
                            'description_en' => $description_en,
                            'display_order' => $display_order,
                            'id' => $id
                        ]);
                        $success_message = 'Zaman çizelgesi öğesi başarıyla güncellendi.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM timeline WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Zaman çizelgesi öğesi başarıyla silindi.';
            } catch (PDOException $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        }
    }
}

// Check if we are loading an item for edit mode via GET
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM timeline WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $edit_mode = true;
        $edit_type = $item['type'];
        $edit_title = $item['title'];
        $edit_title_en = $item['title_en'] ?? '';
        $edit_institution = $item['institution'];
        $edit_institution_en = $item['institution_en'] ?? '';
        $edit_date_range = $item['date_range'];
        $edit_description = $item['description'];
        $edit_description_en = $item['description_en'] ?? '';
        $edit_display_order = $item['display_order'];
    }
}

// Get all items
$stmt = $pdo->query("SELECT * FROM timeline ORDER BY type, display_order ASC, id DESC");
$timeline_items = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Zaman Çizelgesi</title>
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
                <a href="comments.php" class="nav-item">
                    <span class="icon">💬</span> Yorumlar
                </a>
                <a href="timeline.php" class="nav-item active">
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
            <h2>Zaman Çizelgesi (Deneyim & Eğitim)</h2>
            <p class="main-subtitle">Sitenizde listelenecek eğitim ve iş deneyimlerini buradan düzenleyebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <div class="card">
                    <h3><?php echo $edit_mode ? 'Öğeyi Düzenle' : 'Yeni Öğe Ekle'; ?></h3>
                    <form action="timeline.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="type">Tür</label>
                            <select id="type" name="type" required>
                                <option value="exp" <?php echo $edit_type === 'exp' ? 'selected' : ''; ?>>İş Deneyimi (Experience)</option>
                                <option value="edu" <?php echo $edit_type === 'edu' ? 'selected' : ''; ?>>Eğitim (Education)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="title">Başlık / Pozisyon (TR)</label>
                            <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="title_en">Başlık / Pozisyon (EN)</label>
                            <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>">
                        </div>

                        <div class="form-group">
                            <label for="institution">Kurum / Şirket Adı (TR)</label>
                            <input type="text" id="institution" name="institution" value="<?php echo escape($edit_institution); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="institution_en">Kurum / Şirket Adı (EN)</label>
                            <input type="text" id="institution_en" name="institution_en" value="<?php echo escape($edit_institution_en); ?>">
                        </div>

                        <div class="form-group">
                            <label for="date_range">Tarih Aralığı</label>
                            <input type="text" id="date_range" name="date_range" value="<?php echo escape($edit_date_range); ?>" placeholder="Örn: 2021 - Devam Ediyor veya 2018 - 2022" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Açıklama (TR)</label>
                            <textarea id="description" name="description" rows="3"><?php echo escape($edit_description); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="description_en">Açıklama (EN)</label>
                            <textarea id="description_en" name="description_en" rows="3"><?php echo escape($edit_description_en); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="display_order">Sıralama (Küçük sayı önce gösterilir)</label>
                            <input type="number" id="display_order" name="display_order" value="<?php echo escape($edit_display_order); ?>" required>
                        </div>

                        <div class="flex gap-1">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Ekle'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="timeline.php" class="btn btn-secondary">İptal Et</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Mevcut Öğeler</h3>
                    </div>

                    <?php if (count($timeline_items) === 0): ?>
                        <div class="alert alert-info">Henüz hiç zaman çizelgesi öğesi eklenmemiş.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Sıra</th>
                                        <th>Tür</th>
                                        <th>Başlık</th>
                                        <th>Kurum</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeline_items as $item): ?>
                                        <tr>
                                            <td><?php echo escape($item['display_order']); ?></td>
                                            <td>
                                                <?php if ($item['type'] === 'edu'): ?>
                                                    <span class="badge" style="background: var(--primary); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">Eğitim</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: var(--secondary); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">Deneyim</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo escape($item['title']); ?></div>
                                            </td>
                                            <td><?php echo escape($item['institution']); ?></td>
                                            <td><?php echo escape($item['date_range']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="timeline.php?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">✏️</a>
                                                    
                                                    <form action="timeline.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu öğeyi silmek istediğinize emin misiniz?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
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
