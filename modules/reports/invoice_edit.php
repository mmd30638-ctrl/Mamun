<?php
/* 10.98.704 */
/* 10.98.703 — বিক্রয় মেমো এডিট: উপরে দোকানের লোগো, নিবন্ধিত ক্রেতার সাবেক বকেয়া ও +/− সমন্বয় */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/lang_helper.php';
require_once __DIR__ . '/../../includes/sales/sale_common.php';
require_once __DIR__ . '/../../includes/sales/sale_admin_edit.php';
require_once __DIR__ . '/../../includes/super_admin.php';
require_once __DIR__ . '/../../includes/sales/sale_memo_copy.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if (!ame_sale_admin_is_admin()) {
    http_response_code(403);
    echo "<script>alert('শুধু এডমিন ইনভয়েস বদলাতে পারেন।'); window.location='/modules/reports/sales_report.php';</script>";
    exit;
}
require_permission($db, 'm-sales-report');
ensure_sales_schema($db);

$en = function_exists('current_lang') && current_lang() === 'en';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $bn, string $eng) use ($en): string {
    return $en ? $eng : $bn;
};

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /modules/reports/sales_report.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT s.*, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name,
            c.phone AS customer_phone, c.customer_code
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.id = ?
     LIMIT 1"
);
if (!$stmt) {
    $stmt = $db->prepare('SELECT s.* FROM sales s WHERE s.id = ? LIMIT 1');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) {
    $_SESSION['user_flash'] = $t('ইনভয়েস পাওয়া যায়নি।', 'Invoice not found.');
    $_SESSION['user_flash_ok'] = 0;
    header('Location: /modules/reports/sales_report.php');
    exit;
}

$items = [];
$ist = @$db->prepare(
    "SELECT si.id, si.product_id, si.qty, si.sale_price, si.purchase_price, si.unit_id,
            COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name
     FROM sale_items si
     LEFT JOIN products p ON p.id = si.product_id
     WHERE si.sale_id = ?
     ORDER BY si.id ASC"
);
if (!$ist) {
    $ist = $db->prepare(
        "SELECT si.id, si.product_id, si.qty, si.sale_price,
                COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name
         FROM sale_items si
         LEFT JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = ?
         ORDER BY si.id ASC"
    );
}
if ($ist) {
    $ist->bind_param('i', $id);
    $ist->execute();
    $items = $ist->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $ist->close();
}

$services = [];
$sst = @$db->prepare(
    'SELECT engineer_id, service_type, amount FROM sale_services WHERE sale_id = ? ORDER BY id ASC'
);
if ($sst) {
    $sst->bind_param('i', $id);
    $sst->execute();
    $services = $sst->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $sst->close();
}

$accounts = function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
$engineers = [];
$eng_tbl = @$db->query("SHOW TABLES LIKE 'engineers'");
if ($eng_tbl && $eng_tbl->num_rows > 0) {
    $eres = @$db->query(
        "SELECT id, name FROM engineers
         WHERE (status = 'active' OR status IS NULL OR status = '')
         ORDER BY name ASC"
    );
    if ($eres) {
        $engineers = $eres->fetch_all(MYSQLI_ASSOC) ?: [];
        $eres->close();
    }
}
if ($eng_tbl) {
    $eng_tbl->close();
}

$when = (string)($sale['sale_date'] ?: $sale['completed_at'] ?: $sale['created_at'] ?: date('Y-m-d H:i:s'));
$sale_day = preg_match('/^\d{4}-\d{2}-\d{2}/', $when) ? substr($when, 0, 10) : date('Y-m-d');
$sale_time = preg_match('/\d{2}:\d{2}/', $when, $tm) ? substr($tm[0], 0, 5) : date('H:i');
$invoice = (string)($sale['invoice_number'] ?: ('#' . $sale['id']));
$cid = (int)($sale['customer_id'] ?? 0);
$bal = $cid > 0 ? ame_sale_edit_balance_preview($db, $sale, $cid) : ['ok' => false, 'previous' => 0.0, 'live' => 0.0, 'same' => false, 'id' => 0, 'name' => '', 'phone' => '', 'code' => '', 'supplier' => false];
$shop_title = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? trim((string)shop_address()) : '';
$shop_tel = function_exists('shop_phone') ? trim((string)shop_phone()) : '';
$memo_when = date('d-m-Y', strtotime($sale_day)) . ' ' . $sale_time;
$returned_map = ame_sale_admin_returned_qty_map($db, $id);
$can_reopen = ame_sale_admin_can_reopen();
$memo_plain = ame_sale_memo_plaintext($db, $id, $en);

