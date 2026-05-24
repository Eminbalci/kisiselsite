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

$project = null;

// Try to fetch by slug first
if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE slug = :slug");
    $stmt->execute(['slug' => $slug]);
    $project = $stmt->fetch();
}

// Fallback to ID
if (!$project && isset($_GET['id'])) {
    $project_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
    $stmt->execute(['id' => $project_id]);
    $project = $stmt->fetch();
}

// Redirect to home if project not found
if (!$project) {
    header("Location: " . $site_url . "/");
    exit();
}
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Optimization -->
    <title><?php echo escape(($lang === 'en' && !empty($project['title_en'])) ? $project['title_en'] : $project['title']); ?> | <?php echo escape($settings['admin_name']); ?></title>
    <meta name="description" content="<?php echo escape(mb_strimwidth(strip_tags(($lang === 'en' && !empty($project['description_en'])) ? $project['description_en'] : $project['description']), 0, 160, '...')); ?>">
    <meta name="keywords" content="<?php echo escape($project['title']); ?>, <?php echo escape($settings['admin_name']); ?>, mekatronik, bilgisayar mühendisliği, proje, detaylar">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo escape($site_url); ?>/proje/<?php echo escape($project['slug']); ?>">
    
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
    <meta property="og:url" content="<?php echo escape($site_url); ?>/proje/<?php echo escape($project['slug']); ?>">
    <meta property="og:title" content="<?php echo escape($project['title']); ?> | <?php echo escape($settings['admin_name']); ?>">
    <meta property="og:description" content="<?php echo escape(mb_strimwidth(strip_tags($project['description']), 0, 160, '...')); ?>">
    <?php if (!empty($project['image_path'])): ?>
        <meta property="og:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($project['image_path']); ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo escape($site_url); ?>/proje/<?php echo escape($project['slug']); ?>">
    <meta property="twitter:title" content="<?php echo escape($project['title']); ?> | <?php echo escape($settings['admin_name']); ?>">
    <meta property="twitter:description" content="<?php echo escape(mb_strimwidth(strip_tags($project['description']), 0, 160, '...')); ?>">
    <?php if (!empty($project['image_path'])): ?>
        <meta property="twitter:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($project['image_path']); ?>">
    <?php endif; ?>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS File -->
    <link rel="stylesheet" href="<?php echo escape($site_url); ?>/style.css">
    
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
    </style>
    
    <!-- Instant Theme Loader to prevent flash -->
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.add('light-theme');
        }
    </script>
    
    <style>
        .project-detail-container {
            max-width: 900px;
            margin: 120px auto 60px;
            padding: 0 20px;
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
        
        .project-detail-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 50px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .project-detail-img {
            width: 100%;
            max-height: 480px;
            object-fit: cover;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-glass);
            margin-bottom: 40px;
        }
        
        .project-header-meta {
            margin-bottom: 30px;
        }
        
        .project-meta-title {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 15px;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .project-meta-date {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: block;
            margin-bottom: 20px;
        }
        
        .project-detail-body {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 40px;
        }
        
        .project-detail-body p {
            margin-bottom: 20px;
        }
        
        .project-action-buttons {
            display: flex;
            gap: 20px;
            border-top: 1px solid var(--border-glass);
            padding-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        @media (max-width: 768px) {
            .project-detail-card {
                padding: 30px 20px;
            }
            .project-meta-title {
                font-size: 2rem;
            }
            .project-action-buttons {
                flex-direction: column;
            }
            .project-action-buttons .btn {
                width: 100%;
            }
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
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#yetenekler"><?php echo __('Yetenekler', 'Skills'); ?></a></li>
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#portfoy"><?php echo __('Portföy', 'Portfolio'); ?></a></li>
                    <li><a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#iletisim"><?php echo __('İletişim', 'Contact'); ?></a></li>
                </ul>
                <div class="lang-switcher" style="display: flex; gap: 5px; align-items: center;">
                    <a href="?id=<?php echo $project['id'] ?? ''; ?>&slug=<?php echo $project['slug'] ?? ''; ?>&lang=tr" style="color: <?php echo $lang === 'tr' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'tr' ? 'bold' : 'normal'; ?>; text-decoration: none;">TR</a>
                    <span style="color: var(--border-glass);">|</span>
                    <a href="?id=<?php echo $project['id'] ?? ''; ?>&slug=<?php echo $project['slug'] ?? ''; ?>&lang=en" style="color: <?php echo $lang === 'en' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'en' ? 'bold' : 'normal'; ?>; text-decoration: none;">EN</a>
                </div>
                <button id="theme-toggle" class="theme-toggle-btn" aria-label="Tema Değiştir">
                    <span class="theme-toggle-icon">🌙</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Detail Wrapper -->
    <div class="project-detail-container">
        <a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>#portfoy" class="back-link">
            <span>←</span> <?php echo __('Geri Dön', 'Go Back'); ?>
        </a>
        
        <article class="project-detail-card">
            <?php if (!empty($project['image_path']) && file_exists('uploads/' . $project['image_path'])): ?>
                <img src="<?php echo escape($site_url); ?>/uploads/<?php echo escape($project['image_path']); ?>" alt="<?php echo escape($project['title']); ?>" class="project-detail-img">
            <?php else: ?>
                <div class="project-detail-placeholder-img">
                    <span><?php echo escape(mb_strtoupper(mb_substr($project['title'], 0, 1))); ?></span>
                </div>
            <?php endif; ?>
            
            <header class="project-header-meta">
                <span class="project-meta-date"><?php echo __('Ekleme Tarihi:', 'Date Added:'); ?> <?php echo date('d.m.Y', strtotime($project['date_added'])); ?></span>
                <h1 class="project-meta-title"><?php echo escape(($lang === 'en' && !empty($project['title_en'])) ? $project['title_en'] : $project['title']); ?></h1>
            </header>
            
            <div class="project-detail-body">
                <!-- Short Description Summary -->
                <p style="font-weight: 500; color: var(--text-primary); font-size: 1.2rem; border-left: 4px solid var(--primary); padding-left: 15px; margin-bottom: 30px;">
                    <?php echo escape(($lang === 'en' && !empty($project['description_en'])) ? $project['description_en'] : $project['description']); ?>
                </p>
                
                <!-- Detailed content markdown/paragraphs -->
                <div class="project-content-text markdown-body" id="project-rendered-content">
                    <?php if (empty($project['content']) && empty($project['content_en'])): ?>
                        <p class="empty-text"><?php echo __('Bu projeye ait detaylı dokümantasyon bulunmuyor.', 'There is no detailed documentation for this project.'); ?></p>
                    <?php endif; ?>
                </div>
                <script style="display:none;" id="project-markdown-source"><?php echo escape(($lang === 'en' && !empty($project['content_en'])) ? $project['content_en'] : $project['content'] ?? ''); ?></script>
            </div>
            
            <div class="project-action-buttons">
                <?php if (!empty($project['project_link'])): ?>
                    <a href="<?php echo escape($project['project_link']); ?>" target="_blank" class="btn btn-primary">
                        <?php echo __('Projeyi İncele (GitHub / Web)', 'View Project (GitHub / Web)'); ?> →
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($project['file_path'])): ?>
                    <a href="<?php echo escape($site_url); ?>/uploads/<?php echo escape($project['file_path']); ?>" download class="btn btn-download">
                        📥 <?php echo __('Proje Dosyalarını İndir', 'Download Project Files'); ?>
                    </a>
                <?php endif; ?>
            </div>
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
            const renderedDiv = document.getElementById('project-rendered-content');
            if (mdSource && renderedDiv && mdSource.textContent.trim()) {
                renderedDiv.innerHTML = marked.parse(mdSource.textContent);
            }
        });
    </script>

    <!-- Script File -->
    <script src="<?php echo escape($site_url); ?>/app.js"></script>
</body>
</html>
