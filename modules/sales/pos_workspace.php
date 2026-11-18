<?php
/*
 * POS Workspace — Search, Customer, Price mode, Cart, Service
 */

if (!isset($escape) || !is_callable($escape)) {
    $escape = static function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    };
}

$ame_pos_english = function_exists('current_lang')
    && current_lang() === 'en';

$ame_pos_workspace_words = $ame_pos_english
    ? [
        'sale_information' => 'Sale information',
        'retail_price' => 'K',
        'wholesale_price' => 'P',
        'dealer_price' => 'D',
        'retail_price_full' => 'Retail',
        'wholesale_price_full' => 'Wholesale',
        'dealer_price_full' => 'Dealer',
        'more_prices' => 'Show wholesale and dealer',
        'draft_complete' => 'Complete draft',
        'search_product' => 'Part name or code',
        'search_label' => 'Part name or code',
        'finder_hint' => 'See parts by machine model',
        'customer_info' => 'Customer info',
        'all_sales' => 'Recent sales',
        'recent_draft' => 'Draft',
        'recent_complete' => 'Complete',
        'pay_cash' => 'Cash',
        'cancel_col' => 'Remove',
        'serial' => 'SL',
        'cart' => 'Cart',
        'code' => 'Code',
        'product' => 'Product name',
        'price' => 'Price',
        'quantity' => 'Qty',
        'total' => 'Total',
        'empty_cart' => 'Cart is empty. Search a product to add it.',
        'service' => 'Engineer repair and service',
        'select_engineer' => 'Search by name or ID',
        'service_description' => 'Work description',
        'service_amount' => 'Bill amount',
        'add_engineer_bill' => '+ Add engineer bill',
        'remove_bill' => 'Remove',
        'service_total' => 'Service total',
        'vehicle_no' => 'Vehicle no.',
        'service_buy_price' => 'Cost price (optional)',
        'add_service_to_cart' => 'Add all bills to cart',
        'service_added_to_cart' => '✓ All bills are in the cart',
        'service_engineer_label' => 'Engineer',
        'service_cost_label' => 'Cost',
        'phase_find' => '01 Customer  ·  02 Product search',
        'phase_list' => '03 Product list',
        'cart_lines_total' => 'Total amount (৳)',
    ]
    : [
        'sale_information' => 'বিক্রয় তথ্য',
        'retail_price' => 'K',
        'wholesale_price' => 'P',
        'dealer_price' => 'D',
        'retail_price_full' => 'খুচরা',
        'wholesale_price_full' => 'পাইকারি',
        'dealer_price_full' => 'ডিলার',
        'more_prices' => 'পাইকারি ও ডিলার দেখুন',
        'draft_complete' => 'ড্রাফট কমপ্লিট',
        'search_product' => 'যন্ত্রাংশের নাম বা কোড',
        'search_label' => 'যন্ত্রাংশের নাম বা কোড',
        'finder_hint' => 'মেশিন অনুযায়ী পার্টস দেখুন',
        'customer_info' => 'ক্রেতার তথ্য',
        'all_sales' => 'সাম্প্রতিক বিক্রয়',
        'recent_draft' => 'ড্রাফট',
        'recent_complete' => 'কমপ্লিট',
        'pay_cash' => 'ক্যাশ',
        'cancel_col' => 'বাতিল',
        'serial' => 'ক্রম',
        'cart' => 'কার্ট',
        'code' => 'কোড',
        'product' => 'পণ্যের নাম',
        'price' => 'মূল্য',
        'quantity' => 'পরিমাণ',
        'total' => 'মোট',
        'empty_cart' => 'কার্ট খালি। যন্ত্রাংশ সার্চ করে যোগ করুন।',
        'service' => 'ইঞ্জিনিয়ার মেরামত ও সার্ভিস',
        'select_engineer' => 'কর্মীর নাম বা আইডি লিখে সার্চ',
        'service_description' => 'সার্ভিস বা কাজের বিবরণ',
        'service_amount' => 'বিলের টাকা',
        'add_engineer_bill' => '+ ইঞ্জিনিয়ার বিল যোগ',
        'remove_bill' => 'সরান',
        'service_total' => 'সার্ভিস মোট',
        'vehicle_no' => 'গাড়ির নম্বর',
        'service_buy_price' => 'ক্রয়মূল্য (ঐচ্ছিক)',
        'add_service_to_cart' => 'সবগুলো কার্টে বসান',
        'service_added_to_cart' => '✓ সব বিল কার্টে বসেছে',
        'service_engineer_label' => 'ইঞ্জিনিয়ার',
        'service_cost_label' => 'ক্রয়',
        'phase_find' => '০১ ক্রেতা  ·  ০২ পণ্য খোঁজ',
        'phase_list' => '০৩ পণ্য তালিকা',
        'cart_lines_total' => 'মোট অ্যামাউন্ট (৳)',
    ];

$words = array_merge(
    $ame_pos_workspace_words,
    is_array($words ?? null) ? $words : []
);
$words['retail_price'] = 'K';
$words['wholesale_price'] = 'P';
$words['dealer_price'] = 'D';

include __DIR__ . '/pos_customer_data.php';

