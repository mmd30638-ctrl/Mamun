<?php
require_once __DIR__ . '/_boot.php';
$id = (int)($_GET['id'] ?? 0);
$edit = $id ? rental_owner($db, $id) : null;
$rental_page_title = rental_t('owners');
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
$owners = rental_all($db, 'SELECT * FROM rental_owners ORDER BY COALESCE(entry_date, DATE(created_at)) DESC, id DESC');
$entry_date_value = $edit ? rental_owner_entry_date($edit) : rental_today();
?>
<p class="rnt-muted">যার কাছ থেকে গাড়ি আনা হয়। নিচের গাড়ির বক্সে নাম্বার লিখুন — কাস্টমার সেই গাড়ি ভাড়া নিলে স্টেটমেন্ট সেই নাম্বার ধরে হিসাব হবে।</p>
<?php if ($edit && trim((string)($edit['vehicle_no'] ?? '')) === ''): ?>
<div class="rnt-warn">এই মালিকের গাড়ির নাম্বার খালি। নীল বক্সে নাম্বার লিখে সেভ করুন, নাহলে স্টেটমেন্ট গাড়ি ধরে আলাদা হবে না।</div>
<?php endif; ?>
<form class="rnt-form" method="post" action="/modules/rental/process_owner.php" style="margin-bottom:16px">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>">
    <div class="rnt-date-box">
        <h2 class="rnt-date-head"><?php echo esc_html(rental_t('entry_date')); ?></h2>
        <p class="rnt-muted" style="margin:0">ঘড়ি আজকের তারিখ দেখায়, কিন্তু এন্ট্রি আলাদা দিনের হতে পারে। দিন-মাস-বছর নিজে বসান।</p>
        <div class="rnt-date-row">
            <label><?php echo current_lang() === 'en' ? 'Date (manual)' : 'তারিখ — নিজে বসান'; ?> *
                <input type="date" name="entry_date" id="rntEntryDate" required value="<?php echo esc_html($entry_date_value); ?>">
            </label>
            <button type="button" class="rnt-btn" id="rntDateToday"><?php echo current_lang() === 'en' ? 'Use today' : 'আজকের তারিখ'; ?></button>
        </div>
    </div>
    <label>মালিকের নাম *
        <input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>">
    </label>
    <label>মোবাইল
        <input name="phone" value="<?php echo esc_html($edit['phone'] ?? ''); ?>">
    </label>
    <div class="rnt-vehicle-box" id="rntVehicleBox">
        <h2 class="rnt-vehicle-head">গাড়ির পরিচয়</h2>
        <p class="rnt-muted" style="margin:0">কাস্টমার যে গাড়ি ভাড়া নেবে, সেই নাম্বার এখানে বসান। হিসাব-নিকাশ এই গাড়ি ধরে হবে।</p>
        <div class="rnt-vehicle-grid">
            <label><?php echo esc_html(rental_t('vehicle_no')); ?> *
                <input name="vehicle_no" required maxlength="40" placeholder="যেমন: ঢাকা মেট্রো-গ ১২-৩৪৫৬" value="<?php echo esc_html($edit['vehicle_no'] ?? ''); ?>" autocomplete="off">
            </label>
            <label><?php echo esc_html(rental_t('vehicle_name')); ?>
                <input name="vehicle_name" maxlength="120" placeholder="যেমন: ৫১২ সিসি / হাইস" value="<?php echo esc_html($edit['vehicle_name'] ?? ''); ?>">
            </label>
        </div>
    </div>
    <label>পেমেন্ট সাইকেল
        <select name="pay_cycle">
            <?php foreach (rental_cycle_options() as $c): ?>
            <option value="<?php echo $c; ?>" <?php echo (($edit['pay_cycle'] ?? 'monthly') === $c) ? 'selected' : ''; ?>><?php echo esc_html(rental_t($c)); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>মালিককে দিতে হবে (ডিফল্ট)
        <input name="pay_amount" type="number" step="0.01" min="0" value="<?php echo esc_html($edit['pay_amount'] ?? '0'); ?>">
    </label>
    <label>কাস্টম দিন
        <input name="custom_days" type="number" min="1" value="<?php echo esc_html($edit['custom_days'] ?? '7'); ?>">
    </label>
    <label>ঠিকানা
        <input name="address" value="<?php echo esc_html($edit['address'] ?? ''); ?>">
    </label>
    <label class="full">নোট
        <textarea name="notes" rows="2" placeholder="গাড়ির নাম্বার এখানে নয় — উপরের নীল বক্সে লিখুন"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea>
    </label>
    <div class="full rnt-actions">
        <button class="rnt-btn g" type="submit"><?php echo esc_html(rental_t('save')); ?></button>
        <?php if ($edit): ?><a class="rnt-btn ghost" href="/modules/rental/owners.php">নতুন</a><?php endif; ?>
    </div>
</form>
<div class="rnt-scroll">
<table class="rnt-table">
    <tr><th>তারিখ</th><th>মালিক</th><th>গাড়ির নাম্বার</th><th>সাইকেল</th><th>পাওনা</th><th>দেওয়া</th><th>বাকি</th><th>লাভ</th><th></th></tr>
    <?php if (!$owners): ?>
    <tr><td colspan="9" class="rnt-muted"><?php echo esc_html(rental_t('empty')); ?></td></tr>
    <?php endif; ?>
    <?php foreach ($owners as $o): $sum = rental_owner_summary($db, (int)$o['id']); ?>
    <tr>
        <td><?php echo esc_html(rental_fmt_date(rental_owner_entry_date($o))); ?></td>
        <td>
            <a href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>"><?php echo esc_html($o['name']); ?></a>
            <div class="rnt-muted"><?php echo esc_html($o['phone'] ?? ''); ?></div>
        </td>
        <td>
            <strong><?php echo esc_html($o['vehicle_no'] ?? '—'); ?></strong>
            <div class="rnt-muted"><?php echo esc_html($o['vehicle_name'] ?? ''); ?></div>
        </td>
        <td><?php echo esc_html(rental_t($o['pay_cycle'])); ?> <?php echo format_bdt($o['pay_amount']); ?></td>
        <td><?php echo format_bdt($sum['accrued']); ?></td>
        <td><?php echo format_bdt($sum['paid']); ?></td>
        <td><?php echo format_bdt($sum['due']); ?></td>
        <td><?php echo format_bdt($sum['profit']); ?></td>
        <td>
            <a href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>">স্টেটমেন্ট</a>
            · <a href="/modules/rental/owners.php?id=<?php echo (int)$o['id']; ?>">সম্পাদনা</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<script>
(function () {
    var btn = document.getElementById('rntDateToday');
    var input = document.getElementById('rntEntryDate');
    if (!btn || !input) {
        return;
    }
    btn.addEventListener('click', function () {
        var now = new Date();
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var d = String(now.getDate()).padStart(2, '0');
        input.value = now.getFullYear() + '-' + m + '-' + d;
        input.focus();
    });
})();
</script>
<?php include __DIR__ . '/_foot.php'; ?>
