<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$machines = ame_workshop_machines($db);
$pick = (int)($_GET['machine_id'] ?? 0);
$machine = $pick > 0 ? ame_workshop_machine($db, $pick) : null;
$parts = $machine ? ame_workshop_machine_parts($db, $pick) : [];
$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>🔎 কোন মেশিনে কোন পার্টস</h3>
    <p class="ws-hint" style="margin:0 0 12px;">কাউন্টারে ক্রেতা মডেল বললে এখান থেকে OEM/সাপ্লায়ার কোড দেখুন, তারপর পার্টস কাউন্টারে সেই কোড সার্চ করুন।</p>

    <form class="ws-card" method="get">
        <label>মেশিন</label>
        <select name="machine_id" onchange="this.form.submit()">
            <option value="">মডেল বাছুন</option>
            <?php foreach ($machines as $m): ?>
                <option value="<?php echo (int)$m['id']; ?>" <?php echo $pick === (int)$m['id'] ? 'selected' : ''; ?>>
                    <?php echo ame_workshop_h(ame_workshop_machine_label($m)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="ws-btn secondary" type="submit">দেখুন</button></noscript>
    </form>

    <?php if (!$machines): ?>
        <div class="ws-empty">আগে <a href="machines.php">মেশিন ক্যাটালগে</a> মডেল ও যন্ত্রাংশ যোগ করুন।</div>
    <?php elseif (!$machine): ?>
        <div class="ws-empty">একটা মডেল বাছুন — সিল, ইমপেলার, বেয়ারিং যা লাগে তা দেখাবে।</div>
    <?php elseif (!$parts): ?>
        <div class="ws-card">
            <p><b><?php echo ame_workshop_h(ame_workshop_machine_label($machine)); ?></b></p>
            <p class="ws-empty">এই মডেলে এখনো যন্ত্রাংশ বাঁধা নেই। <a href="machine_view.php?id=<?php echo $pick; ?>">যোগ করুন</a>।</p>
        </div>
    <?php else: ?>
        <div class="ws-card" style="overflow-x:auto;">
            <b><?php echo ame_workshop_h(ame_workshop_machine_label($machine)); ?></b>
            <table class="ws-table">
                <thead><tr><th>পার্টস</th><th>কোড</th><th>স্টক</th><th>মূল্য</th></tr></thead>
                <tbody>
                <?php foreach ($parts as $part):
                    $oem = trim((string)($part['oem_code'] ?: ($part['product_oem'] ?? '')));
                    $sup = trim((string)($part['supplier_code'] ?? ''));
                    $avail = ame_workshop_available_qty($db, (int)$part['product_id'], $store_id);
                    $codes = [];
                    if ($oem !== '') {
                        $codes[] = 'OEM ' . $oem;
                    }
                    if ($sup !== '') {
                        $codes[] = 'সাপ ' . $sup;
                    }
                ?>
                    <tr>
                        <td>
                            <?php echo ame_workshop_h($part['product_name']); ?>
                            <?php if (!empty($part['note'])): ?>
                                <div class="ws-hint"><?php echo ame_workshop_h($part['note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="ws-hint"><?php echo ame_workshop_h($codes ? implode(' · ', $codes) : '—'); ?></td>
                        <td><?php echo rtrim(rtrim(number_format($avail, 3, '.', ''), '0'), '.'); ?></td>
                        <td><?php echo number_format((float)$part['sale_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a class="ws-btn" href="/modules/sales/pos.php">পার্টস কাউন্টারে বিক্রি</a>
        </div>
    <?php endif; ?>
    <a class="ws-btn ghost" href="machines.php">ক্যাটালগ এডিট</a>
    <a class="ws-btn ghost" href="index.php">জব তালিকা</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
