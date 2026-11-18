<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
// ফোল্ডার পথ: public_html/reset_password.php
require_once 'includes/config.php';
require_once 'includes/user_functions.php';
require_once 'includes/mail_functions.php';
ensure_user_schema($db);

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$raw_token = trim($_GET['token'] ?? '');
$token_data = $raw_token !== '' ? verify_reset_token($db, $raw_token) : null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>নতুন পাসওয়ার্ড সেট করুন - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    <style>
        :root { --primary: #004D40; --secondary: #00796B; }
        body { background-color: var(--primary) !important; min-height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; padding: 20px 0; font-family: sans-serif; }
        .login-card { width: 95%; max-width: 400px; background: white; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); text-align: center; }
        .input-style { width: 100%; padding: 14px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-size: 15px; transition: all 0.3s; outline: none; }
        .input-style:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(0, 77, 64, 0.1); }
        .btn-login { width: 100%; background: var(--primary); color: white; padding: 14px; border-radius: 12px; font-weight: bold; font-size: 16px; transition: 0.3s; cursor: pointer; border: none; }
        .btn-login:hover { background: #00332B; transform: translateY(-1px); }
        .mb-1{margin-bottom:.25rem}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.mb-5{margin-bottom:1.25rem}.mb-6{margin-bottom:1.5rem}.mb-8{margin-bottom:2rem}.mt-6{margin-top:1.5rem}
        .inline-flex{display:inline-flex}.inline-block{display:inline-block}.items-center{align-items:center}.justify-center{justify-content:center}.block{display:block}
        .w-16{width:4rem}.h-16{height:4rem}.rounded-full{border-radius:9999px}.rounded-xl{border-radius:.75rem}
        .bg-green-50{background:#f0fdf4}.bg-red-50{background:#fef2f2}.p-3{padding:.75rem}
        .text-3xl{font-size:1.875rem}.text-xl{font-size:1.25rem}.text-sm{font-size:.875rem}
        .font-bold{font-weight:700}.text-gray-800{color:#1f2937}.text-gray-500{color:#6b7280}.text-red-700{color:#b91c1c}

</head>
<body>
    <div class="login-card">
        <?php if (!$token_data): ?>
            <div class="mb-6 inline-flex items-center justify-center w-16 h-16 bg-red-50 rounded-full text-3xl">⚠️</div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">লিংকের মেয়াদ শেষ</h2>
            <p class="text-gray-500 mb-6 text-sm">এই রিসেট লিংকটা অকার্যকর অথবা মেয়াদ শেষ হয়ে গেছে (৩০ মিনিট)। আবার চেষ্টা করুন।</p>
            <a href="forgot_password.php" class="btn-login inline-block" style="text-decoration:none;">🔄 নতুন লিংক চাই</a>
        <?php else: ?>
            <div class="mb-6 inline-flex items-center justify-center w-16 h-16 bg-green-50 rounded-full text-3xl">🔑</div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">নতুন পাসওয়ার্ড সেট করুন</h2>
            <p class="text-gray-500 mb-8 text-sm">ইউজার: <strong><?php echo htmlspecialchars($token_data['username']); ?></strong></p>

            <?php if (!empty($_SESSION['reset_error'])): ?>
                <div class="mb-5 text-left bg-red-50 text-red-700 text-sm p-3 rounded-xl">
                    <?php echo htmlspecialchars($_SESSION['reset_error']); unset($_SESSION['reset_error']); ?>
                </div>
            <?php endif; ?>

            <form action="process_new_password.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($raw_token); ?>">
                <div class="mb-4">
                    <input type="password" name="new_password" placeholder="নতুন পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)" class="input-style" required minlength="8" autofocus>
                </div>
                <div class="mb-6">
                    <input type="password" name="new_password_confirm" placeholder="নতুন পাসওয়ার্ড আবার লিখুন" class="input-style" required minlength="8">
                </div>
                <button type="submit" class="btn-login">✅ পাসওয়ার্ড পরিবর্তন করুন</button>
            </form>
        <?php endif; ?>

        <a href="login.php" class="block mt-6 text-sm text-gray-500 hover:text-gray-700">← লগইন পেজে ফিরে যান</a>
    </div>
</body>
</html>
