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
        if ($action === 'approve') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("UPDATE blog_comments SET is_approved = 1 WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Yorum başarıyla onaylandı ve yayına alındı.';
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        } elseif ($action === 'unapprove') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("UPDATE blog_comments SET is_approved = 0 WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Yorum onayı kaldırıldı.';
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $success_message = 'Yorum başarıyla silindi.';
            } catch (Exception $e) {
                $error_message = 'Hata: ' . $e->getMessage();
            }
        }
    }
}

// Get all comments with blog post title
$stmt = $pdo->query("
    SELECT c.*, b.title as post_title, b.slug as post_slug 
    FROM blog_comments c 
    LEFT JOIN blog b ON c.post_id = b.id 
    ORDER BY c.date_added DESC
");
$comments = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Yorumlar</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <style>
        .comment-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        .comment-card.pending {
            border-left: 4px solid var(--accent);
            background: rgba(99, 102, 241, 0.05);
        }
        .comment-card.approved {
            border-left: 4px solid #10b981;
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .commenter-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        .comment-post-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .comment-post-link:hover {
            text-decoration: underline;
        }
        .comment-body {
            margin-bottom: 15px;
            line-height: 1.5;
            white-space: pre-wrap;
            color: var(--text-primary);
            word-break: break-word;
        }
        .comment-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        .badge-approved {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
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
                <a href="comments.php" class="nav-item active">
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
            <h2>Blog Yorumları</h2>
            <p class="main-subtitle">Blog yazılarınıza gelen yorumları buradan onaylayabilir veya silebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="card">
                <?php if (count($comments) === 0): ?>
                    <p class="empty-text">Henüz hiç yorum yapılmamış.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-card <?php echo $comment['is_approved'] == 0 ? 'pending' : 'approved'; ?>">
                            <div class="comment-header">
                                <div>
                                    <span class="commenter-name"><?php echo escape($comment['name']); ?></span>
                                    <span class="text-muted">(<?php echo escape($comment['email']); ?>)</span>
                                    yazı: 
                                    <?php if (!empty($comment['post_title'])): ?>
                                        <a href="../blog/<?php echo escape($comment['post_slug']); ?>" target="_blank" class="comment-post-link"><?php echo escape($comment['post_title']); ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">Silinmiş Yazı</span>
                                    <?php endif; ?>
                                </div>
                                <div class="comment-meta">
                                    <span><?php echo date('d.m.Y H:i', strtotime($comment['date_added'])); ?></span>
                                    <?php if ($comment['is_approved'] == 1): ?>
                                        <span class="badge badge-approved">Onaylı</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Onay Bekliyor</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="comment-body"><?php echo escape($comment['comment']); ?></div>
                            
                            <div class="action-buttons">
                                <?php if ($comment['is_approved'] == 0): ?>
                                    <form action="comments.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Onayla</button>
                                    </form>
                                <?php else: ?>
                                    <form action="comments.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                        <input type="hidden" name="action" value="unapprove">
                                        <input type="hidden" name="id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">Onayı Kaldır</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form action="comments.php" method="POST" style="display: inline;" onsubmit="return confirm('Bu yorumu silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $comment['id']; ?>">
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