$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = (int)($_SESSION['user_flash_ok'] ?? 0);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$ame_body_class = trim((string)($ame_body_class ?? '') . ' ame-srep-page ame-inv-edit-page');
include __DIR__ . '/../../partials/header.php';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $bn, string $eng) use ($en): string {
    return $en ? $eng : $bn;
};
?>
<style>
.iedit { max-width: 1100px; margin: 0 auto; padding: 14px 12px 40px; }
.iedit h1 { margin: 0 0 6px; font-size: 26px; font-weight: 900; color: #003F8F; }
.iedit .sub { margin: 0 0 14px; color: #45556C; font-weight: 700; }
.iedit-flash { padding: 10px 12px; border-radius: 10px; font-weight: 800; margin-bottom: 12px; }
.iedit-flash.ok { background: #E8F5E9; color: #0E8F52; border: 1px solid #0E8F52; }
.iedit-flash.bad { background: #FFEBEE; color: #C62828; border: 1px solid #C62828; }
.iedit-card { background: #fff; border: 1px solid #D7DEE8; border-radius: 12px; padding: 14px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(11,30,77,.06); }
.iedit-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.iedit-grid label, .iedit-card > label { display: block; font-size: 14px; font-weight: 800; color: #003F8F; margin-bottom: 4px; }
.iedit input, .iedit select, .iedit textarea { width: 100%; box-sizing: border-box; min-height: 44px; font-size: 16px; font-weight: 700; color: #003F8F; border: 2px solid #BCC8D6; border-radius: 10px; padding: 8px 10px; background: #fff; }
.iedit table { width: 100%; border-collapse: collapse; }
.iedit th { background: #003F8F; color: #fff; font-size: 14px; padding: 8px; text-align: left; }
.iedit td { padding: 6px; border-bottom: 1px solid #E7EDF4; vertical-align: middle; }
.iedit tr.iedit-voided td { opacity: .55; text-decoration: line-through; background: #FFF5F5; }
.iedit tr.iedit-voided .iedit-void { text-decoration: none; opacity: 1; }
.iedit .btn { display: inline-block; border: 0; border-radius: 10px; padding: 12px 18px; font-size: 16px; font-weight: 800; cursor: pointer; text-decoration: none; color: #fff; background: linear-gradient(135deg, #003F8F, #1565C0); }
.iedit .btn.green { background: linear-gradient(135deg, #0E8F52, #087F3D); }
.iedit .btn.ghost { background: #fff; color: #003F8F; border: 2px solid #003F8F; }
.iedit .btn.danger { background: #fff; color: #C62828; border: 2px solid #C62828; }
.iedit .btn.warn { background: #fff7ed; color: #c2410c; border: 2px solid #ea580c; }
.iedit .row-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.iedit .totals { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.iedit .tot { background: #F0F7FF; border-radius: 10px; padding: 10px; }
.iedit .tot span { display: block; font-size: 13px; font-weight: 800; color: #003F8F; }
.iedit .tot strong { font-size: 20px; color: #0E8F52; }
.iedit-suggest { position: relative; }
.iedit-suggest ul { position: absolute; z-index: 20; left: 0; right: 0; top: 100%; margin: 0; padding: 0; list-style: none; background: #fff; border: 2px solid #003F8F; border-radius: 10px; max-height: 240px; overflow: auto; }
.iedit-suggest li { padding: 8px 10px; cursor: pointer; font-weight: 700; color: #003F8F; }
.iedit-suggest li:hover { background: #F0F7FF; }
@media (max-width: 900px) {
    .iedit-grid, .iedit .totals { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .iedit-grid, .iedit .totals { grid-template-columns: 1fr; }
}
.iedit-memo { background: #fff; border: 1px solid #D7DEE8; border-top: 5px solid #003F8F; border-radius: 14px; padding: 12px 14px 14px; margin-bottom: 12px; box-shadow: 0 2px 10px rgba(11,30,77,.07); }
.iedit-memo-head { text-align: center; padding-bottom: 8px; border-bottom: 1px dashed #BCC8D6; }
.iedit-memo-shop { margin: 4px 0 0; font-size: 22px; font-weight: 900; color: #003F8F; line-height: 1.25; }
.iedit-memo-line { margin: 2px 0 0; font-size: 13px; font-weight: 700; color: #45556C; }
.iedit-memo-badge { display: inline-block; margin-top: 8px; padding: 4px 16px; border-radius: 999px; background: #003F8F; color: #fff; font-size: 14px; font-weight: 800; }
.iedit-memo-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }
.iedit-memo-meta div { background: #F0F7FF; border-radius: 10px; padding: 8px 10px; min-width: 0; }
.iedit-memo-meta span { display: block; font-size: 12px; font-weight: 800; color: #5b7aa8; }
.iedit-memo-meta b { display: block; font-size: 15px; font-weight: 900; color: #003F8F; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.iedit-bal { border: 2px solid #003F8F; border-radius: 14px; background: #fff; margin-bottom: 12px; overflow: hidden; }
.iedit-bal[hidden], .iedit-bal-row[hidden] { display: none; }
.iedit-bal-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 6px; padding: 10px 14px; background: #003F8F; color: #fff; }
.iedit-bal-head strong { font-size: 17px; font-weight: 900; }
.iedit-bal-head span { font-size: 13px; font-weight: 700; opacity: .92; }
.iedit-bal-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; padding: 9px 14px; border-bottom: 1px solid #E7EDF4; font-size: 15px; font-weight: 800; color: #1e293b; }
.iedit-bal-row small { display: block; font-size: 12px; font-weight: 700; color: #64748b; }
.iedit-bal-row b { font-size: 17px; font-weight: 900; white-space: nowrap; }
.iedit-bal-row b.plus { color: #C62828; }
.iedit-bal-row b.minus { color: #0E8F52; }
.iedit-bal-row b.zero { color: #45556C; }
.iedit-bal-row.total { background: #F0F7FF; border-bottom: 0; }
.iedit-bal-row.total b { font-size: 21px; }
.iedit-bal-proof { padding: 8px 14px 10px; font-size: 13px; font-weight: 800; color: #003F8F; background: #F8FBFF; border-top: 1px dashed #BCC8D6; word-break: break-word; }
.iedit-bal-note { padding: 8px 14px; font-size: 12.5px; font-weight: 700; color: #9a3412; background: #FFF7ED; border-top: 1px solid #FED7AA; }
.iedit-bal-note[hidden] { display: none; }
html body #main-content#main-content .iedit .iedit-bal-row small, html body #main-content#main-content .iedit .iedit-bal-head span, html body #main-content#main-content .iedit .iedit-bal-note { font-size: 12.5px !important; line-height: 1.35 !important; }
html body #main-content#main-content .iedit .iedit-memo-meta span { font-size: 12px !important; }
@media (max-width: 900px) { .iedit-memo-meta { grid-template-columns: 1fr 1fr; } }
</style>
<div class="iedit">
    <div class="iedit-memo">
        <div class="iedit-memo-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(64) : ''; ?>
            <h2 class="iedit-memo-shop"><?php echo $h($shop_title); ?></h2>
            <?php if ($shop_addr !== ''): ?><p class="iedit-memo-line"><?php echo $h($shop_addr); ?></p><?php endif; ?>
            <?php if ($shop_tel !== ''): ?><p class="iedit-memo-line">📞 <?php echo $h($shop_tel); ?></p><?php endif; ?>
            <span class="iedit-memo-badge"><?php echo $h($t('বিক্রয় মেমো · সম্পাদনা', 'Sales memo · edit')); ?></span>
        </div>
        <div class="iedit-memo-meta">
            <div><span><?php echo $h($t('ইনভয়েস', 'Invoice')); ?></span><b>#<?php echo $h($invoice); ?></b></div>
            <div><span><?php echo $h($t('তারিখ ও সময়', 'Date & time')); ?></span><b id="ieditMemoWhen"><?php echo $h($memo_when); ?></b></div>
            <div><span><?php echo $h($t('ক্রেতা', 'Customer')); ?></span><b id="ieditMemoCus"><?php echo $h(trim((string)($sale['customer_name'] ?? '')) !== '' ? $sale['customer_name'] : $t('খুচরা ক্রেতা', 'Walk-in')); ?></b></div>
            <div><span><?php echo $h($t('মোবাইল', 'Mobile')); ?></span><b id="ieditMemoTel"><?php echo $h(trim((string)($sale['customer_phone'] ?? $sale['guest_customer_phone'] ?? '')) ?: '—'); ?></b></div>
        </div>
    </div>
    <h1><?php echo $h($t('ইনভয়েস সম্পাদনা', 'Edit invoice')); ?></h1>
    <p class="sub"><?php echo $h($invoice); ?> · <?php echo $h($t('কাস্টমার বদলালে পুরনো লেজার রিভার্স ও নতুন ক্রেতায় বিল লাগবে। পণ্য বাতিল করলে মেমো থেকে সরবে। সুপার এডমিন পূর্ণ এডিটের জন্য মেমো ড্রাফটে আনতে পারেন।', 'Changing customer reverses the old ledger and bills the new one. Line cancel updates stock and balances. Super Admin can reopen to POS draft for full edit.')); ?></p>
    <?php if ($flash !== ''): ?>
        <div class="iedit-flash <?php echo $flash_ok ? 'ok' : 'bad'; ?>"><?php echo $h($flash); ?></div>
    <?php endif; ?>

    <form method="post" action="/modules/reports/process_invoice_edit.php" id="ieditForm">
        <input type="hidden" name="intent_save_action" id="ieditIntentSave" value="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="sale_id" value="<?php echo (int)$sale['id']; ?>">

        <div class="iedit-card">
            <div class="iedit-grid">
                <div>
                    <label><?php echo $h($t('তারিখ', 'Date')); ?></label>
                    <input type="date" name="sale_date" value="<?php echo $h($sale_day); ?>" required>
                </div>
                <div>
                    <label><?php echo $h($t('সময়', 'Time')); ?></label>
                    <input type="time" name="sale_time" value="<?php echo $h($sale_time); ?>">
                </div>
                <div>
                    <label><?php echo $h($t('গাড়ির নম্বর', 'Vehicle no')); ?></label>
                    <input type="text" name="vehicle_no" value="<?php echo $h($sale['vehicle_no'] ?? ''); ?>" maxlength="40">
                </div>
                <div>
                    <label><?php echo $h($t('ক্রেতার ধরন', 'Customer type')); ?></label>
                    <select name="customer_type" id="ieditType">
                        <option value="Retail"<?php echo $cid <= 0 ? ' selected' : ''; ?>><?php echo $h($t('খুচরা', 'Retail')); ?></option>
                        <option value="Registered"<?php echo $cid > 0 ? ' selected' : ''; ?>><?php echo $h($t('নিবন্ধিত / সাপ্লায়ার', 'Registered / supplier')); ?></option>
                    </select>
                </div>
            </div>
            <div class="iedit-grid" style="margin-top:10px;">
                <div class="iedit-suggest" style="grid-column: span 2;">
                    <label><?php echo $h($t('ক্রেতা খুঁজুন', 'Search customer')); ?></label>
                    <input type="search" id="ieditCusQ" value="<?php echo $h(trim((string)($sale['customer_name'] ?? ''))); ?>" placeholder="<?php echo $h($t('নাম / মোবাইল / কোড', 'Name / mobile / code')); ?>" autocomplete="off">
                    <input type="hidden" name="customer_id" id="ieditCusId" value="<?php echo $cid > 0 ? $cid : ''; ?>">
                    <ul id="ieditCusList" hidden></ul>
                </div>
                <div>
                    <label><?php echo $h($t('খুচরা নাম', 'Walk-in name')); ?></label>
                    <input type="text" name="guest_customer_name" value="<?php echo $h($sale['guest_customer_name'] ?? ''); ?>">
                </div>
                <div>
                    <label><?php echo $h($t('খুচরা মোবাইল', 'Walk-in mobile')); ?></label>
                    <input type="text" name="guest_customer_phone" value="<?php echo $h($sale['guest_customer_phone'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="iedit-card">
            <label><?php echo $h($t('পণ্য — যেকোনো লাইন বাতিল করা যায়', 'Items — any line can be cancelled')); ?></label>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo $h($t('পণ্য', 'Product')); ?></th>
                            <th><?php echo $h($t('পরিমাণ', 'Qty')); ?></th>
                            <th><?php echo $h($t('দাম', 'Price')); ?></th>
                            <th><?php echo $h($t('মোট', 'Total')); ?></th>
                            <th><?php echo $h($t('বাতিল', 'Cancel')); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ieditItems">
                    <?php foreach ($items as $i => $it): ?>
                        <?php
                        $pid = (int)($it['product_id'] ?? 0);
                        $ret_q = $pid > 0 ? (float)($returned_map[$pid] ?? 0) : 0.0;
                        ?>
                        <tr data-returned="<?php echo $h((string)$ret_q); ?>">
                            <td>
                                <input type="hidden" name="items[<?php echo (int)$i; ?>][sale_item_id]" value="<?php echo (int)($it['id'] ?? 0); ?>">
                                <input type="hidden" name="items[<?php echo (int)$i; ?>][void]" value="0" class="iedit-void-flag">
                                <input type="hidden" name="items[<?php echo (int)$i; ?>][product_id]" value="<?php echo (int)($it['product_id'] ?? 0); ?>">
                                <input type="hidden" name="items[<?php echo (int)$i; ?>][is_custom]" value="<?php echo ((int)($it['product_id'] ?? 0) <= 0) ? '1' : '0'; ?>">
                                <input type="text" name="items[<?php echo (int)$i; ?>][name]" value="<?php echo $h($it['item_name'] ?? ''); ?>" readonly>
                                <?php if ($ret_q > 0.0001): ?>
                                    <small style="display:block;color:#9a3412;font-weight:800;"><?php echo $h($t('ইতিমধ্যে ফেরত: ', 'Already returned: ') . $ret_q); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><input type="number" step="0.001" min="0" name="items[<?php echo (int)$i; ?>][qty]" value="<?php echo $h($it['qty'] ?? 0); ?>" class="iedit-qty" readonly></td>
                            <td><input type="number" step="0.01" min="0" name="items[<?php echo (int)$i; ?>][price]" value="<?php echo $h($it['sale_price'] ?? 0); ?>" class="iedit-price" readonly></td>
                            <td class="iedit-line">৳0.00</td>
                            <td>
                                <button type="button" class="btn danger iedit-void" title="<?php echo $h($t('এই পণ্য মেমো থেকে বাতিল', 'Cancel this product on the memo')); ?>"><?php echo $h($t('বাতিল', 'Cancel')); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="iedit-card">
            <label><?php echo $h($t('সার্ভিস / মেরামত', 'Service / repair')); ?></label>
            <div id="ieditServices">
                <?php foreach ($services as $si => $sv): ?>
                <div class="iedit-grid iedit-svc" style="margin-bottom:8px;">
                    <div>
                        <select name="services[<?php echo (int)$si; ?>][engineer_id]" disabled>
                            <option value="0"><?php echo $h($t('কর্মী নয়', 'No worker')); ?></option>
                            <?php foreach ($engineers as $eg): ?>
                                <option value="<?php echo (int)$eg['id']; ?>"<?php echo (int)$eg['id'] === (int)($sv['engineer_id'] ?? 0) ? ' selected' : ''; ?>><?php echo $h($eg['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><input type="text" name="services[<?php echo (int)$si; ?>][service_type]" value="<?php echo $h($sv['service_type'] ?? ''); ?>" placeholder="<?php echo $h($t('কাজের বিবরণ', 'Work note')); ?>" readonly></div>
                    <div><input type="number" step="0.01" min="0" name="services[<?php echo (int)$si; ?>][amount]" value="<?php echo $h($sv['amount'] ?? 0); ?>" class="iedit-svc-amt" readonly></div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="sub" style="margin:8px 0 0;"><?php echo $h($t('সার্ভিস লাইনের টাকা বদলানো যায় না।', 'Service amounts cannot be changed.')); ?></p>
        </div>

        <div class="iedit-card">
            <div class="iedit-grid">
                <div>
                    <label><?php echo $h($t('ছাড়', 'Discount')); ?></label>
                    <input type="number" step="0.01" min="0" name="discount" id="ieditDisc" value="<?php echo $h($sale['discount'] ?? 0); ?>" readonly>
                </div>
                <div>
                    <label><?php echo $h($t('ভ্যাট %', 'VAT %')); ?></label>
                    <input type="number" step="0.01" min="0" max="100" name="vat_percent" id="ieditVat" value="<?php echo $h($sale['vat_percent'] ?? 0); ?>" readonly>
                </div>
                <div>
                    <label><?php echo $h($t('রাউন্ডিং', 'Rounding')); ?></label>
                    <input type="number" step="0.01" name="rounding" id="ieditRound" value="<?php echo $h($sale['rounding_amount'] ?? 0); ?>" readonly>
                </div>
                <div>
                    <label><?php echo $h($t('পরিশোধিত', 'Paid')); ?></label>
                    <input type="number" step="0.01" min="0" name="paid" id="ieditPaid" value="<?php echo $h($sale['paid_amount'] ?? 0); ?>" readonly>
                </div>
                <div style="grid-column: span 2;">
                    <label><?php echo $h($t('পেমেন্ট অ্যাকাউন্ট', 'Payment account')); ?></label>
                    <select name="account_id" id="ieditAcc" disabled>
                        <option value="0"><?php echo $h($t('অ্যাকাউন্ট নয় / বাকি', 'No account / due')); ?></option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo (int)$acc['id']; ?>"<?php echo (int)$acc['id'] === (int)($sale['account_id'] ?? 0) ? ' selected' : ''; ?>><?php echo $h($acc['account_name'] ?? ('#' . $acc['id'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><?php echo $h($t('নোট', 'Note')); ?></label>
                    <input type="text" name="guest_customer_note" value="<?php echo $h($sale['guest_customer_note'] ?? ''); ?>">
                </div>
                <div style="display:flex; align-items:flex-end;">
                    <p class="sub" style="margin:0;"><?php echo $h($t('ছাড়, পরিশোধ ও অ্যাকাউন্ট লক — কোনো মডিউল থেকে টাকা বদলাবে না।', 'Discount, paid and account are locked — no module changes money.')); ?></p>
                </div>
            </div>
            <div class="totals" style="margin-top:12px;">
                <div class="tot"><span><?php echo $h($t('সাবটোটাল', 'Subtotal')); ?></span><strong id="ieditSub">৳0.00</strong></div>
                <div class="tot"><span><?php echo $h($t('মোট বিল', 'Bill')); ?></span><strong id="ieditBill">৳0.00</strong></div>
                <div class="tot"><span><?php echo $h($t('পরিশোধ', 'Paid')); ?></span><strong id="ieditPaidV">৳0.00</strong></div>
                <div class="tot"><span><?php echo $h($t('বাকি', 'Due')); ?></span><strong id="ieditDue">৳0.00</strong></div>
            </div>
        </div>

        <div class="iedit-bal" id="ieditBal"<?php echo $cid > 0 && !empty($bal['ok']) ? '' : ' hidden'; ?>>
            <div class="iedit-bal-head">
                <strong id="ieditBalName"><?php echo $h($bal['name'] ?? ''); ?></strong>
                <span id="ieditBalCode"><?php echo $h(trim(($bal['code'] ?? '') . (($bal['phone'] ?? '') !== '' ? ' · ' . $bal['phone'] : ''))); ?></span>
            </div>
            <div class="iedit-bal-row"><span><?php echo $h($t('সাবেক বকেয়া', 'Previous balance')); ?><small id="ieditBalPrevHint"></small></span><b id="ieditBalPrev">৳0.00</b></div>
            <div class="iedit-bal-row"><span><?php echo $h($t('(+) এই ইনভয়েসের বিল', '(+) This invoice bill')); ?></span><b id="ieditBalBill" class="plus">৳0.00</b></div>
            <div class="iedit-bal-row"><span><?php echo $h($t('(−) এই বিলে পরিশোধ', '(−) Paid on this bill')); ?></span><b id="ieditBalPaid" class="minus">৳0.00</b></div>
            <div class="iedit-bal-row"><span><?php echo $h($t('(−) পুরনো বকেয়ায় সমন্বয়', '(−) Adjusted from previous due')); ?><small><?php echo $h($t('বিলের বেশি দেওয়া টাকা থেকে', 'From cash paid above the bill')); ?></small></span><b id="ieditBalAdj" class="minus">৳0.00</b></div>
            <div class="iedit-bal-row total"><span><?php echo $h($t('সর্বমোট বকেয়া (এই মেমোর পর)', 'Balance after this memo')); ?><small id="ieditBalAfterHint"></small></span><b id="ieditBalAfter">৳0.00</b></div>
            <div class="iedit-bal-row"><span><?php echo $h($t('এই ইনভয়েসে বকেয়ার পরিবর্তন', 'Change in balance by this invoice')); ?></span><b id="ieditBalDelta">৳0.00</b></div>
            <div class="iedit-bal-row" id="ieditBalSupRow" hidden><span><?php echo $h($t('সাপ্লায়ারের হিসাব (দেনা +, পাওনা −)', 'Supplier view (we owe +, they owe −)')); ?><small id="ieditBalSupHint"></small></span><b id="ieditBalSup">৳0.00</b></div>
            <div class="iedit-bal-proof" id="ieditBalProof"></div>
            <div class="iedit-bal-note" id="ieditBalNote" hidden></div>
        </div>

        <div class="row-actions">
            <button type="button" class="btn ghost" id="ieditCopyMemo"<?php echo !empty($memo_plain['ok']) ? '' : ' disabled'; ?>><?php echo $h($t('মেমো বিবরণ কপি', 'Copy memo details')); ?></button>
            <?php if ($can_reopen && strtolower((string)($sale['status'] ?? '')) === 'completed'): ?>
                <button type="submit" name="save_action" value="reopen_draft" class="btn warn" id="ieditReopenDraft"><?php echo $h($t('ড্রাফটে আনুন (POS)', 'Reopen as draft (POS)')); ?></button>
            <?php endif; ?>
            <button type="submit" name="save_action" value="void_lines" class="btn danger" id="ieditVoidSave" disabled><?php echo $h($t('পণ্য বাতিল সেভ', 'Save cancelled lines')); ?></button>
            <button type="submit" name="save_action" value="customer" class="btn green" id="ieditCustomerSave"><?php echo $h($t('কাস্টমার সেভ করুন', 'Save customer')); ?></button>
            <a class="btn ghost" href="/modules/reports/sales_report.php?q=<?php echo $h(rawurlencode($invoice)); ?>"><?php echo $h($t('রিপোর্টে ফিরুন', 'Back to report')); ?></a>
            <a class="btn ghost" href="/modules/sales/invoice.php?id=<?php echo (int)$sale['id']; ?>"><?php echo $h($t('প্রিন্ট মেমো', 'Print memo')); ?></a>
        </div>
    </form>
</div>
<script>
(function () {
    var memoCopyText = <?php echo json_encode($memo_plain['text'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
    var origCusId = <?php echo json_encode($cid > 0 ? (string)$cid : '0'); ?>;
    var origCusType = <?php echo json_encode($cid > 0 ? 'Registered' : 'Retail', JSON_UNESCAPED_UNICODE); ?>;
    var copyBtn = document.getElementById('ieditCopyMemo');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            if (!memoCopyText) {
                return;
            }
            var done = function () {
                copyBtn.textContent = <?php echo json_encode($t('কপি হয়েছে ✓', 'Copied ✓'), JSON_UNESCAPED_UNICODE); ?>;
                window.setTimeout(function () {
                    copyBtn.textContent = <?php echo json_encode($t('মেমো বিবরণ কপি', 'Copy memo details'), JSON_UNESCAPED_UNICODE); ?>;
                }, 2200);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(memoCopyText).then(done).catch(function () {
                    window.prompt(<?php echo json_encode($t('কপি করুন:', 'Copy:'), JSON_UNESCAPED_UNICODE); ?>, memoCopyText);
                });
                return;
            }
            window.prompt(<?php echo json_encode($t('কপি করুন:', 'Copy:'), JSON_UNESCAPED_UNICODE); ?>, memoCopyText);
            done();
        });
    }
    var itemBox = document.getElementById('ieditItems');
    var svcBox = document.getElementById('ieditServices');
    var money = function (n) { return '৳' + (Math.round(n * 100) / 100).toFixed(2); };
    var nextItem = itemBox ? itemBox.querySelectorAll('tr').length : 0;
    var nextSvc = svcBox ? svcBox.querySelectorAll('.iedit-svc').length : 0;
    var engHtml = <?php echo json_encode(array_map(static function ($eg) {
        return ['id' => (int)$eg['id'], 'name' => (string)$eg['name']];
    }, $engineers), JSON_UNESCAPED_UNICODE); ?>;

    var voidConfirmTxt = <?php echo json_encode($t('এই পণ্য মেমো থেকে বাতিল করবেন? স্টক ও বিল আপডেট হবে।', 'Cancel this product on the memo? Stock and bill will update.'), JSON_UNESCAPED_UNICODE); ?>;
    function isVoided(tr) {
        var flag = tr.querySelector('.iedit-void-flag');
        return !!(flag && String(flag.value) === '1');
    }
    function syncVoidSave() {
        var btn = document.getElementById('ieditVoidSave');
        if (!btn || !itemBox) { return; }
        var any = false;
        itemBox.querySelectorAll('tr').forEach(function (tr) {
            if (isVoided(tr)) { any = true; }
        });
        btn.disabled = !any;
    }
    function lineTotal(tr) {
        if (isVoided(tr)) {
            var cell0 = tr.querySelector('.iedit-line');
            if (cell0) { cell0.textContent = money(0); }
            return 0;
        }
        var q = parseFloat((tr.querySelector('.iedit-qty') || {}).value || 0);
        var p = parseFloat((tr.querySelector('.iedit-price') || {}).value || 0);
        var cell = tr.querySelector('.iedit-line');
        if (cell) { cell.textContent = money(q * p); }
        return q * p;
    }
    var balState = {
        on: <?php echo ($cid > 0 && !empty($bal['ok'])) ? 'true' : 'false'; ?>,
        prev: <?php echo json_encode((float)($bal['previous'] ?? 0)); ?>,
        live: <?php echo json_encode((float)($bal['live'] ?? 0)); ?>,
        same: <?php echo !empty($bal['same']) ? 'true' : 'false'; ?>,
        supplier: <?php echo !empty($bal['supplier']) ? 'true' : 'false'; ?>
    };
    var bn = <?php echo json_encode($en ? ['due' => 'due', 'adv' => 'advance (shop owes)', 'clear' => 'clear', 'same' => 'Kept from this invoice', 'live' => 'Current balance of the customer', 'extra' => 'Extra cash %s: adjusted %a, change %c.', 'noadj' => 'Extra cash %s is not adjusted - tick the box to cut it from the previous due.', 'sowe' => 'we owe the supplier', 'sthey' => 'supplier owes us'] : ['due' => 'বাকি', 'adv' => 'জমা (দোকানের দেনা)', 'clear' => 'পরিষ্কার', 'same' => 'এই ইনভয়েসে রাখা সাবেক', 'live' => 'কাস্টমারের এখনকার ব্যালেন্স', 'extra' => 'বিলের বেশি টাকা %s: সমন্বয় %a, ফেরত %c।', 'noadj' => 'বিলের বেশি টাকা %s সমন্বয় হচ্ছে না — পুরনো বকেয়া কাটতে নিচের টিক দিন।', 'sowe' => 'সাপ্লায়ারকে আমরা দেব', 'sthey' => 'সাপ্লায়ার আমাদের দেবে'], JSON_UNESCAPED_UNICODE); ?>;
    function fmt(n) { return Math.abs(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function sgn(n) { n = Math.round(n * 100) / 100; return (n < -0.004 ? '−' : '') + '৳' + fmt(n); }
    function tone(el, n) { el.className = n > 0.004 ? 'plus' : (n < -0.004 ? 'minus' : 'zero'); }
    function balHint(n) { return n > 0.004 ? bn.due : (n < -0.004 ? bn.adv : bn.clear); }
    function renderBal(bill, paid) {
        var box = document.getElementById('ieditBal');
        if (!box) { return; }
        box.hidden = !balState.on;
        if (!balState.on) { return; }
        var prev = balState.prev;
        var paidForSale = Math.min(paid, bill);
        var extra = Math.max(0, Math.round((paid - bill) * 100) / 100);
        var adjBox = document.querySelector('input[name="adjust_due"]');
        var wants = !!(adjBox && adjBox.checked);
        var adj = 0;
        if (extra > 0.009) { adj = wants ? extra : Math.min(extra, Math.max(0, prev)); }
        adj = Math.round(adj * 100) / 100;
        var change = Math.max(0, Math.round((extra - adj) * 100) / 100);
        var after = Math.round((prev + bill - paidForSale - adj) * 100) / 100;
        var delta = Math.round((after - prev) * 100) / 100;
        var set = function (id, text, n) { var el = document.getElementById(id); if (el) { el.textContent = text; if (n !== undefined) { tone(el, n); } } };
        set('ieditBalPrev', sgn(prev), prev);
        document.getElementById('ieditBalPrevHint').textContent = balHint(prev) + ' · ' + (balState.same ? bn.same : bn.live);
        set('ieditBalBill', '+' + sgn(bill).replace('−', ''), 1);
        set('ieditBalPaid', paidForSale > 0.004 ? '−' + sgn(paidForSale).replace('−', '') : '৳0.00', paidForSale > 0.004 ? -1 : 0);
        set('ieditBalAdj', adj > 0.004 ? '−' + sgn(adj).replace('−', '') : '৳0.00', adj > 0.004 ? -1 : 0);
        set('ieditBalAfter', sgn(after), after);
        document.getElementById('ieditBalAfterHint').textContent = balHint(after);
        set('ieditBalDelta', (delta > 0.004 ? '+' : (delta < -0.004 ? '−' : '')) + '৳' + fmt(delta), delta);
        var supRow = document.getElementById('ieditBalSupRow');
        if (supRow) {
            supRow.hidden = !balState.supplier;
            if (balState.supplier) {
                var sup = Math.round(-after * 100) / 100;
                var supEl = document.getElementById('ieditBalSup');
                supEl.textContent = (sup > 0.004 ? '+' : (sup < -0.004 ? '−' : '')) + '৳' + fmt(sup);
                tone(supEl, sup);
                document.getElementById('ieditBalSupHint').textContent = sup > 0.004 ? bn.sowe : (sup < -0.004 ? bn.sthey : bn.clear);
            }
        }
        document.getElementById('ieditBalProof').textContent = sgn(prev) + ' + ' + sgn(bill) + ' − ' + sgn(paidForSale) + (adj > 0.004 ? ' − ' + sgn(adj) : '') + ' = ' + sgn(after);
        var note = document.getElementById('ieditBalNote');
        if (extra > 0.009) {
            note.hidden = false;
            note.textContent = (adj > 0.004 || wants)
                ? bn.extra.replace('%s', sgn(extra)).replace('%a', sgn(adj)).replace('%c', sgn(change))
                : bn.noadj.replace('%s', sgn(extra));
        } else {
            note.hidden = true;
        }
    }
    function recalc() {
        var sub = 0;
        itemBox.querySelectorAll('tr').forEach(function (tr) { sub += lineTotal(tr); });
        var svc = 0;
        svcBox.querySelectorAll('.iedit-svc-amt').forEach(function (el) { svc += parseFloat(el.value || 0); });
        var disc = parseFloat(document.getElementById('ieditDisc').value || 0);
        var vat = parseFloat(document.getElementById('ieditVat').value || 0);
        var round = parseFloat(document.getElementById('ieditRound').value || 0);
        var paid = parseFloat(document.getElementById('ieditPaid').value || 0);
        var vatAmt = sub * vat / 100;
        var bill = sub + vatAmt + svc + round - disc;
        if (bill < 0) { bill = 0; }
        var due = bill - paid;
        if (due < 0) { due = 0; }
        document.getElementById('ieditSub').textContent = money(sub);
        document.getElementById('ieditBill').textContent = money(bill);
        document.getElementById('ieditPaidV').textContent = money(paid);
        document.getElementById('ieditDue').textContent = money(due);
        renderBal(bill, paid);
    }
    function bindRow(tr) {
        tr.querySelectorAll('input').forEach(function (el) { el.addEventListener('input', recalc); });
        var del = tr.querySelector('.iedit-del');
        if (del) {
            del.addEventListener('click', function () { tr.remove(); recalc(); syncVoidSave(); });
        }
        var vbtn = tr.querySelector('.iedit-void');
        if (vbtn) {
            vbtn.addEventListener('click', function () {
                if (isVoided(tr)) {
                    var flag = tr.querySelector('.iedit-void-flag');
                    if (flag) { flag.value = '0'; }
                    tr.classList.remove('iedit-voided');
                    vbtn.textContent = <?php echo json_encode($t('বাতিল', 'Cancel'), JSON_UNESCAPED_UNICODE); ?>;
                    recalc();
                    syncVoidSave();
                    return;
                }
                if (!window.confirm(voidConfirmTxt)) { return; }
                var active = 0;
                itemBox.querySelectorAll('tr').forEach(function (row) {
                    if (!isVoided(row)) { active += 1; }
                });
                if (active <= 1) {
                    var hasSvc = svcBox && svcBox.querySelectorAll('.iedit-svc-amt').length > 0;
                    var svcAmt = 0;
                    if (hasSvc) {
                        svcBox.querySelectorAll('.iedit-svc-amt').forEach(function (el) { svcAmt += parseFloat(el.value || 0); });
                    }
                    if (!hasSvc || svcAmt < 0.01) {
                        alert(<?php echo json_encode($t('সব পণ্য বাতিল করা যাবে না। অন্তত একটি পণ্য বা সার্ভিস থাকতে হবে।', 'You cannot cancel every product. At least one product or service must remain.'), JSON_UNESCAPED_UNICODE); ?>);
                        return;
                    }
                }
                var voidFlag = tr.querySelector('.iedit-void-flag');
                if (voidFlag) { voidFlag.value = '1'; }
                tr.classList.add('iedit-voided');
                vbtn.textContent = <?php echo json_encode($t('ফিরিয়ে আনুন', 'Undo'), JSON_UNESCAPED_UNICODE); ?>;
                recalc();
                syncVoidSave();
            });
        }
    }
    itemBox.querySelectorAll('tr').forEach(bindRow);
    svcBox.querySelectorAll('.iedit-svc').forEach(function (row) {
        row.querySelectorAll('input,select').forEach(function (el) { el.addEventListener('input', recalc); el.addEventListener('change', recalc); });
        var del = row.querySelector('.iedit-del-svc');
        if (del) { del.addEventListener('click', function () { row.remove(); recalc(); }); }
    });
    var adjChk = document.querySelector('input[name="adjust_due"]');
    if (adjChk) { adjChk.addEventListener('change', recalc); }
    ['ieditDisc','ieditVat','ieditRound','ieditPaid'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('input', recalc); }
    });

    function addItem(pid, name, price, custom) {
        if (!custom && pid) {
            var held = itemBox.querySelectorAll('tr');
            for (var r = 0; r < held.length; r++) {
                var hid = held[r].querySelector('input[name$="[product_id]"]');
                if (!hid || String(hid.value) !== String(pid) || String(pid) === '0') {
                    continue;
                }
                var qtyBox = held[r].querySelector('.iedit-qty');
                if (qtyBox) {
                    qtyBox.value = (parseFloat(qtyBox.value) || 0) + 1;
                }
                itemBox.appendChild(held[r]);
                recalc();
                return;
            }
        }
        var i = nextItem++;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>'
            + '<input type="hidden" name="items[' + i + '][product_id]" value="' + (pid || 0) + '">'
            + '<input type="hidden" name="items[' + i + '][is_custom]" value="' + (custom ? '1' : '0') + '">'
            + '<input type="text" name="items[' + i + '][name]" value="' + String(name || '').replace(/"/g, '&quot;') + '">'
            + '</td>'
            + '<td><input type="number" step="0.001" min="0" name="items[' + i + '][qty]" value="1" class="iedit-qty"></td>'
            + '<td><input type="number" step="0.01" min="0" name="items[' + i + '][price]" value="' + (price || 0) + '" class="iedit-price"></td>'
            + '<td class="iedit-line">৳0.00</td>'
            + '<td><button type="button" class="btn danger iedit-del">✕</button></td>';
        itemBox.appendChild(tr);
        bindRow(tr);
        recalc();
    }
    var addCustom = document.getElementById('ieditAddCustom');
    if (addCustom) {
        addCustom.addEventListener('click', function () {
            addItem(0, '', 0, true);
        });
    }
    var addSvc = document.getElementById('ieditAddSvc');
    if (addSvc) {
        addSvc.addEventListener('click', function () {
            var i = nextSvc++;
            var row = document.createElement('div');
            row.className = 'iedit-grid iedit-svc';
            row.style.marginBottom = '8px';
            var opts = '<option value="0"><?php echo $h($t('কর্মী নয়', 'No worker')); ?></option>';
            engHtml.forEach(function (eg) {
                opts += '<option value="' + eg.id + '">' + String(eg.name).replace(/</g, '') + '</option>';
            });
            row.innerHTML = '<div><select name="services[' + i + '][engineer_id]">' + opts + '</select></div>'
                + '<div><input type="text" name="services[' + i + '][service_type]" placeholder="<?php echo $h($t('কাজের বিবরণ', 'Work note')); ?>"></div>'
                + '<div><input type="number" step="0.01" min="0" name="services[' + i + '][amount]" value="0" class="iedit-svc-amt"></div>'
                + '<div><button type="button" class="btn danger iedit-del-svc">✕</button></div>';
            svcBox.appendChild(row);
            row.querySelectorAll('input,select').forEach(function (el) { el.addEventListener('input', recalc); el.addEventListener('change', recalc); });
            row.querySelector('.iedit-del-svc').addEventListener('click', function () { row.remove(); recalc(); });
        });
    }

    function bindSuggest(input, list, url, onPick) {
        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 1) { list.hidden = true; list.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch(url + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { onPick(data, list); })
                    .catch(function () { list.hidden = true; });
            }, 180);
        });
        document.addEventListener('click', function (e) {
            if (!list.contains(e.target) && e.target !== input) { list.hidden = true; }
        });
    }
    bindSuggest(document.getElementById('ieditCusQ'), document.getElementById('ieditCusList'), '/modules/customers/actions/customer_search.php?q=', function (data, list) {
        var rows = data.customers || data || [];
        list.innerHTML = '';
        rows.slice(0, 12).forEach(function (c) {
            var li = document.createElement('li');
            li.textContent = (c.name || '') + ' · ' + (c.phone || '') + ' · ' + (c.code || c.customer_code || '');
            li.addEventListener('click', function () {
                document.getElementById('ieditCusId').value = c.id;
                document.getElementById('ieditCusQ').value = c.name || '';
                document.getElementById('ieditType').value = 'Registered';
                list.hidden = true;
                loadBal(c.id, c);
            });
            list.appendChild(li);
        });
        list.hidden = list.children.length === 0;
    });
    var prodQ = document.getElementById('ieditProdQ');
    var prodList = document.getElementById('ieditProdList');
    if (prodQ && prodList) {
        bindSuggest(prodQ, prodList, '/modules/sales/search_product.php?q=', function (data, list) {
            var rows = Array.isArray(data) ? data : (data.products || []);
            list.innerHTML = '';
            rows.slice(0, 12).forEach(function (p) {
                var li = document.createElement('li');
                var name = p.name || p.item_name || '';
                var price = p.sale_price || p.price || p.current_price || 0;
                li.textContent = name + ' · ৳' + price;
                li.addEventListener('click', function () {
                    addItem(p.id || p.product_id || 0, name, price, false);
                    prodQ.value = '';
                    list.hidden = true;
                });
                list.appendChild(li);
            });
            list.hidden = list.children.length === 0;
        });
    }
    function loadBal(cid, c) {
        var cus = document.getElementById('ieditMemoCus'), tel = document.getElementById('ieditMemoTel');
        if (cus && c) { cus.textContent = c.name || ''; }
        if (tel && c) { tel.textContent = c.phone || '—'; }
        fetch('/modules/reports/invoice_edit_balance.php?sale_id=<?php echo (int)$sale['id']; ?>&customer_id=' + encodeURIComponent(cid), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { balState.on = false; recalc(); return; }
                balState.on = true; balState.prev = d.previous; balState.live = d.live; balState.same = !!d.same; balState.supplier = !!d.supplier;
                document.getElementById('ieditBalName').textContent = d.name || '';
                document.getElementById('ieditBalCode').textContent = (d.code || '') + (d.phone ? ' · ' + d.phone : '');
                recalc();
            })
            .catch(function () { balState.on = false; recalc(); });
    }
    document.getElementById('ieditType').addEventListener('change', function () {
        if (this.value === 'Retail') {
            document.getElementById('ieditCusId').value = '';
            balState.on = false;
            var cus = document.getElementById('ieditMemoCus');
            if (cus) { cus.textContent = <?php echo json_encode($t('খুচরা ক্রেতা', 'Walk-in'), JSON_UNESCAPED_UNICODE); ?>; }
            recalc();
        }
    });
    function effectiveCusId() {
        var typeEl = document.getElementById('ieditType');
        if (typeEl && typeEl.value === 'Retail') {
            return '0';
        }
        var hid = document.getElementById('ieditCusId');
        return hid && hid.value ? String(hid.value) : '0';
    }
    function customerIdentityChanged() {
        var typeEl = document.getElementById('ieditType');
        var typeNow = typeEl ? typeEl.value : origCusType;
        if (typeNow !== origCusType) {
            return true;
        }
        return effectiveCusId() !== String(origCusId);
    }
    document.getElementById('ieditForm').addEventListener('submit', function (ev) {
        var sub = ev.submitter;
        if (sub && sub.name === 'save_action' && sub.value === 'reopen_draft') {
            if (!window.confirm(<?php echo json_encode($t('মেমো ড্রাফটে আনলে পুরনো ক্রেতার লেজার/ক্যাশ/স্টক রিভার্স হবে। POS-এ গিয়ে আবার সম্পন্ন করতে হবে। চালাবেন?', 'Reopening reverses ledger, cash and stock for the old customer. You must complete again on POS. Continue?'), JSON_UNESCAPED_UNICODE); ?>)) {
                ev.preventDefault();
                return;
            }
            var intentSave = document.getElementById('ieditIntentSave');
            if (intentSave) {
                intentSave.value = 'reopen_draft';
            }
        }
        if (sub && sub.name === 'save_action' && sub.value === 'void_lines') {
            if (!window.confirm(<?php echo json_encode($t('বাতিল পণ্য সেভ করলে বিল, বাকি, স্টক ও হিসাব নতুন করে ঠিক হবে। চালিয়ে যাবেন?', 'Saving cancelled lines will recalculate bill, due, stock and ledger. Continue?'), JSON_UNESCAPED_UNICODE); ?>)) {
                ev.preventDefault();
                return;
            }
        }
        if (sub && sub.name === 'save_action' && sub.value === 'customer') {
            var voidedAny = false;
            if (itemBox) {
                itemBox.querySelectorAll('tr').forEach(function (tr) {
                    if (isVoided(tr)) { voidedAny = true; }
                });
            }
            if (voidedAny) {
                if (sub.value !== 'void_lines') {
                    if (!window.confirm(<?php echo json_encode($t('বাতিল চিহ্নিত পণ্য আছে — সেভ করলে বিল/স্টক/লেজার আপডেট হবে। চালাবেন?', 'Cancelled lines are marked — saving will update bill, stock and ledger. Continue?'), JSON_UNESCAPED_UNICODE); ?>)) {
                        ev.preventDefault();
                        return;
                    }
                }
            } else if (customerIdentityChanged()) {
                if (!window.confirm(<?php echo json_encode($t('কাস্টমার বদলালে পুরনো ক্রেতার বকেয়া/পরিশোধ রিভার্স ও নতুন ক্রেতায় এই বিল লাগবে। চালাবেন?', 'Changing customer reverses the old party balance and applies this bill to the new one. Continue?'), JSON_UNESCAPED_UNICODE); ?>)) {
                    ev.preventDefault();
                    return;
                }
            }
        }
        this.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
    });
    recalc();
    syncVoidSave();
})();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
