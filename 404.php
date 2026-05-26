<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Fetch settings
$settings = get_settings($pdo);
$lang = get_lang();

// Dynamic Site URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$site_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - <?php echo __('Sayfa Bulunamadı', 'Page Not Found'); ?> | <?php echo escape($settings['admin_name']); ?></title>
    
    <!-- Favicon -->
    <?php if (!empty($settings['site_favicon']) && file_exists('uploads/' . $settings['site_favicon'])): ?>
        <link rel="icon" type="<?php 
            $fav_ext = pathinfo($settings['site_favicon'], PATHINFO_EXTENSION);
            if ($fav_ext === 'svg') echo 'image/svg+xml';
            elseif ($fav_ext === 'ico') echo 'image/x-icon';
            else echo 'image/' . $fav_ext;
        ?>" href="<?php echo escape($site_url); ?>/uploads/<?php echo escape($settings['site_favicon']); ?>">
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
    
    .error-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        position: relative;
        z-index: 2;
    }
    
    .error-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-xl);
        padding: 60px 40px;
        max-width: 600px;
        width: 100%;
        text-align: center;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        animation: cardAppear 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @keyframes cardAppear {
        from {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .error-code {
        font-size: 8rem;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -2px;
        animation: pulseCode 3s ease-in-out infinite alternate;
    }
    
    @keyframes pulseCode {
        0% {
            filter: drop-shadow(0 0 5px rgba(217, 119, 6, 0.2));
        }
        100% {
            filter: drop-shadow(0 0 25px rgba(217, 119, 6, 0.6));
        }
    }
    
    .error-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 15px;
        color: var(--text-color);
    }
    
    .error-description {
        font-size: 1.1rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 35px;
    }
    
    .btn-home {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 14px 35px;
        border-radius: var(--radius-lg);
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 15px var(--primary-glow);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .btn-home:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px var(--primary-glow);
        filter: brightness(1.1);
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
        <div class="gradient-blob blob-1" style="top: 10%; left: 10%; width: 500px; height: 500px;"></div>
        <div class="gradient-blob blob-2" style="bottom: 10%; right: 10%; width: 500px; height: 500px;"></div>
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

    <!-- Main 404 Wrapper -->
    <div class="error-container">
        <div class="error-card">
            <div class="error-code">404</div>
            <h1 class="error-title"><?php echo __('Sayfa Bulunamadı', 'Page Not Found'); ?></h1>
            <p class="error-description">
                <?php echo __('Aradığınız sayfa silinmiş, taşınmış veya geçici olarak kullanım dışı olabilir.', 'The page you are looking for might have been removed, had its name changed or is temporarily unavailable.'); ?>
            </p>
            <a href="<?php echo escape($site_url); ?>/?lang=<?php echo $lang; ?>" class="btn-home">
                🏠 <?php echo __('Ana Sayfaya Dön', 'Return to Home'); ?>
            </a>
        </div>
    </div>

    <!-- Script File -->
    <script src="<?php echo escape($site_url); ?>/app.js"></script>
</body>
</html>
