<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/shop_rent.php';
shop_rent_boot($db);
$summary = shop_rent_summary($db);
$alerts = shop_rent_alerts($db);
$rows = shop_rent_rows($db);
$flash = (string)($_SESSION['shop_rent_flash'] ?? '');
unset($_SESSION['shop_rent_flash']);
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<header class="sr-hero">
    <h1>দোকান ভাড়া</h1>
    <p>যে দোকান আমরা অন্য মালিকের কাছ থেকে ভাড়া নিয়েছি, তার চুক্তি, অগ্রিম, মাসিক ভাড়া এবং কাগজপত্র।</p>
</header>
<?php if ($flash !== ''): ?><div class="sr-note"><?php echo shop_rent_e($flash); ?></div><?php endif; ?>
<section class="sr-cards">
    <article class="sr-card"><b><?php echo (int)$summary['shops']; ?></b><span>ভাড়া নেওয়া দোকান</span></article>
    <article class="sr-card adv"><b>৳<?php echo shop_rent_money($summary['advance']); ?></b><span>মোট অগ্রিম</span></article>
    <article class="sr-card due"><b>৳<?php echo shop_rent_money($summary['due']); ?></b><span>এই মাসের বাকি ভাড়া</span></article>
</section>
<?php foreach ($alerts as $alert): ?>
    <div class="sr-alert <?php echo shop_rent_e($alert['tone']); ?>">
        <strong><?php echo shop_rent_e($alert['title']); ?></strong>
        — <?php echo shop_rent_e($alert['text']); ?>
        <a href="/modules/shop_rent/<?php echo $alert['tone'] === 'due' ? 'pay.php' : 'shop.php'; ?>?id=<?php echo (int)$alert['shop_id']; ?>">খুলুন</a>
    </div>
<?php endforeach; ?>
<?php if (!$rows): ?>
    <div class="sr-box"><p>এখনো কোনো দোকান যোগ হয়নি।</p><a class="sr-btn" href="/modules/shop_rent/shop.php">নতুন দোকান যোগ করুন</a></div>
<?php else: ?>
<div class="sr-list">
<?php foreach ($rows as $row): ?>
    <article class="sr-shop">
        <div>
            <strong><?php echo shop_rent_e($row['title']); ?></strong>
            <small><?php echo shop_rent_e($row['landlord_name']); ?><?php echo $row['phone'] !== '' ? ' · ' . shop_rent_e($row['phone']) : ''; ?></small>
        </div>
        <div>ভাড়া<br><b>৳<?php echo shop_rent_money($row['monthly_rent'] ?? 0); ?></b></div>
        <div>অগ্রিম<br><b>৳<?php echo shop_rent_money($row['advance_amount'] ?? 0); ?></b></div>
        <div>মেয়াদ<br><b><?php echo shop_rent_e($row['end_date'] ?? '—'); ?></b></div>
        <div class="sr-actions">
            <a href="/modules/shop_rent/shop.php?id=<?php echo (int)$row['id']; ?>">চুক্তি</a>
            <a href="/modules/shop_rent/pay.php?id=<?php echo (int)$row['id']; ?>">ভাড়া</a>
            <a class="ghost" href="/modules/shop_rent/locker.php?id=<?php echo (int)$row['id']; ?>">কাগজ</a>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
