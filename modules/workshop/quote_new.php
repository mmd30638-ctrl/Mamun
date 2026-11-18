<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$customers = ame_workshop_customers($db);
$products = ame_workshop_products($db);
$machines = ame_workshop_machines($db);
$catalog = ame_workshop_catalog_map($db);
$quote_no = ame_workshop_next_no($db, 'workshop_quotes', 'QT');
$err = trim((string)($_GET['err'] ?? ''));
?>
<link rel="stylesheet" href="/assets/css/workshop.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
<div class="ws-wrap">
    <h3>📄 নতুন কোটেশন</h3>
    <?php if ($err !== ''): ?><div class="ws-err"><?php echo ame_workshop_h($err); ?></div><?php endif; ?>
    <form class="ws-card" method="post" action="quote_process.php" id="wsQuoteForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_quote">
        <label>কোটেশন নং</label>
        <input type="text" value="<?php echo ame_workshop_h($quote_no); ?>" readonly>
        <div class="ws-row">
            <div>
                <label>ক্রেতা</label>
                <select name="customer_id" id="wsCustomer">
                    <option value="">নাম লিখুন</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"
                            data-name="<?php echo ame_workshop_h($c['name']); ?>"
                            data-phone="<?php echo ame_workshop_h($c['phone'] ?? ''); ?>">
                            <?php echo ame_workshop_h($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>নাম *</label>
                <input type="text" name="customer_name" id="wsName" required>
            </div>
        </div>
        <label>মোবাইল</label>
        <input type="text" name="phone" id="wsPhone">
        <label>ক্যাটালগের মেশিন</label>
        <select name="machine_id" id="wsMachine">
            <option value="">তালিকা থেকে না</option>
            <?php foreach ($machines as $m):
                $mlabel = ame_workshop_machine_label($m);
            ?>
                <option value="<?php echo (int)$m['id']; ?>" data-label="<?php echo ame_workshop_h($mlabel); ?>">
                    <?php echo ame_workshop_h($mlabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>মেশিন / কাজ *</label>
        <input type="text" name="item_name" id="wsItem" required>
        <label>শ্রম (৳)</label>
        <input type="number" step="0.01" min="0" name="labor_charge" value="0">
        <label>ছাড় (৳)</label>
        <input type="number" step="0.01" min="0" name="discount" value="0">
        <label>নোট</label>
        <textarea name="notes" rows="2"></textarea>

        <b>আনুমানিক যন্ত্রাংশ</b>
        <div id="wsLines"></div>
        <button class="ws-btn sm secondary" type="button" id="wsAddLine">+ লাইন</button>
        <template id="wsLineTpl">
            <div class="ws-card" style="margin:8px 0;padding:10px;box-shadow:none;border:1px solid #CFE3F7;">
                <select name="part_product[]">
                    <option value="">কাস্টম নাম</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" data-name="<?php echo ame_workshop_h($p['name']); ?>" data-price="<?php echo ame_workshop_h($p['sale_price']); ?>">
                            <?php
                            $plabel = $p['name'];
                            if (!empty($p['oem_code'])) {
                                $plabel .= ' · OEM ' . $p['oem_code'];
                            }
                            echo ame_workshop_h($plabel);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="part_name[]" placeholder="যন্ত্রাংশের নাম">
                <div class="ws-row">
                    <input type="number" step="0.001" min="0" name="part_qty[]" value="1" placeholder="পরিমাণ">
                    <input type="number" step="0.01" min="0" name="part_price[]" value="0" placeholder="মূল্য">
                </div>
            </div>
        </template>
        <button class="ws-btn" type="submit">কোটেশন সেভ</button>
        <a class="ws-btn ghost" href="quotes.php">তালিকা</a>
    </form>
</div>
<script>
(function () {
    var box = document.getElementById('wsLines');
    var tpl = document.getElementById('wsLineTpl');
    var add = document.getElementById('wsAddLine');
    function bind(row) {
        var sel = row.querySelector('select');
        var name = row.querySelector('input[name="part_name[]"]');
        var price = row.querySelector('input[name="part_price[]"]');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            if (opt && opt.value) {
                name.value = opt.getAttribute('data-name') || '';
                price.value = opt.getAttribute('data-price') || '0';
            }
        });
    }
    add.addEventListener('click', function () {
        var node = tpl.content.cloneNode(true);
        box.appendChild(node);
        bind(box.lastElementChild);
    });
    add.click();
    var cus = document.getElementById('wsCustomer');
    cus.addEventListener('change', function () {
        var opt = cus.options[cus.selectedIndex];
        if (!opt || !opt.value) return;
        document.getElementById('wsName').value = opt.getAttribute('data-name') || '';
        document.getElementById('wsPhone').value = opt.getAttribute('data-phone') || '';
    });
    var catalog = <?php echo json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var mach = document.getElementById('wsMachine');
    var item = document.getElementById('wsItem');
    function fillCatalog(id) {
        var info = catalog[id];
        if (!info) return;
        if (item && info.label) item.value = info.label;
        box.innerHTML = '';
        (info.parts || []).forEach(function (p) {
            add.click();
            var row = box.lastElementChild;
            if (!row) return;
            var sel = row.querySelector('select');
            var name = row.querySelector('input[name="part_name[]"]');
            var price = row.querySelector('input[name="part_price[]"]');
            if (sel) sel.value = String(p.id);
            if (name) name.value = p.name || '';
            if (price) price.value = p.price || 0;
        });
        if (!info.parts || !info.parts.length) add.click();
    }
    if (mach) {
        mach.addEventListener('change', function () {
            if (mach.value) fillCatalog(mach.value);
        });
    }
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
