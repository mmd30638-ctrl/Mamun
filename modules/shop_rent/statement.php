<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/shop_rent.php';
shop_rent_boot($db);
$shops = shop_rent_rows($db);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 && $shops) {
    $id = (int)$shops[0]['id'];
}
$row = $id > 0 ? shop_rent_load($db, $id) : null;
$year = (string)($_GET['year'] ?? shop_rent_now()->format('Y'));
if (!preg_match('/^\d{4}$/', $year)) {
    $year = shop_rent_now()->format('Y');
}
$charges = $row ? shop_rent_charges($db, (int)($row['agreement_id'] ?? 0), $year) : [];
$kinds = shop_rent_charge_kinds();
$methods = shop_rent_pay_methods();
$paid = 0.0;
$due = 0.0;
foreach ($charges as $charge) {
    if ((string)$charge['status'] === 'paid') {
        $paid += (float)$charge['amount'];
    } else {
        $due += (float)$charge['amount'];
    }
}
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<header class="sr-hero">
    <h1>বার্ষিক স্টেটমেন্ট</h1>
    <p>যেকোনো বছরের ভাড়া ও বিল, আর চুক্তির সারাংশ। প্রিন্ট বা পিডিএফ এক ক্লিকে।</p>
</header>
<?php if (!$shops): ?>
    <div class="sr-box"><p>এখনো কোনো দোকান নেই।</p></div>
<?php else: ?>
<form class="sr-tools no-print" method="get">
    <select name="id" aria-label="দোকান" onchange="this.form.submit()">
        <?php foreach ($shops as $shop): ?>
            <option value="<?php echo (int)$shop['id']; ?>" <?php echo (int)$shop['id'] === $id ? 'selected' : ''; ?>><?php echo shop_rent_e($shop['title']); ?></option>
        <?php endforeach; ?>
    </select>
    <select name="year" aria-label="বছর" onchange="this.form.submit()">
        <?php for ($y = (int)shop_rent_now()->format('Y'); $y >= (int)shop_rent_now()->format('Y') - 6; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo (string)$y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
        <?php endfor; ?>
    </select>
    <button class="sr-btn" type="button" id="srSheet">ছাপুন</button>
    <a class="sr-btn" href="/modules/shop_rent/pdf.php?id=<?php echo $id; ?>&year=<?php echo shop_rent_e($year); ?>&doc=statement">বার্ষিক পিডিএফ</a>
    <a class="sr-btn ghost" href="/modules/shop_rent/pdf.php?id=<?php echo $id; ?>&year=<?php echo shop_rent_e($year); ?>&doc=agreement">চুক্তির সারাংশ পিডিএফ</a>
</form>
<?php if ($row): ?>
<article class="sr-paper">
    <h2><?php echo shop_rent_e(function_exists('shop_name') ? shop_name() : ''); ?></h2>
    <h3>বার্ষিক ভাড়ার স্টেটমেন্ট <?php echo shop_rent_e($year); ?></h3>
    <p><strong><?php echo shop_rent_e($row['title']); ?></strong> — <?php echo shop_rent_e($row['name']); ?></p>
    <p><?php echo shop_rent_e($row['address'] ?? ''); ?></p>
    <?php if (!$charges): ?>
        <p>এই বছরে কোনো ভাড়া বা বিল নেই।</p>
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
    <p>মোট দেওয়া ৳<?php echo shop_rent_money($paid); ?> · মোট বাকি ৳<?php echo shop_rent_money($due); ?></p>
    <?php endif; ?>
    <h3>চুক্তির সারাংশ</h3>
    <p>মালিক <?php echo shop_rent_e($row['name']); ?><?php echo (string)($row['phone'] ?? '') !== '' ? ' · ' . shop_rent_e($row['phone']) : ''; ?></p>
    <p>মেয়াদ <?php echo shop_rent_e($row['start_date'] ?? ''); ?> থেকে <?php echo shop_rent_e($row['end_date'] ?? ''); ?></p>
    <p>মাসিক ভাড়া ৳<?php echo shop_rent_money($row['monthly_rent'] ?? 0); ?> · অগ্রিম ৳<?php echo shop_rent_money($row['advance_amount'] ?? 0); ?></p>
    <p>জায়গা <?php echo shop_rent_money($row['size_sqft'] ?? 0); ?> স্কয়ার ফিট<?php echo (string)($row['condition_note'] ?? '') !== '' ? ' · ' . shop_rent_e($row['condition_note']) : ''; ?></p>
</article>
<?php endif; ?>
<?php endif; ?>
</div>
<script>
document.getElementById('srSheet') && document.getElementById('srSheet').addEventListener('click', function () {
    window['print']();
});
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
