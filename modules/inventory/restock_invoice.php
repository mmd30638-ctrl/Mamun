<?php
require_once '../../includes/config.php';
require_once '../../includes/stock_alert_functions.php';
require_once '../../includes/workshop_functions.php';

if (!ame_stock_alert_can_view($db)) {
    http_response_code(403);
    die('❌ এই অংশ ব্যবহারের অনুমতি নেই।');
}

$is_english = current_lang() === 'en';
$words = $is_english
    ? [
        'title' => 'Front order',
        'lead' => 'This is the phone front. Change pieces, add a name, then share that front. Names and pieces only.',
        'add' => 'Add a product',
        'add_ph' => 'Type the product name',
        'add_go' => 'Add',
        'add_extra' => 'Add this name as an extra product',
        'add_none' => 'No shop product with this name. You can still add it.',
        'add_dup' => 'This product is already on the list',
        'invoice' => 'Front order',
        'date' => 'Date',
        'store' => 'Store',
        'phone' => 'Phone',
        'empty' => 'Every product is above the alert line. Nothing to order now.',
        'stock' => 'Stock',
        'min' => 'Min',
        'buy' => 'Cost',
        'items' => 'Lines',
        'pcs' => 'Pieces',
        'total' => 'Total',
        'note' => 'This is a restock draft. It does not post a purchase until you complete buy-in.',
        'share_img' => 'Share front',
        'share_pdf' => 'Save front',
        'share_wa' => 'WhatsApp',
        'print' => 'Print',
        'goto_buy' => 'Open purchase page',
        'del' => 'Remove this product',
        'cancel' => 'Delete',
    ]
    : [
        'title' => 'ফ্রন্ট অর্ডার',
        'lead' => 'এই ফোনের সামনেই অর্ডার। পিস বদলান, নাম যোগ করুন, তারপর ফ্রন্ট শেয়ার। শুধু নাম ও পিস।',
        'add' => 'পণ্য যোগ করুন',
        'add_ph' => 'পণ্যের নাম লিখুন',
        'add_go' => 'যোগ',
        'add_extra' => 'এই নামে এক্সট্রা পণ্য যোগ',
        'add_none' => 'দোকানে এই নামের পণ্য নেই। তারপরও যোগ করা যাবে।',
        'add_dup' => 'এই পণ্য তালিকায় আছে',
        'invoice' => 'ফ্রন্ট অর্ডার',
        'date' => 'তারিখ',
        'store' => 'স্টোর',
        'phone' => 'ফোন',
        'empty' => 'সব পণ্যের স্টক অ্যালার্ট লাইনের উপরে আছে। এখন অর্ডার করার কিছু নেই।',
        'stock' => 'স্টক',
        'min' => 'মিন',
        'buy' => 'ক্রয়',
        'items' => 'পণ্য',
        'pcs' => 'পিস',
        'total' => 'মোট',
        'note' => 'এই ফ্রন্ট শুধু অর্ডার। স্টক বা টাকা এখানে বদলায় না।',
        'share_img' => 'ফ্রন্ট শেয়ার',
        'share_pdf' => 'ফ্রন্ট সেভ',
        'share_wa' => 'হোয়াটসঅ্যাপ',
        'print' => 'প্রিন্ট',
        'goto_buy' => 'ক্রয় পেজ খুলুন',
        'del' => 'এই পণ্য মুছুন',
        'cancel' => 'মুছুন',
    ];

