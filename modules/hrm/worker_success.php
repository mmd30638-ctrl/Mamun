<?php
/* 10.98.694 */
require_once '../../includes/config.php';
require_once '../../includes/worker_desk.php';
require_once '../../includes/employee_hub.php';

if (!ame_worker_can_manage()) {
    header('Location: worker_ledger.php');
    exit;
}
$payId = (int)($_GET['pay'] ?? 0);
$row = null;
if ($payId > 0) {
    $stmt = @$db->prepare(
        "SELECT s.id, s.status, s.amount, s.payment_date, s.notes, s.employee_id, e.name, e.phone, a.account_name
         FROM salaries s
         LEFT JOIN employees e ON e.id = s.employee_id
         LEFT JOIN accounts a ON a.id = s.account_id
         WHERE s.id = ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $payId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}
$amount = $row ? round((float)$row['amount'], 2) : 0.0;
$latest = 0.0;
if ($row) {
    $statement = ame_worker_desk_statement($db, (int)$row['employee_id']);
    $latest = round((float)$statement['closing'], 2);
}
$shop = function_exists('shop_name') ? shop_name() : '';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');
$name = (string)($row['name'] ?? '');
$phone = preg_replace('/\D+/', '', (string)($row['phone'] ?? '')) ?? '';
$view = 'worker_voucher.php?pay=' . $payId;
$file = 'worker-pay-' . $payId;
$latestLabel = $latest < -0.009 ? 'অতিরিক্ত নেওয়া' : ($latest > 0.009 ? 'সর্বশেষ ব্যালেন্স' : 'হিসাব সমান');
$latestText = ($latest < -0.009 ? '−' : '') . '৳' . number_format(abs($latest), 2);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পেমেন্ট সম্পন্ন হয়েছে</title>
    <script src="/assets/js/html2canvas.min.js?v=<?php echo $ver; ?>"></script>
    <style>
        html, body { margin: 0; background: #f4f6f8; color: #111827; font-family: "Segoe UI", "Noto Sans Bengali", sans-serif; }
        .pur-ok { max-width: 440px; margin: 0 auto; min-height: 100vh; background: #fff; padding: 28px 22px 32px; box-sizing: border-box; }
        .pur-ok-mark { width: 84px; height: 84px; margin: 18px auto 16px; border-radius: 50%; background: #22c55e; display: flex; align-items: center; justify-content: center; }
        .pur-ok h1 { margin: 0; text-align: center; font-size: 26px; line-height: 1.35; }
        .pur-ok-amt { margin: 18px 0 4px; text-align: center; font-size: 40px; font-weight: 800; }
        .pur-ok-sub, .pur-ok-party { text-align: center; }
        .pur-ok-sub { margin: 0; color: #6b7280; font-weight: 700; }
        .pur-ok-party { margin: 14px 0 0; font-size: 18px; font-weight: 800; }
        .pur-ok-meta { margin: 18px 0 0; display: grid; gap: 8px; }
        .pur-ok-meta div { display: flex; justify-content: space-between; gap: 12px; font-weight: 700; color: #4b5563; }
        .pur-ok-meta strong { color: #111827; }
        .pur-ok-acts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 28px; }
        .pur-ok-acts button, .pur-ok-acts a { display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; color: #e11d48; background: #fff; border: 0; font: inherit; font-weight: 800; cursor: pointer; }
        .pur-ok-ico { width: 74px; height: 74px; border-radius: 50%; border: 2px solid #fb7185; display: flex; align-items: center; justify-content: center; }
        .pur-ok-ico svg { width: 30px; height: 30px; stroke: #e11d48; fill: none; stroke-width: 2; }
        .pur-ok-home { display: block; margin-top: 28px; padding: 14px 16px; text-align: center; text-decoration: none; color: #e11d48; font-weight: 800; font-size: 18px; border: 2px solid #fb7185; border-radius: 12px; }
        .pur-sheet { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: flex-end; justify-content: center; padding: 12px; z-index: 20; }
        .pur-sheet[hidden] { display: none; }
        .pur-sheet-card { width: min(420px, 100%); background: #fff; border-radius: 16px; padding: 16px; }
        .pur-sheet-card button { display: block; width: 100%; margin: 0 0 8px; padding: 12px; border-radius: 10px; border: 1px solid #e5e7eb; background: #f8fafc; text-align: left; font: inherit; font-weight: 800; cursor: pointer; }
        #memo-container { position: fixed; left: -12000px; top: 0; width: 720px; background: #fff; padding: 28px; }
    </style>
</head>
<body>
<?php if (!$row): ?>
<main class="pur-ok">
    <h1>পেমেন্টের রসিদ পাওয়া যায়নি</h1>
    <a class="pur-ok-home" href="worker_pay.php">আবার পেমেন্ট</a>
    <a class="pur-ok-home" href="/index.php">হোম পেজে যান</a>
</main>
<?php else: ?>
<main class="pur-ok">
    <div class="pur-ok-mark" aria-hidden="true">
        <svg width="46" height="46" viewBox="0 0 46 46" fill="none"><path d="M10 24.5 18.5 33 36 14" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <?php if (($row['status'] ?? '') === 'reversed'): ?><h1 style="color:#9a3412">এই বেতন ফেরত আনা হয়েছে</h1><?php else: ?><h1>পেমেন্ট সম্পন্ন হয়েছে</h1><?php endif; ?>
    <p class="pur-ok-amt">৳ <?php echo number_format($amount, 2); ?></p>
    <p class="pur-ok-sub">কর্মীর পেমেন্ট</p>
    <p class="pur-ok-party"><?php echo $h($name); ?></p>
    <div class="pur-ok-meta">
        <div><span>রসিদ</span><strong>#<?php echo (int)$row['id']; ?></strong></div>
        <div><span>অ্যাকাউন্ট</span><strong><?php echo $h($row['account_name'] ?? ''); ?></strong></div>
        <div><span><?php echo $h($latestLabel); ?></span><strong><?php echo $h($latestText); ?></strong></div>
    </div>
    <div class="pur-ok-acts">
        <button type="button" id="purShare"><span class="pur-ok-ico"><svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="2.2"/><circle cx="6" cy="12" r="2.2"/><circle cx="18" cy="19" r="2.2"/><path d="M8 11.2 16 6.4M8 12.8l8 4.6"/></svg></span>শেয়ার</button>
        <button type="button" id="purDownload"><span class="pur-ok-ico"><svg viewBox="0 0 24 24"><path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg></span>ডাউনলোড</button>
        <a href="<?php echo $h($view); ?>"><span class="pur-ok-ico"><svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg></span>দেখুন</a>
    </div>
    <a class="pur-ok-home" href="/index.php">হোম পেজে যান</a>
</main>
<div class="pur-sheet" id="purShareSheet" hidden>
    <div class="pur-sheet-card">
        <strong>শেয়ার করুন</strong>
        <button type="button" id="purWa">হোয়াটসঅ্যাপ</button>
        <button type="button" id="purMs">মেসেঞ্জার</button>
        <button type="button" id="purImg">ভাউচারের ছবি শেয়ার</button>
        <button type="button" id="purShareClose">বন্ধ</button>
    </div>
</div>
<article id="memo-container">
    <h1><?php echo $h($shop); ?></h1>
    <h2>কর্মী পেমেন্ট #<?php echo (int)$row['id']; ?></h2>
    <p><?php echo $h($row['payment_date'] ?? ''); ?></p>
    <p>কর্মী: <?php echo $h($name); ?></p>
    <p>অ্যাকাউন্ট: <?php echo $h($row['account_name'] ?? ''); ?></p>
    <p>পরিশোধ: ৳ <?php echo number_format($amount, 2); ?></p>
    <p><?php echo $h($latestLabel); ?>: <?php echo $h($latestText); ?></p>
</article>
<?php
$share = $shop . "\nপেমেন্ট সম্পন্ন হয়েছে\nকর্মী " . $name
    . "\n৳" . number_format($amount, 2)
    . "\n" . $latestLabel . ' ' . $latestText;
$payload = json_encode([
    'file' => $file . '.png',
    'title' => $shop . ' · কর্মী পেমেন্ট #' . $payId,
    'text' => $share,
    'phone' => $phone,
    'view' => $view,
    'fail' => 'শেয়ার হয়নি',
    'caption' => false,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
?>
<script src="/assets/js/ame-memo-file.js?v=<?php echo $ver; ?>"></script>
<script>
window.AME_MEMO = <?php echo $payload; ?>;
(function () {
    var sheet = document.getElementById('purShareSheet');
    var meta = window.AME_MEMO || {};
    function viewUrl() { return new URL(meta.view || 'worker_voucher.php', window.location.href).href; }
    function message() { return (meta.text || '') + '\n' + viewUrl(); }
    function sharePdf() {
        sheet.hidden = true;
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture('share', <?php echo json_encode($file); ?>, 'memo-container');
        }
    }
    document.getElementById('purShare').addEventListener('click', sharePdf);
    document.getElementById('purShareClose').addEventListener('click', function () { sheet.hidden = true; });
    sheet.addEventListener('click', function (e) { if (e.target === sheet) sheet.hidden = true; });
    document.getElementById('purWa').addEventListener('click', function () {
        sheet.hidden = true;
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture('wa', <?php echo json_encode($file); ?>, 'memo-container');
        }
    });
    document.getElementById('purMs').addEventListener('click', sharePdf);
    document.getElementById('purImg').addEventListener('click', sharePdf);
    document.getElementById('purDownload').addEventListener('click', function () {
        if (typeof window.ameMemoCapture === 'function') window.ameMemoCapture('download', <?php echo json_encode($file); ?>, 'memo-container');
    });
})();
</script>
<?php endif; ?>
</body>
</html>
