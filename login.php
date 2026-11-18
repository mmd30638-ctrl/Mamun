<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$shop = htmlspecialchars(shop_name(), ENT_QUOTES, 'UTF-8');
$tag = htmlspecialchars(shop_tagline(), ENT_QUOTES, 'UTF-8');
$brand = htmlspecialchars(shop_logo_url('/assets/img/ma-logo.png'), ENT_QUOTES, 'UTF-8');
$icon = htmlspecialchars(shop_app_icon_url(192), ENT_QUOTES, 'UTF-8');
$ver = htmlspecialchars((string)($sys_version ?? AME_ASSET_VERSION), ENT_QUOTES, 'UTF-8');
$eye_icon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 5c-5 0-9.3 3.1-11 7 1.7 3.9 6 7 11 7s9.3-3.1 11-7c-1.7-3.9-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 1 1 0 10zm0-2.2a2.8 2.8 0 1 0 0-5.6 2.8 2.8 0 0 0 0 5.6z"/></svg>';
$desc = $shop . ' — দোকান ও ওয়ার্কশপ ব্যবসা অ্যাপ। হোম স্ক্রিনে বসিয়ে ফোন অ্যাপের মতো খুলুন।';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script src="/assets/js/ame-desktop-site.js?v=<?php echo $ver; ?>"></script>
    <title><?php echo $shop; ?> অ্যাপ</title>
    <meta name="description" content="<?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="application-name" content="<?php echo $shop; ?>">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(shop_short_name(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#FFFFFF">
    <link rel="manifest" href="/manifest.php">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $icon; ?>">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/css/app-shell.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/ame-responsive-pro.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/front-desk-lock.819.css?v=<?php echo $ver; ?>">
</head>
<body class="ame-lock-page">
    <div class="ame-lock">
        <div class="ame-lock-status">
            <span><?php echo $shop; ?></span>
            <span>অ্যাপ</span>
        </div>
        <div class="ame-lock-brand">
            <div class="ame-lock-icon">
                <img src="<?php echo $brand; ?>" alt="<?php echo $shop; ?>">
            </div>
            <h1><?php echo $shop; ?></h1>
            <?php if ($tag !== ''): ?>
            <p><?php echo $tag; ?></p>
            <?php else: ?>
            <p>দোকান ব্যবসা অ্যাপ</p>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="flash">অনেকক্ষণ কাজ না করায় আবার খুলুন।</div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['login_notice'])): ?>
        <div class="flash"><?php echo htmlspecialchars((string)$_SESSION['login_notice']); unset($_SESSION['login_notice']); ?></div>
        <?php endif; ?>

        <form action="process_login.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="field">
                <label for="loginUser">ইউজার</label>
                <input id="loginUser" type="text" name="username" required autocomplete="username" autocapitalize="off">
            </div>
            <div class="field">
                <label for="loginPass">পাসওয়ার্ড</label>
                <div style="position:relative">
                    <input type="password" name="password" id="loginPass" required autocomplete="current-password">
                    <button type="button" class="lgn-eye" id="loginEyeBtn" title="দেখুন"><?php echo $eye_icon; ?></button>
                </div>
            </div>
            <button type="submit" class="enter">অ্যাপ খুলুন</button>
        </form>
        <a class="forgot" href="forgot_password.php">পাসওয়ার্ড ভুলে গেছেন?</a>
        <p class="hint">একবার হোম স্ক্রিনে বসালে গুগল খুঁজে ব্রাউজারে ঢুকতে হবে না — আইকনে ট্যাপ করলেই অ্যাপ খুলবে।</p>

        <div class="ame-lock-install">
            <button type="button" class="and" id="ameLockInstall">হোম স্ক্রিনে অ্যাপ বসান</button>
            <p class="ios">Safari-এ শেয়ার (□↑) চাপুন → Add to Home Screen। তারপর হোমের আইকন থেকে অ্যাপ খুলবে।</p>
        </div>
    </div>
    <script src="/assets/js/app-install.js?v=<?php echo $ver; ?>"></script>
    <script>
        (function () {
            var input = document.getElementById('loginPass');
            var button = document.getElementById('loginEyeBtn');
            if (!input || !button) return;
            button.addEventListener('mousedown', function () { input.type = 'text'; });
            button.addEventListener('mouseup', function () { input.type = 'password'; });
            button.addEventListener('mouseleave', function () { input.type = 'password'; });
            button.addEventListener('touchstart', function (e) { e.preventDefault(); input.type = 'text'; });
            button.addEventListener('touchend', function () { input.type = 'password'; });
        })();
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js?v=<?php echo $ver; ?>').catch(function () {});
        }
    </script>
</body>
</html>