$ame_engineer_list = [];
if (!empty($engineer_rows) && is_array($engineer_rows)) {
    $ame_engineer_list = $engineer_rows;
} elseif (isset($engineers) && $engineers instanceof mysqli_result) {
    if ($engineers->num_rows > 0) {
        $engineers->data_seek(0);
    }
    while ($er = $engineers->fetch_assoc()) {
        $ame_engineer_list[] = $er;
    }
}
if (isset($db) && $db instanceof mysqli && function_exists('ame_engineer_bill_expand_roster')) {
    $ame_engineer_list = ame_engineer_bill_expand_roster($db, $ame_engineer_list);
}
$ame_engineer_json = [];
foreach ($ame_engineer_list as $engineer) {
    $ame_engineer_json[] = [
        'id' => (int)($engineer['id'] ?? 0),
        'name' => (string)($engineer['name'] ?? ''),
        'emp_code' => (string)($engineer['emp_code'] ?? ''),
        'employee_id' => (int)($engineer['employee_id'] ?? 0),
        'present' => !empty($engineer['present']),
        'activated_at' => (string)($engineer['activated_at'] ?? ''),
    ];
}
$ame_work_descriptions = [];
if (isset($db) && $db instanceof mysqli) {
    $ame_work_table = @$db->query("SHOW TABLES LIKE 'employee_work_descriptions'");
    if ($ame_work_table && $ame_work_table->num_rows > 0) {
        $ame_work_res = $db->query('SELECT engineer_id, description FROM employee_work_descriptions ORDER BY id ASC');
        if ($ame_work_res) {
            while ($ame_work_row = $ame_work_res->fetch_assoc()) {
                $ame_work_descriptions[] = [
                    'engineer_id' => (int)($ame_work_row['engineer_id'] ?? 0),
                    'description' => (string)($ame_work_row['description'] ?? ''),
                ];
            }
        }
    }
}
?>
<style>
    .ame-pos-search {
        position: relative;
    }
    #search-res {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 20;
        flex-direction: column;
        background: var(--brand-white);
        border-radius: 0 0 8px 8px;
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.12);
    }
    #search-res:not(:empty) {
        display: flex;
    }
    #search-res:empty {
        display: none;
    }
    #search-res .res-item {
        display: flex !important;
        width: 100% !important;
        margin: 0 !important;
        min-height: 44px;
        box-sizing: border-box;
    }
    .table-pos tfoot.ame-pos-cart-total-row td,
    .table-pos tr.ame-pos-cart-total-row td {
        background: #eef3fb !important;
        color: #0b3a82 !important;
        font-weight: 800 !important;
        border-top: 2px solid #0b3a82 !important;
    }
    .table-pos .ame-pos-cart-total-label {
        text-align: right !important;
        padding-right: 10px !important;
    }
    .table-pos .ame-pos-cart-total-val {
        font-size: 16px !important;
    }
    .ame-pos-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
        min-height: 0;
        max-height: none;
        height: auto;
        border: 1px solid var(--pos-c-cfd6de);
        background: var(--brand-white);
    }
    #search-res:not(:empty) {
        max-height: min(55vh, 520px);
        overflow-y: auto;
    }
    .ame-pos-service-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .ame-pos-service-rows {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 10px;
    }
    @media (min-width: 700px) {
        .ame-pos-service-grid {
            grid-template-columns: 1fr 1.4fr 110px auto;
            align-items: center;
        }
    }
    .ame-svc-notice {
        display: none;
        position: fixed;
        top: calc(72px + env(safe-area-inset-top, 0px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000003;
        width: min(420px, calc(100vw - 20px));
        padding: 0;
        border: 0;
        background: transparent;
    }
    .ame-svc-notice.is-on { display: block; }
    .ame-svc-notice-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 14px 14px 14px 12px;
        border-radius: 18px;
        border: 2px solid #16a34a;
        background:
            radial-gradient(circle at 12% 0%, #bbf7d0 0%, transparent 42%),
            linear-gradient(160deg, #ecfdf5 0%, #fffbeb 55%, #ffffff 100%);
        box-shadow: 0 18px 40px rgba(22, 101, 52, 0.22);
        color: #14532d;
    }
    .ame-svc-notice.is-warn .ame-svc-notice-card {
        border-color: #f59e0b;
        background:
            radial-gradient(circle at 12% 0%, #fed7aa 0%, transparent 42%),
            linear-gradient(160deg, #fff7ed 0%, #fef3c7 55%, #ffffff 100%);
        box-shadow: 0 18px 40px rgba(180, 83, 9, 0.2);
        color: #7c2d12;
    }
    .ame-svc-notice-orb {
        flex: 0 0 44px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        background: #166534;
        color: #fff;
        box-shadow: 0 0 0 6px rgba(22, 163, 74, 0.18);
    }
    .ame-svc-notice.is-warn .ame-svc-notice-orb {
        background: #c2410c;
        box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.2);
    }
    .ame-svc-notice-body { flex: 1; min-width: 0; }
    .ame-svc-notice-body strong {
        display: block;
        font-size: 15px;
        line-height: 1.3;
        margin: 0 0 4px;
    }
    .ame-svc-notice-body p {
        margin: 0 0 10px;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.45;
    }
    .ame-svc-notice-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .ame-svc-notice-go, .ame-svc-notice-x {
        border: 0;
        border-radius: 999px;
        min-height: 34px;
        padding: 0 12px;
        font-weight: 800;
        cursor: pointer;
    }
    .ame-svc-notice-go { background: #166534; color: #ecfdf5; }
    .ame-svc-notice.is-warn .ame-svc-notice-go { background: #c2410c; color: #fff7ed; }
    .ame-svc-notice-x { background: #fff; color: #334155; border: 1px solid #dbe4f0; }
    .ame-pos-service-foot {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .ame-pos-service-addall {
        width: 100%;
        min-height: 44px;
        border: 0;
        border-radius: 10px;
        background: #15803d;
        color: #fff;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
    }
    .ame-pos-service-addall.is-added {
        background: #64748b;
    }
    .ame-eng-search-wrap {
        position: relative;
    }
    .ame-svc-desc-wrap { position: relative; }
    .ame-svc-presets {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 4px;
    }
    .ame-svc-presets[hidden] { display: none; }
    .ame-svc-preset {
        display: block;
        width: 100%;
        min-height: 32px;
        margin: 0;
        padding: 6px 8px;
        border: 1px solid #d7dee8;
        border-radius: 8px;
        background: #fff;
        color: #0b1e4d;
        font-size: 16px;
        font-weight: 800;
        text-align: left;
        cursor: pointer;
    }
    .ame-eng-search-res {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 2px);
        z-index: 30;
        display: none;
        flex-direction: column;
        max-height: 220px;
        overflow-y: auto;
        background: var(--brand-white);
        border: 1px solid var(--pos-c-d1d5db);
        border-radius: 8px;
        box-shadow: 0 12px 28px rgba(17, 24, 39, 0.12);
    }
    .ame-eng-search-res.is-open {
        display: flex;
    }
    .ame-duty-hint {
        margin: 0 0 10px;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        line-height: 1.4;
    }
    .ame-duty-dot, .ame-duty-wake {
        width: 28px; height: 28px; border: 0; border-radius: 50%;
        cursor: pointer; font-size: 16px; line-height: 1; background: transparent;
    }
    .ame-eng-search-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        padding: 8px 10px;
        border: 0;
        border-bottom: 1px solid var(--pos-c-f1f5f9);
        background: var(--brand-white);
        text-align: left;
        cursor: pointer;
        font-size: 14px;
        color: var(--pos-c-111827);
    }
    .ame-eng-search-pick { flex: 1; min-width: 0; background: transparent; border: 0; text-align: left; cursor: pointer; color: inherit; }
    .ame-eng-search-item:hover,
    .ame-eng-search-item:focus {
        background: var(--pos-c-eff6ff);
        outline: none;
    }
    .ame-eng-search-item small {
        display: block;
        margin-top: 2px;
        color: var(--pos-c-64748b);
        font-size: 12px;
    }
    .ame-eng-search-empty {
        padding: 10px 12px;
        color: var(--pos-c-64748b);
        font-size: 13px;
    }
</style>

<div class="ame-desk-stack">
        <?php
        $ame_pos_title = function_exists('ame_pos_title_row') ? ame_pos_title_row($db ?? null) : ame_pos_title_defaults();
        $ame_pos_title_line = trim((string)($ame_pos_title['line_text'] ?? ''));
        if ($ame_pos_title_line === '' && function_exists('ame_pos_title_default')) {
            $ame_pos_title_line = ame_pos_title_default();
        }
        $ame_pos_title_style = function_exists('ame_pos_title_style_attr') ? ame_pos_title_style_attr($ame_pos_title) : '';
        $ame_pos_title_can_edit = function_exists('ame_can_edit_pos_title') && ame_can_edit_pos_title();
        ?>
        <div class="ame-desk-bar">
            <div class="ame-desk-clockbox" hidden></div>
            <strong class="ame-desk-phrase" hidden></strong>
            <nav class="ame-desk-quick" aria-label="<?php echo $ame_pos_english ? 'Sales tools' : 'বিক্রয় টুল'; ?>">
                <?php if (has_permission($db, 'm-sales-report')): ?>
                <a
                    id="ameDeskBarReport"
                    class="ame-desk-quick-btn"
                    href="/modules/reports/sales_report.php"
                    title="<?php echo $ame_pos_english ? 'Sales report' : 'বিক্রয় রিপোর্ট'; ?>"
                    aria-label="<?php echo $ame_pos_english ? 'Sales report' : 'বিক্রয় রিপোর্ট'; ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </a>
                <?php endif; ?>
                <button
                    type="button"
                    id="ameDeskBarPrint"
                    class="ame-desk-quick-btn"
                    title="<?php echo $ame_pos_english ? 'Print this memo' : 'এই মেমো প্রিন্ট'; ?>"
                    aria-label="<?php echo $ame_pos_english ? 'Print this memo' : 'এই মেমো প্রিন্ট'; ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V4h10v4" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="5" y="8" width="14" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 14h10v6H7z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                </button>
                <button
                    type="button"
                    id="ameDeskBarShare"
                    class="ame-desk-quick-btn"
                    title="<?php echo $ame_pos_english ? 'Share memo image / PDF' : 'মেমো ছবি বা পিডিএফ শেয়ার'; ?>"
                    aria-label="<?php echo $ame_pos_english ? 'Share memo image / PDF' : 'মেমো ছবি বা পিডিএফ শেয়ার'; ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="17" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m8 11 7-3M8 13l7 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                </button>
                <a
                    id="ameDeskBarDesigner"
                    class="ame-desk-quick-btn"
                    href="/modules/settings/layout_designer.php?page=sales/pos"
                    title="<?php echo $ame_pos_english ? 'POS Layout Designer (Drag & Drop)' : 'বিক্রয় পেজ লেআউট ও বক্স এডিটর (ড্র্যাগ & ড্রপ)'; ?>"
                    aria-label="<?php echo $ame_pos_english ? 'POS Layout Designer' : 'বিক্রয় পেজ লেআউট ও বক্স এডিটর'; ?>"
                    style="background:#fef08a;color:#854d0e;border-color:#facc15;"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" title="লেআউট এডিটর"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                </a>
            </nav>
            <a class="ame-desk-home" href="/index.php"><?php echo $ame_pos_english ? 'Home' : 'হোমে যান'; ?></a>
            <strong id="ameDeskClock" class="ame-desk-clock">--:--:--</strong>
            <span id="ameDeskDate" class="ame-desk-datepill"></span>
        </div>
    <div class="ame-desk-main">
    <section class="ame-pos-card ame-pos-term-top">
        <div class="ame-desk-price-row" id="posPriceModeBar">
            <button
                type="button"
                id="priceModeToggle"
                class="ame-pos-mode-arrow"
                hidden
                aria-expanded="true"
                aria-controls="extraPriceModes"
                title="<?php echo $escape($words['more_prices']); ?>"
            >▸</button>
            <div id="extraPriceModes" class="ame-pos-mode-extra is-open show">
                <label class="ame-pos-mode" title="<?php echo $escape($words['retail_price_full']); ?>">
                    <input type="radio" name="price_mode" value="retail" checked onchange="renderCart()">
                    <span class="ame-pos-price-letter">K</span>
                </label>
                <label class="ame-pos-mode" title="<?php echo $escape($words['wholesale_price_full']); ?>">
                    <input type="radio" name="price_mode" value="wholesale" onchange="renderCart()">
                    <span class="ame-pos-price-letter">P</span>
                </label>
                <label class="ame-pos-mode" title="<?php echo $escape($words['dealer_price_full']); ?>">
                    <input type="radio" name="price_mode" value="dealer" onchange="renderCart()">
                    <span class="ame-pos-price-letter">D</span>
                </label>
            </div>
        </div>
        <?php if ($ame_pos_title_line !== ''): ?>
        <div
            class="ame-pos-title-line<?php echo $ame_pos_title_can_edit ? ' is-editable' : ''; ?>"
            id="amePosTitleLine"
            style="<?php echo esc_html($ame_pos_title_style); ?>"
            <?php if ($ame_pos_title_can_edit): ?>
            role="button"
            tabindex="0"
            title="এই লেখা এডিট করুন"
            onclick="ameOpenPosTitleEdit()"
            onkeydown="if(event.key==='Enter'){ameOpenPosTitleEdit();}"
            <?php endif; ?>
        ><span class="ame-pos-title-live"><?php echo $escape($ame_pos_title_line); ?></span></div>
        <?php endif; ?>
        <div class="ame-phase"><?php echo $escape($words['phase_find']); ?></div>
        <div class="ame-pos-term-bar">
            <input type="hidden" id="amePosSaleMode" value="complete">
            <div class="ame-pos-term-controls">
                <div class="ame-desk-card-head"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 19c1.5-3 3.8-4.5 7-4.5S17.5 16 19 19" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><span><?php echo $ame_pos_english ? 'Customer / Supplier' : 'ক্রেতা / সাপ্লায়ার'; ?></span></div>
                <div class="ame-pos-term-radios">
                    <div class="ame-pos-radios ame-pos-term-customer-radios" id="posToolbarCustomerRadios">
                        <label>
                            <input type="radio" name="cus_type_toolbar" value="Retail" checked>
                            <?php echo $escape($words['retail_customer'] ?? ($ame_pos_english ? 'Retail customer' : 'রিটেইল ক্রেতা')); ?>
                        </label>
                        <label>
                            <input type="radio" name="cus_type_toolbar" value="Registered">
                            <?php echo $escape($words['registered_customer'] ?? ($ame_pos_english ? 'Registered customer' : 'নিবন্ধিত ক্রেতা')); ?>
                        </label>
                        <label>
                            <input type="radio" name="cus_type_toolbar" value="Supplier">
                            <?php echo $escape($words['supplier_customer'] ?? ($ame_pos_english ? 'Supplier' : 'সাপ্লায়ার')); ?>
                        </label>
                    </div>
                </div>

                <div
                    class="pos-customer-picker pos-info-picker"
                    id="posCustomerBox"
                    data-none="<?php echo $escape($customer_words['search_none']); ?>"
                    data-hint="<?php echo $escape($customer_words['search_hint']); ?>"
                    data-retail="<?php echo $escape($customer_words['retail']); ?>"
                >
                    <input
                        type="text"
                        id="posCustomerInfoField"
                        class="ame-pos-input"
                        autocomplete="off"
                        spellcheck="false"
                        inputmode="search"
                        enterkeyhint="search"
                        aria-autocomplete="list"
                        aria-controls="posCustomerSearchList"
                        placeholder="<?php echo $escape($words['customer_info']); ?>"
                        data-ph-retail="<?php echo $escape($words['customer_info']); ?>"
                        data-ph-search="<?php echo $escape($customer_words['search_ph']); ?>"
                        data-ph-supplier="<?php echo $escape($words['supplier_search_ph'] ?? ($ame_pos_english ? 'Supplier name, number or mobile' : 'সাপ্লায়ারের নাম, নাম্বার বা মোবাইল')); ?>"
                    >
                    <button
                        type="button"
                        id="ameSavedCustomerBtn"
                        class="ame-saved-customer-btn"
                        title="<?php echo $escape($ame_pos_english ? 'Select a saved customer' : 'সেভ করা গ্রাহক সিলেক্ট'); ?>"
                    >▾</button>
                    <div id="ameCustomerSuggestModal" class="ame-pos-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ameCustomerSuggestTitle">
                        <div class="ame-pos-modal-bar">
                            <strong id="ameCustomerSuggestTitle"><?php echo $ame_pos_english ? 'Select customer or supplier' : 'ক্রেতা বা সাপ্লায়ার নির্বাচন'; ?></strong>
                            <button type="button" class="ame-pos-modal-x" data-ame-close="customer" aria-label="<?php echo $ame_pos_english ? 'Close' : 'বন্ধ'; ?>">×</button>
                        </div>
                        <ul id="posCustomerSearchList" class="pos-customer-suggest" hidden></ul>
                    </div>
                </div>

            </div>

            <div class="ame-pos-term-actions">
                <div class="ame-pos-recent-wrap">
                    <section class="ame-desk-box ame-pos-hist-box">
                        <button
                            type="button"
                            id="amePosTermHist"
                            class="ame-pos-term-hist"
                            title="<?php echo $escape($words['all_sales']); ?>"
                            aria-haspopup="dialog"
                            aria-controls="draftDrawer"
                        >
                            ⚖️ <?php echo $escape($words['all_sales']); ?>
                        </button>
                    </section>
                    <div class="ame-pos-term-action-row">
                        <button
                            type="button"
                            id="amePosDraftComplete"
                            class="ame-pos-draft-icon ame-pos-draft-complete"
                            title="<?php echo $escape($words['draft_complete']); ?>"
                            aria-label="<?php echo $escape($words['draft_complete']); ?>"
                        >✅</button>
                    </div>
                    <div class="ame-pos-complete-row">
                    <button
                        type="button"
                        id="amePosCashPaid"
                        class="ame-pos-cash-paid"
                        title="<?php echo $escape($ame_pos_english
                            ? 'Pay the full amount in cash, complete the memo, and open share'
                            : 'পুরো টাকা ক্যাশে নিয়ে মেমো সম্পন্ন করুন ও শেয়ার খুলুন'); ?>"
                    ><?php echo $escape($words['pay_cash']); ?></button>
                    <button
                        type="button"
                        id="amePosRecentToggle"
                        class="ame-pos-recent-select"
                        aria-expanded="false"
                        aria-haspopup="listbox"
                        aria-controls="amePosRecentFlyout"
                    >
                        <span id="amePosRecentCurrent"><?php echo $escape($words['recent_complete']); ?></span>
                        <span class="ame-pos-recent-caret" aria-hidden="true">▾</span>
                    </button>
                    </div>
                    <div id="amePosRecentFlyout" class="ame-pos-recent-flyout" hidden role="listbox">
                        <button type="button" id="amePosRecentDraft" class="ame-pos-recent-option" role="option">
                            <?php echo $escape($words['recent_draft']); ?>
                        </button>
                        <button type="button" id="amePosRecentComplete" class="ame-pos-recent-option is-active" role="option">
                            <?php echo $escape($words['recent_complete']); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="ame-pos-term-search">
                <h2 class="ame-pos-term-title ame-sale-brand">
                    <img src="/assets/img/header-logo.png?v=<?php echo htmlspecialchars((string)(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                    <strong><?php echo $escape(function_exists('shop_name') ? shop_name() : ''); ?></strong>
                </h2>
                <p class="ame-search-zikir">(পাস -আনফাস যিকির হচ্ছে কি?)</p>
                <div class="ame-pos-search">
                    <input
                        type="search"
                        id="p-search"
                        class="ame-pos-input"
                        placeholder="<?php echo $escape($words['search_label']); ?>"
                        autocomplete="off"
                    >
                    <div id="ameProductSearchModal" class="ame-pos-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ameProductSearchTitle">
                        <div class="ame-pos-modal-bar">
                            <strong id="ameProductSearchTitle"><?php echo $ame_pos_english ? 'Find product' : 'পণ্য খুঁজে বের করুন'; ?></strong>
                            <button type="button" class="ame-pos-modal-x" data-ame-close="product" aria-label="<?php echo $ame_pos_english ? 'Close' : 'বন্ধ'; ?>">×</button>
                        </div>
                        <div id="search-res"></div>
                    </div>
                </div>
                <div class="ame-pos-search-subline">
                    <button
                        type="button"
                        id="openQuickCustomer"
                        class="pos-customer-add ame-pos-quick-cust-btn"
                        title="<?php echo $escape($customer_words['add']); ?>"
                    >
                        ➕ <?php echo $escape($customer_words['add']); ?>
                    </button>
                    <p class="ame-pos-finder-link">
                        <a href="/modules/workshop/parts_finder.php"><?php echo $escape($words['finder_hint']); ?></a>
                    </p>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/pos_customer_panel.php'; ?>
    </section>

    <section class="ame-pos-card ame-pos-term-cart">
        <div class="ame-desk-table-head">
            <strong><?php echo $ame_pos_english ? 'Product list' : 'পণ্য তালিকা'; ?></strong>
            <div class="ame-desk-table-actions">
                <label class="ame-desk-vehicle" id="posVehicleBox">
                    <input
                        type="text"
                        id="posVehicleNo"
                        maxlength="40"
                        autocomplete="off"
                        placeholder="<?php echo $escape($words['vehicle_no']); ?>"
                        aria-label="<?php echo $escape($words['vehicle_no']); ?>"
                    >
                </label>
                <button type="button" class="ame-desk-tool ame-desk-tool-eng" onclick="ameToggleFold('amePosServiceBox','ameServiceArrow')">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l2.1-2.1a4 4 0 0 1-5.6 5.6L8.5 18.5a2.1 2.1 0 0 1-3-3l5.7-5.7a4 4 0 0 1 5.6-5.6l-2.1 2.1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <?php echo $ame_pos_english ? 'Engineer and repair' : 'ইঞ্জিনিয়ার ও মেরামত'; ?>
                </button>
                <button type="button" class="ame-desk-tool ame-desk-tool-add" onclick="ameToggleCustomItemBox()">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <?php echo $ame_pos_english ? '+ Add product' : '+ নতুন পণ্য যোগ করুন'; ?>
                </button>
                <a href="/modules/sales/layout_designer.php" class="ame-desk-tool ame-desk-tool-designer" style="background:#fef08a;color:#854d0e;border:1.5px solid #eab308;font-weight:800;text-decoration:none;" title="বিক্রয় পেজ লেআউট ও বক্স এডিটর (ড্র্যাগ & ড্রপ)">
                    <svg viewBox="0 0 24 24" aria-hidden="true" style="stroke:#854d0e;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    <?php echo $ame_pos_english ? '📐 Design Layout' : '📐 লেআউট ডিজাইন'; ?>
                </a>
            </div>
        </div>
        <div class="ame-pos-table-wrap" id="amePosCartTableWrap" role="region" aria-label="<?php echo $escape($ame_pos_english ? 'Product cart lines' : 'বিক্রয় কার্টের পণ্য তালিকা'); ?>">
            <table class="table-pos">
                <colgroup>
                    <col style="width:4%">
                    <col style="width:4.5%">
                    <col style="width:12%">
                    <col style="width:28%">
                    <col style="width:10%">
                    <col style="width:11%">
                    <col style="width:10%">
                    <col style="width:11%">
                    <col style="width:9.5%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="ame-pos-col-sl"><?php echo $escape($ame_pos_english ? 'SL' : 'ক্রম'); ?></th>
                        <th class="ame-pos-col-check"><input type="checkbox" id="posSelectAll" aria-label="<?php echo $escape($ame_pos_english ? 'Match all' : 'সব মিলান'); ?>"></th>
                        <th><?php echo $escape($ame_pos_english ? 'Code' : 'পণ্য কোড'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Product' : 'পণ্যের নাম'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Qty' : 'পরিমাণ'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Price (৳)' : 'দাম (৳)'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Disc (%)' : 'ছাড় (%)'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Line total (৳)' : 'লাইন মোট (৳)'); ?></th>
                        <th><?php echo $escape($ame_pos_english ? 'Action' : 'অ্যাকশন'); ?></th>
                    </tr>
                </thead>
                <tbody id="cart-body">
                    <tr class="ame-pos-empty-row">
                        <td
                            colspan="9"
                            class="ame-pos-empty-cell"
                        >
                            <?php echo $escape($words['empty_cart']); ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot id="cart-lines-foot">
                    <tr class="ame-pos-cart-total-row">
                        <td colspan="7" class="ame-pos-cart-total-label"><?php echo $escape($words['cart_lines_total']); ?></td>
                        <td id="cartLinesTotal" class="ame-pos-cart-total-val">0.00</td>
                        <td class="ame-pos-col-remove"></td>
                    </tr>
                </tfoot>
                <tbody id="ameExchangeBody"></tbody>
            </table>
        </div>
        <div class="ame-pos-cart-total-bar" id="ameCartLinesTotalBar" hidden aria-live="polite">
            <span class="ame-pos-cart-total-label"><?php echo $escape($words['cart_lines_total']); ?></span>
            <strong id="ameCartLinesTotalBarVal" class="ame-pos-cart-total-val">0.00</strong>
        </div>
        <p class="ame-pos-cart-scroll-hint" id="amePosCartScrollHint" hidden>
            <?php echo $ame_pos_english
                ? '↓ Scroll inside the table for more lines (no row limit)'
                : '↓ আরও পণ্য থাকলে টেবিলের ভিতর উপরে-নিচে স্ক্রল করুন (কোনো লাইন সীমা নেই)'; ?>
        </p>
        <button type="button" class="ame-pos-add-row" id="ameAddProductRow">
            <?php echo $ame_pos_english ? 'Add another product row' : 'আরেকটি পণ্যের সারি যোগ করুন'; ?>
        </button>
        <p class="ame-pos-row-note">
            <?php echo $ame_pos_english
                ? 'Empty rows are not saved, and they do not change stock, totals, or accounts.'
                : 'খালি সারি সেভ হয় না, স্টক, মোট বা হিসাবে যোগ হয় না।'; ?>
        </p>
        <a
            href="#amePosPay"
            class="ame-pos-pay-jump"
            id="amePosPayJump"
            hidden
            data-complete-label="<?php echo $escape($ame_pos_english
                ? '💳 Go down to payment and complete sale'
                : '💳 পেমেন্ট ও বিক্রয় সম্পন্ন — নিচে যান'); ?>"
            data-draft-label="<?php echo $escape($ame_pos_english
                ? '💳 Go down and save draft'
                : '💳 নিচে গিয়ে ড্রাফট সেভ করুন'); ?>"
        ><?php echo $ame_pos_english
            ? '💳 Go down to payment and complete sale'
            : '💳 পেমেন্ট ও বিক্রয় সম্পন্ন — নিচে যান'; ?></a>
    </section>

    <div class="ame-pos-fold-stack">
        <div class="ame-pos-custom-item">
            <div
                class="ame-pos-custom-item-toggle"
                role="button"
                tabindex="0"
                onclick="ameToggleCustomItemBox()"
                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();ameToggleCustomItemBox();}"
            >
                <span>➕ <?php echo $ame_pos_english ? 'Add a custom item (not in stock)' : 'স্টকে নেই এমন পণ্য হাতে যোগ করুন'; ?></span>
                <small id="ameCustomItemArrow">▾</small>
            </div>
            <div id="ameCustomItemBox" class="ame-pos-custom-item-box ame-pos-modal-panel" role="dialog" aria-modal="true" hidden>
                <div class="ame-pos-modal-bar">
                    <strong><?php echo $ame_pos_english ? 'Add product' : 'নতুন পণ্য যোগ করুন'; ?></strong>
                    <button type="button" class="ame-pos-modal-x" data-ame-close="custom" aria-label="<?php echo $ame_pos_english ? 'Close' : 'বন্ধ'; ?>">×</button>
                </div>
                <input type="text" id="ameCustomItemName" placeholder="<?php echo $ame_pos_english ? 'Item name' : 'পণ্যের নাম'; ?>" class="ame-pos-input">
                <input type="number" id="ameCustomItemBuyPrice" placeholder="<?php echo $escape($words['service_buy_price']); ?>" step="0.01" min="0" class="ame-pos-input">
                <div class="ame-pos-custom-item-row">
                    <input type="number" id="ameCustomItemPrice" placeholder="<?php echo $ame_pos_english ? 'Price' : 'দাম'; ?>" step="0.01" class="ame-pos-input">
                    <input type="number" id="ameCustomItemQty" placeholder="<?php echo $ame_pos_english ? 'Qty' : 'পরিমাণ'; ?>" step="0.01" value="1" class="ame-pos-input">
                </div>
                <button type="button" class="ame-pos-custom-item-btn" onclick="ameAddCustomItem()">
                    <?php echo $ame_pos_english ? '✅ Add to cart' : '✅ কার্টে যোগ করুন'; ?>
                </button>
            </div>
        </div>

        <div class="ame-pos-custom-item">
            <div
                class="ame-pos-custom-item-toggle"
                role="button"
                tabindex="0"
                onclick="ameToggleFold('amePosServiceBox','ameServiceArrow')"
                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();ameToggleFold('amePosServiceBox','ameServiceArrow');}"
            >
                <span>🔧 <?php echo $escape($words['service']); ?></span>
                <small id="ameServiceArrow">▾</small>
            </div>
            <div id="amePosServiceBox" class="ame-pos-custom-item-box ame-pos-modal-panel" role="dialog" aria-modal="true" hidden>
                <div class="ame-pos-modal-bar">
                    <strong><?php echo $ame_pos_english ? 'Engineer and repair' : 'ইঞ্জিনিয়ার ও মেরামত'; ?></strong>
                    <button type="button" class="ame-pos-modal-x" data-ame-close="service" aria-label="<?php echo $ame_pos_english ? 'Close' : 'বন্ধ'; ?>">×</button>
                </div>
                <section class="ame-pos-service" id="amePosService">
                    <div id="ameServiceRows" class="ame-pos-service-rows"></div>
                    <div class="ame-pos-service-foot">
                        <button type="button" id="ameAddServiceRow" class="ame-pos-service-add">
                            <?php echo $escape($words['add_engineer_bill']); ?>
                        </button>
                        <button type="button" id="ameAddAllServiceCart" class="ame-pos-service-addall">
                            <?php echo $escape($words['add_service_to_cart']); ?>
                        </button>
                        <strong class="ame-pos-service-sum">
                            <?php echo $escape($words['service_total']); ?>:
                            <span id="ameServiceTotalLabel">৳0.00</span>
                        </strong>
                    </div>
                    <input type="hidden" id="service_amount" value="">
                    <template id="ameServiceRowTemplate">
                        <div class="ame-pos-service-row ame-pos-service-grid">
                            <div class="ame-eng-search-wrap">
                                <input
                                    type="text"
                                    class="ame-pos-input ame-svc-eng-q"
                                    placeholder="<?php echo $escape($words['select_engineer']); ?>"
                                    autocomplete="off"
                                    aria-label="<?php echo $escape($words['select_engineer']); ?>"
                                >
                                <input type="hidden" class="ame-svc-eng" value="">
                                <div class="ame-eng-search-res" role="listbox"></div>
                            </div>
                            <div class="ame-svc-desc-wrap">
                            <input
                                type="text"
                                class="ame-pos-input ame-svc-type"
                                maxlength="255"
                                placeholder="<?php echo $escape($words['service_description']); ?>"
                            >
                            <div class="ame-svc-presets" hidden></div>
                            </div>
                            <input
                                type="number"
                                class="ame-pos-input ame-svc-amount"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="<?php echo $escape($words['service_amount']); ?>"
                            >
                            <button
                                type="button"
                                class="ame-pos-service-remove"
                                aria-label="<?php echo $escape($words['remove_bill']); ?>"
                            >✖</button>
                        </div>
                    </template>
                </section>
            </div>
        </div>
    </div>
</div>
    </div>
<div id="ameLiveMemo" class="ame-live-memo" aria-hidden="true"></div>
<div id="amePosOverlay" hidden></div>
<div id="ameServiceNotice" class="ame-svc-notice" hidden>
    <div class="ame-svc-notice-card" role="alertdialog" aria-labelledby="ameServiceNoticeTitle" aria-describedby="ameServiceNoticeText">
        <div class="ame-svc-notice-orb" id="ameServiceNoticeOrb">🟢</div>
        <div class="ame-svc-notice-body">
            <strong id="ameServiceNoticeTitle"></strong>
            <p id="ameServiceNoticeText"></p>
            <div class="ame-svc-notice-actions">
                <button type="button" class="ame-svc-notice-go" id="ameServiceNoticeGo"></button>
                <button type="button" class="ame-svc-notice-x" id="ameServiceNoticeClose"><?php echo $ame_pos_english ? 'Close' : 'ঠিক আছে'; ?></button>
            </div>
        </div>
    </div>
</div>

<script>
function ameSyncPosOverlay() {
    var overlay = document.getElementById('amePosOverlay');
    if (!overlay) {
        return;
    }
    var search = document.getElementById('search-res');
    var cust = document.getElementById('posCustomerSearchList');
    var custom = document.getElementById('ameCustomItemBox');
    var service = document.getElementById('amePosServiceBox');
    var open = (search && search.childNodes.length > 0)
        || (cust && !cust.hasAttribute('hidden'))
        || (custom && !custom.hasAttribute('hidden'))
        || (service && !service.hasAttribute('hidden'));
    overlay.toggleAttribute('hidden', !open);
}

function ameSetFold(boxId, arrowId, open) {
    var box = document.getElementById(boxId);
    var arrow = document.getElementById(arrowId);
    if (!box) {
        return;
    }
    if (open) {
        box.removeAttribute('hidden');
        if (arrow) {
            arrow.textContent = '▴';
        }
        var field = box.querySelector('input, select, textarea');
        if (field) {
            window.setTimeout(function () {
                field.focus();
            }, 30);
        }
    } else {
        box.setAttribute('hidden', 'hidden');
        if (arrow) {
            arrow.textContent = '▾';
        }
    }
    ameSyncPosOverlay();
}

function ameToggleFold(boxId, arrowId) {
    var box = document.getElementById(boxId);
    if (!box) {
        return;
    }
    var open = box.hasAttribute('hidden');
    if (open) {
        if (boxId !== 'ameCustomItemBox') {
            ameSetFold('ameCustomItemBox', 'ameCustomItemArrow', false);
        }
        if (boxId !== 'amePosServiceBox') {
            ameSetFold('amePosServiceBox', 'ameServiceArrow', false);
        }
    }
    ameSetFold(boxId, arrowId, open);
}

function ameClosePosLayer(kind) {
    if (kind === 'product' && typeof window.closeProductSearch === 'function') {
        window.closeProductSearch();
    } else if (kind === 'product') {
        var search = document.getElementById('search-res');
        if (search) {
            search.replaceChildren();
        }
    }
    if (kind === 'customer' || kind === 'all') {
        var cust = document.getElementById('posCustomerSearchList');
        if (cust) {
            cust.hidden = true;
            cust.innerHTML = '';
        }
    }
    if (kind === 'custom' || kind === 'all') {
        ameSetFold('ameCustomItemBox', 'ameCustomItemArrow', false);
    }
    if (kind === 'service' || kind === 'all') {
        ameSetFold('amePosServiceBox', 'ameServiceArrow', false);
    }
    if (kind === 'product' || kind === 'customer') {
        ameSyncPosOverlay();
    }
}

(function () {
    var overlay = document.getElementById('amePosOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            ameClosePosLayer('all');
            ameClosePosLayer('product');
            ameClosePosLayer('customer');
        });
    }
    document.addEventListener('click', function (event) {
        var closer = event.target.closest('[data-ame-close]');
        if (!closer) {
            return;
        }
        event.preventDefault();
        ameClosePosLayer(closer.getAttribute('data-ame-close') || 'all');
    });
    ['search-res', 'posCustomerSearchList', 'ameCustomItemBox', 'amePosServiceBox'].forEach(function (id) {
        var node = document.getElementById(id);
        if (!node || typeof MutationObserver !== 'function') {
            return;
        }
        new MutationObserver(ameSyncPosOverlay).observe(node, {
            childList: true,
            attributes: true,
            attributeFilter: ['hidden']
        });
    });
    document.addEventListener('keydown', function (event) {
        if (!document.body.classList.contains('ame-sales-pro') && !document.body.classList.contains('ame-pos-terminal-page')) {
            return;
        }
        var saleKeys = document.getElementById('ameSaleKeys');
        if (saleKeys && !saleKeys.hidden) {
            return;
        }
        if (event.key === 'Escape' && !event.defaultPrevented) {
            ameClosePosLayer('all');
            ameClosePosLayer('product');
            ameClosePosLayer('customer');
            return;
        }
        if (event.altKey && !event.ctrlKey && !event.metaKey) {
            var priceMode = '';
            if (event.code === 'KeyK') {
                priceMode = 'retail';
            } else if (event.code === 'KeyP') {
                priceMode = 'wholesale';
            } else if (event.code === 'KeyD') {
                priceMode = 'dealer';
            }
            if (priceMode) {
                event.preventDefault();
                var priceRadio = document.querySelector('input[name="price_mode"][value="' + priceMode + '"]');
                if (priceRadio) {
                    priceRadio.checked = true;
                    priceRadio.dispatchEvent(new Event('change', { bubbles: true }));
                    if (typeof window.renderCart === 'function') {
                        window.renderCart();
                    }
                }
                return;
            }
            if (event.code === 'Digit2') {
                event.preventDefault();
                var payArrow = document.getElementById('deskPayArrow');
                if (payArrow) {
                    payArrow.click();
                }
                return;
            }
        }
        var focusId = '';
        if (event.key === 'F2') {
            focusId = 'posCustomerInfoField';
        } else if (event.key === 'F3') {
            focusId = 'p-search';
        } else if (event.key === 'F7') {
            focusId = 'deskDiscountTaka';
        } else if (event.key === 'F8') {
            focusId = 'deskPayMethod';
        }
        if (focusId) {
            var field = document.getElementById(focusId);
            if (field) {
                event.preventDefault();
                field.focus();
                if (typeof field.select === 'function' && field.tagName === 'INPUT') {
                    field.select();
                }
            }
            return;
        }
        if (event.key === 'F4') {
            event.preventDefault();
            ameToggleFold('amePosServiceBox', 'ameServiceArrow');
            return;
        }
        if (event.key === 'F6') {
            event.preventDefault();
            ameToggleCustomItemBox();
            return;
        }
        if (event.key === 'F9' || (event.key === 'Enter' && (event.ctrlKey || event.metaKey))) {
            if (typeof window.submitFinalSale === 'function') {
                event.preventDefault();
                window.submitFinalSale();
            }
        }
    });
})();

