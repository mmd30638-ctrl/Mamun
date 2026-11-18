<?php
/**
 * মোবাইল ওয়ান-ক্লিক ZIP — ইউজার ট্যাপ ছাড়া অটো-রিফ্রেশ নয় (মোবাইল ব্লক করে)।
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/pack_zip.php';

// লগইনবিহীন কেউ যেন সরাসরি এই লিংকে পুরো সোর্স কোড ZIP না নামাতে পারে —
// শুধু অ্যাডমিন সেশন থাকলেই এই পেজ/ZIP পাওয়া যাবে (Settings → Tools থেকেই আসতে হবে)।
admin_only();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$go = isset($_GET['go']) && (string)$_GET['go'] !== '' && (string)$_GET['go'] !== '0';
$force = isset($_GET['rebuild']) && (string)$_GET['rebuild'] === '1';

try {
    $pack = ame_pack_public_html_zip(null, $force);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ZIP</title></head><body>';
    echo '<p>ZIP তৈরি হয়নি: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
    exit;
}

if ($go) {
    ame_stream_zip_file($pack['path'], 'Aponshop_POS_STACK_UPDATE.zip');
}

$kb = $pack['bytes'] / 1024;
$size = $kb >= 1024 ? (number_format($kb / 1024, 1) . ' MB') : (number_format($kb, 0) . ' KB');
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '';
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$file = 'Aponshop_POS_STACK_UPDATE.zip';
$static = '/download_static.php?file=Aponshop.zip';
$has_static = is_file(__DIR__ . '/downloads/Aponshop.zip');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>ZIP ডাউনলোড — <?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: "Noto Sans Bengali", "Segoe UI", system-ui, sans-serif; margin: 0; background: #F4F7FB; color: #111827; }
        .box { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center; box-sizing: border-box; }
        h1 { font-size: 22px; margin: 0 0 8px; color: #003F8F; }
        p { color: #111827; margin: 0 0 18px; line-height: 1.5; max-width: 28rem; font-weight: 600; }
        a.btn { display: block; width: min(420px, 100%); padding: 18px 16px; border-radius: 12px; background: #003F8F; color: #fff; font-weight: 800; font-size: 18px; text-decoration: none; box-sizing: border-box; }
        a.btn2 { margin-top: 10px; background: #04933A; }
        small { display: block; margin-top: 14px; color: #667085; word-break: break-all; font-size: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>APONSHOP v<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>নীল বাটনে একবার ট্যাপ করুন — ছোট কোড ZIP ফোনে সেভ হবে। ছবি (<code>uploads</code>) এই ফাইলে নেই, লাইভ সাইটের ছবি মুছবেন না।</p>
        <a class="btn" id="ameZipBtn" href="/download.php?go=1" download="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">⬇️ ZIP ডাউনলোড (<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>)</a>
        <?php if ($has_static): ?>
        <a class="btn btn2" href="<?php echo htmlspecialchars($static, ENT_QUOTES, 'UTF-8'); ?>" download="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">📎 সরাসরি ফাইল (যদি উপরেরটা না নামে)</a>
        <?php endif; ?>
        <small><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?><br>SHA256: <?php echo htmlspecialchars($pack['sha256'], ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
</body>
</html>
