<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$customers = ame_workshop_customers($db);
$engineers = ame_workshop_engineers($db);
$products = ame_workshop_products($db);
$machines = ame_workshop_machines($db);
$job_no = ame_workshop_next_no($db, 'workshop_jobs', 'JOB');
$err = trim((string)($_GET['err'] ?? ''));
$quote_id = (int)($_GET['quote_id'] ?? 0);
$prefill = [
    'customer_id' => 0,
    'customer_name' => '',
    'phone' => '',
    'item_name' => '',
    'labor_charge' => '0',
    'notes' => '',
    'machine_id' => 0,
];
if ($quote_id > 0) {
    $qs = $db->prepare('SELECT * FROM workshop_quotes WHERE id = ?');
    $qs->bind_param('i', $quote_id);
    $qs->execute();
    $quote = $qs->get_result()->fetch_assoc();
    $qs->close();
    if ($quote) {
        $prefill['customer_id'] = (int)$quote['customer_id'];
        $prefill['customer_name'] = (string)$quote['customer_name'];
        $prefill['phone'] = (string)$quote['phone'];
        $prefill['item_name'] = (string)$quote['item_name'];
        $prefill['labor_charge'] = (string)$quote['labor_charge'];
        $prefill['notes'] = (string)$quote['notes'];
        $prefill['machine_id'] = (int)($quote['machine_id'] ?? 0);
    }
}
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>➕ নতুন জব কার্ড</h3>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>
    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_job">
        <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">

        <label>জব নম্বর</label>
        <input type="text" value="<?php echo ame_workshop_h($job_no); ?>" readonly>

        <div class="ws-row">
            <div>
                <label>সংরক্ষিত ক্রেতা (ঐচ্ছিক)</label>
                <select name="customer_id" id="wsCustomer">
                    <option value="">খুচরা / নতুন নাম</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"
                            data-name="<?php echo ame_workshop_h($c['name']); ?>"
                            data-phone="<?php echo ame_workshop_h($c['phone'] ?? ''); ?>"
                            <?php echo $prefill['customer_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo ame_workshop_h($c['name'] . ' · ' . ($c['customer_code'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>ক্রেতার নাম *</label>
                <input type="text" name="customer_name" id="wsName" required value="<?php echo ame_workshop_h($prefill['customer_name']); ?>">
            </div>
        </div>
        <label>মোবাইল</label>
        <input type="text" name="phone" id="wsPhone" value="<?php echo ame_workshop_h($prefill['phone']); ?>" placeholder="017...">

        <label>ক্যাটালগের মেশিন</label>
        <select name="machine_id" id="wsMachine">
            <option value="">তালিকা থেকে না — নিজে লিখব</option>
            <?php foreach ($machines as $m):
                $mlabel = ame_workshop_machine_label($m);
            ?>
                <option value="<?php echo (int)$m['id']; ?>" data-label="<?php echo ame_workshop_h($mlabel); ?>"
                    <?php echo $prefill['machine_id'] === (int)$m['id'] ? 'selected' : ''; ?>>
                    <?php echo ame_workshop_h($mlabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="ws-hint">ক্যাটালগে না থাকলে নিচে নাম লিখুন। নতুন মডেল <a href="machines.php">মেশিন ক্যাটালগে</a> যোগ করুন।</p>

        <div class="ws-row">
            <div>
                <label>মেশিন / পণ্য *</label>
                <input type="text" name="item_name" id="wsItem" required value="<?php echo ame_workshop_h($prefill['item_name']); ?>" placeholder="যেমন: পাম্প মোটর ২ হর্স">
            </div>
            <div>
                <label>সিরিয়াল / IMEI</label>
                <input type="text" name="serial_no" placeholder="ওয়ারেন্টি খোঁজার জন্য">
            </div>
        </div>

        <label>সমস্যার বিবরণ</label>
        <textarea name="complaint" rows="2" placeholder="কী খারাপ, কখন থেকে"></textarea>

        <div class="ws-row">
            <div>
                <label>ইঞ্জিনিয়ার</label>
                <?php echo function_exists('ame_workshop_duty_widget') ? ame_workshop_duty_widget($engineers) : ''; ?>
                <select name="engineer_id">
                    <option value="">এখন না</option>
                    <?php foreach ($engineers as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>"><?php echo ame_workshop_h($e['name']); ?><?php echo !empty($e['present']) ? ' · 🟢' : ' · ⚪'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>শ্রম / সার্ভিস চার্জ (৳)</label>
                <input type="number" step="0.01" min="0" name="labor_charge" value="<?php echo ame_workshop_h($prefill['labor_charge']); ?>">
            </div>
        </div>

        <label>নোট</label>
        <textarea name="notes" rows="2"><?php echo ame_workshop_h($prefill['notes']); ?></textarea>

        <p class="ws-hint">যন্ত্রাংশ জব খোলার পর যোগ করবেন। রিজার্ভ হলে স্টক কাটে না — ডেলিভারিতে কাটবে।</p>
        <label><input type="checkbox" name="send_sms" value="1" checked> ক্রেতাকে গ্রহণ এসএমএস (গেটওয়ে না থাকলে শুধু লগ)</label>
        <button class="ws-btn" type="submit">জব খুলুন</button>
        <a class="ws-btn ghost" href="index.php">ফিরে যান</a>
    </form>
</div>
<script>
(function () {
    var sel = document.getElementById('wsCustomer');
    if (!sel) return;
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        document.getElementById('wsName').value = opt.getAttribute('data-name') || '';
        document.getElementById('wsPhone').value = opt.getAttribute('data-phone') || '';
    });
    var mach = document.getElementById('wsMachine');
    var item = document.getElementById('wsItem');
    if (mach && item) {
        mach.addEventListener('change', function () {
            var opt = mach.options[mach.selectedIndex];
            if (opt && opt.value && opt.getAttribute('data-label')) {
                item.value = opt.getAttribute('data-label');
            }
        });
    }
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
