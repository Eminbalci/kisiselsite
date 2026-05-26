<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Fetch settings
$settings = get_settings($pdo);

// Initialize language and log view
$lang = get_lang();
log_page_view($pdo);

// Fetch skills
$stmt = $pdo->query("SELECT * FROM skills ORDER BY name ASC");
$skills = $stmt->fetchAll();

// Group skills by category
$skills_by_category = [];
foreach ($skills as $skill) {
    $skills_by_category[$skill['category']][] = $skill;
}

// Fetch skill categories for translation map
$stmt = $pdo->query("SELECT name, name_en FROM skill_categories");
$categories_db = $stmt->fetchAll();
$cat_en_map = [];
foreach ($categories_db as $c) {
    $cat_en_map[$c['name']] = $c['name_en'];
}

// Fetch portfolio items
$stmt = $pdo->query("SELECT * FROM portfolio ORDER BY date_added DESC");
$portfolio_items = $stmt->fetchAll();

// Extract unique categories from portfolio items
$project_categories = [];
foreach ($portfolio_items as $item) {
    if (!empty($item['category'])) {
        $cat = trim($item['category']);
        if (!in_array($cat, $project_categories)) {
            $project_categories[] = $cat;
        }
    }
}

// Extract GitHub username
$github_username = '';
if (!empty($settings['github_link'])) {
    $parsed_url = parse_url($settings['github_link']);
    if (isset($parsed_url['host']) && strpos($parsed_url['host'], 'github.com') !== false) {
        $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
        $path_parts = explode('/', $path);
        if (!empty($path_parts[0])) {
            $github_username = $path_parts[0];
        }
    }
}

// Fetch timeline
$stmt = $pdo->query("SELECT * FROM timeline ORDER BY type, display_order ASC, id DESC");
$timeline_items = $stmt->fetchAll();

$timeline_edu = [];
$timeline_exp = [];
foreach ($timeline_items as $item) {
    if ($item['type'] === 'edu') $timeline_edu[] = $item;
    else $timeline_exp[] = $item;
}

// Fetch certificates
$stmt = $pdo->query("SELECT * FROM certificates ORDER BY date_issued DESC");
$certificates = $stmt->fetchAll();

// Fetch blog posts
$stmt = $pdo->query("SELECT * FROM blog ORDER BY date_added DESC LIMIT 3");
$blog_posts = $stmt->fetchAll();

