<!-- profile_transaction_form.php: নতুন চার্জ/লেনদেন যোগ করার ফর্ম সেকশন (profile_view.php থেকে require হয়) -->
<!-- =====================================================
     NEW TRANSACTION
====================================================== -->

<div class="card no-print">

<h3 style="margin-top:0;">
🧾 নতুন চার্জ / লেনদেন যোগ করুন
</h3>


<div class="charge-selector">

<div
    class="charge-card"
    onclick="toggleChargeForm('credit', this)"
>

<div class="icon">
🛒
</div>

<div class="label">
বাকিতে পণ্য বিক্রি
</div>

</div>


<div
    class="charge-card"
    onclick="toggleChargeForm('installment', this)"
>

<div class="icon">
🏦
</div>

<div class="label">
কিস্তিতে বিক্রি
</div>

</div>


<div
    class="charge-card"
    onclick="toggleChargeForm('rental', this)"
>

<div class="icon">
🤝
</div>

<div class="label">
ভাড়ায় দিন
</div>

</div>

</div>


<!-- CREDIT SALE -->

<div
    id="form-credit"
    class="charge-form-box"
>

<h5>
🛒 বাকিতে পণ্য বিক্রি
</h5>

<form
    action="actions/process_credit_sale.php"
    method="POST"
    onsubmit="return validateCredit(this);"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?php echo htmlspecialchars(csrf_token()); ?>"
>

<input
    type="hidden"
    name="customer_id"
    value="<?php echo (int)$customer_id; ?>"
>

<div class="form-grid">

<input
    type="text"
    name="product_details"
    placeholder="পণ্যের বিবরণ"
    maxlength="500"
    required
>

<input
    type="number"
    step="0.01"
    min="0.01"
    name="amount"
    placeholder="মোট মূল্য (৳)"
    required
>

</div>

<button
    type="submit"
    class="form-submit"
    style="background:#0D47A1;"
>
💾 বাকি বিক্রি নিশ্চিত করুন
</button>

</form>

</div>


<!-- INSTALLMENT -->

<div
    id="form-installment"
    class="charge-form-box"
>

<h5>
🏦 নতুন কিস্তি চুক্তি
</h5>

<form
    action="actions/process_installment.php"
    method="POST"
    onsubmit="return validateInstallment(this);"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?php echo htmlspecialchars(csrf_token()); ?>"
>

<input
    type="hidden"
    name="customer_id"
    value="<?php echo (int)$customer_id; ?>"
>

<div class="form-grid">

<input
    type="text"
    name="product_details"
    placeholder="পণ্যের বিবরণ"
    maxlength="500"
    required
>

<select name="product_condition">

<option value="NEW">
নতুন পণ্য
</option>

<option value="OLD_REFURBISHED">
পুরাতন/এক্সচেঞ্জ
</option>

</select>

<input
    type="number"
    step="0.01"
    min="0.01"
    name="total_amount"
    placeholder="মোট মূল্য (৳)"
    required
>

<input
    type="number"
    step="0.01"
    min="0"
    name="down_payment"
    placeholder="ডাউন পেমেন্ট (৳)"
    value="0"
>

<input
    type="number"
    min="1"
    name="total_installments"
    placeholder="মোট কিস্তি সংখ্যা"
    value="12"
    required
>

<select name="installment_type">

<option value="MONTHLY">
মাসিক
</option>

<option value="WEEKLY">
সাপ্তাহিক
</option>

<option value="DAILY">
দৈনিক
</option>

</select>

</div>

<button
    type="submit"
    class="form-submit"
    style="background:#4527A0;"
>
💾 কিস্তি চুক্তি নিশ্চিত করুন
</button>

</form>

</div>


<?php
$rental_owners = [];
if (is_file(__DIR__ . '/../../includes/rental_schema.php')) {
    require_once __DIR__ . '/../../includes/rental_schema.php';
    require_once __DIR__ . '/../../includes/rental_functions.php';
    if (isset($db) && $db instanceof mysqli) {
        ensure_rental_schema($db);
        $rental_owners = rental_all(
            $db,
            'SELECT id, name, pay_cycle, pay_amount, custom_days, vehicle_no, vehicle_name FROM rental_owners WHERE status=1 ORDER BY name'
        );
    }
}
?>
<!-- RENTAL -->

<div
    id="form-rental"
    class="charge-form-box"
