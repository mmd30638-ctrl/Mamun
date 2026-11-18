<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$rows = ame_workshop_due_jobs($db);
$total_due = 0.0;
foreach ($rows as $row) {
    $total_due += (float)$row['due_amount'];
}
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>💰 জব বকেয়া</h3>
    <p class="ws-hint" style="margin:0 0 12px;">মেরামত বিলের বাকি টাকা। জব খুলে জমা নিন — আলাদা POS বিল লাগে না।</p>

    <div class="ws-grid">
        <div class="ws-stat"><b><?php echo count($rows); ?></b><span>বকেয়া জব</span></div>
        <div class="ws-stat"><b><?php echo number_format($total_due, 0); ?></b><span>মোট বকেয়া ৳</span></div>
    </div>

    <div class="ws-card" style="overflow-x:auto;">
        <?php if (!$rows): ?>
            <div class="ws-empty">এখন কোনো জব বকেয়া নেই।</div>
        <?php else: ?>
            <table class="ws-table">
                <thead>
                    <tr>
                        <th>জব</th>
                        <th>ক্রেতা</th>
                        <th>মেশিন</th>
                        <th>বকেয়া</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <a href="job_view.php?id=<?php echo (int)$row['id']; ?>">
                                <b><?php echo ame_workshop_h($row['job_no']); ?></b>
                            </a>
                            <div class="ws-hint"><?php echo ame_workshop_h(ame_workshop_status_label($row['status'])); ?></div>
                        </td>
                        <td>
                            <?php echo ame_workshop_h($row['customer_name']); ?>
                            <?php if (!empty($row['phone'])): ?>
                                <div class="ws-hint"><?php echo ame_workshop_h($row['phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo ame_workshop_h($row['item_name']); ?></td>
                        <td><b><?php echo number_format((float)$row['due_amount'], 2); ?></b></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <a class="ws-btn ghost" href="index.php">জব তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
