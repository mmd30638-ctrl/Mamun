<?php
require_once __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$job = $id > 0 ? ame_workshop_job($db, $id) : null;
if (!$job) {
    header('Location: index.php?err=' . rawurlencode('জব পাওয়া যায়নি।'));
    exit;
}

$parts = ame_workshop_job_parts($db, $id);
$engineers = ame_workshop_engineers($db);
$products = ame_workshop_products($db);
$accounts = ame_workshop_accounts($db);
$machine = !empty($job['machine_id']) ? ame_workshop_machine($db, (int)$job['machine_id']) : null;
$catalog_parts = $machine ? ame_workshop_machine_parts($db, (int)$machine['id']) : [];
$prev_jobs = ame_workshop_customer_jobs(
    $db,
    (int)$job['customer_id'],
    (string)($job['phone'] ?? ''),
    $id
);
$locked = in_array($job['status'], ['delivered', 'cancelled'], true);
$flash = trim((string)($_GET['ok'] ?? ''));
$err = trim((string)($_GET['err'] ?? ''));
$eng_name = '';
if ((int)$job['engineer_id'] > 0) {
    foreach ($engineers as $e) {
        if ((int)$e['id'] === (int)$job['engineer_id']) {
            $eng_name = (string)$e['name'];
            break;
        }
    }
}
include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>🛠️ <?php echo ame_workshop_h($job['job_no']); ?></h3>
    <?php if ($flash === '1'): ?><div class="ws-flash">আপডেট হয়েছে।</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>

    <div class="ws-card">
        <p><b><?php echo ame_workshop_h($job['customer_name']); ?></b>
            <?php if ($job['phone']): ?> · <?php echo ame_workshop_h($job['phone']); ?><?php endif; ?></p>
        <p><?php echo ame_workshop_h($job['item_name']); ?>
            <?php if ($machine): ?>
                <br><span class="ws-hint">ক্যাটালগ: <a href="machine_view.php?id=<?php echo (int)$machine['id']; ?>"><?php echo ame_workshop_h(ame_workshop_machine_label($machine)); ?></a></span>
            <?php endif; ?>
            <?php if ($job['serial_no']): ?><br>সিরিয়াল: <b><?php echo ame_workshop_h($job['serial_no']); ?></b><?php endif; ?></p>
        <?php if ($job['complaint']): ?><p class="ws-hint">সমস্যা: <?php echo nl2br(ame_workshop_h($job['complaint'])); ?></p><?php endif; ?>
        <p><span class="ws-badge"><?php echo ame_workshop_h(ame_workshop_status_label($job['status'])); ?></span>
            <?php if ($eng_name): ?> · ইঞ্জিনিয়ার: <?php echo ame_workshop_h($eng_name); ?><?php endif; ?></p>
        <p>শ্রম <?php echo number_format((float)$job['labor_charge'], 2); ?> + যন্ত্রাংশ <?php echo number_format((float)$job['parts_total'], 2); ?>
            − ছাড় <?php echo number_format((float)$job['discount'], 2); ?>
            = <b><?php echo number_format((float)$job['grand_total'], 2); ?> ৳</b></p>
        <p>পরিশোধ <?php echo number_format((float)$job['paid_amount'], 2); ?> · বকেয়া <?php echo number_format((float)$job['due_amount'], 2); ?> ৳</p>
        <a class="ws-btn sm secondary" href="slip.php?id=<?php echo $id; ?>">
            <?php echo $locked ? 'বিল / প্রিন্ট' : 'গ্রহণ স্লিপ / প্রিন্ট'; ?>
        </a>
    </div>

    <?php if ($prev_jobs): ?>
    <div class="ws-card">
        <b>এই ক্রেতার পুরনো জব</b>
        <table class="ws-table" style="margin-top:8px;">
            <tbody>
            <?php foreach ($prev_jobs as $pj): ?>
                <tr>
                    <td>
                        <a href="job_view.php?id=<?php echo (int)$pj['id']; ?>">
                            <?php echo ame_workshop_h($pj['job_no']); ?>
                        </a>
                        <div class="ws-hint"><?php echo ame_workshop_h($pj['item_name']); ?></div>
                    </td>
                    <td><?php echo ame_workshop_h(ame_workshop_status_label($pj['status'])); ?></td>
                    <td class="ws-hint">বকেয়া <?php echo number_format((float)$pj['due_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!$locked): ?>
    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_job">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <div class="ws-row">
            <div>
                <label>অবস্থা</label>
                <select name="status">
                    <?php foreach (ame_workshop_statuses() as $key => $label): ?>
                        <?php if (in_array($key, ['delivered', 'cancelled'], true)) { continue; } ?>
                        <option value="<?php echo ame_workshop_h($key); ?>" <?php echo $job['status'] === $key ? 'selected' : ''; ?>>
                            <?php echo ame_workshop_h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>ইঞ্জিনিয়ার</label>
                <?php echo function_exists('ame_workshop_duty_widget') ? ame_workshop_duty_widget($engineers) : ''; ?>
                <select name="engineer_id">
                    <option value="">—</option>
                    <?php foreach ($engineers as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>" <?php echo (int)$job['engineer_id'] === (int)$e['id'] ? 'selected' : ''; ?>>
                            <?php echo ame_workshop_h($e['name']); ?><?php echo !empty($e['present']) ? ' · 🟢' : ' · ⚪'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label>ডায়াগনোসিস</label>
        <textarea name="diagnosis" rows="2"><?php echo ame_workshop_h($job['diagnosis'] ?? ''); ?></textarea>
        <div class="ws-row">
            <div>
                <label>শ্রম (৳)</label>
                <input type="number" step="0.01" min="0" name="labor_charge" value="<?php echo ame_workshop_h($job['labor_charge']); ?>">
            </div>
            <div>
                <label>ছাড় (৳)</label>
                <input type="number" step="0.01" min="0" name="discount" value="<?php echo ame_workshop_h($job['discount']); ?>">
            </div>
        </div>
        <button class="ws-btn" type="submit">জব আপডেট</button>
    </form>

    <?php
    $product_options = '';
    foreach ($products as $p) {
        $avail = ame_workshop_available_qty($db, (int)$p['id'], (int)$job['store_id']);
        $extra = [];
        if (!empty($p['oem_code'])) {
            $extra[] = 'OEM ' . $p['oem_code'];
        }
        if (!empty($p['supplier_code'])) {
            $extra[] = 'সাপ ' . $p['supplier_code'];
        }
        $label = $p['name']
            . ($extra ? ' · ' . implode(' · ', $extra) : '')
            . ' · অবশিষ্ট ' . rtrim(rtrim(number_format($avail, 3, '.', ''), '0'), '.');
        $product_options .= '<option value="' . (int)$p['id'] . '" data-price="' . ame_workshop_h($p['sale_price']) . '">'
            . ame_workshop_h($label) . '</option>';
    }
    ?>
    <?php if ($catalog_parts): ?>
    <div class="ws-card">
        <b>এই মেশিনের যন্ত্রাংশ</b>
        <p class="ws-hint">ক্যাটালগ থেকে দ্রুত বাছুন — নিচের ফর্মে পণ্য বসে যাবে।</p>
        <select id="wsCatalogPick">
            <option value="">ক্যাটালগ থেকে বাছুন</option>
            <?php foreach ($catalog_parts as $cp): ?>
                <option value="<?php echo (int)$cp['product_id']; ?>" data-price="<?php echo ame_workshop_h($cp['sale_price']); ?>">
                    <?php
                    $clabel = $cp['product_name'];
                    $coem = trim((string)($cp['oem_code'] ?: $cp['product_oem'] ?? ''));
                    if ($coem !== '') {
                        $clabel .= ' · OEM ' . $coem;
                    }
                    echo ame_workshop_h($clabel);
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_part">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <input type="hidden" name="line_kind" value="repair">
        <label>মেরামত যন্ত্রাংশ রিজার্ভ (স্টক এখন কাটবে না)</label>
        <select name="product_id" class="ws-part-pick" required>
            <option value="">পণ্য বাছুন</option>
            <?php echo $product_options; ?>
        </select>
        <div class="ws-row">
            <div>
                <label>পরিমাণ</label>
                <input type="number" step="0.001" min="0.001" name="qty" value="1" required>
            </div>
            <div>
                <label>একক মূল্য (৳)</label>
                <input type="number" step="0.01" min="0" name="unit_price" class="ws-part-price" value="0">
            </div>
        </div>
        <p class="ws-hint">স্টক কম হলেও মেরামত লাইন যোগ হবে — <a href="needs.php">ক্রয় তালিকায়</a> উঠবে। রিজার্ভ হলে কাউন্টারে অবশিষ্ট কম দেখাবে।</p>
        <button class="ws-btn secondary" type="submit">রিজার্ভ করুন</button>
    </form>

    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_part">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <input type="hidden" name="line_kind" value="sold">
        <label>সাথে কেনা পার্টস (স্টক এখনই কাটবে — একই ক্রেতার বিলে)</label>
        <select name="product_id" class="ws-part-pick" required>
            <option value="">পণ্য বাছুন</option>
            <?php echo $product_options; ?>
        </select>
        <div class="ws-row">
            <div>
                <label>পরিমাণ</label>
                <input type="number" step="0.001" min="0.001" name="qty" value="1" required>
            </div>
            <div>
                <label>একক মূল্য (৳)</label>
                <input type="number" step="0.01" min="0" name="unit_price" class="ws-part-price" value="0">
            </div>
        </div>
        <p class="ws-hint">মেরামতের বাইরে অতিরিক্ত পার্টসও এই জব স্লিপেই উঠবে। আলাদা POS বিল লাগবে না।</p>
        <button class="ws-btn" type="submit">বিলে যোগ করুন</button>
    </form>
    <?php endif; ?>

    <div class="ws-card" style="overflow-x:auto;">
        <b>যন্ত্রাংশ</b>
        <?php if (!$parts): ?>
            <p class="ws-empty">এখনো যন্ত্রাংশ নেই।</p>
        <?php else: ?>
            <table class="ws-table">
                <thead><tr><th>পণ্য</th><th>পরিমাণ</th><th>মোট</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($parts as $part):
                    $kind = (string)($part['line_kind'] ?? 'repair');
                    $sold = $kind === 'sold';
                    $can_remove = !$locked && (!(int)$part['issued'] || $sold);
                    $state = $sold
                        ? 'সাথে কেনা'
                        : ((int)$part['issued'] ? 'মেরামত · ইস্যু' : 'মেরামত · রিজার্ভ');
                ?>
                    <tr>
                        <td><?php echo ame_workshop_h($part['product_name']); ?>
                            <div class="ws-hint"><?php echo ame_workshop_h($state); ?></div>
                        </td>
                        <td><?php echo ame_workshop_h($part['qty']); ?> × <?php echo number_format((float)$part['unit_price'], 2); ?></td>
                        <td><?php echo number_format((float)$part['line_total'], 2); ?></td>
                        <td>
                            <?php if ($can_remove): ?>
                            <form method="post" action="job_process.php" style="margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="remove_part">
                                <input type="hidden" name="job_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="part_id" value="<?php echo (int)$part['id']; ?>">
                                <button class="ws-btn sm danger" type="submit">সরান</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (!$locked): ?>
    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="take_payment">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <label>অগ্রিম / আংশিক জমা (৳)</label>
        <input type="number" step="0.01" min="0.01" name="paid_now" value="" placeholder="মেশিন রাখার সময় টাকা">
        <label>অ্যাকাউন্ট</label>
        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">
            <option value="">পরে হিসাব</option>
            <?php foreach ($accounts as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                    <?php echo ame_workshop_h(ame_account_option_label($a)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="ws-hint">ডেলিভারির আগে টাকা নিলে এখানে জমা হবে। ডেলিভারিতে আবার পুরো বিল কাটা হবে না — শুধু বাকিটা।</p>
        <button class="ws-btn secondary" type="submit">জমা নিন</button>
    </form>

    <form class="ws-card" method="post" action="job_process.php" onsubmit="return confirm('ডেলিভারি করলে যন্ত্রাংশের স্টক কাটবে এবং জব বন্ধ হবে। নিশ্চিত?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="deliver">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <p class="ws-hint">ইতিমধ্যে জমা <?php echo number_format((float)$job['paid_amount'], 2); ?> ৳ · বকেয়া <?php echo number_format((float)$job['due_amount'], 2); ?> ৳</p>
        <label>এখন আরও নিতে (৳)</label>
        <input type="number" step="0.01" min="0" name="paid_now" value="<?php echo ame_workshop_h($job['due_amount']); ?>">
        <label>মেরামত ওয়ারেন্টি (মাস)</label>
        <input type="number" min="0" step="1" name="warranty_months" value="<?php echo (int)($job['warranty_months'] ?? 0); ?>">
        <label>অ্যাকাউন্ট (এখন টাকা নিলে)</label>
        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">
            <option value="">নাও নিই / পরে</option>
            <?php foreach ($accounts as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                    <?php echo ame_workshop_h(ame_account_option_label($a)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label><input type="checkbox" name="send_sms" value="1" checked> ক্রেতাকে এসএমএস (গেটওয়ে না থাকলে শুধু লগ)</label>
        <button class="ws-btn ok" type="submit">ডেলিভারি সম্পন্ন</button>
    </form>

    <form class="ws-card" method="post" action="job_process.php" onsubmit="return confirm('জব বাতিল করবেন? রিজার্ভ যন্ত্রাংশ খুলে যাবে। জমা টাকা অটো ফেরত হবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <button class="ws-btn danger" type="submit">জব বাতিল</button>
    </form>
    <?php elseif ($job['status'] === 'delivered' && (float)$job['due_amount'] > 0.0001): ?>
    <form class="ws-card" method="post" action="job_process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="take_payment">
        <input type="hidden" name="job_id" value="<?php echo $id; ?>">
        <label>বকেয়া আদায় (৳)</label>
        <input type="number" step="0.01" min="0.01" name="paid_now" value="<?php echo ame_workshop_h($job['due_amount']); ?>">
        <label>অ্যাকাউন্ট</label>
        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">
            <option value="">পরে হিসাব</option>
            <?php foreach ($accounts as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                    <?php echo ame_workshop_h(ame_account_option_label($a)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="ws-btn" type="submit">বকেয়া নিন</button>
    </form>
    <?php endif; ?>

    <a class="ws-btn ghost" href="index.php">তালিকায় ফিরুন</a>
</div>
<script>
(function () {
    function bindPick(sel) {
        if (!sel) return;
        var form = sel.form;
        var price = form ? form.querySelector('.ws-part-price') : null;
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            if (price && opt && opt.getAttribute('data-price')) {
                price.value = opt.getAttribute('data-price');
            }
        });
    }
    document.querySelectorAll('.ws-part-pick').forEach(bindPick);
    var catalog = document.getElementById('wsCatalogPick');
    if (catalog) {
        catalog.addEventListener('change', function () {
            var id = catalog.value;
            if (!id) return;
            var opt = catalog.options[catalog.selectedIndex];
            document.querySelectorAll('.ws-part-pick').forEach(function (sel) {
                sel.value = id;
                var ev = document.createEvent('HTMLEvents');
                ev.initEvent('change', true, false);
                sel.dispatchEvent(ev);
            });
            if (opt && opt.getAttribute('data-price')) {
                document.querySelectorAll('.ws-part-price').forEach(function (inp) {
                    inp.value = opt.getAttribute('data-price');
                });
            }
        });
    }
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
