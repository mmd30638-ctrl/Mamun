<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$machines = ame_workshop_machines($db);
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $ql = mb_strtolower($q);
    $machines = array_values(array_filter($machines, static function ($row) use ($ql) {
        $hay = mb_strtolower(
            ($row['brand'] ?? '') . ' ' . ($row['model'] ?? '') . ' ' . ($row['note'] ?? '') . ' ' . ($row['machine_type'] ?? '')
        );
        return mb_strpos($hay, $ql) !== false;
    }));
}
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>⚙️ মেশিন / মডেল ক্যাটালগ</h3>
    <p class="ws-hint" style="margin:0 0 12px;">কোন পাম্প, মোটর বা ইঞ্জিনে কোন যন্ত্রাংশ লাগে — একবার লিখে রাখুন। জব খুললে সেই তালিকা থেকে পার্টস বাছা যাবে।</p>
    <?php if ($ok === '1'): ?><div class="ws-flash">সংরক্ষণ হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>

    <form class="ws-card" method="get">
        <label>খোঁজ</label>
        <input type="search" name="q" value="<?php echo ame_workshop_h($q); ?>" placeholder="ব্র্যান্ড, মডেল">
        <button class="ws-btn secondary" type="submit">খুঁজুন</button>
    </form>

    <form class="ws-card" method="post" action="machine_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_machine">
        <h4 style="margin:0 0 8px;">নতুন মেশিন</h4>
        <div class="ws-row">
            <div>
                <label>ধরন</label>
                <select name="machine_type" required>
                    <?php foreach (ame_workshop_machine_types() as $key => $label): ?>
                        <option value="<?php echo ame_workshop_h($key); ?>"><?php echo ame_workshop_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>ব্র্যান্ড</label>
                <input type="text" name="brand" placeholder="যেমন: Pedrollo">
            </div>
        </div>
        <label>মডেল *</label>
        <input type="text" name="model" required placeholder="যেমন: CPm 158">
        <label>নোট</label>
        <input type="text" name="note" placeholder="হর্স পাওয়ার, ভোল্ট — যা মনে রাখতে চান">
        <button class="ws-btn" type="submit">মেশিন যোগ করুন</button>
    </form>

    <div class="ws-card" style="overflow-x:auto;">
        <?php if (!$machines): ?>
            <div class="ws-empty">এখনো কোনো মেশিন নেই। উপরের ফর্ম দিয়ে পাম্প/মোটর/ইঞ্জিন যোগ করুন।</div>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>মেশিন</th><th>নোট</th></tr></thead>
                <tbody>
                <?php foreach ($machines as $m): ?>
                    <tr>
                        <td>
                            <a href="machine_view.php?id=<?php echo (int)$m['id']; ?>">
                                <b><?php echo ame_workshop_h(ame_workshop_machine_label($m)); ?></b>
                            </a>
                        </td>
                        <td class="ws-hint"><?php echo ame_workshop_h($m['note'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <a class="ws-btn ghost" href="index.php">জব তালিকায় ফিরুন</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
