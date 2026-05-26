<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Fetch settings
$settings = get_settings($pdo);

$lang = get_lang();
log_page_view($pdo);

// Dynamic Site URL for SEO canonical and share tags
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$site_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

$post = null;

// Try to fetch by slug first
if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $pdo->prepare("SELECT * FROM blog WHERE slug = :slug");
    $stmt->execute(['slug' => $slug]);
    $post = $stmt->fetch();
}

// Fallback to ID
if (!$post && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM blog WHERE id = :id");
    $stmt->execute(['id' => $post_id]);
    $post = $stmt->fetch();
}

// Redirect to home if post not found
if (!$post) {
    header("Location: " . $site_url . "/");
    exit();
}

// Increase view count
$stmt = $pdo->prepare("UPDATE blog SET views = views + 1 WHERE id = :id");
$stmt->execute(['id' => $post['id']]);

$title = ($lang === 'en' && !empty($post['title_en'])) ? $post['title_en'] : $post['title'];
$content = ($lang === 'en' && !empty($post['content_en'])) ? $post['content_en'] : $post['content'];

$comment_success = '';
$comment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($name) || empty($email) || empty($comment)) {
        $comment_error = __('Lütfen tüm alanları doldurun.', 'Please fill in all fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $comment_error = __('Geçersiz e-posta adresi.', 'Invalid email address.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO blog_comments (post_id, name, email, comment, is_approved) VALUES (:post_id, :name, :email, :comment, 0)");
            $stmt->execute([
                'post_id' => $post['id'],
                'name' => $name,
                'email' => $email,
                'comment' => $comment
            ]);
            $comment_success = __('Yorumunuz alındı. Onaylandıktan sonra yayınlanacaktır.', 'Your comment has been received. It will be published after approval.');
        } catch (PDOException $e) {
            $comment_error = __('Bir veritabanı hatası oluştu: ', 'A database error occurred: ') . $e->getMessage();
        }
    }
}

