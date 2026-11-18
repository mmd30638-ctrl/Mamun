<?php
/* 10.98.714 — পার্টনারশিপ খরচের প্রিন্টযোগ্য ভাউচার */
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
pa_guard();
ensure_partner_accounting_schema($db);

$row = pa_expense_by_id($db, (int)($_GET['id'] ?? 0));
if (!$row) {
    http_response_code(404);
    die('ভাউচার পাওয়া যায়নি।');
}
$h = static function ($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$cats = pa_expense_category_labels($db);
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$logo = function_exists('shop_logo_url') ? shop_logo_url('/assets/img/header-logo.png') : '/assets/img/header-logo.png';
$void = ($row['status'] ?? 'active') === 'void';
$byName = '';
$uid = (int)($row['created_by'] ?? 0);
if ($uid > 0) {
    $u = $db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if ($u) {
        $u->bind_param('i', $uid);
        $u->execute();
        $byName = (string)($u->get_result()->fetch_assoc()['username'] ?? '');
        $u->close();
    }
}
$amount = pa_money($row['amount']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>খরচ ভাউচার <?php echo $h(pa_expense_voucher_label($row)); ?></title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #eef2f7; font-family: 'Noto Sans Bengali', 'Segoe UI', Arial, sans-serif; color: #142033; }
    .bar { max-width: 760px; margin: 12px auto 0; padding: 0 12px; display: flex; gap: 8px; }
    .bar a, .bar button { border: 1px solid #0b5cad; background: #fff; color: #0b5cad; border-radius: 10px; padding: 10px 16px; font-size: 16px; font-weight: 700; text-decoration: none; cursor: pointer; }
    .bar button { background: #0b5cad; color: #fff; }
    .v { position: relative; max-width: 760px; margin: 12px auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 28px; }
    .head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #0b5cad; padding-bottom: 12px; }
    .head img { height: 56px; width: auto; }
    .head h1 { margin: 0; font-size: 22px; }
    .head small { color: #526074; display: block; }
    .title { display: flex; justify-content: space-between; align-items: baseline; margin: 16px 0 8px; }
    .title h2 { margin: 0; font-size: 20px; letter-spacing: .5px; }
    .no { font-weight: 800; font-size: 18px; color: #0b5cad; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: 16px; vertical-align: top; }
    td:first-child { width: 34%; color: #526074; font-weight: 700; }
    .amt { font-size: 28px; font-weight: 800; color: #b91c1c; }
    .sign { display: flex; justify-content: space-between; gap: 20px; margin-top: 56px; }
    .sign div { flex: 1; border-top: 1px solid #142033; text-align: center; padding-top: 6px; font-size: 14px; color: #526074; }
    .stamp { position: absolute; top: 120px; right: 28px; border: 4px solid #b91c1c; color: #b91c1c; font-size: 34px; font-weight: 900; padding: 4px 16px; transform: rotate(-12deg); opacity: .8; }
    .foot { margin-top: 18px; font-size: 12px; color: #64748b; text-align: center; }
    @media print { body { background: #fff; } .bar { display: none; } .v { border: 0; margin: 0; max-width: none; } @page { size: A4; margin: 14mm; } }
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">প্রিন্ট / PDF</button><a href="partner_accounting.php?month=<?php echo $h($row['period_ym']); ?>&amp;tab=draw">ফিরে যান</a></div>
<div class="v">
    <div class="head">
        <img src="<?php echo $h($logo); ?>" alt="">
        <div><h1><?php echo $h($shop); ?></h1><small>পার্টনারশিপ খরচ ভাউচার</small></div>
    </div>
    <?php if ($void): ?><div class="stamp">বাতিল</div><?php endif; ?>
    <div class="title"><h2>EXPENSE VOUCHER</h2><span class="no"><?php echo $h(pa_expense_voucher_label($row)); ?></span></div>
    <table>
        <tr><td>তারিখ</td><td><?php echo $h(date('d-m-Y', strtotime((string)$row['expense_date']))); ?></td></tr>
        <tr><td>খাত</td><td><?php echo $h($cats[$row['category']] ?? $row['category']); ?></td></tr>
        <tr><td>প্রাপক</td><td><?php echo $h((string)($row['payee'] ?? '')) ?: '—'; ?></td></tr>
        <tr><td>বিবরণ</td><td><?php echo $h($row['description']); ?></td></tr>
        <tr><td>পেমেন্ট মাধ্যম</td><td><?php echo $h(pa_expense_payment_label($db, $row, true)); ?></td></tr>
        <?php if (trim((string)($row['reference'] ?? '')) !== ''): ?><tr><td>রেফারেন্স</td><td><?php echo $h($row['reference']); ?></td></tr><?php endif; ?>
        <?php if (trim((string)($row['note'] ?? '')) !== ''): ?><tr><td>নোট</td><td><?php echo $h($row['note']); ?></td></tr><?php endif; ?>
        <tr><td>টাকা</td><td class="amt">৳<?php echo $h(number_format($amount, 2)); ?></td></tr>
        <?php if ($void): ?><tr><td>বাতিলের কারণ</td><td><?php echo $h((string)($row['void_reason'] ?? '')); ?></td></tr><?php endif; ?>
    </table>
    <div class="sign"><div>প্রাপকের স্বাক্ষর</div><div>অনুমোদনকারী</div></div>
    <div class="foot">এন্ট্রি: <?php echo $h($byName !== '' ? $byName : '—'); ?> · <?php echo $h(!empty($row['created_at']) ? date('d-m-Y H:i', strtotime((string)$row['created_at'])) : ''); ?> · কম্পিউটারে তৈরি ভাউচার</div>
</div>
</body>
</html>
