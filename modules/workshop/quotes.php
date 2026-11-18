<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$q = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT * FROM workshop_quotes ORDER BY id DESC LIMIT 80';
$params = [];
$types = '';
if ($q !== '') {
    $sql = 'SELECT * FROM workshop_quotes WHERE quote_no LIKE ? OR customer_name LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT 80';
    $like = '%' . $q . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}
$stmt = $db->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$labels = ['open' => 'খোলা', 'converted' => 'জবে রূপান্তর', 'cancelled' => 'বাতিল'];
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>📄 কোটেশন তালিকা</h3>
    <a class="ws-btn" href="quote_new.php">নতুন কোটেশন</a>
    <form class="ws-card" method="get">
        <input type="search" name="q" value="<?php echo ame_workshop_h($q); ?>" placeholder="নম্বর বা নাম">
        <button class="ws-btn secondary" type="submit">খুঁজুন</button>
    </form>
    <div class="ws-card" style="overflow-x:auto;">
        <?php if (!$rows): ?>
            <div class="ws-empty">কোনো কোটেশন নেই। আগে আনুমানিক বিল দিন, রাজি হলে জব খুলুন।</div>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>নম্বর</th><th>ক্রেতা</th><th>মোট</th><th>অবস্থা</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="quote_view.php?id=<?php echo (int)$row['id']; ?>"><b><?php echo ame_workshop_h($row['quote_no']); ?></b></a></td>
                        <td><?php echo ame_workshop_h($row['customer_name']); ?><div class="ws-hint"><?php echo ame_workshop_h($row['item_name']); ?></div></td>
                        <td><?php echo number_format((float)$row['grand_total'], 2); ?></td>
                        <td><span class="ws-badge"><?php echo ame_workshop_h($labels[$row['status']] ?? $row['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <a class="ws-btn ghost" href="index.php">জব তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
