<?php
// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current language
 */
function get_lang() {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) {
        $_SESSION['lang'] = $_GET['lang'];
        // Remove lang parameter from URL using Javascript on next load, or just keep it
    }
    return $_SESSION['lang'] ?? 'tr';
}

/**
 * Translate static text
 */
function __($tr_text, $en_text) {
    $lang = get_lang();
    return ($lang === 'en' && !empty($en_text)) ? $en_text : $tr_text;
}

/**
 * Get language specific setting
 */
function get_setting_lang($settings, $key) {
    $lang = get_lang();
    if ($lang === 'en' && !empty($settings[$key . '_en'])) {
        return $settings[$key . '_en'];
    }
    return $settings[$key] ?? '';
}

/**
 * Auto translate using Google Translate Free API
 */
function auto_translate($text, $source = 'tr', $target = 'en') {
    if (empty(trim($text))) return $text;
    
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=" . urlencode($source) . "&tl=" . urlencode($target) . "&dt=t&q=" . urlencode($text);
    
    $response = false;
    
    // First try cURL if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    } 
    // Fallback to file_get_contents
    else if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
            ]
        ]);
        $response = @file_get_contents($url, false, $ctx);
    }
    
    if ($response) {
        $json = json_decode($response, true);
        if (isset($json[0]) && is_array($json[0])) {
            $translated = '';
            foreach ($json[0] as $segment) {
                if (isset($segment[0])) {
                    $translated .= $segment[0];
                }
            }
            return $translated;
        }
    }
    return '';
}

/**
 * Track page views
 */
function log_page_view($pdo) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT id FROM analytics WHERE visit_date = :today");
    $stmt->execute(['today' => $today]);
    $row = $stmt->fetch();
    
    if (!$row) {
        $ins = $pdo->prepare("INSERT INTO analytics (visit_date, page_views, unique_visitors) VALUES (:today, 1, 1)");
        $ins->execute(['today' => $today]);
        $_SESSION['visited_today_' . $today] = true;
    } else {
        $pdo->exec("UPDATE analytics SET page_views = page_views + 1 WHERE visit_date = '$today'");
        
        if (!isset($_SESSION['visited_today_' . $today])) {
            $pdo->exec("UPDATE analytics SET unique_visitors = unique_visitors + 1 WHERE visit_date = '$today'");
            $_SESSION['visited_today_' . $today] = true;
        }
    }
}

/**
 * Escape text for HTML output to prevent XSS
 */
function escape($text) {
    if ($text === null) return '';
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * Fetch all site settings into a simple associative array
 */
function get_settings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Sanitizes a filename to keep it safe while preserving readability
 */
function sanitize_filename($filename) {
    // Prevent directory traversal
    $filename = basename($filename);
    
    $pathinfo = pathinfo($filename);
    $name = isset($pathinfo['filename']) ? $pathinfo['filename'] : '';
    $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
    
    // Turkish to English conversion
    $find = ['Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ı', 'ö'];
    $replace = ['c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'i', 'o'];
    $name = str_replace($find, $replace, $name);
    
    // Replace non-alphanumeric (except dots, dashes, underscores) with a dash
    $name = preg_replace('/[^a-zA-Z0-9\._-]/', '-', $name);
    $name = preg_replace('/[-]+/', '-', $name);
    $name = preg_replace('/[_]+/', '_', $name);
    $name = trim($name, '-_');
    
    if (empty($name)) {
        $name = 'file_' . bin2hex(random_bytes(4));
    }
    
    return !empty($ext) ? $name . '.' . strtolower($ext) : $name;
}

/**
 * Returns a unique filename in the target directory by appending numbers if the file exists
 */
function get_unique_filename($target_dir, $filename) {
    $filename = sanitize_filename($filename);
    $pathinfo = pathinfo($filename);
    $name = isset($pathinfo['filename']) ? $pathinfo['filename'] : '';
    $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
    
    $dest_path = rtrim($target_dir, '/') . '/' . $filename;
    if (!file_exists($dest_path)) {
        return $filename;
    }
    
    $counter = 1;
    while (true) {
        $new_filename = $name . '-' . $counter . $ext;
        $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;
        if (!file_exists($dest_path)) {
            return $new_filename;
        }
        $counter++;
    }
}

/**
 * Securely handles image upload preserving original filename if unique
 * Returns string file path on success, or throws Exception on failure
 */
function handle_image_upload($file, $target_dir = '../uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Dosya yüklenirken hata oluştu.");
    }
    
    // Check MIME Type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } else {
        // Fallback if finfo is not installed
        $mime = $file['type'];
    }
    
    if (!in_array($mime, $allowed_types)) {
        throw new Exception("Sadece JPG, PNG, GIF ve WEBP formatları desteklenmektedir.");
    }
    
    // Determine Extension from mime type to avoid trust issues with client extension
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $ext = isset($extensions[$mime]) ? $extensions[$mime] : pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Get original filename without extension to preserve it
    $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
    $new_filename = get_unique_filename($target_dir, $original_name . '.' . $ext);
    
    // Ensure upload dir exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return $new_filename;
    } else {
        throw new Exception("Dosya taşınırken bir sunucu hatası oluştu.");
    }
}

