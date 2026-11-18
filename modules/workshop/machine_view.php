<?php
require_once __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$machine = $id > 0 ? ame_workshop_machine($db, $id) : null;
if (!$machine) {
    header('Location: machines.php?err=' . rawurlencode('মেশিন পাওয়া যায়নি।'));
    exit;
}
$parts = ame_workshop_machine_parts($db, $id);
$products = ame_workshop_products($db);
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>⚙️ <?php echo ame_workshop_h(ame_workshop_machine_label($machine)); ?></h3>
    <?php if ($ok === '1'): ?><div class="ws-flash">আপডেট হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>

    <form class="ws-card" method="post" action="machine_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_machine">
        <input type="hidden" name="machine_id" value="<?php echo $id; ?>">
        <div class="ws-row">
            <div>
                <label>ধরন</label>
                <select name="machine_type" required>
                    <?php foreach (ame_workshop_machine_types() as $key => $label): ?>
                        <option value="<?php echo ame_workshop_h($key); ?>" <?php echo $machine['machine_type'] === $key ? 'selected' : ''; ?>>
                            <?php echo ame_workshop_h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>ব্র্যান্ড</label>
                <input type="text" name="brand" value="<?php echo ame_workshop_h($machine['brand']); ?>">
            </div>
        </div>
        <label>মডেল *</label>
        <input type="text" name="model" required value="<?php echo ame_workshop_h($machine['model']); ?>">
        <label>নোট</label>
        <input type="text" name="note" value="<?php echo ame_workshop_h($machine['note'] ?? ''); ?>">
        <button class="ws-btn" type="submit">মেশিন আপডেট</button>
    </form>

    <form class="ws-card" method="post" action="machine_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_machine_part">
        <input type="hidden" name="machine_id" value="<?php echo $id; ?>">
        <label>এই মডেলে যে যন্ত্রাংশ লাগে</label>
        <select name="product_id" required>
            <option value="">স্টক থেকে পার্টস বাছুন</option>
            <?php foreach ($products as $p):
                $extra = [];
                if (!empty($p['oem_code'])) {
                    $extra[] = 'OEM ' . $p['oem_code'];
                }
                if (!empty($p['supplier_code'])) {
                    $extra[] = 'সাপ ' . $p['supplier_code'];
                }
                $suffix = $extra ? ' · ' . implode(' · ', $extra) : '';
            ?>
                <option value="<?php echo (int)$p['id']; ?>">
                    <?php echo ame_workshop_h($p['name'] . $suffix); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="ws-row">
            <div>
                <label>এই মডেলের OEM কোড (ঐচ্ছিক)</label>
                <input type="text" name="oem_code" placeholder="মেশিন-স্পেসিফিক কোড">
            </div>
            <div>
                <label>নোট</label>
                <input type="text" name="note" placeholder="যেমন: ইমপেলার, মেকানিক্যাল সিল">
            </div>
        </div>
        <button class="ws-btn secondary" type="submit">যন্ত্রাংশ যোগ করুন</button>
    </form>

    <div class="ws-card" style="overflow-x:auto;">
        <b>লাগে যে যন্ত্রাংশ</b>
        <?php if (!$parts): ?>
            <p class="ws-empty">এখনো যন্ত্রাংশ বাঁধা নেই। সিল, ইমপেলার, বেয়ারিং — যা লাগে তা যোগ করুন।</p>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>পার্টস</th><th>কোড</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($parts as $part):
                    $oem = trim((string)($part['oem_code'] ?: $part['product_oem'] ?? ''));
                    $sup = trim((string)($part['supplier_code'] ?? ''));
                ?>
                    <tr>
                        <td>
                            <?php echo ame_workshop_h($part['product_name']); ?>
                            <?php if (!empty($part['note'])): ?>
                                <div class="ws-hint"><?php echo ame_workshop_h($part['note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="ws-hint">
                            <?php if ($oem !== ''): ?>OEM <?php echo ame_workshop_h($oem); ?><?php endif; ?>
                            <?php if ($sup !== ''): ?><?php echo $oem !== '' ? '<br>' : ''; ?>সাপ <?php echo ame_workshop_h($sup); ?><?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="machine_process.php" style="margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_machine_part">
                                <input type="hidden" name="machine_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="part_id" value="<?php echo (int)$part['id']; ?>">
                                <button class="ws-btn sm danger" type="submit">সরান</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <form class="ws-card" method="post" action="machine_process.php" onsubmit="return confirm('এই মেশিন তালিকা থেকে মুছবেন? জব থাকলে মুছা যাবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="delete_machine">
        <input type="hidden" name="machine_id" value="<?php echo $id; ?>">
        <button class="ws-btn danger" type="submit">মেশিন মুছুন</button>
    </form>
    <a class="ws-btn ghost" href="machines.php">ক্যাটালগে ফিরুন</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
