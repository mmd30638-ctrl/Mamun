<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$q = trim((string)($_GET['q'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));
$products = ame_workshop_products($db);
$rows = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        "SELECT s.*, p.name AS product_name, c.name AS customer_name
         FROM product_serials s
         LEFT JOIN products p ON p.id = s.product_id
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.serial_no LIKE ? OR p.name LIKE ?
         ORDER BY s.id DESC LIMIT 80"
    );
    $stmt->bind_param('ss', $like, $like);
} else {
    $stmt = $db->prepare(
        "SELECT s.*, p.name AS product_name, c.name AS customer_name
         FROM product_serials s
         LEFT JOIN products p ON p.id = s.product_id
         LEFT JOIN customers c ON c.id = s.customer_id
         ORDER BY s.id DESC LIMIT 50"
    );
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$status_bn = [
    'in_stock' => 'স্টকে',
    'sold' => 'বিক্রি',
    'on_job' => 'জবে',
    'returned' => 'ফেরত',
    'scrapped' => 'বাতিল',
];
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>🔢 সিরিয়াল / IMEI খাতা</h3>
    <?php if ($ok === '1'): ?><div class="ws-flash">সিরিয়াল সংরক্ষণ হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>

    <form class="ws-card" method="get">
        <input type="search" name="q" value="<?php echo ame_workshop_h($q); ?>" placeholder="সিরিয়াল বা পণ্যের নাম">
        <button class="ws-btn" type="submit">খুঁজুন</button>
    </form>

    <form class="ws-card" method="post" action="serial_process.php">
        <?php echo csrf_field(); ?>
        <b>নতুন সিরিয়াল</b>
        <label>সিরিয়াল / IMEI *</label>
        <input type="text" name="serial_no" required>
        <label>পণ্য</label>
        <select name="product_id">
            <option value="">—</option>
            <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo ame_workshop_h($p['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="ws-row">
            <div>
                <label>ওয়ারেন্টি (মাস)</label>
                <input type="number" min="0" name="warranty_months" value="0">
            </div>
            <div>
                <label>অবস্থা</label>
                <select name="status">
                    <option value="in_stock">স্টকে</option>
                    <option value="sold">বিক্রি</option>
                    <option value="on_job">জবে</option>
                </select>
            </div>
        </div>
        <label>নোট</label>
        <input type="text" name="notes">
        <button class="ws-btn" type="submit">খাতায় যোগ</button>
    </form>

    <div class="ws-card" style="overflow-x:auto;">
        <?php if (!$rows): ?>
            <div class="ws-empty"><?php echo $q !== '' ? 'কোনো সিরিয়াল মেলেনি।' : 'এখনো সিরিয়াল নেই। স্টক বা জবে নম্বর তুলে রাখুন।'; ?></div>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>সিরিয়াল</th><th>পণ্য</th><th>অবস্থা</th><th>ওয়ারেন্টি</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><b><?php echo ame_workshop_h($row['serial_no']); ?></b>
                            <?php if ($row['customer_name']): ?><div class="ws-hint"><?php echo ame_workshop_h($row['customer_name']); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo ame_workshop_h($row['product_name'] ?: '—'); ?></td>
                        <td><span class="ws-badge"><?php echo ame_workshop_h($status_bn[$row['status']] ?? $row['status']); ?></span>
                            <?php if ((int)$row['job_id'] > 0): ?>
                                <div class="ws-hint"><a href="job_view.php?id=<?php echo (int)$row['job_id']; ?>">জব</a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['warranty_expiry'])): ?>
                                <?php echo ame_workshop_h($row['warranty_expiry']); ?>
                            <?php elseif ((int)$row['warranty_months'] > 0): ?>
                                <?php echo (int)$row['warranty_months']; ?> মাস
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <a class="ws-btn ghost" href="index.php">জব তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
