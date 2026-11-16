<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/shop_rent.php';
shop_rent_boot($db);
$id = (int)($_GET['id'] ?? $_POST['shop_id'] ?? 0);
$row = $id > 0 ? shop_rent_load($db, $id) : null;
$shops = shop_rent_rows($db);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        shop_rent_record_payment($db, $_POST);
        $_SESSION['shop_rent_flash'] = 'ভাড়া বা বিল লেখা হয়েছে।';
        header('Location: /modules/shop_rent/pay.php?id=' . (int)($_POST['shop_id'] ?? 0));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$flash = (string)($_SESSION['shop_rent_flash'] ?? '');
unset($_SESSION['shop_rent_flash']);
$charges = $row ? shop_rent_charges($db, (int)($row['agreement_id'] ?? 0)) : [];
$accounts = function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
$kinds = shop_rent_charge_kinds();
$methods = shop_rent_pay_methods();
$month = shop_rent_now()->format('Y-m');
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<header class="sr-hero">
    <h1>মাসিক ভাড়া ও বিল</h1>
    <p>যে তারিখে ভাড়া ধার্য হয় সেদিন ডেস্কে সতর্কতা আসে। বিদ্যুৎ, পানি ও সার্ভিস চার্জ ভাড়া থেকে আলাদা।</p>
</header>
<?php if ($flash !== ''): ?><div class="sr-note"><?php echo shop_rent_e($flash); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="sr-error"><?php echo shop_rent_e($error); ?></div><?php endif; ?>
<?php if (!$shops): ?>
    <div class="sr-box"><p>আগে একটি দোকান যোগ করুন।</p><a class="sr-btn" href="/modules/shop_rent/shop.php">নতুন দোকান</a></div>
<?php else: ?>
<form class="sr-form" method="get">
    <section class="sr-box sr-span">
        <label for="shopPick">দোকান</label>
        <select id="shopPick" name="id" onchange="this.form.submit()">
            <option value="0">দোকান বাছুন</option>
            <?php foreach ($shops as $shop): ?>
                <option value="<?php echo (int)$shop['id']; ?>" <?php echo (int)$shop['id'] === $id ? 'selected' : ''; ?>><?php echo shop_rent_e($shop['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </section>
</form>
<?php if ($row): ?>
<form class="sr-form" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="shop_id" value="<?php echo (int)$row['id']; ?>">
    <section class="sr-box">
        <h2><?php echo shop_rent_e($row['title']); ?></h2>
        <p>মাসিক ভাড়া ৳<?php echo shop_rent_money($row['monthly_rent'] ?? 0); ?> · প্রতি মাসের <?php echo (int)($row['pay_day'] ?? 5); ?> তারিখ</p>
        <label for="period_ym">মাস</label>
        <input id="period_ym" name="period_ym" type="month" required value="<?php echo shop_rent_e($_POST['period_ym'] ?? $month); ?>">
        <label for="kind">কী দিচ্ছেন</label>
        <select id="kind" name="kind">
            <?php foreach ($kinds as $key => $label): ?>
                <option value="<?php echo shop_rent_e($key); ?>" <?php echo (string)($_POST['kind'] ?? 'rent') === $key ? 'selected' : ''; ?>><?php echo shop_rent_e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="amount">টাকা</label>
        <input id="amount" name="amount" type="number" min="0.01" step="0.01" required value="<?php echo shop_rent_e($_POST['amount'] ?? ($row['monthly_rent'] ?? '')); ?>">
    </section>
    <section class="sr-box">
        <h2>কীভাবে দেওয়া হলো</h2>
        <label for="pay_method">মাধ্যম</label>
        <select id="pay_method" name="pay_method">
            <?php foreach ($methods as $key => $label): ?>
                <option value="<?php echo shop_rent_e($key); ?>" <?php echo (string)($_POST['pay_method'] ?? 'cash') === $key ? 'selected' : ''; ?>><?php echo shop_rent_e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="account_id">কোন অ্যাকাউন্ট থেকে</label>
        <select id="account_id" name="account_id" class="ame-account-select" data-ame-account-select="1" required>
            <option value="">অ্যাকাউন্ট বাছুন</option>
            <?php foreach ($accounts as $account): ?>
                <option value="<?php echo (int)$account['id']; ?>" <?php echo (string)($_POST['account_id'] ?? '') === (string)$account['id'] ? 'selected' : ''; ?>><?php echo shop_rent_e(shop_rent_account_label($account)); ?></option>
            <?php endforeach; ?>
        </select>
        <label for="note">নোট</label>
        <input id="note" name="note" value="<?php echo shop_rent_e($_POST['note'] ?? ''); ?>">
        <div class="sr-tools"><button class="sr-btn" type="submit">পেমেন্ট লিখুন</button></div>
    </section>
</form>
<section class="sr-box" style="margin-top:14px">
    <h2>ইতিহাস</h2>
    <?php if (!$charges): ?>
        <p>এই দোকানের কোনো ভাড়া বা বিল এখনো নেই।</p>
    <?php else: ?>
    <table class="sr-table">
        <thead><tr><th>মাস</th><th>ধরন</th><th>টাকা</th><th>অবস্থা</th><th>মাধ্যম</th><th>সময়</th></tr></thead>
        <tbody>
        <?php foreach ($charges as $charge): ?>
            <tr>
                <td><?php echo shop_rent_e(shop_rent_period_label((string)$charge['period_ym'])); ?></td>
                <td><?php echo shop_rent_e($kinds[$charge['kind']] ?? $charge['kind']); ?></td>
                <td>৳<?php echo shop_rent_money($charge['amount']); ?></td>
                <td><?php echo (string)$charge['status'] === 'paid' ? 'দেওয়া' : 'বাকি'; ?></td>
                <td><?php echo shop_rent_e($methods[$charge['pay_method'] ?? ''] ?? '—'); ?></td>
                <td><?php echo shop_rent_e((string)$charge['status'] === 'paid' ? $charge['paid_at'] : $charge['due_date']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
