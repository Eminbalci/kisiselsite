<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Force login
require_login();

$settings = get_settings($pdo);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
    } else {
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Mesaj başarıyla silindi.';
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        } elseif ($action === 'mark_read') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = :id");
                $stmt->execute(['id' => $id]);
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        }
    }
}

// Mark as read via GET
if (isset($_GET['mark_read'])) {
    $id = intval($_GET['mark_read']);
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = :id")->execute(['id' => $id]);
    header("Location: messages.php");
    exit;
}

// Get all messages
$stmt = $pdo->query("SELECT * FROM messages ORDER BY date_sent DESC");
$messages = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Gelen Kutusu</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <style>
        .msg-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        .msg-card.unread {
            border-left: 4px solid var(--accent);
            background: rgba(99, 102, 241, 0.05);
        }
        .msg-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .msg-sender {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        .msg-body {
            margin-bottom: 15px;
            line-height: 1.5;
            white-space: pre-wrap;
            color: var(--text-primary);
            word-break: break-word;
        }
    </style>
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
                <a href="certificates.php" class="nav-item">
                    <span class="icon">🏆</span> Sertifikalar
                </a>
                <a href="references.php" class="nav-item">
                    <span class="icon">🤝</span> Referanslar
                </a>
                <a href="messages.php" class="nav-item active">
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
            <h2>Gelen Kutusu</h2>
            <p class="main-subtitle">İletişim formundan gelen mesajları buradan görüntüleyebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="card">
                <?php if (count($messages) === 0): ?>
                    <div class="alert alert-info">Henüz hiç mesajınız yok.</div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="msg-card <?php echo $msg['is_read'] == 0 ? 'unread' : ''; ?>">
                            <div class="msg-header">
                                <div>
                                    <span class="msg-sender"><?php echo escape($msg['name']); ?></span> 
                                    (<?php echo escape($msg['email']); ?>)
                                </div>
                                <div>
                                    <?php echo date('d.m.Y H:i', strtotime($msg['date_sent'])); ?>
                                </div>
                            </div>
                            <div class="msg-body"><?php echo escape($msg['message']); ?></div>
                            <div class="action-buttons">
                                <?php if ($msg['is_read'] == 0): ?>
                                    <a href="messages.php?mark_read=<?php echo $msg['id']; ?>" class="btn btn-sm btn-primary">Okundu İşaretle</a>
                                <?php endif; ?>
                                <form action="messages.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu mesajı silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
