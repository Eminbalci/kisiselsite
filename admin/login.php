<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$settings = get_settings($pdo);
$error_message = '';

// Check rate limit in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = time();
}

// Reset attempts if 5 minutes have passed
if (time() - $_SESSION['last_attempt_time'] > 300) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Güvenlik doğrulaması başarısız oldu (CSRF).';
    } else if ($_SESSION['login_attempts'] >= 5) {
        $error_message = 'Çok fazla başarısız giriş denemesi. Lütfen 5 dakika bekleyin.';
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if (empty($username) || empty($password)) {
            $error_message = 'Lütfen tüm alanları doldurun.';
        } else {
            // Prepare user query
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login success
                // Prevent Session Fixation by regenerating ID
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                $_SESSION['user_fingerprint'] = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
                
                // Clear attempts
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt_time']);
                
                header("Location: index.php");
                exit();
            } else {
                // Failed login
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                
                // Introduce delay to slow down brute force
                usleep(300000); // 300ms delay
                
                $error_message = 'Kullanıcı adı veya şifre hatalı.';
            }
        }
    }
}

// Generate CSRF token for the login form
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi | Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <!-- Favicon -->
    <?php if (!empty($settings['site_favicon']) && file_exists('../uploads/' . $settings['site_favicon'])): ?>
        <link rel="icon" type="<?php 
            $fav_ext = pathinfo($settings['site_favicon'], PATHINFO_EXTENSION);
            if ($fav_ext === 'svg') echo 'image/svg+xml';
            elseif ($fav_ext === 'ico') echo 'image/x-icon';
            else echo 'image/' . $fav_ext;
        ?>" href="../uploads/<?php echo escape($settings['site_favicon']); ?>">
    <?php endif; ?>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-box">
            <h2>Yönetim Paneli</h2>
            <p class="login-subtitle">Devam etmek için lütfen giriş yapın</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo escape($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-warning">
                    <?php 
                    if ($_GET['error'] === 'session_timeout') {
                        echo 'Oturumunuz zaman aşımına uğradı. Lütfen tekrar giriş yapın.';
                    } elseif ($_GET['error'] === 'session_invalid') {
                        echo 'Oturum güvenliği doğrulanamadı. Tekrar giriş yapın.';
                    } else {
                        echo 'Lütfen önce giriş yapın.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
            </form>
            <div class="login-footer">
                <a href="../index.php">← Siteye Geri Dön</a>
            </div>
        </div>
    </div>
</body>
</html>
