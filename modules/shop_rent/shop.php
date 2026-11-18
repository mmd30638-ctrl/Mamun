<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/shop_rent.php';
shop_rent_boot($db);
$id = (int)($_GET['id'] ?? 0);
$row = $id > 0 ? shop_rent_load($db, $id) : null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $saved = shop_rent_save_profile($db, $_POST);
        $_SESSION['shop_rent_flash'] = 'দোকান ও চুক্তি সেভ হয়েছে।';
        header('Location: /modules/shop_rent/shop.php?id=' . $saved);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $row = array_merge($row ?? [], $_POST);
    }
}
$accounts = function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
$v = static function (string $key, $default = '') use ($row) {
    return $row[$key] ?? $default;
};
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<header class="sr-hero">
    <h1><?php echo $id > 0 ? 'চুক্তি বদলান' : 'নতুন দোকান ভাড়া'; ?></h1>
    <p>মালিক, দোকানের ঠিকানা, অগ্রিম এবং কতদিন পর ভাড়া বাড়বে — সব একসাথে।</p>
</header>
<?php if ($error !== ''): ?><div class="sr-error"><?php echo shop_rent_e($error); ?></div><?php endif; ?>
<form class="sr-form" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="shop_id" value="<?php echo (int)$id; ?>">
    <section class="sr-box">
        <h2>দোকান মালিক</h2>
        <label for="name">মালিকের নাম</label>
        <input id="name" name="name" required value="<?php echo shop_rent_e($v('name')); ?>">
        <label for="phone">মোবাইল</label>
        <input id="phone" name="phone" value="<?php echo shop_rent_e($v('phone')); ?>">
        <label for="emergency_phone">ইমার্জেন্সি নম্বর</label>
        <input id="emergency_phone" name="emergency_phone" value="<?php echo shop_rent_e($v('emergency_phone')); ?>">
        <label for="pay_method">টাকা নেওয়ার মাধ্যম</label>
        <select id="pay_method" name="pay_method">
            <?php foreach (shop_rent_pay_methods() as $key => $label): ?>
                <option value="<?php echo shop_rent_e($key); ?>" <?php echo (string)$v('pay_method', 'cash') === $key ? 'selected' : ''; ?>><?php echo shop_rent_e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="bank_name">ব্যাংক / মোবাইল ব্যাংকের নাম</label>
        <input id="bank_name" name="bank_name" value="<?php echo shop_rent_e($v('bank_name')); ?>">
        <label for="account_name">অ্যাকাউন্টের নাম</label>
        <input id="account_name" name="account_name" value="<?php echo shop_rent_e($v('account_name')); ?>">
        <label for="account_no">অ্যাকাউন্ট বা মোবাইল নম্বর</label>
        <input id="account_no" name="account_no" value="<?php echo shop_rent_e($v('account_no')); ?>">
        <label for="note">নোট</label>
        <textarea id="note" name="note" rows="2"><?php echo shop_rent_e($v('landlord_note', $v('note'))); ?></textarea>
    </section>
    <section class="sr-box">
        <h2>দোকান ও চুক্তি</h2>
        <label for="title">দোকানের নাম</label>
        <input id="title" name="title" required value="<?php echo shop_rent_e($v('title')); ?>">
        <label for="address">সম্পূর্ণ ঠিকানা</label>
        <textarea id="address" name="address" rows="2"><?php echo shop_rent_e($v('address')); ?></textarea>
        <label for="size_sqft">জায়গা (স্কয়ার ফিট)</label>
        <input id="size_sqft" name="size_sqft" type="number" min="0" step="0.01" value="<?php echo shop_rent_e($v('size_sqft', '0')); ?>">
        <label for="condition_note">বর্তমান অবস্থা</label>
        <input id="condition_note" name="condition_note" value="<?php echo shop_rent_e($v('condition_note')); ?>">
        <label for="start_date">চুক্তি শুরু</label>
        <input id="start_date" name="start_date" type="date" required value="<?php echo shop_rent_e($v('start_date', shop_rent_today())); ?>">
        <label for="end_date">চুক্তি শেষ</label>
        <input id="end_date" name="end_date" type="date" required value="<?php echo shop_rent_e($v('end_date')); ?>">
        <label for="alert_months">নবায়নের সতর্কতা</label>
        <select id="alert_months" name="alert_months">
            <?php foreach ([1, 2, 3] as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo (int)$v('alert_months', 3) === $m ? 'selected' : ''; ?>><?php echo $m; ?> মাস আগে</option>
            <?php endforeach; ?>
        </select>
        <label for="monthly_rent">মাসিক ভাড়া</label>
        <input id="monthly_rent" name="monthly_rent" type="number" min="0" step="0.01" value="<?php echo shop_rent_e($v('monthly_rent', '0')); ?>">
        <label for="pay_day">প্রতি মাসে যে তারিখে ভাড়া</label>
        <input id="pay_day" name="pay_day" type="number" min="1" max="28" value="<?php echo (int)$v('pay_day', 5); ?>">
        <label for="advance_amount">অগ্রিম / সিকিউরিটি</label>
        <input id="advance_amount" name="advance_amount" type="number" min="0" step="0.01" value="<?php echo shop_rent_e($v('advance_amount', '0')); ?>" <?php echo (int)$v('advance_posted', 0) === 1 ? 'readonly' : ''; ?>>
        <?php if ((int)$v('advance_posted', 0) === 1): ?><p>এই অগ্রিম অ্যাকাউন্ট থেকে একবার কাটা হয়েছে।</p><?php endif; ?>
        <label for="advance_account_id">অগ্রিম কোন অ্যাকাউন্ট থেকে</label>
        <select id="advance_account_id" name="advance_account_id" class="ame-account-select" data-ame-account-select="1">
            <option value="0">অগ্রিম না থাকলে ফাঁকা রাখুন</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?php echo (int)$account['id']; ?>" <?php echo (int)$v('advance_account_id', 0) === (int)$account['id'] ? 'selected' : ''; ?>><?php echo shop_rent_e(shop_rent_account_label($account)); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="increment_months">কত মাস পর ভাড়া বাড়বে</label>
        <input id="increment_months" name="increment_months" type="number" min="0" value="<?php echo (int)$v('increment_months', 0); ?>">
        <label for="increment_percent">বাড়ার হার (%)</label>
        <input id="increment_percent" name="increment_percent" type="number" min="0" step="0.01" value="<?php echo shop_rent_e($v('increment_percent', '0')); ?>">
        <label for="alert_email">সতর্কতার ইমেইল</label>
        <input id="alert_email" name="alert_email" type="email" value="<?php echo shop_rent_e($v('alert_email')); ?>" placeholder="আমাদের ইমেইল">
        <label for="status">দোকানের অবস্থা</label>
        <select id="status" name="status">
            <option value="active" <?php echo (string)$v('status', 'active') === 'active' ? 'selected' : ''; ?>>চলছে</option>
            <option value="left" <?php echo (string)$v('status') === 'left' ? 'selected' : ''; ?>>ছেড়ে দিয়েছি</option>
        </select>
    </section>
    <div class="sr-span"><button class="sr-btn" type="submit">সেভ করুন</button></div>
</form>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