function togglePriceModes(forceOpen) {
    var extra = document.getElementById('extraPriceModes');
    var arrow = document.getElementById('priceModeToggle');

    if (!extra || !arrow) {
        return;
    }

    var open = typeof forceOpen === 'boolean'
        ? forceOpen
        : extra.hasAttribute('hidden');

    extra.toggleAttribute('hidden', !open);
    extra.classList.toggle('show', open);
    extra.classList.toggle('is-open', open);
    arrow.textContent = open ? '◂' : '▸';
    arrow.setAttribute('aria-expanded', open ? 'true' : 'false');
}

(function () {
    var toggle = document.getElementById('priceModeToggle');
    var extra = document.getElementById('extraPriceModes');

    if (toggle) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            togglePriceModes();
        });
    }

    var selected = document.querySelector('input[name="price_mode"]:checked');
    if (selected && selected.value !== 'retail') {
        togglePriceModes(true);
    }

    if (extra) {
        extra.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    }
})();

function ameToggleCustomItemBox() {
    ameToggleFold('ameCustomItemBox', 'ameCustomItemArrow');
}

function ameAddCustomItem() {
    const nameInput = document.getElementById('ameCustomItemName');
    const priceInput = document.getElementById('ameCustomItemPrice');
    const qtyInput = document.getElementById('ameCustomItemQty');
    const buyInput = document.getElementById('ameCustomItemBuyPrice');

    if (typeof window.addCustomItemToCart !== 'function') {
        return;
    }

    window.addCustomItemToCart(nameInput.value, priceInput.value, qtyInput.value, buyInput ? buyInput.value : '');
    ameSetFold('ameCustomItemBox', 'ameCustomItemArrow', false);

    nameInput.value = '';
    priceInput.value = '';
    qtyInput.value = '1';
    if (buyInput) { buyInput.value = ''; }
}

