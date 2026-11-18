<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

$customer_id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
if (!$c) { die("কাস্টমার পাওয়া যায়নি!"); }

$receivable = (float)$c['receivable_balance'];
$payable = (float)$c['payable_balance'];
$max_netting = min($receivable, $payable);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">
<meta charset="UTF-8">
<title>Auto Netting - <?php echo htmlspecialchars($c['name']); ?></title>
<style>
    body { font-family: sans-serif; background: #f4f7f9; padding: 20px; }
    .netting-card { background: #fff; max-width: 600px; margin: 0 auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .bal-box { display: flex; justify-content: space-between; margin-bottom: 20px; text-align: center; }
    .bal-item { flex: 1; padding: 15px; border-radius: 8px; }
    .bg-dr { background: #FFEBEE; color: #C62828; } .bg-cr { background: #E8F5E9; color: #2E7D32; }
</style>

</head>
<body>
<div class="netting-card">
    <h3>🔄 কাস্টমার-সাপ্লায়ার অটো-নেটিং</h3>
    <p><strong>গ্রাহক:</strong> <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['customer_code']); ?>)</p>
    <div class="bal-box">
        <div class="bal-item bg-dr"><small>দোকানের পাওনা</small><h2>৳<?php echo number_format($receivable, 2); ?></h2></div>
        <div class="bal-item bg-cr" style="margin-left: 10px;"><small>দোকানের দেনা</small><h2>৳<?php echo number_format($payable, 2); ?></h2></div>
    </div>
    <?php if($max_netting > 0): ?>
    <form action="actions/process_netting.php" method="POST">
        <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
        <div style="margin-bottom: 15px;">
            <label style="font-weight:bold; font-size:13px;">অ্যাডজাস্টমেন্টের পরিমাণ (৳) [সর্বোচ্চ: ৳<?php echo number_format($max_netting, 2); ?>]</label>
            <input type="number" step="0.01" name="adjust_amount" max="<?php echo $max_netting; ?>" value="<?php echo $max_netting; ?>" required style="width:100%; padding:10px; margin-top:5px; border:1px solid #ccc; border-radius:6px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="font-weight:bold; font-size:13px;">নোট / বিবরণ</label>
            <textarea name="remarks" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;" placeholder="সমন্বয়ের কারণ..."></textarea>
        </div>
        <button type="submit" style="width:100%; padding:12px; background:#2E7D32; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">⚡ নেটিং সেটেলমেন্ট নিশ্চিত করুন</button>
    </form>
    <?php else: ?>
    <div style="padding:15px; background:#FFF3E0; color:#E65100; border-radius:6px; text-align:center;">⚠️ নেটিংয়ের জন্য পর্যাপ্ত দেনা-পাওনা নেই।</div>
    <?php endif; ?>
</div>
</body>
</html>