// Fetch approved comments
$stmt_comments = $pdo->prepare("SELECT * FROM blog_comments WHERE post_id = :post_id AND is_approved = 1 ORDER BY date_added ASC");
$stmt_comments->execute(['post_id' => $post['id']]);
$comments_list = $stmt_comments->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Optimization -->
    <title><?php echo escape($title); ?> | <?php echo escape(get_setting_lang($settings, 'site_title')); ?></title>
    <meta name="description" content="<?php echo escape(mb_strimwidth(strip_tags($content), 0, 160, '...')); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo escape($site_url); ?>/blog.php?slug=<?php echo escape($post['slug']); ?>">
    
    <!-- Favicon -->
    <?php if (!empty($settings['site_favicon']) && file_exists('uploads/' . $settings['site_favicon'])): ?>
        <link rel="icon" type="<?php 
            $fav_ext = pathinfo($settings['site_favicon'], PATHINFO_EXTENSION);
            if ($fav_ext === 'svg') echo 'image/svg+xml';
            elseif ($fav_ext === 'ico') echo 'image/x-icon';
            else echo 'image/' . $fav_ext;
        ?>" href="<?php echo escape($site_url); ?>/uploads/<?php echo escape($settings['site_favicon']); ?>">
    <?php endif; ?>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo escape($site_url); ?>/blog.php?slug=<?php echo escape($post['slug']); ?>">
    <meta property="og:title" content="<?php echo escape($title); ?>">
    <meta property="og:description" content="<?php echo escape(mb_strimwidth(strip_tags($content), 0, 160, '...')); ?>">
    <?php if (!empty($post['image_path'])): ?>
        <meta property="og:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($post['image_path']); ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo escape($site_url); ?>/blog.php?slug=<?php echo escape($post['slug']); ?>">
    <meta property="twitter:title" content="<?php echo escape($title); ?>">
    <meta property="twitter:description" content="<?php echo escape(mb_strimwidth(strip_tags($content), 0, 160, '...')); ?>">
    <?php if (!empty($post['image_path'])): ?>
        <meta property="twitter:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($post['image_path']); ?>">
    <?php endif; ?>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- CSS File -->
    <link rel="stylesheet" href="<?php echo escape($site_url); ?>/style.css">
    <!-- Highlight.js for code formatting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.2.0/github-markdown-dark.min.css">
    
    <!-- Dynamic Theme Colors -->
    <style>
    :root {
        --primary: <?php echo escape($settings['theme_color'] ?? '#d97706'); ?>;
        --primary-glow: <?php echo hex2rgba($settings['theme_color'] ?? '#d97706', 0.35); ?>;
        --secondary: <?php echo escape($settings['theme_secondary_color'] ?? '#fbbf24'); ?>;
        --secondary-glow: <?php echo hex2rgba($settings['theme_secondary_color'] ?? '#fbbf24', 0.35); ?>;
    }
    html.light-theme, body.light-theme {
        --primary: <?php echo escape($settings['theme_color_light'] ?? '#2563eb'); ?>;
        --primary-glow: <?php echo hex2rgba($settings['theme_color_light'] ?? '#2563eb', 0.35); ?>;
        --secondary: <?php echo escape($settings['theme_secondary_color_light'] ?? '#60a5fa'); ?>;
        --secondary-glow: <?php echo hex2rgba($settings['theme_secondary_color_light'] ?? '#60a5fa', 0.35); ?>;
    }
    .markdown-body {
        box-sizing: border-box;
        min-width: 200px;
        max-width: 980px;
        margin: 0 auto;
        padding: 45px;
        background: transparent !important;
        font-family: 'Outfit', sans-serif !important;
    }
    body:not(.light-theme) .markdown-body {
        color: var(--text-color);
    }
    body.light-theme .markdown-body {
        color: var(--text-color);
    }
    .markdown-body pre {
        background-color: var(--bg-card) !important;
        border: 1px solid var(--border-glass);
    }
    
    .blog-detail-container {
        max-width: 900px;
        margin: 120px auto 50px auto;
        padding: 0 20px;
        position: relative;
        z-index: 2;
    }
    .blog-detail-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-xl);
        padding: 50px;
        backdrop-filter: blur(8px);
    }
    .blog-detail-img {
        width: 100%;
        height: auto;
        max-height: 400px;
        object-fit: cover;
        border-radius: var(--radius-lg);
        margin-bottom: 30px;
    }
    .blog-meta {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
    }
    .blog-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 30px;
        color: var(--text-color);
    }
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 500;
        margin-bottom: 30px;
        transition: var(--transition-smooth);
    }
    .back-link:hover {
        color: var(--primary);
        transform: translateX(-4px);
    }

    /* Social Share Buttons */
    .share-section {
        margin: 40px 0 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-glass);
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    .share-title {
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-primary);
    }
    .share-buttons {
        display: flex;
        gap: 10px;
    }
    .share-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(4px);
    }
    .share-btn:hover {
        transform: translateY(-3px) scale(1.08);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .share-btn.x-twitter:hover { background: #000000; color: #fff; }
    .share-btn.linkedin:hover { background: #0077b5; color: #fff; }
    .share-btn.whatsapp:hover { background: #25d366; color: #fff; }
    .share-btn.email:hover { background: var(--primary); color: #fff; }

    /* Comments Section */
    .comments-section {
        margin-top: 50px;
        padding-top: 30px;
        border-top: 1px solid var(--border-glass);
    }
    .comments-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 25px;
        color: var(--text-color);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 40px;
    }
    .comment-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: 20px;
        backdrop-filter: blur(4px);
    }
    .comment-meta-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 0.9rem;
        color: var(--text-muted);
        flex-wrap: wrap;
        gap: 10px;
    }
    .comment-author {
        font-weight: 600;
        color: var(--primary);
    }
    .comment-content {
        color: var(--text-secondary);
        line-height: 1.6;
        font-size: 1rem;
        word-break: break-word;
    }
    
    /* Comment Form */
    .comment-form-container {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: 30px;
        backdrop-filter: blur(4px);
    }
    .comment-form-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--text-color);
    }
    .comment-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    @media (max-width: 600px) {
        .comment-form-grid {
            grid-template-columns: 1fr;
        }
    }
    .comment-input-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .comment-input-group label {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-secondary);
    }
    .comment-input-group input,
    .comment-input-group textarea {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-md);
        color: var(--text-color);
        padding: 12px 16px;
        font-family: inherit;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    .comment-input-group input:focus,
    .comment-input-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 10px var(--primary-glow);
    }
    </style>
