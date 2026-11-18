<?php
/**
 * মোবাইল ওয়ান-ট্যাপ ZIP পেজ (লোকাল প্রিভিউ)।
 *
 * HARD LOCK (2026-09-29): ফোনে দেওয়ার লিংক এই পেজ নয়।
 * প্রতিটা আপডেটে শুধু Filebin archive দিন:
 *   https://filebin.net/archive/<bin>/zip
 * এক বিনে একটাই ছোট ZIP (public_html/ প্রিফিক্স)।
 * সরাসরি ফাইল লিংক / দুইটা ফাইল / 9MB / 127.0.0.1 — সহজ আপডেটে বসবে না।
 * docs/PHONE_ONE_CLICK_ZIP.md
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/pack_zip.php';
admin_only();

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$force = isset($_GET['rebuild']) && (string)$_GET['rebuild'] === '1';
$go = strtolower((string)($_GET['go'] ?? ''));

try {
    $update = ame_pack_public_html_zip(null, $force);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ZIP</title></head><body style="font-family:sans-serif;padding:24px;background:#F4F7FB;color:#111827">';
    echo '<p>ZIP তৈরি হয়নি: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
}

if ($go === 'update' || $go === '1' || $go === 'stack' || $go === 'dl' || $go === 'full') {
    ame_stream_zip_file($update['path'], ame_zip_basename());
}

$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '';
$file = ame_zip_basename();
$mb = number_format(((int)$update['bytes']) / 1048576, 2);
$fallback = '';
$hint = __DIR__ . '/data/mobile_zip_url.txt';
if (is_file($hint)) {
    $try = trim((string)file_get_contents($hint));
    if (preg_match('#^https://[A-Za-z0-9._/?&=%-]+$#', $try)) {
        $fallback = $try;
    }
}
$binPage = '';
if ($fallback !== '' && preg_match('#^https://filebin\.net/archive/([^/]+)/zip#', $fallback, $m)) {
    $binPage = 'https://filebin.net/' . $m[1];
} elseif ($fallback !== '' && preg_match('#^(https://filebin\.net/[^/]+)/#', $fallback, $m)) {
    $binPage = $m[1];
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>এক ট্যাপে ZIP — APONSHOP <?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100vh;
            background: #F4F7FB !important;
            color: #111827 !important;
            font-family: "Noto Sans Bengali", "Segoe UI", system-ui, sans-serif;
        }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px 16px 32px; }
        .card {
            width: min(440px, 100%);
            background: #fff;
            border: 1px solid #D7DEE8;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        }
        .bar { background: #003F8F; color: #fff; padding: 16px 18px; font-weight: 800; font-size: 15px; letter-spacing: .02em; }
        .body { padding: 20px 18px 22px; }
        h1 { margin: 0 0 8px; font-size: 22px; color: #003F8F; }
        p { margin: 0 0 12px; line-height: 1.55; font-weight: 600; color: #111827; }
        .muted { color: #667085; font-weight: 500; font-size: 14px; }
        ol { margin: 0 0 18px; padding-left: 20px; color: #111827; font-weight: 600; line-height: 1.55; }
        li { margin: 0 0 6px; }
        .btn {
            display: flex; align-items: center; justify-content: center;
            width: 100%; min-height: 56px; padding: 16px 14px;
            border: 0; border-radius: 12px; background: #003F8F; color: #fff;
            font-size: 18px; font-weight: 800; text-decoration: none; text-align: center;
        }
        .btn:active { background: #002e68; }
        .btn2 {
            display: block; margin-top: 10px; min-height: 48px; padding: 12px;
            border-radius: 10px; background: #fff; color: #003F8F;
            border: 1px solid #D7DEE8; font-weight: 800; text-align: center; text-decoration: none;
        }
        .name { margin-top: 14px; font-size: 13px; color: #667085; word-break: break-all; }
        code { background: #F4F7FB; border: 1px solid #D7DEE8; border-radius: 6px; padding: 1px 6px; color: #111827; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="bar">APONSHOP <?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?> · সহজ আপডেট</div>
            <div class="body">
                <h1>এক ট্যাপে ZIP নামান</h1>
                <p class="muted">ফোনে নামবে <code><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></code> — এই ফাইলটাই ইঞ্জিন রুমে আপলোড করবেন।</p>
                <ol>
                    <li>নীল বাটনে একবার ট্যাপ করুন।</li>
                    <li>সেটিংস → টুলস → সহজ আপডেট খুলুন।</li>
                    <li>ধাপ ২-এ <b>Choose File</b> চাপুন, এই ZIP বাছুন।</li>
                    <li><b>ইঞ্জিন চালু — ইমপোর্ট করুন</b> চাপুন।</li>
                </ol>
                <a class="btn" href="/one_click.php?go=update" download="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">⬇️ ZIP ডাউনলোড (<?php echo htmlspecialchars($mb, ENT_QUOTES, 'UTF-8'); ?> MB)</a>
                <?php if ($fallback !== ''): ?>
                <a class="btn2" href="<?php echo htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8'); ?>">📱 ফোন — আসল ZIP নামান</a>
                <?php endif; ?>
                <?php if ($binPage !== ''): ?>
                <a class="btn2" href="<?php echo htmlspecialchars($binPage, ENT_QUOTES, 'UTF-8'); ?>">ফাইলবিন পেজ খুলুন (তারপর ফাইল ট্যাপ)</a>
                <?php endif; ?>
                <p class="muted">ফাইলবিনের সরাসরি লিংক কখনো HTML পেজ নামান — সেটা সহজ আপডেটে বসবে না। নীল বাটন বা «আসল ZIP» লিংক ব্যবহার করুন।</p>
                <p class="name"><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?><br>SHA256: <?php echo htmlspecialchars($update['sha256'], ENT_QUOTES, 'UTF-8'); ?><br><code>uploads</code> ও <code>.env</code> এই ফাইলে নেই — লাইভ ছবি মুছবেন না।</p>
            </div>
        </div>
    </div>
</body>
</html>
