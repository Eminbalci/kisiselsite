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
$edit_name_en = '';
$edit_percentage = 0;
$edit_category = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
    } else {
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $name_en = trim($_POST['name_en'] ?? '');
            if (empty($name_en) && !empty($name)) $name_en = auto_translate($name, 'tr', 'en');
            $percentage = intval($_POST['percentage']);
            $category = trim($_POST['category']);
            
            if (empty($name) || empty($category) || $_POST['percentage'] === '') {
                $error_message = 'Tüm alanları doldurmak zorunludur.';
            } elseif ($percentage < 0 || $percentage > 100) {
                $error_message = 'Yüzde değeri 0 ile 100 arasında olmalıdır.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO skills (name, name_en, percentage, category) VALUES (:name, :name_en, :percentage, :category)");
                    $stmt->execute([
                        'name' => $name,
                        'name_en' => $name_en,
                        'percentage' => $percentage,
                        'category' => $category
                    ]);
                    $success_message = 'Yetenek başarıyla eklendi.';
                } catch (PDOException $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $name_en = trim($_POST['name_en'] ?? '');
            if (empty($name_en) && !empty($name)) $name_en = auto_translate($name, 'tr', 'en');
            $percentage = intval($_POST['percentage']);
            $category = trim($_POST['category']);
            
            if (empty($name) || empty($category) || $_POST['percentage'] === '') {
                $error_message = 'Tüm alanları doldurmak zorunludur.';
            } elseif ($percentage < 0 || $percentage > 100) {
                $error_message = 'Yüzde değeri 0 ile 100 arasında olmalıdır.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE skills SET name = :name, name_en = :name_en, percentage = :percentage, category = :category WHERE id = :id");
                    $stmt->execute([
                        'name' => $name,
                        'name_en' => $name_en,
                        'percentage' => $percentage,
                        'category' => $category,
                        'id' => $id
                    ]);
                    $success_message = 'Yetenek başarıyla güncellendi.';
                } catch (PDOException $e) {
                    $error_message = 'Hata: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM skills WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Yetenek başarıyla silindi.';
            } catch (PDOException $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        } elseif ($action === 'add_category') {
            $cat_name = trim($_POST['category_name']);
            if (empty($cat_name)) {
                $error_message = 'Kategori adı boş olamaz.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO skill_categories (name) VALUES (:name)");
                    $stmt->execute(['name' => $cat_name]);
                    $success_message = 'Kategori başarıyla eklendi.';
                } catch (PDOException $e) {
                    $error_message = 'Kategori eklenemedi (zaten mevcut olabilir).';
                }
            }
        } elseif ($action === 'delete_category') {
            $cat_id = intval($_POST['category_id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM skill_categories WHERE id = :id");
                $stmt->execute(['id' => $cat_id]);
                $success_message = 'Kategori başarıyla silindi.';
            } catch (PDOException $e) {
                $error_message = 'Kategori silinirken hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Check if we are loading an item for edit mode via GET
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $skill = $stmt->fetch();
    
    if ($skill) {
        $edit_mode = true;
        $edit_name = $skill['name'];
        $edit_name_en = $skill['name_en'] ?? '';
        $edit_percentage = $skill['percentage'];
        $edit_category = $skill['category'];
    }
}

// Get all skills
$stmt = $pdo->query("SELECT * FROM skills ORDER BY category, name ASC");
$skills = $stmt->fetchAll();

// Fetch all categories from skill_categories table
$stmt = $pdo->query("SELECT * FROM skill_categories ORDER BY name ASC");
$skill_categories = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Yetenekler</title>
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
                <a href="index.php" class="nav-item">
                    <span class="icon">⚙️</span> Genel Ayarlar
                </a>
                <a href="skills.php" class="nav-item active">
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
            <h2>Yetenek Yönetimi</h2>
            <p class="main-subtitle">Sitenizde listelenecek yetenekleri ve seviyelerini buradan düzenleyebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-2">
                <!-- Left Column -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Add/Edit Skill Card -->
                    <div class="card" style="margin: 0;">
                        <h3><?php echo $edit_mode ? 'Yetenek Düzenle' : 'Yeni Yetenek Ekle'; ?></h3>
                        <form action="skills.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                            
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="name">Yetenek Adı (TR)</label>
                                <input type="text" id="name" name="name" value="<?php echo escape($edit_name); ?>" placeholder="Örn: PHP, Figma, React" required>
                            </div>

                            <div class="form-group">
                                <label for="name_en">Yetenek Adı (EN)</label>
                                <input type="text" id="name_en" name="name_en" value="<?php echo escape($edit_name_en); ?>" placeholder="Örn: PHP, Figma, React">
                            </div>

                            <div class="form-group">
                                <label for="percentage">Seviye (Yüzde %)</label>
                                <input type="number" id="percentage" name="percentage" min="0" max="100" value="<?php echo escape($edit_percentage); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="category">Kategori</label>
                                <select id="category" name="category" required>
                                    <option value="" disabled <?php echo !$edit_mode ? 'selected' : ''; ?>>Bir Kategori Seçin</option>
                                    <?php foreach ($skill_categories as $cat): ?>
                                        <option value="<?php echo escape($cat['name']); ?>" <?php echo $edit_category === $cat['name'] ? 'selected' : ''; ?>><?php echo escape($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex gap-1 mt-1">
                                <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Ekle'; ?></button>
                                <?php if ($edit_mode): ?>
                                    <a href="skills.php" class="btn btn-secondary">İptal Et</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Kategori Yönetimi Card -->
                    <div class="card" style="margin: 0;">
                        <h3>Kategori Yönetimi</h3>
                        
                        <!-- Add Category Form -->
                        <form action="skills.php" method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--border-glass);">
                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                            <input type="hidden" name="action" value="add_category">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="category_name" style="margin-bottom: 0.5rem;">Yeni Kategori Ekle</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" id="category_name" name="category_name" placeholder="Örn: Mobile, DevOps" required style="flex: 1;">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 0 15px;">Ekle</button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Categories List -->
                        <label style="display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.75rem;">Mevcut Kategoriler</label>
                        <?php if (count($skill_categories) === 0): ?>
                            <p class="empty-text" style="font-size: 0.85rem; padding: 10px 0;">Kategori bulunmuyor.</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; padding-right: 5px;">
                                <?php foreach ($skill_categories as $cat): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-glass);">
                                        <span style="font-size: 0.9rem; font-weight: 500;"><?php echo escape($cat['name']); ?></span>
                                        <form action="skills.php" method="POST" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz? Kategoriyi sildiğinizde bu kategorideki yeteneklerinizi düzenlemeniz gerekebilir.');" style="display: inline; margin: 0; padding: 0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.85rem;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--text-muted)'">❌ Sil</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Skills List Card -->
                <div class="card">
                    <h3>Mevcut Yetenekler</h3>
                    <?php if (count($skills) === 0): ?>
                        <p class="empty-text">Henüz eklenmiş bir yetenek yok.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Yetenek</th>
                                        <th>Yüzde</th>
                                        <th style="width: 120px;">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($skills as $s): ?>
                                        <tr>
                                            <td><span class="badge"><?php echo escape($s['category']); ?></span></td>
                                            <td><strong><?php echo escape($s['name']); ?></strong></td>
                                            <td>
                                                <div class="admin-progress-container">
                                                    <span>%<?php echo escape($s['percentage']); ?></span>
                                                    <div class="admin-progress-bar" style="width: <?php echo escape($s['percentage']); ?>%;"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex gap-0-5">
                                                    <a href="skills.php?edit=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary-outline">Düzenle</a>
                                                    
                                                    <form action="skills.php" method="POST" onsubmit="return confirm('Bu yeteneği silmek istediğinize emin misiniz?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger-outline">Sil</button>
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