(function () {
    var list = document.getElementById('ameServiceRows');
    var tpl = document.getElementById('ameServiceRowTemplate');
    var addBtn = document.getElementById('ameAddServiceRow');
    var totalInput = document.getElementById('service_amount');
    var totalLabel = document.getElementById('ameServiceTotalLabel');
    var isEnglish = (typeof window.AME_LANG === 'string' && window.AME_LANG !== '')
        ? window.AME_LANG === 'en'
        : false;
    var incompleteMsg = isEnglish
        ? 'Each engineer bill needs an engineer and an amount. A product is not required.'
        : 'প্রতিটি ইঞ্জিনিয়ার বিলে ইঞ্জিনিয়ার এবং টাকা দিন। পণ্য ছাড়াও এই বিল যাবে।';
    var dutyTitle = isEnglish ? 'Not Active today' : 'আজকের জন্য Active করা হয়নি';
    var dutyText = isEnglish
        ? 'This worker or service engineer is not Active for today. Tap the green icon in the header and activate them first.'
        : 'এই কর্মী বা সার্ভিস ইঞ্জিনিয়ার আজকের জন্য Active করা হয়নি। উপরে হেডারের 🟢 আইকন থেকে Active করুন।';
    var dutyGo = isEnglish ? 'Activate from header' : 'হেডার থেকে Active করুন';
    var incompleteTitle = isEnglish ? 'Bill is not complete' : 'বিল সম্পূর্ণ নয়';
    var engineerList = <?php echo json_encode($ame_engineer_json, JSON_UNESCAPED_UNICODE); ?> || [];
    var workDescriptions = <?php echo json_encode($ame_work_descriptions, JSON_UNESCAPED_UNICODE); ?> || [];
    var noMatchText = isEnglish ? 'No worker found.' : 'কর্মী পাওয়া যায়নি।';
    var idLabel = isEnglish ? 'ID' : 'আইডি';
    var presentLabel = isEnglish ? 'Active today' : 'আজ উপস্থিত';
    var absentLabel = isEnglish ? 'Activate from the header icon' : 'হেডারের 🟢 থেকে Active করুন';

    function markEngineerPresent(id, activatedAt) {
        engineerList.forEach(function (item) {
            if (Number(item.id) === Number(id) || Number(item.employee_id) === Number(id)) {
                item.present = true;
                if (activatedAt) item.activated_at = activatedAt;
            }
        });
    }

    function openHeaderDuty() {
        hideServiceNotice();
        if (typeof window.ameOpenDutyPanel === 'function') {
            window.ameOpenDutyPanel();
        }
    }

    var noticeBox = document.getElementById('ameServiceNotice');
    var noticeTitle = document.getElementById('ameServiceNoticeTitle');
    var noticeText = document.getElementById('ameServiceNoticeText');
    var noticeGo = document.getElementById('ameServiceNoticeGo');
    var noticeClose = document.getElementById('ameServiceNoticeClose');
    var noticeOrb = document.getElementById('ameServiceNoticeOrb');
    var noticeTimer = 0;

    function hideServiceNotice() {
        if (!noticeBox) return;
        noticeBox.hidden = true;
        noticeBox.classList.remove('is-on', 'is-warn');
        if (noticeTimer) {
            window.clearTimeout(noticeTimer);
            noticeTimer = 0;
        }
    }

    function showServiceNotice(kind, title, text, goLabel) {
        if (!noticeBox) {
            window.alert(text || title);
            return;
        }
        noticeBox.classList.toggle('is-warn', kind === 'warn');
        if (noticeOrb) noticeOrb.textContent = kind === 'warn' ? '⚠️' : '🟢';
        if (noticeTitle) noticeTitle.textContent = title || '';
        if (noticeText) noticeText.textContent = text || '';
        if (noticeGo) {
            noticeGo.textContent = goLabel || dutyGo;
            noticeGo.hidden = kind !== 'duty';
        }
        noticeBox.hidden = false;
        noticeBox.classList.add('is-on');
        if (noticeTimer) window.clearTimeout(noticeTimer);
        noticeTimer = window.setTimeout(hideServiceNotice, 8000);
    }

    function showDutyNotice(name) {
        var who = String(name || '').trim();
        var text = dutyText;
        if (who !== '') {
            text = (isEnglish
                ? (who + ' is not Active for today. Tap the green icon in the header and activate them.')
                : (who + ' আজকের জন্য Active করা হয়নি। উপরে হেডারের 🟢 আইকন থেকে Active করুন।'));
        }
        showServiceNotice('duty', dutyTitle, text, dutyGo);
    }

    if (noticeGo) {
        noticeGo.addEventListener('click', function () {
            openHeaderDuty();
        });
    }
    if (noticeClose) {
        noticeClose.addEventListener('click', hideServiceNotice);
    }

    window.ameShowServiceNotice = function (err) {
        var msg = (err && err.message) ? String(err.message) : String(err || '');
        if (msg.indexOf('AME_DUTY:') === 0) {
            showDutyNotice(msg.slice(9));
            if (typeof ameSetFold === 'function') {
                ameSetFold('amePosServiceBox', 'ameServiceArrow', true);
            }
            return;
        }
        showServiceNotice('warn', incompleteTitle, msg || incompleteMsg, '');
        if (typeof ameSetFold === 'function') {
            ameSetFold('amePosServiceBox', 'ameServiceArrow', true);
        }
    };

    document.addEventListener('ame-staff-woke', function (ev) {
        var d = ev.detail || {};
        markEngineerPresent(d.id || d.employee_id || 0, d.activated_at || '');
        hideServiceNotice();
    });

    if (!list || !tpl) {
        return;
    }

    function findEngineer(id) {
        var target = Number(id || 0);
        if (!target) {
            return null;
        }
        for (var i = 0; i < engineerList.length; i++) {
            if (Number(engineerList[i].id) === target) {
                return engineerList[i];
            }
        }
        return null;
    }

    function engineerLabel(item) {
        if (!item) {
            return '';
        }
        var code = String(item.emp_code || '').trim();
        if (code !== '') {
            return code;
        }
        return String(item.id || '');
    }

    function filterEngineers(query) {
        var q = String(query || '').trim().toLowerCase();
        if (q === '') {
            return engineerList.slice(0, 24);
        }
        return engineerList.filter(function (item) {
            var name = String(item.name || '').toLowerCase();
            var code = String(item.emp_code || '').toLowerCase();
            var id = String(item.id || '');
            var employeeId = String(item.employee_id || '');
            return name.indexOf(q) !== -1
                || code.indexOf(q) !== -1
                || id === q
                || employeeId === q;
        }).slice(0, 24);
    }

    function closeEngResults(wrap) {
        if (!wrap) {
            return;
        }
        var box = wrap.querySelector('.ame-eng-search-res');
        if (box) {
            box.classList.remove('is-open');
            box.innerHTML = '';
        }
    }

    function renderEngResults(wrap, matches) {
        var box = wrap.querySelector('.ame-eng-search-res');
        if (!box) {
            return;
        }
        box.innerHTML = '';
        if (!matches.length) {
            var empty = document.createElement('div');
            empty.className = 'ame-eng-search-empty';
            empty.textContent = noMatchText;
            box.appendChild(empty);
            box.classList.add('is-open');
            return;
        }
        matches.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'ame-eng-search-item';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ame-eng-search-pick';
            btn.appendChild(document.createTextNode(item.name || ''));
            var meta = [];
            if (String(item.emp_code || '').trim() !== '') {
                meta.push(idLabel + ': ' + item.emp_code);
            }
            meta.push('#' + item.id);
            meta.push(item.present ? presentLabel : absentLabel);
            var small = document.createElement('small');
            small.textContent = meta.join(' • ');
            btn.appendChild(small);
            btn.addEventListener('mousedown', function (event) {
                event.preventDefault();
                selectEngineer(wrap, item);
            });
            var wake = document.createElement('button');
            wake.type = 'button';
            wake.className = 'ame-duty-wake';
            wake.textContent = item.present ? '🟢' : '⚪';
            wake.title = item.present ? presentLabel : absentLabel;
            wake.addEventListener('mousedown', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!item.present) {
                    openHeaderDuty();
                }
            });
            row.appendChild(btn);
            row.appendChild(wake);
            box.appendChild(row);
        });
        box.classList.add('is-open');
    }

    function selectEngineer(wrap, item) {
        var hidden = wrap.querySelector('.ame-svc-eng');
        var input = wrap.querySelector('.ame-svc-eng-q');
        if (hidden) {
            hidden.value = String(item.id || '');
        }
        if (input) {
            input.value = engineerLabel(item);
        }
        closeEngResults(wrap);
        var row = wrap.closest('.ame-pos-service-row');
        if (row) {
            showWorkPresets(row);
            keepCompleteBillOnCart(row);
        }
        hideServiceNotice();
    }

    function showWorkPresets(row) {
        var box = row.querySelector('.ame-svc-presets');
        var hidden = row.querySelector('.ame-svc-eng');
        var type = row.querySelector('.ame-svc-type');
        if (!box) {
            return;
        }
        var engineerId = Number((hidden && hidden.value) || 0);
        var typed = type ? String(type.value || '').trim().toLowerCase() : '';
        box.innerHTML = '';
        var shown = 0;
        workDescriptions.forEach(function (item) {
            if (Number(item.engineer_id) !== engineerId) {
                return;
            }
            var text = String(item.description || '').trim();
            if (text === '') {
                return;
            }
            if (typed !== '' && text.toLowerCase() === typed) {
                return;
            }
            if (typed !== '' && text.toLowerCase().indexOf(typed) === -1) {
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ame-svc-preset';
            btn.textContent = text;
            btn.addEventListener('mousedown', function (event) {
                event.preventDefault();
                if (type) {
                    type.value = text;
                    type.dispatchEvent(new Event('input', { bubbles: true }));
                }
                box.hidden = true;
            });
            box.appendChild(btn);
            shown += 1;
        });
        box.hidden = shown === 0;
    }

    function bindEngineerSearch(row) {
        var wrap = row.querySelector('.ame-eng-search-wrap');
        if (!wrap) {
            return;
        }
        var input = wrap.querySelector('.ame-svc-eng-q');
        var hidden = wrap.querySelector('.ame-svc-eng');
        if (!input || !hidden) {
            return;
        }
        input.addEventListener('input', function () {
            hidden.value = '';
            renderEngResults(wrap, filterEngineers(input.value));
        });
        input.addEventListener('focus', function () {
            renderEngResults(wrap, filterEngineers(input.value));
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                closeEngResults(wrap);
            }, 150);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeEngResults(wrap);
            }
        });
    }

    function clearEngineerRow(row) {
        var input = row.querySelector('.ame-svc-eng-q');
        var hidden = row.querySelector('.ame-svc-eng');
        if (input) {
            input.value = '';
        }
        if (hidden) {
            hidden.value = '';
        }
        closeEngResults(row.querySelector('.ame-eng-search-wrap'));
    }

    function number(value) {
        var parsed = parseFloat(value);
        return isFinite(parsed) ? parsed : 0;
    }

    function syncTotal() {
        var sum = 0;
        list.querySelectorAll('.ame-svc-amount').forEach(function (input) {
            sum += number(input.value);
        });
        sum = Math.round(sum * 100) / 100;
        if (totalInput) {
            totalInput.value = sum > 0 ? sum.toFixed(2) : '';
        }
        if (totalLabel) {
            totalLabel.textContent = '৳' + sum.toFixed(2);
        }
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }
    }

    var addCartLabel = <?php echo json_encode($words['add_service_to_cart']); ?>;
    var addCartDoneLabel = <?php echo json_encode($words['service_added_to_cart']); ?>;
    var addAllBtn = document.getElementById('ameAddAllServiceCart');

    function refreshCartView() {
        if (typeof window.renderCart === 'function') {
            window.renderCart();
        } else if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }
    }

    function markRowChanged(row) {
        if (row.dataset.addedToCart === '1') {
            refreshCartView();
        }
        syncAddAllButton();
    }

    function resolvedEngineerId(row) {
        var hidden = row.querySelector('.ame-svc-eng');
        var typed = row.querySelector('.ame-svc-eng-q');
        var amount = row.querySelector('.ame-svc-amount');
        var id = Number((hidden && hidden.value) || 0);
        if (id > 0 && findEngineer(id)) {
            return id;
        }
        var q = String((typed && typed.value) || '').trim().toLowerCase();
        if (q !== '') {
            var exact = [];
            engineerList.forEach(function (item) {
                var name = String(item.name || '').trim().toLowerCase();
                var code = String(item.emp_code || '').trim().toLowerCase();
                if (name === q || code === q || String(item.id) === q || String(item.employee_id || '') === q) {
                    exact.push(item);
                }
            });
            if (exact.length === 1) {
                if (hidden) {
                    hidden.value = String(exact[0].id);
                }
                return Number(exact[0].id);
            }
        }
        if (id <= 0 && number(amount && amount.value) > 0 && engineerList.length === 1) {
            if (hidden) {
                hidden.value = String(engineerList[0].id);
            }
            return Number(engineerList[0].id);
        }
        return id > 0 ? id : 0;
    }

    function rowState(row) {
        var type = row.querySelector('.ame-svc-type');
        var amount = row.querySelector('.ame-svc-amount');
        var engId = resolvedEngineerId(row);
        var desc = (type && type.value || '').trim();
        var amt = number(amount && amount.value);
        if (desc === '' && engId > 0 && amt > 0) {
            desc = 'কাজের বিল';
        }
        var item = findEngineer(engId);
        var empty = !engId && desc === '' && amt <= 0;
        return {
            empty: empty,
            complete: engId > 0 && amt > 0,
            present: !!(item && item.present),
            item: item,
            engId: engId,
            desc: desc,
            amt: amt
        };
    }

    function keepCompleteBillOnCart(row) {
        var state = rowState(row);
        if (!state.complete) {
            return;
        }
        markRowInCart(row, state);
        syncAddAllButton();
        refreshCartView();
    }

    function markRowInCart(row, state) {
        row.dataset.addedToCart = '1';
        var picked = state && state.item ? state.item : findEngineer(state && state.engId);
        var code = picked ? engineerLabel(picked) : '';
        row.dataset.cartEngineerCode = code;
        row.dataset.cartEngineerName = code;
    }

    function syncAddAllButton() {
        if (!addAllBtn) return;
        var added = list.querySelectorAll('.ame-pos-service-row[data-added-to-cart="1"]').length;
        var ready = 0;
        list.querySelectorAll('.ame-pos-service-row').forEach(function (row) {
            if (rowState(row).complete) ready += 1;
        });
        if (added > 0 && added >= ready) {
            addAllBtn.textContent = addCartDoneLabel;
            addAllBtn.classList.add('is-added');
        } else {
            addAllBtn.textContent = addCartLabel;
            addAllBtn.classList.remove('is-added');
        }
    }

    function addAllServiceRowsToCart() {
        var rows = list.querySelectorAll('.ame-pos-service-row');
        var ready = [];
        var incomplete = false;
        rows.forEach(function (row) {
            var state = rowState(row);
            if (state.empty) {
                return;
            }
            if (!state.complete) {
                incomplete = true;
                return;
            }
            ready.push({ row: row, state: state });
        });
        if (incomplete || ready.length === 0) {
            showServiceNotice('warn', incompleteTitle, incompleteMsg, '');
            return 0;
        }
        ready.forEach(function (entry) {
            markRowInCart(entry.row, entry.state);
        });
        hideServiceNotice();
        syncAddAllButton();
        refreshCartView();
        if (typeof ameSetFold === 'function') {
            ameSetFold('amePosServiceBox', 'ameServiceArrow', false);
        }
        return ready.length;
    }

    function bindRow(row) {
        var amount = row.querySelector('.ame-svc-amount');
        var type = row.querySelector('.ame-svc-type');
        var remove = row.querySelector('.ame-pos-service-remove');
        bindEngineerSearch(row);
        if (amount) {
            amount.addEventListener('input', function () {
                syncTotal();
                keepCompleteBillOnCart(row);
                markRowChanged(row);
            });
        }
        if (type) {
            type.addEventListener('input', function () {
                markRowChanged(row);
                showWorkPresets(row);
            });
            type.addEventListener('focus', function () {
                showWorkPresets(row);
            });
        }
        if (remove) {
            remove.addEventListener('click', function () {
                var rows = list.querySelectorAll('.ame-pos-service-row');
                var wasAdded = row.dataset.addedToCart === '1';
                if (rows.length <= 1) {
                    clearEngineerRow(row);
                    row.querySelector('.ame-svc-type').value = '';
                    row.querySelector('.ame-svc-amount').value = '';
                    row.dataset.addedToCart = '';
                } else {
                    row.remove();
                }
                syncTotal();
                syncAddAllButton();
                if (wasAdded) {
                    refreshCartView();
                }
            });
        }
    }

    function addRow(data) {
        var node = tpl.content.firstElementChild.cloneNode(true);
        if (data) {
            var engItem = findEngineer(data.engineer_id);
            var hidden = node.querySelector('.ame-svc-eng');
            var query = node.querySelector('.ame-svc-eng-q');
            if (!engItem && data.engineer_name) {
                var byName = String(data.engineer_name || '').trim();
                engineerList.forEach(function (row) {
                    if (engItem) {
                        return;
                    }
                    if (row && String(row.name || '').trim() === byName) {
                        engItem = row;
                    }
                });
                if (engItem && !data.engineer_id) {
                    data.engineer_id = Number(engItem.id);
                }
            }
            if (hidden) {
                hidden.value = String(data.engineer_id || '');
            }
            if (query && engItem) {
                query.value = engineerLabel(engItem);
            } else if (query && data.engineer_name) {
                query.value = String(data.engineer_name);
            }
            node.querySelector('.ame-svc-type').value = data.service_type || '';
            node.querySelector('.ame-svc-amount').value = data.amount
                ? Number(data.amount).toFixed(2)
                : '';
            var restored = number(data.amount) > 0
                && (
                    Number(data.engineer_id || 0) > 0
                    || String(data.service_type || '').trim() !== ''
                );
            if (restored) {
                node.dataset.addedToCart = '1';
                var code = engItem ? engineerLabel(engItem) : String(data.engineer_code || data.engineer_id || '');
                node.dataset.cartEngineerCode = code;
                node.dataset.cartEngineerName = code;
                if (query && query.value === '' && code !== '') {
                    query.value = code;
                }
            }
        }
        bindRow(node);
        list.appendChild(node);
        syncTotal();
        syncAddAllButton();
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.ame-eng-search-wrap')) {
            list.querySelectorAll('.ame-eng-search-wrap').forEach(closeEngResults);
        }
    });

    function cartEmpWorkBillServices() {
        if (typeof cart === 'undefined' || !Array.isArray(cart)) {
            return [];
        }
        var out = [];
        cart.forEach(function (item) {
            if (!item) {
                return;
            }
            var sku = String(item.sku || '').trim().toUpperCase();
            if (!/^EMP-\d+$/.test(sku)) {
                return;
            }
            var eng = null;
            engineerList.forEach(function (row) {
                if (String(row.emp_code || '').trim().toUpperCase() === sku) {
                    eng = row;
                }
            });
            if (!eng || Number(eng.id) <= 0) {
                return;
            }
            var price = number(item.current_price);
            var qty = number(item.qty) || 1;
            var pct = Math.min(100, Math.max(0, number(item.discount_pct)));
            var gross = price * qty;
            var amt = Math.round((gross - (gross * pct / 100)) * 100) / 100;
            if (amt <= 0) {
                return;
            }
            var desc = String(item.name || '').trim();
            if (desc === '') {
                desc = 'কাজের বিল';
            }
            out.push({
                engineer_id: Number(eng.id),
                service_type: desc,
                amount: amt
            });
        });
        return out;
    }

    function mergeServiceLines(primary, extra) {
        var merged = primary.slice();
        var seen = {};
        merged.forEach(function (line) {
            var key = String(line.engineer_id || 0) + ':' + number(line.amount).toFixed(2);
            seen[key] = true;
        });
        extra.forEach(function (line) {
            var key = String(line.engineer_id || 0) + ':' + number(line.amount).toFixed(2);
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            merged.push(line);
        });
        return merged;
    }

    window.ameCollectServices = function (strict) {
        var services = [];
        var incomplete = false;
        list.querySelectorAll('.ame-pos-service-row').forEach(function (row) {
            var state = rowState(row);
            if (state.empty) {
                return;
            }
            if (!state.complete) {
                incomplete = true;
                return;
            }
            services.push({
                engineer_id: state.engId,
                service_type: state.desc,
                amount: state.amt
            });
        });
        if (strict && incomplete) {
            throw new Error(incompleteMsg);
        }
        return mergeServiceLines(services, cartEmpWorkBillServices());
    };

    window.ameSetServiceRows = function (services) {
        list.replaceChildren();
        if (!Array.isArray(services) || services.length === 0) {
            addRow(null);
            return;
        }
        services.forEach(function (service) {
            addRow(service);
        });
    };

    function engineerCodeOf(row) {
        var hidden = row.querySelector('.ame-svc-eng');
        var item = findEngineer(hidden && hidden.value);
        if (item) {
            return engineerLabel(item);
        }
        var stored = String(row.dataset.cartEngineerCode || '').trim();
        if (stored !== '') {
            return stored;
        }
        var raw = String(row.dataset.cartEngineerName || '');
        var match = raw.match(/\(([^)]+)\)\s*$/);
        return match ? match[1] : '';
    }

    window.ameRenderServiceCartRows = function (tbody, startIndex) {
        if (!tbody) {
            return 0;
        }
        var rows = list.querySelectorAll('.ame-pos-service-row[data-added-to-cart="1"]');
        var serial = Number(startIndex) || 0;
        rows.forEach(function (row) {
            var desc = (row.querySelector('.ame-svc-type').value || '').trim();
            if (desc === '') {
                desc = 'কাজের বিল';
            }
            var amt = number(row.querySelector('.ame-svc-amount').value);
            serial += 1;

            var tr = document.createElement('tr');
            tr.className = 'ame-cart-service-row';

            var slTd = document.createElement('td');
            slTd.className = 'ame-pos-col-sl';
            slTd.textContent = String(serial);
            tr.appendChild(slTd);

            var checkTd = document.createElement('td');
            checkTd.className = 'ame-pos-col-check';
            tr.appendChild(checkTd);

            var codeTd = document.createElement('td');
            codeTd.textContent = engineerCodeOf(row);
            tr.appendChild(codeTd);

            var nameTd = document.createElement('td');
            nameTd.textContent = desc;
            tr.appendChild(nameTd);

            var qtyTd = document.createElement('td');
            qtyTd.textContent = '1';
            tr.appendChild(qtyTd);

            var priceTd = document.createElement('td');
            var priceInput = document.createElement('input');
            priceInput.type = 'number';
            priceInput.className = 'ame-pos-line-input';
            priceInput.min = '0';
            priceInput.step = '0.01';
            priceInput.value = amt > 0 ? amt.toFixed(2) : '';
            priceInput.addEventListener('input', function () {
                var next = number(priceInput.value);
                if (next < 0) {
                    next = 0;
                }
                var source = row.querySelector('.ame-svc-amount');
                if (source) {
                    source.value = priceInput.value;
                }
                totalTd.textContent = next.toFixed(2);
                syncTotal();
                if (typeof window.amePaintCartLinesTotal === 'function') {
                    window.amePaintCartLinesTotal();
                }
            });
            priceTd.appendChild(priceInput);
            tr.appendChild(priceTd);

            var discTd = document.createElement('td');
            discTd.textContent = '0';
            tr.appendChild(discTd);

            var totalTd = document.createElement('td');
            totalTd.textContent = amt.toFixed(2);
            tr.appendChild(totalTd);

            var removeTd = document.createElement('td');
            removeTd.className = 'ame-pos-col-remove';
            removeTd.textContent = '✖';
            removeTd.title = 'বাতিল';
            removeTd.addEventListener('click', function () {
                row.dataset.addedToCart = '';
                syncAddAllButton();
                refreshCartView();
            });
            tr.appendChild(removeTd);

            tbody.appendChild(tr);
        });
        return rows.length;
    };

    window.ameHasServiceCartRows = function () {
        return list.querySelectorAll('.ame-pos-service-row[data-added-to-cart="1"]').length > 0;
    };

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            addRow(null);
        });
    }
    if (addAllBtn) {
        addAllBtn.addEventListener('click', function () {
            addAllServiceRowsToCart();
        });
    }

    if (list.children.length === 0) {
        addRow(null);
    }
    syncAddAllButton();
})();
</script>
<?php if (!empty($ame_pos_title_can_edit)): ?>
<div class="ame-pos-title-edit" id="amePosTitleEdit" hidden>
    <div class="ame-pos-title-edit-card">
        <h3>POS শিরোনাম লেখা</h3>
        <p>শুধু এই লেখা এডিট হয়। অন্য কোনো সেটিংস বদলাবে না।</p>
        <textarea id="amePosTitleInput" maxlength="300"><?php echo $escape($ame_pos_title_line); ?></textarea>
        <div class="ame-pos-title-edit-msg" id="amePosTitleMsg"></div>
        <button type="button" class="ame-pos-title-save" onclick="ameSavePosTitleLine()">সেভ করুন</button>
        <button type="button" class="ame-pos-title-cancel" onclick="ameClosePosTitleEdit()">বন্ধ</button>
    </div>
