<?php
/*
 * Professional POS
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';

$is_english = current_lang() === 'en';

$edit_sale_id = (int)($_GET['edit_sale'] ?? 0);
if ($edit_sale_id > 0) {
    // এই POS নতুন sale তৈরি করে; পুরোনো invoice লোড করে edit করে না।
    // তাই edit_sale চালালে stock/commission ডাবল হতে পারে—নিরাপদে invoice-এ ফেরত দিই।
    $_SESSION['user_flash'] = 'ইনভয়েস এডিট সাময়িকভাবে বন্ধ: ডাবল স্টক ও কর্মী কমিশন রোধ করা হয়েছে। প্রয়োজন হলে রিটার্ন/সমন্বয় ব্যবহার করুন।';
    $_SESSION['user_flash_ok'] = 0;
    header('Location: invoice.php?id=' . $edit_sale_id);
    exit;
}
$ame_pos_asset = defined('AME_ASSET_VERSION') ? (string) AME_ASSET_VERSION : '1.0';
if (version_compare($ame_pos_asset, '5.71', '<')) {
    $ame_pos_asset = '5.71';
}

$words = $is_english
    ? [
        'title' => 'Parts counter',
        'subtitle' => 'Spare parts sales — search, cart, customers and payments.',
        'live' => 'LIVE SALE',
        'sale_information' => 'Sales Information',
        'retail_customer' => 'Retail Customer',
        'registered_customer' => 'Registered Customer',
        'retail_price' => 'K',
        'wholesale_price' => 'P',
        'dealer_price' => 'D',
        'select_customer' => 'Select a registered customer',
        'search_product' => 'Part name or code...',
        'cart' => 'Sales Cart',
        'code' => 'Code',
        'product' => 'Product name',
        'price' => 'Price',
        'quantity' => 'Quantity',
        'total' => 'Total',
        'empty_cart' => 'The cart is empty. Search for a product.',
        'service' => 'Engineer Repair and Service',
        'select_engineer' => 'Search by name or ID',
        'service_description' => 'Service description',
        'service_amount' => 'Service charge',
        'calculator' => 'Payment Calculator',
        'subtotal' => 'Subtotal',
        'vat' => 'VAT (%)',
        'discount' => 'Discount (-)',
        'rounding' => 'Rounding (+/-)',
        'net_payable' => 'Net Payable',
        'adjust_due' => 'Adjust extra payment against previous due',
        'previous_due' => 'Previous Due',
        'current_due' => 'Current Balance',
        'send_sms' => 'Send SMS to customer',
        'complete_sale' => 'Complete Sale',
        'dashboard' => 'Back to Dashboard'
    ]
    : [
        'title' => 'পার্টস কাউন্টার',
        'subtitle' => 'স্পেয়ার পার্টস বিক্রি — সার্চ, কার্ট, ক্রেতা ও পরিশোধ।',
        'live' => 'চলমান বিক্রয়',
        'sale_information' => 'বিক্রয়ের তথ্য',
        'retail_customer' => 'রিটেইল ক্রেতা',
        'registered_customer' => 'নিবন্ধিত ক্রেতা',
        'retail_price' => 'K',
        'wholesale_price' => 'P',
        'dealer_price' => 'D',
        'select_customer' => 'নিবন্ধিত ক্রেতা নির্বাচন করুন',
        'search_product' => 'যন্ত্রাংশের নাম বা কোড...',
        'cart' => 'বিক্রয় কার্ট',
        'code' => 'কোড',
        'product' => 'পণ্যের নাম',
        'price' => 'মূল্য',
        'quantity' => 'পরিমাণ',
        'total' => 'মোট',
        'empty_cart' => 'কার্ট খালি। যন্ত্রাংশ খুঁজুন।',
        'service' => 'ইঞ্জিনিয়ার মেরামত ও সার্ভিস',
        'select_engineer' => 'কর্মীর নাম বা আইডি লিখে সার্চ',
        'service_description' => 'সার্ভিস বা কাজের বিবরণ',
        'service_amount' => 'সার্ভিস চার্জ',
        'calculator' => 'পেমেন্ট ক্যালকুলেটর',
        'subtotal' => 'সাব-টোটাল',
        'vat' => 'ভ্যাট (%)',
        'discount' => 'ডিসকাউন্ট (-)',
        'rounding' => 'রাউন্ডিং (+/-)',
        'net_payable' => 'সর্বমোট পাওনা',
        'adjust_due' => 'অতিরিক্ত টাকা পূর্বের বকেয়ার সঙ্গে সমন্বয় করুন',
        'previous_due' => 'পূর্বের বকেয়া',
        'current_due' => 'বর্তমান বকেয়া',
        'send_sms' => 'ক্রেতাকে এসএমএস পাঠান',
        'complete_sale' => 'বিক্রয় সম্পন্ন করুন',
        'dashboard' => 'ড্যাশবোর্ডে ফিরে যান'
    ];

$escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

if (!function_exists('ame_active_accounts')) {
    require_once __DIR__ . '/../../includes/account_functions.php';
}
$ame_pos_accounts = ame_active_accounts($db);

if (is_file(__DIR__ . '/../../includes/sales/sale_common.php')) {
    require_once __DIR__ . '/../../includes/sales/sale_common.php';
}
if (function_exists('ame_sync_active_suppliers_to_customers')) {
    ame_sync_active_suppliers_to_customers($db);
}

$ame_pos_customer_rows = [];

$engineers = false;
$engineer_rows = [];
$ame_pos_roster_ready = false;
if (is_file(__DIR__ . '/../../includes/staff_duty.php')) {
    require_once __DIR__ . '/../../includes/attendance_functions.php';
    require_once __DIR__ . '/../../includes/staff_duty.php';
    if (function_exists('ame_staff_duty_roster')) {
        $engineer_rows = ame_staff_duty_roster($db);
        $ame_pos_roster_ready = true;
    }
}
if (!$ame_pos_roster_ready && !$engineer_rows) {
    $engineers_table = @$db->query("SHOW TABLES LIKE 'engineers'");
    if ($engineers_table && $engineers_table->num_rows > 0) {
        $emp_code_col = '';
        $emp_cols = @$db->query("SHOW COLUMNS FROM employees LIKE 'employee_code'");
        if ($emp_cols && $emp_cols->num_rows > 0) {
            $emp_code_col = 'emp.employee_code';
        } else {
            $emp_id_col = @$db->query("SHOW COLUMNS FROM employees LIKE 'emp_id'");
            if ($emp_id_col && $emp_id_col->num_rows > 0) {
                $emp_code_col = 'emp.emp_id';
            }
        }
        $code_select = $emp_code_col !== ''
            ? "COALESCE({$emp_code_col}, '') AS emp_code"
            : "'' AS emp_code";
        $engineers = $db->query(
            "SELECT e.id, e.name, e.employee_id, {$code_select}
             FROM engineers e
             LEFT JOIN employees emp ON emp.id = e.employee_id
             WHERE (e.purged_at IS NULL OR e.purged_at = '')
               AND (e.status = 'active' OR e.status IS NULL OR e.status = '')
               AND (emp.id IS NULL OR (emp.purged_at IS NULL AND emp.removed_at IS NULL))
             ORDER BY e.name ASC"
        );
    }
}

$ame_pos_skin = function_exists('ame_pos_skin_current') ? ame_pos_skin_current() : 'workshop';
$ame_body_class = 'ame-pos-terminal-page ame-pos-skin-' . $ame_pos_skin . ' ame-sales-pro ame-sale-pro';
$GLOBALS['ame_body_class'] = $ame_body_class;
$ame_pos_skin_label = $ame_pos_skin;
if (function_exists('ame_pos_skins')) {
    $ame_pos_skin_meta = ame_pos_skins()[$ame_pos_skin] ?? [];
    $ame_pos_skin_label = $is_english
        ? (string)($ame_pos_skin_meta['name_en'] ?? $ame_pos_skin)
        : (string)($ame_pos_skin_meta['name'] ?? $ame_pos_skin);
}

include '../../partials/header.php';
include __DIR__ . '/pos_style.php';
include __DIR__ . '/pos_color_override.php';
?>
<script>
/* Desktop site on a phone shows the desktop sales page, scaled into the screen. */
window.amePhoneDesktopSite = function () {
  var ua = navigator.userAgent || '';
  var touch = navigator.maxTouchPoints > 0;
  try {
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) touch = true;
  } catch (e) {}
  if (!touch) return false;
  var shortSide = Math.min(screen.width || 0, screen.height || 0);
  if (!(shortSide > 0 && shortSide <= 540)) return false;
  var mobileUa = /Mobile|iPhone|iPod|IEMobile|Opera Mini/i.test(ua);
  var uadMobile = null;
  try {
    if (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean') {
      uadMobile = navigator.userAgentData.mobile;
    }
  } catch (e2) {}
  if (uadMobile === false) return true;
  return !mobileUa;
};
window.ameApplySaleDesktopViewport = function () {
  if (!window.amePhoneDesktopSite()) return;
  window.AME_SALE_DESKTOP_SITE = true;
  document.documentElement.classList.add('ame-phone-desktop');
  var design = 1366;
  if (!window.AME_SALE_DESKTOP_FIT) {
    var fitNow = window.innerWidth || screen.width || design;
    var shortSide = Math.min(screen.width || fitNow, screen.height || fitNow);
    if (shortSide > 0 && shortSide <= 540) fitNow = Math.min(fitNow, shortSide);
    window.AME_SALE_DESKTOP_FIT = fitNow;
  }
  var scale = Math.max(0.2, Math.min(1, window.AME_SALE_DESKTOP_FIT / design));
  var meta = document.querySelector('meta[name="viewport"]');
  if (!meta) return;
  meta.setAttribute(
    'content',
    'width=' + design + ', initial-scale=' + scale.toFixed(4) + ', minimum-scale=0.2, maximum-scale=5, user-scalable=yes, viewport-fit=cover'
  );
};
window.ameApplySaleDesktopViewport();
</script>
<script>
/* ame-device-early — mobile mode stays mobile; desktop mode on a phone does not */
(function () {
  function isPhone() {
    if (window.AME_SALE_DESKTOP_SITE) return false;
    var ua = navigator.userAgent || '';
    try { if (navigator.userAgentData && navigator.userAgentData.mobile) return true; } catch (e) {}
    if (/Android.+Mobile|iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua)) return true;
    if (/Android/i.test(ua) && !/Tablet|iPad/i.test(ua) && Math.min(screen.width||0, screen.height||0) <= 550) return true;
    var coarse = false;
    try { coarse = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches; } catch (e2) {}
    var shortSide = Math.min(screen.width || 0, screen.height || 0);
    if (coarse && shortSide && shortSide <= 550) return true;
    return false;
  }
  function isTablet() {
    var ua = navigator.userAgent || '';
    if (/iPad|Android(?!.*Mobile)|Tablet|Silk/i.test(ua)) return true;
    var shortSide = Math.min(screen.width || 0, screen.height || 0);
    var coarse = false;
    try { coarse = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches; } catch (e) {}
    return coarse && shortSide > 550 && shortSide <= 900;
  }
  function mode() {
    if (isPhone()) return 'phone';
    if (isTablet()) return 'tablet';
    var w = window.innerWidth || document.documentElement.clientWidth || 0;
    if (w < 768) return 'phone';
    if (w < 1100) return 'tablet';
    return 'pc';
  }
  function apply() {
    if (window.AME_SALE_DESKTOP_SITE && typeof window.ameApplySaleDesktopViewport === 'function') {
      window.ameApplySaleDesktopViewport();
    }
    var m = window.AME_SALE_DESKTOP_SITE ? 'pc' : mode();
    var phone = window.AME_SALE_DESKTOP_SITE ? false : ((m === 'phone') || isPhone());
    var root = document.documentElement;
    root.setAttribute('data-ame-device', phone ? 'phone' : m);
    root.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-is-phone', 'ame-force-phone');
    root.classList.add('ame-device-' + (phone ? 'phone' : m));
    if (phone) {
      root.classList.add('ame-is-phone', 'ame-force-phone');
      root.setAttribute('data-ame-device', 'phone');
    }
    if (document.body) {
      document.body.setAttribute('data-ame-device', phone ? 'phone' : m);
      document.body.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-force-phone');
      document.body.classList.add('ame-device-' + (phone ? 'phone' : m));
      if (phone) document.body.classList.add('ame-force-phone');
    }
  }
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  window.addEventListener('resize', apply);
  window.addEventListener('orientationchange', function () { setTimeout(apply, 50); });
})();
</script>

