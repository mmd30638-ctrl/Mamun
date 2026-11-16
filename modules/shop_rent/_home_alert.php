<?php
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}
if (!isset($db) || !($db instanceof mysqli)) {
    return;
}
$srHomeOk = function_exists('ame_is_admin_like') && ame_is_admin_like();
if (!$srHomeOk && function_exists('has_permission')) {
    $srHomeOk = has_permission($db, 'm-shop-rent');
}
if (!$srHomeOk) {
    return;
}
try {
    if (!function_exists('shop_rent_boot')) {
        require_once __DIR__ . '/../../includes/shop_rent.php';
    }
    shop_rent_boot($db);
    $srSum = shop_rent_summary($db);
    $srAlerts = shop_rent_alerts($db);
    if ((int)$srSum['shops'] === 0 && !$srAlerts) {
        return;
    }
} catch (Throwable $e) {
    return;
}
?>
<link rel="stylesheet" href="/modules/shop_rent/shop-rent.css?v=<?php echo shop_rent_e(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1'); ?>">
<section class="sr-home">
    <div class="sr-alert">
        <strong><?php echo (int)$srSum['shops']; ?>টি দোকান ভাড়া</strong>
        · অগ্রিম ৳<?php echo shop_rent_money($srSum['advance']); ?>
        · এই মাসে বাকি ৳<?php echo shop_rent_money($srSum['due']); ?>
        <a href="/modules/shop_rent/index.php">ডেস্ক খুলুন</a>
    </div>
    <?php foreach (array_slice($srAlerts, 0, 3) as $alert): ?>
        <div class="sr-alert <?php echo shop_rent_e($alert['tone']); ?>">
            <strong><?php echo shop_rent_e($alert['title']); ?></strong>
            — <?php echo shop_rent_e($alert['text']); ?>
        </div>
    <?php endforeach; ?>
</section>