$h = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$fmt = static function ($n) {
    return number_format((float)$n, 2, '.', ',');
};
$qtyf = static function ($n) {
    $s = rtrim(rtrim(number_format((float)$n, 3, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
};

$items = ame_stock_alert_items($db);
$shop_name = function_exists('shop_name') ? shop_name() : (string)($shop['shop_name'] ?? '');
$shop_phone = function_exists('shop_phone') ? shop_phone() : '';
$shop_address = function_exists('shop_address') ? shop_address() : '';
$store_name = function_exists('current_store_name') ? (string)current_store_name() : '';
$invoice_no = 'RSTK-' . date('Ymd-His');
$invoice_date = date('d/m/Y h:i A');
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.74', ENT_QUOTES, 'UTF-8');

$ame_body_class = 'rstk-body';
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/restock-invoice.css?v=<?php echo $ver; ?>-s74">

<div class="rstk-page">
  <div class="rstk-phone" data-front="390">
    <div class="rstk-ear" aria-hidden="true"></div>
    <p class="rstk-lead"><?php echo $h($words['lead']); ?></p>

    <div class="rstk-add no-print">
        <p class="rstk-add-kicker"><?php echo $h($words['title']); ?></p>
        <label for="rstkAddQ"><?php echo $h($words['add']); ?></label>
        <div class="rstk-add-row">
            <input id="rstkAddQ" type="text" inputmode="text" autocomplete="off" enterkeyhint="done" placeholder="<?php echo $h($words['add_ph']); ?>">
            <button type="button" id="rstkAddGo"><?php echo $h($words['add_go']); ?></button>
        </div>
        <div id="rstkAddHits" class="rstk-hits" hidden></div>
    </div>

    <div id="rstkSheet" class="rstk-sheet">
        <div class="rstk-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(52) : ''; ?>
            <h1><?php echo $h($shop_name); ?></h1>
            <p><?php echo $h($words['invoice']); ?></p>
            <?php if ($shop_address !== ''): ?>
                <p><?php echo $h($shop_address); ?></p>
            <?php endif; ?>
        </div>

        <div class="rstk-meta">
            <div>
                <span><?php echo $h($words['invoice']); ?></span>
                <b id="rstkNo"><?php echo $h($invoice_no); ?></b>
            </div>
            <div>
                <span><?php echo $h($words['date']); ?></span>
                <b><?php echo $h($invoice_date); ?></b>
            </div>
            <?php if ($shop_phone !== ''): ?>
            <div>
                <span><?php echo $h($words['phone']); ?></span>
                <b><?php echo $h($shop_phone); ?></b>
            </div>
            <?php endif; ?>
            <?php if ($store_name !== ''): ?>
            <div>
                <span><?php echo $h($words['store']); ?></span>
                <b><?php echo $h($store_name); ?></b>
            </div>
            <?php endif; ?>
        </div>

        <div id="rstkEmpty" class="rstk-empty"<?php echo $items ? ' hidden' : ''; ?>>
            ✅ <?php echo $h($words['empty']); ?>
        </div>

        <ul id="rstkList" class="rstk-list"<?php echo $items ? '' : ' hidden'; ?>>
            <?php foreach ($items as $item): ?>
                <?php
                $code = $item['sku'] !== '' ? $item['sku'] : ('PRD-' . str_pad((string)$item['id'], 6, '0', STR_PAD_LEFT));
                $bits = ['ID: ' . $code];
                if ($item['oem_code'] !== '') {
                    $bits[] = 'OEM ' . $item['oem_code'];
                }
                $unit = $item['unit_name'] !== '' ? ' ' . $item['unit_name'] : '';
                ?>
                <li class="rstk-row" data-pid="<?php echo (int)$item['id']; ?>" data-price="<?php echo $h($item['purchase_price']); ?>">
                    <div class="rstk-row-top">
                        <span class="rstk-idx" aria-hidden="true"></span>
                        <div class="rstk-name-wrap">
                            <p class="rstk-name"><?php echo $h($item['name']); ?></p>
                            <p class="rstk-sub">
                                <span class="rstk-code"><?php echo $h(implode(' · ', $bits)); ?></span>
                                <span class="rstk-hide-share">
                                    · <span class="rstk-stock"><?php echo $h($words['stock']); ?> <?php echo $h($qtyf($item['available'])); ?><?php echo $h($unit); ?></span>
                                    · <?php echo $h($words['min']); ?> <?php echo $h($qtyf($item['min_stock'])); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="rstk-row-bot">
                        <div class="rstk-price rstk-money"><?php echo $h($words['buy']); ?> ৳<?php echo $h($fmt($item['purchase_price'])); ?></div>
                        <div class="rstk-qty rstk-no-capture">
                            <button type="button" data-rstk="minus" aria-label="-">−</button>
                            <input class="rstk-qty-val" type="number" min="1" step="1" value="<?php echo (int)$item['qty']; ?>" inputmode="numeric">
                            <button type="button" data-rstk="plus" aria-label="+">+</button>
                        </div>
                        <div class="rstk-qty-print"><span class="rstk-qty-num"><?php echo (int)$item['qty']; ?></span></div>
                        <div class="rstk-line rstk-money">৳<?php echo $h($fmt($item['line_total'])); ?></div>
                        <button type="button" class="rstk-del rstk-no-capture" data-rstk="del" aria-label="<?php echo $h($words['del']); ?>" onclick="return ameRstkDrop(this)"><?php echo $h($words['cancel']); ?></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div id="rstkFoot" class="rstk-foot"<?php echo $items ? '' : ' hidden'; ?>>
            <div class="rstk-tot">
                <span><?php echo $h($words['items']); ?></span>
                <span id="rstkCount"><?php echo count($items); ?></span>
            </div>
            <div class="rstk-tot">
                <span><?php echo $h($words['pcs']); ?></span>
                <span id="rstkPcs">0</span>
            </div>
            <div class="rstk-tot grand rstk-money">
                <span><?php echo $h($words['total']); ?></span>
                <span id="rstkTotal">৳0.00</span>
            </div>
            <p class="rstk-note rstk-hide-share"><?php echo $h($words['note']); ?></p>
        </div>
    </div>

    <div class="rstk-actions no-print">
        <button type="button" class="rstk-btn-wa" id="rstkGoWa" onclick="return ameRestockShare('wa');"><?php echo $h($words['share_wa']); ?></button>
        <button type="button" class="rstk-btn-img" id="rstkGoShare" onclick="return ameRestockShare('image');"><?php echo $h($words['share_img']); ?></button>
        <button type="button" class="rstk-btn-pdf" id="rstkGoPdf" onclick="return ameRestockShare('save');"><?php echo $h($words['share_pdf']); ?></button>
        <button type="button" class="rstk-btn-print" id="rstkGoPrint" onclick="window.print(); return false;"><?php echo $h($words['print']); ?></button>
        <?php if ($is_admin_hdr || has_permission($db, 'm-purchase')): ?>
        <a class="rstk-btn-buy" href="/modules/purchase/add.php"><?php echo $h($words['goto_buy']); ?></a>
        <?php endif; ?>
    </div>
  </div>
</div>

<script>
window.AME_RSTK_ADD = <?php echo json_encode([
    'search' => '/modules/inventory/actions/restock_product_search.php',
    'none' => $words['add_none'],
    'extra' => $words['add_extra'],
    'dup' => $words['add_dup'],
    'buy' => $words['buy'],
    'stock' => $words['stock'],
    'del' => $words['del'],
    'cancel' => $words['cancel'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script>
window.ameRstkDrop = function (btn) {
    var node = btn;
    if (node && node.nodeType === 3) node = node.parentElement;
    var hit = node && node.closest ? node.closest('[data-rstk="del"], .rstk-del') : node;
    var row = hit && hit.closest ? hit.closest('.rstk-row') : null;
    if (!row) return false;
    var id = String(row.getAttribute('data-pid') || '');
    try {
        var kept = JSON.parse(sessionStorage.getItem('ameRstkHide') || '[]');
        if (!Array.isArray(kept)) kept = [];
        if (id && kept.indexOf(id) < 0) kept.push(id);
        sessionStorage.setItem('ameRstkHide', JSON.stringify(kept));
    } catch (err) {}
    if (row.parentNode) row.parentNode.removeChild(row);
    if (typeof window.ameRstkRecalc === 'function') window.ameRstkRecalc();
    return false;
};
(function () {
    try { sessionStorage.removeItem('ameRstkHide'); } catch (err) {}
    function rstkHit(ev) {
        var t = ev.target;
        if (t && t.nodeType === 3) t = t.parentElement;
        var hit = t && t.closest ? t.closest('[data-rstk="del"], .rstk-del') : null;
        if (!hit || (hit.closest && hit.closest('#ameConfirmActs, .ame-confirm-acts'))) return null;
        return hit;
    }
    function rstkStop(ev) {
        var hit = rstkHit(ev);
        if (!hit) return;
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation();
        if (hit.getAttribute('data-rstk-gone') === '1') return;
        hit.setAttribute('data-rstk-gone', '1');
        window.ameRstkDrop(hit);
    }
    document.addEventListener('pointerdown', rstkStop, true);
    document.addEventListener('click', rstkStop, true);
})();
</script>
<script src="/assets/js/restock-invoice.js?v=<?php echo $ver; ?>-drop614"></script>
<?php include '../../partials/footer.php'; ?>
