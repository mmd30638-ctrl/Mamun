<?php
/* 10.98.694 */
require_once '../../includes/config.php';
require_once '../../includes/worker_desk.php';

$payId = (int)($_GET['pay'] ?? 0);
$row = null;
if ($payId > 0) {
    $stmt = @$db->prepare(
        "SELECT s.id, s.status, s.amount, s.payment_date, s.notes, s.employee_id, e.name, a.account_name
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
if ($row && !ame_worker_can_manage() && (int)$row['employee_id'] !== ame_worker_self_id($db)) {
    http_response_code(403);
    $row = null;
}
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$shop = function_exists('shop_name') ? shop_name() : '';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>পেমেন্ট ভাউচার</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", "Noto Sans Bengali", sans-serif; background: #f4f6f8; color: #111; }
        article { max-width: 440px; margin: 24px auto; background: #fff; border-radius: 16px; padding: 22px; }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p { display: flex; justify-content: space-between; gap: 12px; font-weight: 700; }
        a { color: #0f766e; }
    </style>
</head>
<body>
<article>
<?php if (!$row): ?>
    <h1>ভাউচার পাওয়া যায়নি</h1>
    <a href="/index.php">হোম পেজে যান</a>
<?php else: ?>
    <h1><?php echo $h($shop); ?></h1>
    <h2>কর্মী পেমেন্ট #<?php echo (int)$row['id']; ?></h2>
    <?php if (($row['status'] ?? '') === 'reversed'): ?><p style="color:#9a3412;background:#ffedd5;border-radius:10px;padding:8px 10px">এই বেতন ফেরত আনা হয়েছে (বাতিল)। টাকা অ্যাকাউন্টে ফিরেছে।</p><?php endif; ?>
    <p><span>তারিখ</span><strong><?php echo $h($row['payment_date']); ?></strong></p>
    <p><span>কর্মী</span><strong><?php echo $h($row['name']); ?></strong></p>
    <p><span>অ্যাকাউন্ট</span><strong><?php echo $h($row['account_name']); ?></strong></p>
    <p><span>পরিশোধ</span><strong>৳ <?php echo number_format((float)$row['amount'], 2); ?></strong></p>
    <?php if (trim((string)($row['notes'] ?? '')) !== ''): ?><p><span>নোট</span><strong><?php echo $h($row['notes']); ?></strong></p><?php endif; ?>
    <p><a href="/index.php">হোম পেজে যান</a></p>
<?php endif; ?>
</article>
</body>
</html>