// Dynamic Site URL for SEO canonical and share tags
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$site_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Optimization -->
    <title><?php echo escape(get_setting_lang($settings, 'site_title')); ?></title>
    <meta name="description" content="<?php echo escape(mb_strimwidth(strip_tags(get_setting_lang($settings, 'about_text')), 0, 160, '...')); ?>">
    <meta name="keywords" content="<?php echo escape($settings['admin_name']); ?>, mekatronik, bilgisayar mühendisliği, portfolio, portfolyo, projeler, python, c#, java, web geliştirme">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo escape($site_url); ?>/">
    
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
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo escape($site_url); ?>/">
    <meta property="og:title" content="<?php echo escape($settings['site_title']); ?>">
    <meta property="og:description" content="<?php echo escape(mb_strimwidth(strip_tags($settings['about_text']), 0, 160, '...')); ?>">
    <?php if (!empty($settings['profile_image'])): ?>
        <meta property="og:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($settings['profile_image']); ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo escape($site_url); ?>/">
    <meta property="twitter:title" content="<?php echo escape($settings['site_title']); ?>">
    <meta property="twitter:description" content="<?php echo escape(mb_strimwidth(strip_tags($settings['about_text']), 0, 160, '...')); ?>">
    <?php if (!empty($settings['profile_image'])): ?>
        <meta property="twitter:image" content="<?php echo escape($site_url); ?>/uploads/<?php echo escape($settings['profile_image']); ?>">
    <?php endif; ?>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- CSS File -->
    <link rel="stylesheet" href="style.css">
    
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
            <a href="#" class="logo">
                <span><?php echo escape($settings['logo_text']); ?></span>
            </a>
            <?php else: ?>
            <div style="width: 50px;"></div>
            <?php endif; ?>
            <div class="nav-actions" style="display: flex; align-items: center; gap: 20px;">
                <ul class="nav-links">
                    <li><a href="#hakkimda"><?php echo __('Hakkımda', 'About Me'); ?></a></li>
                    <li><a href="#yetenekler"><?php echo __('Yetenekler', 'Skills'); ?></a></li>
                    <li><a href="#portfoy"><?php echo __('Portföy', 'Portfolio'); ?></a></li>
                    <li><a href="#iletisim"><?php echo __('İletişim', 'Contact'); ?></a></li>
                </ul>
                <div class="lang-switcher" style="display: flex; gap: 5px; align-items: center;">
                    <a href="?lang=tr" style="color: <?php echo $lang === 'tr' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'tr' ? 'bold' : 'normal'; ?>; text-decoration: none;">TR</a>
                    <span style="color: var(--border-glass);">|</span>
                    <a href="?lang=en" style="color: <?php echo $lang === 'en' ? 'var(--primary)' : 'var(--text-color)'; ?>; font-weight: <?php echo $lang === 'en' ? 'bold' : 'normal'; ?>; text-decoration: none;">EN</a>
                </div>
                <button id="theme-toggle" class="theme-toggle-btn" aria-label="Tema Değiştir">
                    <span class="theme-toggle-icon">🌙</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="hakkimda">
        <div class="hero-content">
            <p class="hero-subtitle"><?php echo __('Merhaba, Ben', 'Hello, I am'); ?></p>
            <h1 class="hero-title"><span><?php echo escape($settings['admin_name']); ?></span></h1>
            <h2 style="font-size: 1.8rem; font-weight: 600; margin-bottom: 25px; color: var(--text-secondary);">
                <?php echo escape(get_setting_lang($settings, 'admin_title')); ?>
            </h2>
            <p class="hero-desc">
                <?php echo nl2br(escape(get_setting_lang($settings, 'about_text'))); ?>
            </p>
            <div class="hero-buttons">
                <a href="#iletisim" class="btn btn-primary"><?php echo __('İletişime Geç', 'Get In Touch'); ?></a>
                <a href="#portfoy" class="btn btn-outline"><?php echo __('Çalışmalarımı Gör', 'View My Work'); ?></a>
            </div>
        </div>
        <div class="hero-image-wrapper">
            <div class="hero-image-glow">
                <?php if (!empty($settings['profile_image'])): ?>
                    <img src="uploads/<?php echo escape($settings['profile_image']); ?>" alt="<?php echo escape($settings['admin_name']); ?>" class="hero-image">
                <?php else: ?>
                    <!-- Premium SVG Avatar Fallback -->
                    <svg viewBox="0 0 100 100" class="hero-image" style="background: radial-gradient(circle, #2e3b5e 0%, #0d1326 100%);">
                        <defs>
                            <linearGradient id="svgGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#6366f1" />
                                <stop offset="100%" stop-color="#a855f7" />
                            </linearGradient>
                        </defs>
                        <circle cx="50" cy="40" r="18" fill="url(#svgGlow)" opacity="0.85"/>
                        <path d="M50,62 C32,62 24,72 24,80 C24,84 50,84 50,84 C50,84 76,84 76,80 C76,72 68,62 50,62 Z" fill="url(#svgGlow)" opacity="0.85"/>
                    </svg>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section (Embedded in hero layout) -->

    <!-- Skills Section -->
    <section id="yetenekler">
        <h2 class="section-title"><?php echo __('Yetenekler', 'Skills'); ?> <span><?php echo __('& Teknolojiler', '& Technologies'); ?></span></h2>
        
        <?php if (count($skills_by_category) === 0): ?>
            <p class="empty-text">Henüz yetenek eklenmemiş.</p>
        <?php else: ?>
            <div class="skills-grid">
                <?php foreach ($skills_by_category as $category => $cat_skills): ?>
                    <div class="skills-card">
                        <h3><?php echo escape(($lang === 'en' && !empty($cat_en_map[$category])) ? $cat_en_map[$category] : $category); ?></h3>
                        <?php foreach ($cat_skills as $s): ?>
                            <div class="skill-item">
                                <div class="skill-info">
                                    <span class="skill-name"><?php echo escape(($lang === 'en' && !empty($s['name_en'])) ? $s['name_en'] : $s['name']); ?></span>
                                    <span class="skill-percentage"><?php echo escape($s['percentage']); ?>%</span>
                                </div>
                                <div class="skill-bar">
                                    <div class="skill-fill" data-percent="<?php echo escape($s['percentage']); ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Timeline Section -->
    <?php if (count($timeline_edu) > 0 || count($timeline_exp) > 0): ?>
    <section id="zaman-cizelgesi">
        <h2 class="section-title"><?php echo __('Deneyim', 'Experience'); ?> <span><?php echo __('& Eğitim', '& Education'); ?></span></h2>
        
        <div class="grid grid-2" style="gap: 40px; margin-top: 30px;">
            <?php if (count($timeline_exp) > 0): ?>
            <div>
                <h3 style="color: var(--primary); font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    💼 <?php echo __('İş Deneyimi', 'Work Experience'); ?>
                </h3>
                <div class="timeline-container" style="border-left: 2px solid var(--border-glass); padding-left: 20px; display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($timeline_exp as $item): ?>
                    <div style="position: relative;">
                        <div style="position: absolute; left: -26px; top: 0; width: 10px; height: 10px; background: var(--primary); border-radius: 50%; box-shadow: 0 0 10px var(--primary-glow);"></div>
                        <?php 
                        $translated_date = $item['date_range'];
                        if ($lang === 'en') {
                            $translated_date = str_ireplace(['Devam Ediyor', 'Günümüz', 'Şu An', 'Yatay Geçiş'], ['Present', 'Present', 'Present', 'Transfer'], $translated_date);
                        }
                        ?>
                        <div style="font-size: 0.85rem; color: var(--primary); font-weight: 600; margin-bottom: 5px;"><?php echo escape($translated_date); ?></div>
                        <h4 style="font-size: 1.2rem; margin-bottom: 5px; color: var(--text-color);"><?php echo escape(($lang === 'en' && !empty($item['title_en'])) ? $item['title_en'] : $item['title']); ?></h4>
                        <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 10px;"><?php echo escape(($lang === 'en' && !empty($item['institution_en'])) ? $item['institution_en'] : $item['institution']); ?></div>
                        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.5;">
                            <?php echo escape(($lang === 'en' && !empty($item['description_en'])) ? $item['description_en'] : $item['description']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($timeline_edu) > 0): ?>
            <div>
                <h3 style="color: var(--secondary); font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    🎓 <?php echo __('Eğitim', 'Education'); ?>
                </h3>
                <div class="timeline-container" style="border-left: 2px solid var(--border-glass); padding-left: 20px; display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($timeline_edu as $item): ?>
                    <div style="position: relative;">
                        <div style="position: absolute; left: -26px; top: 0; width: 10px; height: 10px; background: var(--secondary); border-radius: 50%; box-shadow: 0 0 10px var(--secondary-glow);"></div>
                        <?php 
                        $translated_date = $item['date_range'];
                        if ($lang === 'en') {
                            $translated_date = str_ireplace(['Devam Ediyor', 'Günümüz', 'Şu An', 'Yatay Geçiş'], ['Present', 'Present', 'Present', 'Transfer'], $translated_date);
                        }
                        ?>
                        <div style="font-size: 0.85rem; color: var(--secondary); font-weight: 600; margin-bottom: 5px;"><?php echo escape($translated_date); ?></div>
                        <h4 style="font-size: 1.2rem; margin-bottom: 5px; color: var(--text-color);"><?php echo escape(($lang === 'en' && !empty($item['title_en'])) ? $item['title_en'] : $item['title']); ?></h4>
                        <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 10px;"><?php echo escape(($lang === 'en' && !empty($item['institution_en'])) ? $item['institution_en'] : $item['institution']); ?></div>
                        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.5;">
                            <?php echo escape(($lang === 'en' && !empty($item['description_en'])) ? $item['description_en'] : $item['description']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($github_username)): ?>
    <!-- GitHub Contributions Section -->
    <section id="github-katkilari">
        <h2 class="section-title">GitHub <span><?php echo __('Katkılarım', 'Contributions'); ?></span></h2>
        <div class="github-card">
            <p class="github-intro"><?php echo __('Açık kaynak dünyasındaki güncel kodlama hareketliliğim:', 'My recent open source coding activity:'); ?></p>
            <div class="github-chart-container">
                <img src="https://ghchart.rshah.org/<?php echo escape(ltrim($settings['theme_color'], '#')); ?>/<?php echo escape($github_username); ?>" alt="<?php echo escape($github_username); ?> GitHub Contributions" class="github-chart" loading="lazy" />
            </div>
            <a href="<?php echo escape($settings['github_link']); ?>" target="_blank" class="btn btn-outline mt-2">
                <?php echo __('GitHub Profilimi Ziyaret Et', 'Visit My GitHub Profile'); ?>
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Portfolio Section -->
    <section id="portfoy">
        <h2 class="section-title"><?php echo __('Son', 'Latest'); ?> <span><?php echo __('Projelerim', 'Projects'); ?></span></h2>
        
        <?php if (count($portfolio_items) === 0): ?>
            <p class="empty-text"><?php echo __('Henüz portföy projesi eklenmemiş.', 'No portfolio projects added yet.'); ?></p>
        <?php else: ?>
            <?php if (count($project_categories) > 0): ?>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all"><?php echo __('Tümü', 'All'); ?></button>
                    <?php foreach ($project_categories as $cat): ?>
                        <button class="filter-tab" data-filter="<?php echo escape(slugify($cat)); ?>"><?php echo escape($cat); ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="portfolio-grid">
                <?php foreach ($portfolio_items as $item): ?>
                    <div class="portfolio-card" data-category="<?php echo !empty($item['category']) ? escape(slugify($item['category'])) : ''; ?>">
                        <div class="portfolio-img-wrapper">
                            <?php if (!empty($item['image_path']) && file_exists('uploads/' . $item['image_path'])): ?>
                                <img src="uploads/<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['title']); ?>" class="portfolio-img" loading="lazy">
                            <?php else: ?>
                                <div class="portfolio-placeholder-img">
                                    <span><?php echo escape(mb_strtoupper(mb_substr($item['title'], 0, 1))); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="portfolio-info-body">
                            <h3 class="portfolio-card-title"><?php echo escape($item['title']); ?></h3>
                            <p class="portfolio-card-desc"><?php echo nl2br(escape(($lang === 'en' && !empty($item['description_en'])) ? $item['description_en'] : $item['description'])); ?></p>
                            
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:auto;">
                                <a href="project.php?slug=<?php echo escape($item['slug']); ?>" class="portfolio-card-link">
                                    <?php echo __('Detayları Gör', 'View Details'); ?> <span>→</span>
                                </a>
                                <?php if (!empty($item['project_link'])): ?>
                                    <a href="<?php echo escape($item['project_link']); ?>" target="_blank" class="portfolio-card-link" style="color:var(--secondary); font-size:0.85rem;">
                                        GitHub <span>↗</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Certificates Section -->
    <?php if (count($certificates) > 0): ?>
    <section id="sertifikalar">
        <h2 class="section-title"><?php echo __('Sertifikalar', 'Certificates'); ?> <span><?php echo __('& Başarılar', '& Achievements'); ?></span></h2>
        <div class="grid grid-3" style="gap: 20px; margin-top: 30px;">
            <?php foreach ($certificates as $cert): ?>
            <div class="card" style="padding: 20px; border-radius: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); transition: transform 0.3s ease;">
                <div style="display: flex; align-items: flex-start; gap: 15px;">
                    <?php if (!empty($cert['image_path'])): ?>
                        <img src="uploads/<?php echo escape($cert['image_path']); ?>" alt="<?php echo escape($cert['title']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" loading="lazy">
                    <?php else: ?>
                        <div style="width: 60px; height: 60px; background: var(--primary-glow); color: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">🏆</div>
                    <?php endif; ?>
                    <div>
                        <h4 style="font-size: 1.1rem; margin-bottom: 5px; color: var(--text-color);"><?php echo escape(($lang === 'en' && !empty($cert['title_en'])) ? $cert['title_en'] : $cert['title']); ?></h4>
                        <div style="font-size: 0.9rem; color: var(--primary); margin-bottom: 5px;"><?php echo escape($cert['issuer']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo escape($cert['date_issued']); ?></div>
                        <?php if (!empty($cert['link'])): ?>
                            <a href="<?php echo escape($cert['link']); ?>" target="_blank" style="display: inline-block; margin-top: 10px; font-size: 0.85rem; color: var(--secondary); text-decoration: none;">
                                <?php echo __('Sertifikayı Görüntüle', 'View Certificate'); ?> ↗
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Blog Section -->
    <?php if (count($blog_posts) > 0): ?>
    <section id="blog">
        <h2 class="section-title"><?php echo __('Son', 'Latest'); ?> <span><?php echo __('Yazılar', 'Posts'); ?></span></h2>
        <div class="grid grid-3" style="gap: 30px; margin-top: 30px;">
            <?php foreach ($blog_posts as $post): ?>
            <div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); transition: transform 0.3s ease;">
                <?php if (!empty($post['image_path'])): ?>
                    <img src="uploads/<?php echo escape($post['image_path']); ?>" alt="<?php echo escape($post['title']); ?>" style="width: 100%; height: 150px; object-fit: cover;" loading="lazy">
                <?php else: ?>
                    <div style="width: 100%; height: 150px; background: var(--primary-glow); display: flex; align-items: center; justify-content: center; font-size: 2rem;">📝</div>
                <?php endif; ?>
                <div style="padding: 20px;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;"><?php echo date('d.m.Y', strtotime($post['date_added'])); ?></div>
                    <h4 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--text-color);">
                        <a href="blog.php?slug=<?php echo escape($post['slug']); ?>" style="color: inherit; text-decoration: none;"><?php echo escape(($lang === 'en' && !empty($post['title_en'])) ? $post['title_en'] : $post['title']); ?></a>
                    </h4>
                    <p style="font-size: 0.95rem; color: var(--text-secondary); margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        <?php echo escape(mb_strimwidth(strip_tags(($lang === 'en' && !empty($post['content_en'])) ? $post['content_en'] : $post['content']), 0, 150, '...')); ?>
                    </p>
                    <a href="blog.php?slug=<?php echo escape($post['slug']); ?>" style="color: var(--primary); font-size: 0.9rem; font-weight: 500; text-decoration: none;">
                        <?php echo __('Devamını Oku', 'Read More'); ?> →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Contact Section -->
    <section id="iletisim">
        <h2 class="section-title"><?php echo __('İletişime', 'Get In'); ?> <span><?php echo __('Geç', 'Touch'); ?></span></h2>
        <div class="contact-container">
            <p class="contact-text"><?php echo __('Yeni projeler, iş birlikleri veya sadece sohbet etmek için bana ulaşın.', 'Reach out to me for new projects, collaborations, or just to chat.'); ?></p>
            
            <form id="contact-form" class="contact-form">
                <div class="form-group">
                    <input type="text" id="contact-name" name="name" placeholder="<?php echo __('Adınız Soyadınız', 'Your Name'); ?>" required>
                </div>
                <div class="form-group">
                    <input type="email" id="contact-email" name="email" placeholder="<?php echo __('E-posta Adresiniz', 'Your Email'); ?>" required>
                </div>
                <div class="form-group">
                    <textarea id="contact-message" name="message" rows="5" placeholder="<?php echo __('Mesajınız...', 'Your Message...'); ?>" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="contact-submit" style="width: 100%; justify-content: center;">
                    <?php echo __('Gönder', 'Send Message'); ?>
                </button>
                <div id="contact-status" style="margin-top: 15px; font-size: 0.9rem; text-align: center; display: none;"></div>
            </form>

            <div style="margin: 30px 0; border-top: 1px solid var(--border-glass);"></div>
            
            <a href="mailto:<?php echo escape($settings['email']); ?>" class="contact-email">
                <?php echo escape($settings['email']); ?>
            </a>
            
            <div class="social-links">
                <?php if (!empty($settings['github_link'])): ?>
                    <a href="<?php echo escape($settings['github_link']); ?>" target="_blank" class="social-icon" title="GitHub" aria-label="GitHub">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.867 8.167 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.164 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($settings['linkedin_link'])): ?>
                    <a href="<?php echo escape($settings['linkedin_link']); ?>" target="_blank" class="social-icon" title="LinkedIn" aria-label="LinkedIn">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <?php if (!empty($settings['instagram_link'])): ?>
                    <a href="<?php echo escape($settings['instagram_link']); ?>" target="_blank" class="social-icon" title="Instagram" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <a href="https://github.com/Eminbalci/kisiselsite" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none; cursor: pointer; transition: color 0.3s;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='inherit'">
            <p>&copy; <?php echo date('Y'); ?> <?php echo escape($settings['admin_name']); ?>. <?php echo __('Tüm Hakları Saklıdır.', 'All Rights Reserved.'); ?></p>
        </a>
    </footer>

    <!-- JavaScript File -->
    <script src="<?php echo escape($site_url); ?>/app.js"></script>
</body>
</html>
