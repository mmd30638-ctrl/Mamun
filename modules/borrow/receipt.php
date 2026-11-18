<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT t.*, l.provider_name, l.provider_type FROM biz_loan_txns t JOIN biz_loans l ON l.id = t.loan_id WHERE t.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    die('রসিদ পাওয়া যায়নি।');
}
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ধার রসিদ</title>
<style>
body { font-family: sans-serif; margin: 16px; }
table { width: 100%; max-width: 640px; border-collapse: collapse; }
td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
@media print { button { display: none; } }
</style>
</head>
<body>
<h1>ধার রসিদ #<?php echo (int)$row['id']; ?></h1>
<table>
<tr><td>উৎস</td><td><?php echo htmlspecialchars($row['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><td>ধরন</td><td><?php echo htmlspecialchars($row['kind'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><td>তারিখ</td><td><?php echo htmlspecialchars($row['txn_date'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><td>আসল</td><td><?php echo number_format((float)$row['principal_part'], 2); ?></td></tr>
<tr><td>সুদ</td><td><?php echo number_format((float)$row['interest_part'], 2); ?></td></tr>
<tr><td>ফি</td><td><?php echo number_format((float)$row['fee_part'], 2); ?></td></tr>
<tr><td>সঞ্চয়</td><td><?php echo number_format((float)$row['savings_part'], 2); ?></td></tr>
<tr><td>জামানত</td><td><?php echo number_format((float)$row['security_part'], 2); ?></td></tr>
<tr><td>মোট</td><td><b><?php echo number_format((float)$row['total'], 2); ?></b></td></tr>
<tr><td>রেফারেন্স</td><td><?php echo htmlspecialchars((string)$row['reference_no'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
</table>
<p><button type="button" onclick="window.print()">প্রিন্ট</button></p>
<?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
</body>
</html>
