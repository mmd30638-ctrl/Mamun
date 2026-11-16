<?php
require_once __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM workshop_quotes WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quote) {
    header('Location: quotes.php?err=' . rawurlencode('কোটেশন পাওয়া যায়নি।'));
    exit;
}
$it = $db->prepare('SELECT * FROM workshop_quote_items WHERE quote_id = ? ORDER BY id');
$it->bind_param('i', $id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
$it->close();
include __DIR__ . '/../../partials/header.php';
$labels = ['open' => 'খোলা', 'converted' => 'জবে রূপান্তর', 'cancelled' => 'বাতিল'];
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>📄 <?php echo ame_workshop_h($quote['quote_no']); ?></h3>
    <div class="ws-card">
        <p><b><?php echo ame_workshop_h($quote['customer_name']); ?></b>
            <?php if ($quote['phone']): ?> · <?php echo ame_workshop_h($quote['phone']); ?><?php endif; ?></p>
        <p><?php echo ame_workshop_h($quote['item_name']); ?>
            <?php
            $qm = !empty($quote['machine_id']) ? ame_workshop_machine($db, (int)$quote['machine_id']) : null;
            if ($qm): ?>
                <span class="ws-hint"> · <?php echo ame_workshop_h(ame_workshop_machine_label($qm)); ?></span>
            <?php endif; ?>
        </p>
        <p><span class="ws-badge"><?php echo ame_workshop_h($labels[$quote['status']] ?? $quote['status']); ?></span></p>
        <table class="ws-table">
            <thead><tr><th>বিবরণ</th><th>টাকা</th></tr></thead>
            <tbody>
                <tr><td>শ্রম</td><td><?php echo number_format((float)$quote['labor_charge'], 2); ?></td></tr>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo ame_workshop_h($item['product_name']); ?> × <?php echo ame_workshop_h($item['qty']); ?></td>
                        <td><?php echo number_format((float)$item['line_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ((float)$quote['discount'] > 0): ?>
                    <tr><td>ছাড়</td><td>− <?php echo number_format((float)$quote['discount'], 2); ?></td></tr>
                <?php endif; ?>
                <tr><td><b>মোট</b></td><td><b><?php echo number_format((float)$quote['grand_total'], 2); ?></b></td></tr>
            </tbody>
        </table>
        <?php if ($quote['notes']): ?><p class="ws-hint"><?php echo nl2br(ame_workshop_h($quote['notes'])); ?></p><?php endif; ?>
    </div>
    <?php if ($quote['status'] === 'open'): ?>
        <form method="post" action="quote_process.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="convert">
            <input type="hidden" name="quote_id" value="<?php echo $id; ?>">
            <button class="ws-btn ok" type="submit">জব কার্ডে রূপান্তর</button>
        </form>
        <form method="post" action="quote_process.php" onsubmit="return confirm('কোটেশন বাতিল?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="cancel_quote">
            <input type="hidden" name="quote_id" value="<?php echo $id; ?>">
            <button class="ws-btn danger" type="submit">বাতিল</button>
        </form>
    <?php elseif ((int)$quote['job_id'] > 0): ?>
        <a class="ws-btn" href="job_view.php?id=<?php echo (int)$quote['job_id']; ?>">জব দেখুন</a>
    <?php endif; ?>
    <a class="ws-btn ghost" href="quotes.php">তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