>

<h5>
🤝 নতুন পণ্য ভাড়া প্রদান
</h5>
<p style="margin:0 0 10px;">
<a href="/modules/customers/hire/create.php?customer_id=<?php echo (int)$customer_id; ?>" style="font-weight:800;color:#003F8F;">পূর্ণ ভাড়া চুক্তি ফর্ম →</a>
· <a href="/modules/customers/hire/all.php?customer_id=<?php echo (int)$customer_id; ?>" style="font-weight:700;">এই কাস্টমারের চুক্তি</a>
</p>

<form
    action="actions/process_rental.php"
    method="POST"
    onsubmit="return validateRental(this);"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?php echo htmlspecialchars(csrf_token()); ?>"
>

<input
    type="hidden"
    name="customer_id"
    value="<?php echo (int)$customer_id; ?>"
>

<div class="form-grid">

<input
    type="text"
    name="item_name"
    placeholder="পণ্যের নাম ও সিরিয়াল"
    maxlength="500"
    required
>
<input type="text" name="serial_no" placeholder="সিরিয়াল / অ্যাসেট আইডি" maxlength="80">
<input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
<input type="date" name="expected_return" placeholder="প্রত্যাশিত ফেরত">
<select name="advance_usage">
<option value="0">জামানত ব্যবহার বন্ধ (শুধু ওয়ালেট)</option>
<option value="1">জামানত ব্যবহার চালু</option>
</select>

<input
    type="number"
    step="0.01"
    min="0"
    name="security_deposit"
    placeholder="সিকিউরিটি জামানত (৳)"
    required
>

<input
    type="number"
    step="0.01"
    min="0.01"
    name="rent_rate"
    placeholder="ভাড়ার হার (৳)"
    required
>

<select name="rate_type">

<option value="DAILY">
প্রতিদিন
</option>

<option value="HOURLY">
প্রতি ঘন্টা
</option>

<option value="WEEKLY">
সাপ্তাহিক
</option>

<option value="MONTHLY">
মাসিক
</option>

</select>

<select name="owner_id" id="rntOwnerSelect">
<option value="0">মালিক / সোর্স — নিজের জিনিস হলে খালি</option>
<?php foreach ($rental_owners as $ro): ?>
<option
    value="<?php echo (int)$ro['id']; ?>"
    data-cycle="<?php echo htmlspecialchars((string)$ro['pay_cycle'], ENT_QUOTES, 'UTF-8'); ?>"
    data-rate="<?php echo htmlspecialchars((string)$ro['pay_amount'], ENT_QUOTES, 'UTF-8'); ?>"
    data-days="<?php echo htmlspecialchars((string)$ro['custom_days'], ENT_QUOTES, 'UTF-8'); ?>"
    data-vehicle="<?php echo htmlspecialchars((string)($ro['vehicle_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
    data-vname="<?php echo htmlspecialchars((string)($ro['vehicle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
>
<?php
$olabel = $ro['name'];
if (!empty($ro['vehicle_no'])) {
    $olabel .= ' — ' . $ro['vehicle_no'];
}
echo htmlspecialchars($olabel, ENT_QUOTES, 'UTF-8');
?>
</option>
<?php endforeach; ?>
</select>

<div id="rntVehicleBox" style="grid-column:1/-1;background:#F0F7FF;border:2px solid #64B5F6;border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:6px;">
<label for="rntVehicleNo" style="font-weight:800;color:#0D47A1;margin:0;">গাড়ির নাম্বার</label>
<input
    type="text"
    name="vehicle_no"
    id="rntVehicleNo"
    maxlength="40"
    placeholder="যেমন: ঢাকা মেট্রো-গ ১২-৩৪৫৬"
    style="font-weight:700;"
>
<span style="font-size:12px;color:#546E7A;">মালিক বেছে নিলে নাম্বার আপনাআপনি আসবে। এই গাড়ির ভাড়া স্টেটমেন্টে উঠবে।</span>
</div>

<input
    type="number"
    step="0.01"
    min="0"
    name="owner_rate"
    id="rntOwnerRate"
    placeholder="মালিককে দিতে হবে (৳) যেমন ৪০০০"
>

