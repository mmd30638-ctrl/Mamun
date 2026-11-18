<?php
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.34', ENT_QUOTES, 'UTF-8');
$shop = function_exists('shop_name') ? shop_name() : 'আমার ব্যবসা প্রতিষ্ঠান';
$when = date('d/m/Y h:i A');
?>
<link rel="stylesheet" href="/assets/css/accounts-print-share.css?v=<?php echo $ver; ?>">
<div class="acc-print-banner" aria-hidden="true">
    <strong><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></strong>
    <span>হিসাব নিকাশ · <?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html(isset($db) && $db instanceof mysqli ? $db : null) : ''; ?>
</div>
<div class="acc-share-bar no-print" id="accShareBar">
    <button type="button" class="acc-share-btn acc-share-print" onclick="ameAccPrint()">প্রিন্ট</button>
    <button type="button" class="acc-share-btn acc-share-send" onclick="ameAccShareOpen()">শেয়ার</button>
</div>
<div class="acc-share-sheet no-print" id="accShareSheet" hidden>
    <div class="acc-share-card" role="dialog" aria-label="শেয়ার">
        <div class="acc-share-head">
            <strong>হিসাব শেয়ার করুন</strong>
            <button type="button" class="acc-share-x" onclick="ameAccShareClose()" aria-label="বন্ধ">&times;</button>
        </div>
        <p class="acc-share-hint">হোয়াটসঅ্যাপ বা অন্য অ্যাপে পাঠান। প্রিন্ট করলে শুধু হিসাব বের হবে।</p>
        <button type="button" class="acc-share-opt" onclick="ameAccShareNative()">ফোনের শেয়ার</button>
        <button type="button" class="acc-share-opt" onclick="ameAccShareWhatsApp()">হোয়াটসঅ্যাপ</button>
        <button type="button" class="acc-share-opt" onclick="ameAccShareCopy()">লেখা কপি</button>
        <div class="acc-share-msg" id="accShareMsg" hidden></div>
    </div>
</div>
<script>
window.AME_SHOP_NAME = <?php echo json_encode($shop, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/assets/js/accounts-print-share.js?v=<?php echo $ver; ?>" defer></script>
