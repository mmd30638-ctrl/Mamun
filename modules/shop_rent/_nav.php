<?php
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}
$sr_here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$sr_links = [
    'index.php' => 'ডেস্ক',
    'shop.php' => 'দোকান ও চুক্তি',
    'pay.php' => 'মাসিক ভাড়া',
    'locker.php' => 'কাগজ ও ছবি',
    'statement.php' => 'স্টেটমেন্ট',
];
?>
<link rel="stylesheet" href="/modules/shop_rent/shop-rent.css?v=<?php echo shop_rent_e(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1'); ?>">
<div class="sr-wrap">
<nav class="sr-nav" aria-label="দোকান ভাড়া">
<?php foreach ($sr_links as $file => $label): ?>
    <a class="<?php echo $sr_here === $file ? 'is-on' : ''; ?>" href="/modules/shop_rent/<?php echo shop_rent_e($file); ?>"><?php echo shop_rent_e($label); ?></a>
<?php endforeach; ?>
</nav>
