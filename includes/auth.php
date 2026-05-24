<?php
// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Secure session settings before starting
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Support HTTPS cookies if page is loaded securely
    $secure_cookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    
    // In PHP 7.3+, we can pass options array
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie (until browser closes)
        'path' => '/',
        'domain' => '',
        'secure' => $secure_cookie,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_name('Secure_PortfolioSession');
    session_start();
}

// Session Hijacking Protection
if (isset($_SESSION['admin_logged_in'])) {
    $ip_agent_hash = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    if (!isset($_SESSION['user_fingerprint'])) {
        $_SESSION['user_fingerprint'] = $ip_agent_hash;
    } else if ($_SESSION['user_fingerprint'] !== $ip_agent_hash) {
        // Fingerprint changed, destroy session
        session_unset();
        session_destroy();
        header("Location: login.php?error=session_invalid");
        exit();
    }
    
    // Session expiration check (inactive for 30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=session_timeout");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function is_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
