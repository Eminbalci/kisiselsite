<?php
header("Content-Type: application/xml; charset=utf-8");
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Prepare base URL dynamically
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$site_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Home Page
echo '  <url>' . "\n";
echo '    <loc>' . escape($site_url) . '/</loc>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '  </url>' . "\n";

// Project Slugs
try {
    $stmt = $pdo->query("SELECT slug FROM portfolio ORDER BY date_added DESC");
    $projects = $stmt->fetchAll();
    foreach ($projects as $project) {
        if (!empty($project['slug'])) {
            echo '  <url>' . "\n";
            echo '    <loc>' . escape($site_url) . '/proje/' . escape($project['slug']) . '</loc>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '  </url>' . "\n";
        }
    }
} catch (PDOException $e) {
    // Fail silently in sitemap if table query fails
}

echo '</urlset>' . "\n";
