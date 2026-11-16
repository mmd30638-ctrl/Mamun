<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
$rows = ame_workshop_parts_needed($db, $store_id);
$short_n = 0;
foreach ($rows as $row) {
    if ($row['short_qty'] > 0.0001) {
        $short_n++;
    }
}
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>🛒 যন্ত্রাংশ কিনতে হবে</h3>
    <p class="ws-hint" style="margin:0 0 12px;">খোলা জবে যে যন্ত্রাংশ রিজার্ভ আছে, স্টকের সঙ্গে মিলিয়ে দেখা। ঘাটতি থাকলে ক্রয় করুন — কাউন্টারে সেই মাল বিক্রি কম দেখাবে।</p>

    <div class="ws-grid">
        <div class="ws-stat"><b><?php echo count($rows); ?></b><span>রিজার্ভ পার্টস</span></div>
        <div class="ws-stat"><b><?php echo (int)$short_n; ?></b><span>ঘাটতি — কিনতে হবে</span></div>
    </div>

    <div class="ws-card" style="overflow-x:auto;">
        <?php if (!$rows): ?>
            <div class="ws-empty">এখন কোনো জবে যন্ত্রাংশ রিজার্ভ নেই।</div>
        <?php else: ?>
            <table class="ws-table">
                <thead>
                    <tr>
                        <th>পার্টস</th>
                        <th>জবে লাগে</th>
                        <th>স্টকে</th>
                        <th>ঘাটতি</th>
                        <th>জব</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $short = $row['short_qty'] > 0.0001;
                ?>
                    <tr>
                        <td>
                            <b><?php echo ame_workshop_h($row['product_name']); ?></b>
                            <?php if ($short): ?>
                                <div class="ws-hint" style="color:#C62828;">স্টক কম — ক্রয় করুন</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo rtrim(rtrim(number_format($row['need_qty'], 3, '.', ''), '0'), '.'); ?></td>
                        <td><?php echo rtrim(rtrim(number_format($row['on_hand'], 3, '.', ''), '0'), '.'); ?></td>
                        <td><?php echo $short ? rtrim(rtrim(number_format($row['short_qty'], 3, '.', ''), '0'), '.') : '—'; ?></td>
                        <td class="ws-hint"><?php echo ame_workshop_h($row['job_nos']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <a class="ws-btn" href="/modules/purchase/add.php">পার্টস ক্রয়</a>
    <a class="ws-btn ghost" href="index.php">জব তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