<link rel="stylesheet" href="/assets/css/pos-terminal.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>-ex79">
<link rel="stylesheet" href="/assets/css/pos-skins.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>-skin">
<link rel="stylesheet" href="/assets/css/pos-box-type.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>-ex79">
<link rel="stylesheet" href="/assets/css/ame-sales-pro.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<!-- ফিক্স: POS শেল/গ্রিড/টেবিল-র‍্যাপের কলাম-সংখ্যা ও উচ্চতার একমাত্র উৎস।
     আগে এখানে দুইটা ভিন্ন <style> ব্লকে (ame-force-phone-90 এবং
     ame-pos-desktop-fit-fix) এই একই নিয়মগুলো ছড়িয়ে ছিল; সবগুলো একত্র করে
     assets/css/pos-terminal-layout.css-এ নেওয়া হয়েছে (এই ফাইলটাই এখন
     pos-terminal.css ও ame-sales-pro.css-এর কমেন্টে উল্লেখিত single source
     of truth)। এটা সব <link>/inline CSS-এর পরে লোড হয়, তাই ক্যাসকেডেও শেষে
     জেতে। বিস্তারিত ব্যাখ্যা ও যাচাইয়ের ফলাফল ওই ফাইলের হেডার-কমেন্টে আছে। -->
<link rel="stylesheet" id="ame-pos-terminal-layout-fix" href="/assets/css/pos-terminal-layout.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>-ex79">
<link rel="stylesheet" href="/assets/css/ame-sale-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/pos-add-boxes.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/sale-cream.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/pos-right-center.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/partner-cost-discount-box.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($_GET['sold'])): ?>
<p class="ame-sale-saved"><?php echo $is_english ? 'Sale completed.' : 'বিক্রয় সম্পন্ন হয়েছে।'; ?></p>
<?php endif; ?>
<div class="ame-pos-page ame-sale-pro ame-sale-desk">
    <div class="ame-pos-shell">
        <main class="ame-pos-grid">
            <?php include __DIR__ . '/pos_workspace.php'; ?>
            <?php include __DIR__ . '/pos_summary.php'; ?>
        </main>
    </div>
