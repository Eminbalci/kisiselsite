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
                <span><?php echo escape($settings['logo_text']); ?></span>.
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
                <img src="<?php echo escape($site_url); ?>/uploads/<?php echo escape($post['image_path']); ?>" alt="<?php echo escape($title); ?>" class="blog-detail-img">
            <?php endif; ?>
            
            <div class="blog-meta">
                <span>📅 <?php echo date('d.m.Y', strtotime($post['date_added'])); ?></span>
                <span>👁️ <?php echo (int)$post['views']; ?> <?php echo __('Görüntülenme', 'Views'); ?></span>
            </div>
            
            <h1 class="blog-title"><?php echo escape($title); ?></h1>
            
            <div class="project-content-text markdown-body" id="project-rendered-content">
            </div>
            <script style="display:none;" id="project-markdown-source"><?php echo escape($content ?? ''); ?></script>
        </article>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape($settings['admin_name']); ?>. <?php echo __('Tüm Hakları Saklıdır.', 'All Rights Reserved.'); ?></p>
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