/**
 * Securely handles project file upload (zip, pdf, docs, etc.) preserving original filename
 * Returns string file path on success, or throws Exception on failure
 */
function handle_file_upload($file, $target_dir = '../uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Dosya yüklenirken hata oluştu.");
    }
    
    // Get extension
    $original_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Blocked extensions (prevent execution of scripts on server)
    $blocked_exts = ['php', 'phtml', 'php5', 'php7', 'php8', 'cgi', 'pl', 'py', 'exe', 'bat', 'sh', 'cmd', 'js', 'htaccess'];
    if (in_array($original_ext, $blocked_exts) || empty($original_ext)) {
        throw new Exception("Bu dosya formatının yüklenmesi güvenlik nedeniyle yasaktır.");
    }
    
    // Create unique safe filename preserving original name
    $new_filename = get_unique_filename($target_dir, $file['name']);
    
    // Ensure upload dir exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return $new_filename;
    } else {
        throw new Exception("Dosya kaydedilirken bir hata oluştu.");
    }
}

/**
 * Securely handles favicon upload (ico, png, svg, etc.)
 * Returns string file path on success, or throws Exception on failure
 */
function handle_favicon_upload($file, $target_dir = '../uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Favicon yüklenirken hata oluştu.");
    }
    
    // Check MIME Type or Extension
    $allowed_types = [
        'image/x-icon', 
        'image/vnd.microsoft.icon', 
        'image/png', 
        'image/jpeg', 
        'image/webp', 
        'image/gif',
        'image/svg+xml'
    ];
    
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } else {
        $mime = $file['type'];
    }
    
    // Some systems don't have accurate mime detection for .ico or .svg, so let's check extension too
    $original_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['ico', 'png', 'svg', 'jpg', 'jpeg', 'webp', 'gif'];
    
    if (!in_array($mime, $allowed_types) && !in_array($original_ext, $allowed_exts)) {
        throw new Exception("Sadece ICO, PNG, SVG, JPG ve WEBP formatları desteklenmektedir.");
    }
    
    // Determine file extension
    $ext = $original_ext;
    if (empty($ext)) {
        $extensions = [
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'image/png' => 'png',
            'image/svg+xml' => 'svg',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif'
        ];
        $ext = isset($extensions[$mime]) ? $extensions[$mime] : 'png';
    }
    
    // Keep name clean but distinct
    $new_filename = 'favicon.' . $ext;
    
    // Ensure upload dir exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;
    
    // Delete old favicon if it exists (since we might change extension, let's delete any favicon.* in the folder)
    $existing_files = glob(rtrim($target_dir, '/') . '/favicon.*');
    if ($existing_files) {
        foreach ($existing_files as $f) {
            @unlink($f);
        }
    }
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return $new_filename;
    } else {
        throw new Exception("Dosya kaydedilirken bir hata oluştu.");
    }
}

/**
 * Polyfill for mb_strimwidth if the mbstring extension is not loaded
 */
if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth($str, $start, $width, $trimmarker = '', $encoding = null) {
        if ($encoding === null) {
            $encoding = 'UTF-8';
        }
        
        // Use mb functions if they exist but strimwidth doesn't
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($str, $encoding) <= $width) {
                return $str;
            }
            return mb_substr($str, $start, $width, $encoding) . $trimmarker;
        }
        
        if (strlen($str) <= $width) {
            return $str;
        }
        return substr($str, $start, $width) . $trimmarker;
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = null) {
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($str, $encoding = null) {
        return strtoupper($str);
    }
}

/**
 * Convert string into clean, URL-friendly slug
 */
function slugify($text) {
    $find = ['Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ı', 'ö'];
    $replace = ['c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'i', 'o'];
    $text = str_replace($find, $replace, $text);
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return strtolower($text);
}

/**
 * Convert hex color to rgba color string
 */
function hex2rgba($hex, $opacity) {
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "rgba($r, $g, $b, $opacity)";
}

