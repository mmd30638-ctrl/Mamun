<?php
/**
 * 🔔 AponShop ব্র্যান্ডেড নোটিফিকেশন পেজ
 * ------------------------------------------------------------
 * ব্রাউজারের সাদামাটা alert() বক্সের বদলে ব্যবহারের জন্য — একটা
 * অ্যাকশন (process_*.php) সফল/ব্যর্থ হওয়ার পর ইউজারকে সুন্দর,
 * অ্যানিমেটেড কার্ড দেখিয়ে তারপর নির্দিষ্ট পেজে নিয়ে যায়।
 *
 * ব্যবহার:
 *   require_once __DIR__ . '/../../../includes/ame_notify.php';
 *   ame_notify('বার্তা এখানে', 'redirect_url.php', 'success'); // অথবা 'error'
 *   // এই ফাংশন কল করার পর কোড আর চলবে না (exit করে দেয়)
 *
 * কালার হার্ডকোড না করে assets/css/ame-theme-vars.css এর
 * কেন্দ্রীয় ভ্যারিয়েবল থেকে নেওয়া হয়েছে।
 */
if (!function_exists('ame_notify')) {
    function ame_notify(string $message, string $redirect_url, string $type = 'success', int $auto_ms = 1600): void
    {
        $safe_msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $is_err   = $type === 'error';
        $tone_var = $is_err ? '--brand-danger' : '--brand-success';
        $tone_bg  = $is_err ? '--brand-danger-soft' : '--brand-success-soft';

        $icon_path = $is_err
            ? 'M16 16 L36 36 M36 16 L16 36'
            : 'M14 27 L22 35 L38 17';
        ?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>অপনশপ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
    html, body {
        margin: 0; padding: 0; min-height: 100vh;
        background: var(--brand-backdrop);
        font-family: 'Hind Siliguri', 'Segoe UI', sans-serif;
    }
    .ame-ntf-wrap {
        position: fixed; inset: 0; display: flex; align-items: center;
        justify-content: center; padding: 18px; box-sizing: border-box;
    }
    .ame-ntf-card {
        background: var(--brand-bg); border-radius: 20px;
        padding: 32px 26px 24px; max-width: 340px; width: 100%;
        text-align: center; box-shadow: var(--shadow-lg);
        animation: ameNtfPop .35s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes ameNtfPop {
        from { opacity: 0; transform: scale(.85) translateY(10px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    .ame-ntf-logo {
        width: 38px; height: 38px; object-fit: contain;
        margin-bottom: 8px; opacity: .82;
    }
    .ame-ntf-icon {
        width: 76px; height: 76px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 4px auto 18px; background: var(<?php echo $tone_bg; ?>);
    }
    .ame-ntf-icon svg { width: 42px; height: 42px; }
    .ame-ntf-ring {
        fill: none; stroke: var(<?php echo $tone_var; ?>); stroke-width: 2.5;
        stroke-dasharray: 145; stroke-dashoffset: 145;
        animation: ameNtfRing .5s ease-out forwards;
    }
    .ame-ntf-mark {
        fill: none; stroke: var(<?php echo $tone_var; ?>); stroke-width: 3.5;
        stroke-linecap: round; stroke-linejoin: round;
        stroke-dasharray: 42; stroke-dashoffset: 42;
        animation: ameNtfMark .35s .4s ease-out forwards;
    }
    @keyframes ameNtfRing { to { stroke-dashoffset: 0; } }
    @keyframes ameNtfMark { to { stroke-dashoffset: 0; } }
    .ame-ntf-msg {
        font-size: 16px; font-weight: 600; color: var(--brand-black);
        line-height: 1.55; margin: 0 0 22px;
    }
    .ame-ntf-btn {
        display: inline-block; background: var(--brand-navy); color: var(--brand-white);
        border: none; border-radius: 12px; padding: 12px 36px;
        font-size: 15px; font-weight: 700; font-family: inherit;
        cursor: pointer; text-decoration: none; letter-spacing: .2px;
    }
    .ame-ntf-btn:active { opacity: .82; }
    .ame-ntf-bar {
        height: 3px; background: var(--brand-border); border-radius: 3px;
        margin-top: 18px; overflow: hidden;
    }
    .ame-ntf-bar i {
        display: block; height: 100%; width: 100%;
        background: var(<?php echo $tone_var; ?>);
        transform-origin: left;
        animation: ameNtfBar <?php echo (int)$auto_ms; ?>ms linear forwards;
    }
    @keyframes ameNtfBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>
</head>
<body>
<div class="ame-ntf-wrap">
    <div class="ame-ntf-card">
        <img src="/assets/img/ma-logo.png" class="ame-ntf-logo" alt="">
        <div class="ame-ntf-icon">
            <svg viewBox="0 0 52 52">
                <circle class="ame-ntf-ring" cx="26" cy="26" r="23"></circle>
                <path class="ame-ntf-mark" d="<?php echo $icon_path; ?>"></path>
            </svg>
        </div>
        <p class="ame-ntf-msg"><?php echo $safe_msg; ?></p>
        <a href="#" class="ame-ntf-btn" id="ameNtfOk">ওকে</a>
        <div class="ame-ntf-bar"><i></i></div>
    </div>
</div>
<script>
(function () {
    var url = <?php echo json_encode($redirect_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var timer = setTimeout(function () { window.location.href = url; }, <?php echo (int)$auto_ms; ?>);
    var okBtn = document.getElementById('ameNtfOk');
    if (okBtn) {
        okBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearTimeout(timer);
            window.location.href = url;
        });
    }
})();
</script>
</body>
</html>
<?php
        exit;
    }
}
