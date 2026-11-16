<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'includes/config.php';
require_once 'includes/user_functions.php';
require_once 'includes/password_reset_otp.php';
ensure_user_schema($db);
ensure_password_reset_schema($db);

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!empty($_GET['restart'])) {
    ame_clear_password_reset_session();
    header('Location: forgot_password.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$step = $_SESSION['reset_step'] ?? 'username';
$channels = [];
if (!empty($_SESSION['reset_user_id'])) {
    $reset_user = ame_reset_user_by_id($db, (int)$_SESSION['reset_user_id']);
    if ($reset_user && ame_user_is_active($reset_user)) {
        $channels = ame_user_reset_channels($reset_user);
    } else {
        ame_clear_password_reset_session();
        $step = 'username';
    }
}

$eye_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5c-5 0-9.3 3.1-11 7 1.7 3.9 6 7 11 7s9.3-3.1 11-7c-1.7-3.9-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.2a2.8 2.8 0 1 0 0-5.6 2.8 2.8 0 0 0 0 5.6z"/></svg>';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/device-fit.css">
    <title>পাসওয়ার্ড রিসেট - <?php echo htmlspecialchars(shop_name()); ?></title>
    <style>
        :root { --primary: #004D40; --primary-dark: #00332B; --accent: #00796B; }
        body { background: var(--primary) !important; min-height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; padding: 20px 0; font-family: sans-serif; }
        .login-card { width: 95%; max-width: 420px; background: #fff; border-radius: 24px; padding: 36px 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); text-align: center; }
        .input-style { width: 100%; padding: 14px; border: 1.5px solid #E2E8F0; border-radius: 12px; font-size: 15px; outline: none; box-sizing: border-box; }
        .input-style:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(0, 77, 64, 0.1); }
        .btn-login { width: 100%; background: var(--primary); color: #fff; padding: 14px; border-radius: 12px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; }
        .btn-login:hover { background: var(--primary-dark); }
        .mb-3{margin-bottom:.75rem}.mb-4{margin-bottom:1rem}.mb-5{margin-bottom:1.25rem}
        .inline-flex{display:inline-flex}.items-center{align-items:center}.justify-center{justify-content:center}
        .w-16{width:4rem}.h-16{height:4rem}.rounded-full{border-radius:9999px}.bg-green-50{background:#f0fdf4}
        .text-3xl{font-size:1.875rem}.text-2xl{font-size:1.5rem}.text-sm{font-size:.875rem}
        .font-bold{font-weight:700}.text-gray-800{color:#1f2937}.text-gray-600{color:#4b5563}.text-gray-500{color:#6b7280}
        .btn-login:disabled { opacity: .55; cursor: not-allowed; }
        .btn-ghost { width: 100%; background: #fff; color: var(--primary); padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1.5px solid #CFE3F7; cursor: pointer; margin-top: 8px; }
        .steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 22px; }
        .steps span { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; background: #E8F5E9; color: #2E7D32; }
        .steps span.active { background: var(--primary); color: #fff; }
        .steps span.done { background: #A5D6A7; color: #1B5E20; }
        .notice { text-align: left; background: #E8F5E9; color: #1B5E20; font-size: 13px; padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; line-height: 1.5; }
        .error { text-align: left; background: #FFEBEE; color: #C62828; font-size: 13px; padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; line-height: 1.5; }
        .warn { text-align:left; background:#FFF8E1; color:#9A5B00; font-size:13px; padding:12px 14px; border-radius:12px; margin-bottom:16px; line-height:1.5; }
        .otp-preview { font-size:28px; font-weight:800; letter-spacing:8px; margin:8px 0 4px; color:#004D40; }
        .channel-grid { display: grid; gap: 10px; margin-bottom: 16px; }
        .channel-card { display: flex; align-items: center; gap: 12px; text-align: left; padding: 14px; border: 1.5px solid #E2E8F0; border-radius: 14px; background: #FAFFFE; cursor: pointer; transition: .2s; }
        .channel-card:hover, .channel-card.selected { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,77,64,.08); }
        .channel-card input { display: none; }
        .channel-icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; background: #E0F2F1; }
        .channel-meta b { display: block; color: #0B1E4D; font-size: 14px; }
        .channel-meta small { color: #64748B; font-size: 12px; }
        .otp-input { letter-spacing: 10px; text-align: center; font-size: 24px; font-weight: 800; }
        .lgn-field-wrap { position: relative; margin-bottom: 14px; }
        .lgn-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; background: none; border: 0; color: #64748B; cursor: pointer; }
        .back-link { display: block; margin-top: 18px; font-size: 13px; color: #64748B; text-decoration: none; }
        .back-link:hover { color: #334155; }
    </style>

</head>
<body>
    <div class="login-card">
        <div class="mb-4 inline-flex items-center justify-center w-16 h-16 bg-green-50 rounded-full text-3xl">
            <?php echo $step === 'password' ? '🔐' : ($step === 'otp' ? '📲' : '🔑'); ?>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-1">পাসওয়ার্ড রিসেট</h2>
        <p class="text-gray-500 mb-5 text-sm">
            <?php
            if ($step === 'password') {
                echo 'নতুন পাসওয়ার্ড সেট করুন';
            } elseif ($step === 'otp') {
                echo 'OTP কোড লিখুন — ' . htmlspecialchars((string)($_SESSION['reset_destination_mask'] ?? ''));
            } elseif ($step === 'channel') {
                echo 'কোড পাঠানোর মাধ্যম বেছে নিন';
            } else {
                echo 'ইউজারনেম, ইমেইল বা মোবাইল দিন — OTP পাবেন';
            }
            ?>
        </p>

        <div class="steps" aria-hidden="true">
            <span class="<?php echo $step === 'username' ? 'active' : 'done'; ?>">১</span>
            <span class="<?php echo $step === 'channel' ? 'active' : ($step === 'otp' || $step === 'password' ? 'done' : ''); ?>">২</span>
            <span class="<?php echo $step === 'otp' ? 'active' : ($step === 'password' ? 'done' : ''); ?>">৩</span>
            <span class="<?php echo $step === 'password' ? 'active' : ''; ?>">৪</span>
        </div>

        <?php if (!empty($_SESSION['reset_notice'])): ?>
            <div class="notice"><?php echo htmlspecialchars((string)$_SESSION['reset_notice']); unset($_SESSION['reset_notice']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['reset_error'])): ?>
            <div class="error"><?php echo htmlspecialchars((string)$_SESSION['reset_error']); unset($_SESSION['reset_error']); ?></div>
        <?php endif; ?>

        <?php if ($step === 'username'): ?>
        <form action="process_password_reset.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="step" value="lookup">
            <div class="mb-4">
                <input type="text" name="username" class="input-style" placeholder="ইউজারনেম / ইমেইল / মোবাইল" required autofocus>
            </div>
            <button type="submit" class="btn-login">এগিয়ে যান →</button>
        </form>

        <?php elseif ($step === 'channel'): ?>
        <?php if (!$channels): ?>
            <div class="error">এই একাউন্টে ইমেইল, মোবাইল বা WhatsApp নম্বর সংরক্ষিত নেই।</div>
            <p class="text-sm text-gray-600 mb-4" style="line-height:1.5;">অ্যাডমিনকে বলুন <strong>সেটিংস → ইউজার</strong> থেকে আপনার মোবাইল/WhatsApp/ইমেইল যোগ করতে।</p>
            <a href="forgot_password.php?restart=1" class="btn-ghost" style="display:block;text-decoration:none;line-height:1.2;">আবার শুরু করুন</a>
        <?php else: ?>
        <form action="process_password_reset.php" method="POST" id="channelForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="step" value="send_otp">
            <input type="hidden" name="channel" id="channelInput" value="">
            <p class="text-sm text-gray-600 mb-3">ইউজার: <strong><?php echo htmlspecialchars((string)($_SESSION['reset_username'] ?? '')); ?></strong></p>
            <div class="channel-grid">
                <?php if (isset($channels['email'])): ?>
                <label class="channel-card" data-channel="email">
                    <input type="radio" name="channel_pick" value="email">
                    <span class="channel-icon">📧</span>
                    <span class="channel-meta"><b>Gmail / ইমেইল</b><small><?php echo htmlspecialchars($channels['email']['hint']); ?></small></span>
                </label>
                <?php endif; ?>
                <?php if (isset($channels['sms'])): ?>
                <label class="channel-card" data-channel="sms">
                    <input type="radio" name="channel_pick" value="sms">
                    <span class="channel-icon">📱</span>
                    <span class="channel-meta"><b>মোবাইল SMS</b><small><?php echo htmlspecialchars($channels['sms']['hint']); ?></small></span>
                </label>
                <?php endif; ?>
                <?php if (isset($channels['whatsapp'])): ?>
                <label class="channel-card" data-channel="whatsapp">
                    <input type="radio" name="channel_pick" value="whatsapp">
                    <span class="channel-icon">💬</span>
                    <span class="channel-meta"><b>WhatsApp</b><small><?php echo htmlspecialchars($channels['whatsapp']['hint']); ?></small></span>
                </label>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-login" id="sendOtpBtn" disabled>OTP পাঠান</button>
        </form>
        <?php endif; ?>

        <?php elseif ($step === 'no_contact'): ?>
        <div class="error">এই একাউন্টে ইমেইল, মোবাইল বা WhatsApp নম্বর সংরক্ষিত নেই।</div>
        <p class="text-sm text-gray-600 mb-4" style="line-height:1.5;">অ্যাডমিনকে বলুন <strong>সেটিংস → ইউজার</strong> থেকে আপনার মোবাইল/WhatsApp/ইমেইল যোগ করতে। তারপর আবার চেষ্টা করুন।</p>
        <a href="forgot_password.php?restart=1" class="btn-ghost" style="display:block;text-decoration:none;line-height:1.2;">আবার শুরু করুন</a>

        <?php elseif ($step === 'otp'): ?>
        <?php if (!empty($_SESSION['reset_otp_preview'])): ?>
        <div class="warn">
            <?php echo htmlspecialchars((string)($_SESSION['reset_mock_notice'] ?? 'গেটওয়ে সেটআপ নেই — টেস্ট OTP:')); ?>
            <div class="otp-preview"><?php echo htmlspecialchars((string)$_SESSION['reset_otp_preview']); ?></div>
            <small>লাইভে OTP পেতে সেটিংস → SMS / WhatsApp OTP / Brevo কনফিগার করুন।</small>
        </div>
        <?php endif; ?>
        <form action="process_password_reset.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="step" value="verify_otp">
            <div class="mb-4">
                <input type="text" name="otp" class="input-style otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required autofocus>
            </div>
            <button type="submit" class="btn-login">যাচাই করুন</button>
            <button type="button" class="btn-ghost" onclick="location.href='process_password_reset.php?resend=1'">OTP আবার পাঠান</button>
        </form>

        <?php elseif ($step === 'password'): ?>
        <form action="process_password_reset.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="step" value="set_password">
            <p class="text-sm text-gray-600 mb-3">ইউজার: <strong><?php echo htmlspecialchars((string)($_SESSION['reset_username'] ?? '')); ?></strong></p>
            <div class="lgn-field-wrap">
                <input type="password" name="new_password" id="newPass" class="input-style" placeholder="নতুন পাসওয়ার্ড (৮+ অক্ষর, অক্ষর+সংখ্যা)" minlength="8" required>
                <button type="button" class="lgn-eye" data-target="newPass"><?php echo $eye_icon; ?></button>
            </div>
            <div class="lgn-field-wrap">
                <input type="password" name="new_password_confirm" id="newPass2" class="input-style" placeholder="পাসওয়ার্ড আবার লিখুন" minlength="8" required>
                <button type="button" class="lgn-eye" data-target="newPass2"><?php echo $eye_icon; ?></button>
            </div>
            <button type="submit" class="btn-login">✅ পাসওয়ার্ড সেভ করুন</button>
        </form>
        <?php endif; ?>

        <a href="login.php" class="back-link">← লগইন পেজে ফিরে যান</a>
    </div>
    <script>
    document.querySelectorAll('.channel-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.channel-card').forEach(function(c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            var ch = card.getAttribute('data-channel');
            document.getElementById('channelInput').value = ch;
            document.getElementById('sendOtpBtn').disabled = false;
        });
    });
    document.querySelectorAll('.lgn-eye').forEach(function(btn) {
        var target = document.getElementById(btn.getAttribute('data-target'));
        if (!target) return;
        btn.addEventListener('mousedown', function() { target.type = 'text'; });
        btn.addEventListener('mouseup', function() { target.type = 'password'; });
        btn.addEventListener('mouseleave', function() { target.type = 'password'; });
        btn.addEventListener('touchstart', function(e) { e.preventDefault(); target.type = 'text'; });
        btn.addEventListener('touchend', function() { target.type = 'password'; });
    });
    </script>
</body>
</html>