</div>

<script>
    /* নিরাপত্তা ফিক্স: process_sale.php-তে এখন CSRF যাচাই হয়,
       তাই টোকেনটা এখানে JS-এ পাঠিয়ে দেওয়া হলো */
    window.AME_CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
</script>
<script src="/assets/js/pos-cart.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>-ex78"></script>
<script src="/assets/js/pos-exchange.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>-ex78"></script>
<script src="/assets/js/pos-payment.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>-ex78"></script>
<script>
if (typeof window.submitFinalSale !== 'function') {
    window.submitFinalSale = function () {
        alert('বিক্রয়ের স্ক্রিপ্ট লোড হয়নি। পাতা আবার খুলুন।');
    };
}
</script>
<script src="/assets/js/pos-draft.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>-ex78"></script>
<script src="/assets/js/ame-sale-desk.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/pos-discount-zero.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/pos-title-live.js?v=<?php echo htmlspecialchars($ame_pos_asset, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include __DIR__ . '/pos_recent_boot.php'; ?>
<!-- বাগ-ফিক্স: pos-skins.css আর pos-box-type.css এখানে (পেজের শেষদিকে,
     pos-terminal-layout.css-এর পরে) আবার দ্বিতীয়বার <link> করা ছিল — একই
     ফাইল ব্রাউজার দুইবার লোড করত (অপচয়), আর CSS cascade-এ এই দ্বিতীয় কপিটা
     pos-terminal-layout.css-এর পরে থাকায়, সমান specificity-র যেকোনো নিয়মে
     সেটাই জিতে যেত — অর্থাৎ pos-terminal-layout.css-কে "সবার শেষে, একমাত্র
     উৎস" রাখার নকশাটা এই দুইটা ফাইলের জন্য কাজ করত না। উপরে (লাইন ~217-219)
     এই দুইটা ফাইলই ইতিমধ্যে একবার লোড হয়েছে, তাই দ্বিতীয় কপিটা মুছে ফেলা
     হলো। -->

<script src="/assets/js/ame-sales-pro.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.92', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include '../../partials/footer.php'; ?>
