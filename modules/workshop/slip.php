<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$id = (int)($_GET['id'] ?? 0);
$job = $id > 0 ? ame_workshop_job($db, $id) : null;
if (!$job) {
    echo '<div class="ws-wrap"><div class="ws-err">জব পাওয়া যায়নি।</div></div>';
    include __DIR__ . '/../../partials/footer.php';
    exit;
}
$parts = ame_workshop_job_parts($db, $id);
$repair_parts = [];
$sold_parts = [];
foreach ($parts as $part) {
    if (($part['line_kind'] ?? 'repair') === 'sold') {
        $sold_parts[] = $part;
    } else {
        $repair_parts[] = $part;
    }
}
$shop_name = htmlspecialchars(function_exists('shop_name') ? shop_name() : ($shop['shop_name'] ?? 'দোকান'), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<style>
@media print {
  .no-print, .sidebar, header, .header, .top-header, #sidebar, .menu-toggle { display: none !important; }
  .ws-wrap { max-width: 100%; padding: 0; }
  .ws-card { box-shadow: none; }
}
</style>
<div class="ws-wrap" id="wsSlip">
    <div class="ws-card">
        <h3 style="text-align:center;margin-bottom:4px;"><?php echo $shop_name; ?></h3>
        <p style="text-align:center;margin:0 0 12px;" class="ws-hint">
            <?php echo $job['status'] === 'delivered' ? 'ওয়ার্কশপ + পার্টস বিল · এক ক্রেতা' : 'জব গ্রহণ টোকেন — মেশিন রাখার রসিদ'; ?>
        </p>
        <p><b>জব:</b> <?php echo ame_workshop_h($job['job_no']); ?>
            · <?php echo ame_workshop_h(ame_workshop_status_label($job['status'])); ?></p>
        <p><b>ক্রেতা:</b> <?php echo ame_workshop_h($job['customer_name']); ?>
            <?php if ($job['phone']): ?><br><b>মোবাইল:</b> <?php echo ame_workshop_h($job['phone']); ?><?php endif; ?></p>
        <p><b>মেশিন:</b> <?php echo ame_workshop_h($job['item_name']); ?>
            <?php if ($job['serial_no']): ?><br><b>সিরিয়াল:</b> <?php echo ame_workshop_h($job['serial_no']); ?><?php endif; ?></p>
        <?php if ($job['diagnosis'] || $job['complaint']): ?>
            <p class="ws-hint"><?php echo nl2br(ame_workshop_h($job['diagnosis'] ?: $job['complaint'])); ?></p>
        <?php endif; ?>

        <table class="ws-table">
            <thead><tr><th>বিবরণ</th><th>টাকা</th></tr></thead>
            <tbody>
                <tr><td>শ্রম / সার্ভিস</td><td><?php echo number_format((float)$job['labor_charge'], 2); ?></td></tr>
                <?php if ($repair_parts): ?>
                    <tr><td colspan="2"><b>মেরামত যন্ত্রাংশ</b></td></tr>
                    <?php foreach ($repair_parts as $part): ?>
                        <tr>
                            <td><?php echo ame_workshop_h($part['product_name']); ?> × <?php echo ame_workshop_h($part['qty']); ?></td>
                            <td><?php echo number_format((float)$part['line_total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($sold_parts): ?>
                    <tr><td colspan="2"><b>সাথে কেনা পার্টস</b></td></tr>
                    <?php foreach ($sold_parts as $part): ?>
                        <tr>
                            <td><?php echo ame_workshop_h($part['product_name']); ?> × <?php echo ame_workshop_h($part['qty']); ?></td>
                            <td><?php echo number_format((float)$part['line_total'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ((float)$job['discount'] > 0): ?>
                    <tr><td>ছাড়</td><td>− <?php echo number_format((float)$job['discount'], 2); ?></td></tr>
                <?php endif; ?>
                <tr><td><b>মোট</b></td><td><b><?php echo number_format((float)$job['grand_total'], 2); ?></b></td></tr>
                <tr><td>পরিশোধ</td><td><?php echo number_format((float)$job['paid_amount'], 2); ?></td></tr>
                <tr><td>বকেয়া</td><td><?php echo number_format((float)$job['due_amount'], 2); ?></td></tr>
            </tbody>
        </table>
        <p class="ws-hint">গ্রহণ: <?php echo ame_workshop_h(substr((string)$job['received_at'], 0, 16)); ?>
            <?php if ($job['delivered_at']): ?> · ডেলিভারি: <?php echo ame_workshop_h(substr((string)$job['delivered_at'], 0, 16)); ?><?php endif; ?></p>
    </div>
    <button class="ws-btn no-print" type="button" onclick="window.print()">প্রিন্ট</button>
    <a class="ws-btn ghost no-print" href="job_view.php?id=<?php echo $id; ?>">জবে ফিরুন</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