</div>
<script>
function ameOpenPosTitleEdit() {
    var box = document.getElementById('amePosTitleEdit');
    if (box) { box.hidden = false; }
    var input = document.getElementById('amePosTitleInput');
    if (input) { input.focus(); }
}
function ameClosePosTitleEdit() {
    var box = document.getElementById('amePosTitleEdit');
    if (box) { box.hidden = true; }
}
function ameSavePosTitleLine() {
    var input = document.getElementById('amePosTitleInput');
    var msg = document.getElementById('amePosTitleMsg');
    var line = document.getElementById('amePosTitleLine');
    if (!input) { return; }
    msg.textContent = 'সেভ হচ্ছে...';
    var body = new FormData();
    body.append('csrf_token', <?php echo json_encode(csrf_token()); ?>);
    body.append('line_text', input.value);
    body.append('ajax', '1');
    fetch('/modules/settings/process_pos_title.php', {
        method: 'POST',
        body: body,
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': <?php echo json_encode(csrf_token()); ?> }
    }).then(function (res) { return res.json(); }).then(function (data) {
        if (data && data.success) {
            if (line) {
                var live = line.querySelector('.ame-pos-title-live');
                var text = data.line_text || input.value.trim();
                if (live) {
                    live.textContent = text;
                } else {
                    line.textContent = text;
                }
            }
            msg.textContent = 'সেভ হয়েছে।';
            setTimeout(ameClosePosTitleEdit, 500);
        } else {
            msg.textContent = (data && data.message) ? data.message : 'সেভ হয়নি।';
        }
    }).catch(function () {
        msg.textContent = 'সার্ভার এরর।';
    });
}
document.getElementById('amePosTitleEdit').addEventListener('click', function (e) {
    if (e.target === this) { ameClosePosTitleEdit(); }
});
</script>
<?php endif; ?>