</head>
<body>
    <script>
        // Check theme immediately on body parse to prevent flash
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-theme');
        }
    </script>

    <!-- Background Glow Blobs -->
    <div class="bg-gradients">
        <div class="gradient-blob blob-1"></div>
        <div class="gradient-blob blob-2"></div>
    </div>

    <!-- Navigation Header -->
    <nav class="navbar" id="main-nav">
        <div class="nav-container">
            <?php if (!empty($settings['logo_text'])): ?>
            <a href="<?php echo escape($site_url); ?>/" class="logo">
                <span><?php echo escape($settings['logo_text']); ?></span>
            </a>
            <?php else: ?>
            <div style="width: 50px;"></div>
            <?php endif; ?>
            <div class="nav-actions" style="display: flex; align-items: center; gap: 20px;">
                <ul class="nav-links">
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#hakkimda"><?php echo __('Hakkımda', 'About Me'); ?></a></li>
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#blog"><?php echo __('Blog', 'Blog'); ?></a></li>
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#iletisim"><?php echo __('İletişim', 'Contact'); ?></a></li>
                </ul>
                <div class="lang-switcher" style="display: flex; gap: 5px; align-items: center;">
                    <a href="?id=<?php echo $post['id'] ?? ''; ?>&slug=<?php echo $post['slug'] ?? ''; ?>&lang=tr" style="color: <?php echo $lang === 'tr' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'tr' ? 'bold' : 'normal'; ?>; text-decoration: none;">TR</a>
                    <span style="color: var(--border-glass);">|</span>
                    <a href="?id=<?php echo $post['id'] ?? ''; ?>&slug=<?php echo $post['slug'] ?? ''; ?>&lang=en" style="color: <?php echo $lang === 'en' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'en' ? 'bold' : 'normal'; ?>; text-decoration: none;">EN</a>
                </div>
                <button id="theme-toggle" class="theme-toggle-btn" aria-label="Tema Değiştir">
                    <span class="theme-toggle-icon">🌙</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Detail Wrapper -->
    <div class="blog-detail-container">
        <a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#blog" class="back-link">
            <span>←</span> <?php echo __('Geri Dön', 'Go Back'); ?>
        </a>
        
        <article class="blog-detail-card">
            <?php if (!empty($post['image_path']) && file_exists('uploads/' . $post['image_path'])): ?>
                <img src="<?php echo escape($site_url); ?>/uploads/<?php echo escape($post['image_path']); ?>" alt="<?php echo escape($title); ?>" class="blog-detail-img" loading="lazy">
            <?php endif; ?>
            
            <div class="blog-meta">
                <span>📅 <?php echo date('d.m.Y', strtotime($post['date_added'])); ?></span>
                <span>👁️ <?php echo (int)$post['views']; ?> <?php echo __('Görüntülenme', 'Views'); ?></span>
            </div>
            
            <h1 class="blog-title"><?php echo escape($title); ?></h1>
            
            <div class="project-content-text markdown-body" id="project-rendered-content">
            </div>
            <script style="display:none;" id="project-markdown-source"><?php echo escape($content ?? ''); ?></script>

            <!-- Social Share Buttons -->
            <?php 
                $current_url = urlencode($site_url . '/blog.php?slug=' . $post['slug']);
                $share_title = urlencode($title);
            ?>
            <div class="share-section">
                <span class="share-title"><?php echo __('Paylaş:', 'Share:'); ?></span>
                <div class="share-buttons">
                    <a href="https://twitter.com/intent/tweet?text=<?php echo $share_title; ?>&url=<?php echo $current_url; ?>" target="_blank" class="share-btn x-twitter" aria-label="X / Twitter'da Paylaş">
                        <svg style="width:18px;height:18px;fill:currentColor;" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $current_url; ?>" target="_blank" class="share-btn linkedin" aria-label="LinkedIn'de Paylaş">
                        <svg style="width:18px;height:18px;fill:currentColor;" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo $share_title; ?>%20<?php echo $current_url; ?>" target="_blank" class="share-btn whatsapp" aria-label="WhatsApp'ta Paylaş">
                        <svg style="width:18px;height:18px;fill:currentColor;" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.503-5.728-1.46L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.37 9.863-9.743.002-2.602-1.01-5.05-2.85-6.892C16.636 2.128 14.191 1.11 11.59 1.11c-5.442 0-9.873 4.372-9.877 9.745-.001 1.83.51 3.568 1.48 5.1L2.217 21.83l6.43-1.676zM17.65 14.8c-.3-.15-1.78-.88-2.05-.98-.28-.1-.48-.15-.68.15-.2.3-.77.98-.95 1.18-.18.2-.35.23-.65.08-1.22-.6-2.08-1.08-2.9-2.5-.22-.38-.22-.73-.07-.88.13-.13.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.03-.53-.08-.15-.68-1.65-.93-2.27-.25-.6-.5-.52-.68-.53-.18-.01-.38-.01-.58-.01-.2 0-.52.08-.8.38-.28.3-1.08 1.05-1.08 2.57 0 1.52 1.1 3 1.25 3.2.15.2 2.18 3.32 5.28 4.66.74.32 1.3.5 1.76.65.74.23 1.42.2 1.95.12.6-.09 1.78-.73 2.03-1.43.25-.7.25-1.3.18-1.43-.07-.13-.27-.2-.58-.35z"/></svg>
                    </a>
                    <a href="mailto:?subject=<?php echo $share_title; ?>&body=<?php echo $current_url; ?>" class="share-btn email" aria-label="E-posta ile Paylaş">
                        <svg style="width:18px;height:18px;fill:currentColor;" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Comments List Section -->
            <div class="comments-section">
                <h2 class="comments-title">
                    💬 <?php echo __('Yorumlar', 'Comments'); ?> (<?php echo count($comments_list); ?>)
                </h2>
                
                <?php if (!empty($comment_success)): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo escape($comment_success); ?></div>
                <?php endif; ?>
                <?php if (!empty($comment_error)): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;"><?php echo escape($comment_error); ?></div>
                <?php endif; ?>

                <div class="comments-list">
                    <?php if (count($comments_list) === 0): ?>
                        <p class="text-muted" style="font-style: italic;"><?php echo __('Henüz yorum yapılmamış. İlk yorumu siz yapın!', 'No comments yet. Be the first to comment!'); ?></p>
                    <?php else: ?>
                        <?php foreach ($comments_list as $c_item): ?>
                            <div class="comment-item">
                                <div class="comment-meta-info">
                                    <span class="comment-author"><?php echo escape($c_item['name']); ?></span>
                                    <span><?php echo date('d.m.Y H:i', strtotime($c_item['date_added'])); ?></span>
                                </div>
                                <div class="comment-content"><?php echo nl2br(escape($c_item['comment'])); ?></div>
                             </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Comment Form -->
                <div class="comment-form-container">
                    <h3 class="comment-form-title"><?php echo __('Yorum Yapın', 'Leave a Comment'); ?></h3>
                    <form action="" method="POST">
                        <div class="comment-form-grid">
                            <div class="comment-input-group">
                                <label for="c_name"><?php echo __('Ad Soyad', 'Name'); ?> *</label>
                                <input type="text" id="c_name" name="name" required placeholder="<?php echo __('Örn: Ahmet Yılmaz', 'e.g. John Doe'); ?>">
                            </div>
                            <div class="comment-input-group">
                                <label for="c_email"><?php echo __('E-posta', 'Email'); ?> *</label>
                                <input type="email" id="c_email" name="email" required placeholder="<?php echo __('Örn: ahmet@mail.com', 'e.g. john@mail.com'); ?>">
                                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 3px;"><?php echo __('E-posta adresiniz yayınlanmayacaktır.', 'Your email address will not be published.'); ?></small>
                            </div>
                        </div>
                        <div class="comment-input-group" style="margin-bottom: 20px;">
                            <label for="c_comment"><?php echo __('Yorumunuz', 'Your Comment'); ?> *</label>
                            <textarea id="c_comment" name="comment" rows="5" required placeholder="<?php echo __('Düşüncelerinizi buraya yazın...', 'Write your thoughts here...'); ?>"></textarea>
                        </div>
                        <button type="submit" name="submit_comment" class="btn btn-primary"><?php echo __('Yorum Gönder', 'Submit Comment'); ?></button>
                    </form>
                </div>
            </div>
        </article>
    </div>

    <!-- Footer -->
    <footer>
        <a href="https://github.com/Eminbalci/kisiselsite" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none; cursor: pointer; transition: color 0.3s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='inherit'">
            <p>&copy; <?php echo date('Y'); ?> <?php echo escape($settings['admin_name']); ?>. <?php echo __('Tüm Hakları Saklıdır.', 'All Rights Reserved.'); ?></p>
        </a>
    </footer>

    <!-- Marked.js Markdown Parser -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const mdSource = document.getElementById('project-markdown-source');
            if (mdSource && typeof marked !== 'undefined') {
                const contentDiv = document.getElementById('project-rendered-content');
                // Basic configuration for marked
                marked.setOptions({
                    breaks: true,
                    gfm: true
                });
                
                let rawText = mdSource.textContent || mdSource.innerText;
                // Parse and inject markdown
                contentDiv.innerHTML = marked.parse(rawText);
            }
        });
    </script>
    
    <!-- JavaScript File -->
    <script src="<?php echo escape($site_url); ?>/app.js"></script>
</body>
</html>