<select name="owner_cycle" id="rntOwnerCycle">
<option value="monthly">মালিক: মাসিক</option>
<option value="weekly">মালিক: সাপ্তাহিক</option>
<option value="daily">মালিক: দৈনিক</option>
<option value="custom">মালিক: কাস্টম</option>
</select>

<input
    type="number"
    min="1"
    name="owner_custom_days"
    id="rntOwnerDays"
    value="7"
    placeholder="কাস্টম দিন"
>

</div>
<p style="font-size:12px;color:#546E7A;margin:8px 0 0;">
কাস্টমার থেকে যেমন ৳৫,০০০, মালিককে ৳৪,০০০ — লাভ ৳১,০০০। গাড়ির নাম্বার স্টেটমেন্টে উঠবে।
</p>

<button
    type="submit"
    class="form-submit"
    style="background:#D84315;"
>
💾 ভাড়া চুক্তি চালু করুন
</button>
<script>
(function(){
  var sel = document.getElementById('rntOwnerSelect');
  if (!sel) return;
  sel.addEventListener('change', function(){
    var o = sel.options[sel.selectedIndex];
    if (!o || !o.value || o.value === '0') return;
    document.getElementById('rntOwnerCycle').value = o.getAttribute('data-cycle') || 'monthly';
    document.getElementById('rntOwnerRate').value = o.getAttribute('data-rate') || '';
    document.getElementById('rntOwnerDays').value = o.getAttribute('data-days') || '7';
    var v = document.getElementById('rntVehicleNo');
    if (v) v.value = o.getAttribute('data-vehicle') || '';
  });
})();
</script>

</form>

</div>

</div>


<!-- =====================================================
     LEDGER
====================================================== -->

<div class="card">

<div
    class="flex-between"
    style="margin-bottom:10px;"
>

<h4 style="margin:0;">
🏛️ ব্যাংকিং লেজার স্টেটমেন্ট
</h4>

<button
    type="button"
    onclick="window.print()"
    class="btn btn-primary no-print"
>
🖨️ প্রিন্ট করুন
</button>

</div>


<div class="table-wrap">

<table class="ledger-table">

<thead>

<tr>

<th>তারিখ</th>

<th>ধরন</th>

<th>রেফারেন্স ও বিবরণ</th>

<th>ডেবিট (Dr)</th>

<th>ক্রেডিট (Cr)</th>

<th>রানিং ব্যালেন্স</th>

</tr>

</thead>


<tbody>

<?php if (count($ledgers) > 0): ?>

<?php foreach ($ledgers as $l): ?>

<?php

$module =
    (string)($l['module_type'] ?? '');

$badge =
    $module_badge_colors[$module]
    ?? 'bg-cus';

$debit =
    (float)($l['debit'] ?? 0);

$credit =
    (float)($l['credit'] ?? 0);

$running =
    (float)($l['running_balance'] ?? 0);

/*
 * Debit = balance বৃদ্ধি
 * Credit = balance হ্রাস
 */
$previous =
    $running
    - $debit
    + $credit;

?>

<tr>

<td>
<?php echo h($l['txn_date'] ?? '—'); ?>
</td>

<td>

<span class="role-badge <?php echo h($badge); ?>">
<?php echo h($module ?: '—'); ?>
</span>

</td>

<td>

<strong>
<?php echo h($l['ref_no'] ?? '—'); ?>
</strong>

<br>

<small style="color:#666;">
<?php echo h($l['description'] ?? ''); ?>
</small>

</td>

<td class="dr">

<?php if ($debit > 0): ?>

৳<?php echo taka($debit); ?>

<?php else: ?>

-

<?php endif; ?>

</td>

<td class="cr">

<?php if ($credit > 0): ?>

৳<?php echo taka($credit); ?>

<?php else: ?>

-

<?php endif; ?>

</td>

<td>

<?php if ($debit > 0): ?>

<div class="balance-calc">

৳<?php echo taka($previous); ?>

+

৳<?php echo taka($debit); ?>

</div>

<?php elseif ($credit > 0): ?>

<div class="balance-calc">

৳<?php echo taka($previous); ?>

−

৳<?php echo taka($credit); ?>

</div>

<?php endif; ?>

<div class="balance-final">

= ৳<?php echo taka($running); ?>

</div>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="6"
    class="empty"
>
কোনো ট্রানজেকশন রেকর্ড পাওয়া যায়নি।
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>



<?php require_once __DIR__ . '/profile_installments.php'; ?>