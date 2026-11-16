<!-- profile_view.php: প্রোফাইল পেজের HTML শেল/হেডার/মূল কাস্টমার তথ্য (profile.php থেকে require হয়) -->
<!DOCTYPE html>

<html lang="bn">

<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Customer 360 -
<?php echo h($customer['name'] ?? 'Customer'); ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    padding: 15px;

    background: #f4f7f9;

    color: #333;

    font-family:
        "Segoe UI",
        Tahoma,
        Arial,
        sans-serif;
}

.container {

    max-width: 1200px;

    margin: auto;
}

.card {

    background: #fff;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.05);
}

.flex-between {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;
}

.profile {

    display: flex;

    align-items: center;

    gap: 15px;
}

.profile img {

    width: 70px;

    height: 70px;

    border-radius: 50%;

    object-fit: cover;

    border: 3px solid #0D47A1;
}

.profile h2 {

    margin: 0 0 5px;

    font-size: 20px;
}

.profile p {

    margin: 0 0 8px;

    font-size: 12px;

    color: #666;

    line-height: 1.6;
}

.role-badge {

    display: inline-block;

    padding: 4px 10px;

    margin-right: 5px;

    margin-bottom: 4px;

    border-radius: 20px;

    color: #fff;

    font-size: 11px;

    font-weight: bold;
}

.bg-cus {
    background: #0D47A1;
}

.bg-sup {
    background: #2E7D32;
}

.bg-ins {
    background: #4527A0;
}

.bg-ren {
    background: #D84315;
}

.ame-wholesale-price-badge {
    border: 2px solid #94a3b8;
    background: #f1f5f9;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: default;
    user-select: none;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}

.ame-wholesale-price-badge.is-on {
    border-color: #0284c7;
    background: linear-gradient(135deg, #0ea5e9, #0369a1);
    color: #fff;
    box-shadow: 0 2px 8px rgba(2, 132, 199, .35);
}

.ame-wholesale-price-badge:not(.is-readonly) {
    cursor: pointer;
}

.ame-wholesale-price-badge:not(.is-readonly):hover {
    filter: brightness(1.05);
}

.ame-wholesale-price-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-weight: 900;
    font-size: 12px;
    background: rgba(255, 255, 255, .22);
}

.ame-wholesale-price-badge:not(.is-on) .ame-wholesale-price-icon {
    background: #cbd5e1;
    color: #334155;
}

.bg-danger {
    background: #B71C1C;
}

.bank-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(230px, 1fr));

    gap: 15px;

    margin-bottom: 20px;
}

.bank-box {

    background: #fafafa;

    border: 1px solid #e0e0e0;

    border-left: 5px solid #0D47A1;

    border-radius: 10px;

    padding: 15px;
}

.bank-box h5 {

    margin: 0 0 5px;

    color: #666;

    font-size: 12px;
}

.amount {

    font-size: 20px;

    font-weight: 800;
}

.dr {
    color: #B71C1C !important;
}

.cr {
    color: #2E7D32 !important;
}

.sub {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}

.ai-score-box {

    min-width: 250px;

    padding: 15px 20px;

    border-radius: 12px;

    background: #0D47A1;

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.score-circle {

    width: 55px;

    height: 55px;

    border: 3px solid rgba(255,255,255,0.5);

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #fff;

    font-size: 18px;

    font-weight: 900;
}

.btn {

    border: 0;

    border-radius: 6px;

    padding: 8px 14px;

    color: #fff;

    text-decoration: none;

    cursor: pointer;

    font-size: 12px;

    font-weight: bold;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;
}

.btn-primary {
    background: #0D47A1;
}

.btn-edit {
    background: #E65100;
}

.btn-success {
    background: #2E7D32;
}

.btn-danger {
    background: #B71C1C;
}

.charge-selector {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    margin-bottom: 15px;
}

.charge-card {

    padding: 15px 8px;

    text-align: center;

    border: 2px solid #e0e0e0;

    border-radius: 10px;

    background: #fafafa;

    cursor: pointer;

    transition: .2s;
}

.charge-card:hover,
.charge-card.active {

    border-color: #0D47A1;

    background: #E3F2FD;
}

.charge-card .icon {

    font-size: 24px;
}

.charge-card .label {

    margin-top: 5px;

    font-size: 12px;

    font-weight: bold;
}

.charge-form-box {

    display: none;

    padding: 15px;

    margin-bottom: 20px;

    background: #f9f9f9;

    border: 1px solid #ddd;

    border-radius: 10px;
}

.charge-form-box.open {
    display: block;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(180px, 1fr));

    gap: 10px;

    margin-bottom: 10px;
}

.form-grid input,
.form-grid select {

    width: 100%;

    padding: 9px;

    border: 1px solid #ccc;

    border-radius: 6px;

    background: #fff;

    font-size: 13px;
}

.form-grid input:focus,
.form-grid select:focus {

    outline: none;

    border-color: #0D47A1;

    box-shadow:
        0 0 0 2px rgba(13,71,161,.1);
}

.form-submit {

    padding: 10px 18px;

    border: 0;

    border-radius: 6px;

    color: #fff;

    font-weight: bold;

    cursor: pointer;
}

.table-wrap {

    width: 100%;

    overflow-x: auto;
}

.ledger-table {

    width: 100%;

    min-width: 850px;

    border-collapse: collapse;

    font-size: 13px;
}

.ledger-table th {

    padding: 10px;

    color: #fff;

    background: #0D47A1;

    text-align: left;

    white-space: nowrap;
}

.ledger-table td {

    padding: 10px;

    border-bottom: 1px solid #eee;

    vertical-align: top;
}

.ledger-table tr:hover {

    background: #f9f9f9;
}

.balance-calc {

    color: #888;

    font-size: 11px;
}

.balance-final {

    margin-top: 2px;

    color: #111;

    font-size: 14px;

    font-weight: bold;
}

.empty {

    padding: 25px !important;

    color: #888;

    text-align: center;
}

/* ⭐ উপরের "কিস্তি"/"ভাড়া" ব্যাজে ক্লিক করলে এই ছোট বক্সে সেই ফিচারের
   তালিকা + এডিট বাটন দেখাবে (নিচে স্ক্রল না করেই)। */
.role-badge.ame-pf-badge-btn {
    border: none;
    cursor: pointer;
    font-family: inherit;
}
.ame-pf-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 16px;
}
.ame-pf-backdrop[hidden] {
    display: none;
}
.ame-pf-card {
    background: #fff; border-radius: 14px; padding: 20px;
    max-width: 420px; width: 100%; max-height: 80vh; overflow: auto;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}
.ame-pf-card h3 { margin: 0 0 6px; }
.ame-pf-list-row {
    display:flex; justify-content:space-between; align-items:center; gap:10px;
    padding:10px; border:1px solid #e5e9f0; border-radius:10px; margin-bottom:8px;
}

@media(max-width:768px) {

    body {
        padding: 8px;
    }

    .card {
        padding: 14px;
    }

    .profile {
        width: 100%;
    }

    .ai-score-box {
        width: 100%;

        min-width: 0;
    }

    .charge-selector {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

@media print {

    body {
        padding: 0;

        background: #fff;
    }

    .card {
        box-shadow: none;

        border: 1px solid #ddd;
    }

    .no-print {
        display: none !important;
    }

    .ledger-table {
        min-width: 0;

        font-size: 10px;
    }

}

</style>


</head>

<body>
<?php
$ame_profile_csrf = function_exists('csrf_token') ? csrf_token() : (string)($_SESSION['csrf_token'] ?? '');
?>
<script>window.AME_CSRF_TOKEN = <?php echo json_encode($ame_profile_csrf, JSON_UNESCAPED_UNICODE); ?>;</script>

<div class="container">


<!-- =====================================================
     NAVIGATION
====================================================== -->

<div
    class="no-print"
    style="
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        margin-bottom:15px;
    "
>

<a
    href="index.php"
    class="btn btn-primary"
>
    ⬅️ কাস্টমার ডিরেক্টরিতে ফিরে যান
</a>

<?php if (empty($is_purged)): ?>
<a
    href="edit_customer.php?id=<?php echo (int)$customer_id; ?>"
    class="btn btn-edit"
>
    ✏️ তথ্য সম্পাদনা করুন
</a>
<?php endif; ?>

<?php if ((int)($customer['is_customer'] ?? 1) === 1 && empty($is_purged)): ?>
<?php
$ws_on_top = !empty($always_wholesale);
$ws_title_top = $ws_on_top
    ? 'পাইকারি দাম চালু — বিক্রয়ে সব পণ্য পাইকারি রেটে (অ্যাডমিন বন্ধ না করা পর্যন্ত)'
    : 'পাইকারি দাম বন্ধ — অ্যাডমিন চালু করলে বিক্রয়ে সবসময় পাইকারি রেট';
?>
<button
    type="button"
    class="btn ame-wholesale-price-badge ame-wholesale-price-toggle<?php echo $ws_on_top ? ' is-on' : ''; ?><?php echo empty($can_toggle_wholesale_price) ? ' is-readonly' : ''; ?>"
    id="ameWholesalePriceToolbarBtn"
    title="<?php echo h($ws_title_top); ?>"
    data-customer-id="<?php echo (int)$customer_id; ?>"
    data-on="<?php echo $ws_on_top ? '1' : '0'; ?>"
    style="background:<?php echo $ws_on_top ? 'linear-gradient(135deg,#0ea5e9,#0369a1)' : '#e2e8f0'; ?>;color:<?php echo $ws_on_top ? '#fff' : '#334155'; ?>;font-weight:900;border:2px solid <?php echo $ws_on_top ? '#0284c7' : '#94a3b8'; ?>;"
>
    <span aria-hidden="true">P</span>
    <?php echo $ws_on_top ? 'সবসময় পাইকারি (চালু)' : 'পাইকারি দাম মোড'; ?>
</button>
<?php endif; ?>

<button
    type="button"
    onclick="window.print()"
    class="btn btn-success"
>
    🖨️ প্রিন্ট
</button>

<?php if (empty($is_purged)): ?>
<form method="post" action="actions/hide_customer.php" style="display:inline;" onsubmit="return confirm('চিরতরে মুছুন। তালিকা থেকে সরান। আগের বিক্রয়, পেমেন্ট, লেজার ও টাকা মুছবে না।');">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo (int)$customer_id; ?>">
    <button type="submit" class="btn btn-danger">চিরতরে মুছুন</button>
</form>
<?php endif; ?>

</div>

<?php if (!empty($is_purged)): ?>
<div class="card" style="border:1px solid #fecaca;background:#fff1f2;color:#7f1d1d;font-weight:700;">
    এই কাস্টমার তালিকা থেকে সরানো। ফেরত আনলে আবার তালিকায় ও বিক্রয়ে দেখা যাবে। বিক্রয়, পেমেন্ট ও টাকা বদলায় না।
    <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <form method="post" action="actions/restore_customer.php" style="display:inline;" onsubmit="return confirm('এই কাস্টমার আবার তালিকায় ফিরবে। টাকা বদলাবে না।');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$customer_id; ?>">
            <input type="hidden" name="back" value="profile">
            <button type="submit" class="btn btn-success">ফেরত আনুন</button>
        </form>
        <a href="ledger.php?id=<?php echo (int)$customer_id; ?>" style="color:#7f1d1d;">সম্পূর্ণ স্টেটমেন্ট দেখুন</a>
        <a href="index.php?role=deleted" style="color:#7f1d1d;">মুছে ফেলা তালিকা</a>
    </div>
</div>
<?php endif; ?>


<link rel="stylesheet" href="/assets/css/customer-sign.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
<div class="card ame-sign-card" id="customerSignView">
    <h3 style="margin:0 0 8px;color:#0b1e4d;">চুক্তির স্বাক্ষর</h3>
    <?php if (!empty($sign_src)): ?>
        <img src="/<?php echo h($sign_src); ?>" alt="গ্রাহকের স্বাক্ষর">
        <?php if (!empty($sign_when)): ?>
            <p style="margin:8px 0 0;font-weight:800;">সইয়ের সময় <?php echo h($sign_when); ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p style="margin:0;font-weight:700;">এখনও সই নেই। এডিট থেকে বোর্ডে আঙুল দিয়ে সই দেওয়া যায়।</p>
    <?php endif; ?>
    <?php if (empty($is_purged)): ?>
        <a href="edit_customer.php?id=<?php echo (int)$customer_id; ?>#customerSign">সই বদলান</a>
    <?php endif; ?>
</div>

<!-- =====================================================
     CUSTOMER PROFILE
====================================================== -->

<div class="card">

<div class="flex-between">

<div class="profile">

<img
    src="<?php echo h($photo_src); ?>"
    alt="Customer"
    onerror="
        this.onerror=null;
        this.src='https://via.placeholder.com/100x100.png?text=Customer';
    "
>

<div>

<h2>
<?php echo h($customer['name'] ?? 'N/A'); ?>
</h2>
<?php $profile_on = function_exists('ame_customer_status_enabled') ? ame_customer_status_enabled($customer['status'] ?? 1) : ((int)($customer['status'] ?? 1) === 1); ?>
<p style="margin:6px 0 0;font-weight:900;font-size:15px;color:<?php echo $profile_on ? '#047857' : '#b91c1c'; ?>;"><?php echo $profile_on ? 'এখন চালু (Enable)' : 'এখন বন্ধ (Disable)'; ?></p>

<p>

📞 <?php echo h($customer['phone'] ?? 'N/A'); ?>

|

🆔 NID:
<?php echo h($customer['nid'] ?? 'N/A'); ?>

|

কোড:
<strong>
<?php echo h($customer['customer_code'] ?? 'N/A'); ?>
</strong>

</p>

<div>

<?php if ((int)($customer['is_customer'] ?? 1) === 1): ?>

<span class="role-badge bg-cus">
ক্রেতা
</span>

<?php endif; ?>

<?php if (!empty($customer['is_supplier'])): ?>

<span class="role-badge bg-sup">
সাপ্লায়ার
</span>

<?php endif; ?>

<?php if (!empty($customer['is_installment'])): ?>

<button type="button" class="role-badge bg-ins ame-pf-badge-btn no-print" onclick="ameProfileFeatureClick('installment')">
কিস্তি
</button>

<?php endif; ?>

<?php if (!empty($customer['is_rental'])): ?>

<button type="button" class="role-badge bg-ren ame-pf-badge-btn no-print" onclick="ameProfileFeatureClick('rental')">
ভাড়া
</button>

<?php endif; ?>

<?php if ((int)($customer['is_customer'] ?? 1) === 1): ?>
<?php
$ws_on = !empty($always_wholesale);
$ws_title = $ws_on
    ? 'পাইকারি দাম চালু — বিক্রয় ডেস্কে সব পণ্য পাইকারি রেটে (অ্যাডমিন বন্ধ না করা পর্যন্ত)'
    : 'পাইকারি দাম বন্ধ — ক্লিক করে চালু করলে বিক্রয়ে সবসময় পাইকারি রেট';
?>
<span
    id="ameWholesalePriceBadge"
    class="role-badge ame-wholesale-price-badge ame-wholesale-price-toggle<?php echo $ws_on ? ' is-on' : ''; ?><?php echo empty($can_toggle_wholesale_price) ? ' is-readonly' : ''; ?> no-print"
    title="<?php echo h($ws_title); ?>"
    data-customer-id="<?php echo (int)$customer_id; ?>"
    data-on="<?php echo $ws_on ? '1' : '0'; ?>"
    <?php if (!empty($can_toggle_wholesale_price)): ?>role="button" tabindex="0"<?php endif; ?>
>
    <span class="ame-wholesale-price-icon" aria-hidden="true">P</span>
    <span class="ame-wholesale-price-text"><?php echo $ws_on ? 'সবসময় পাইকারি' : 'পাইকারি দাম'; ?></span>
</span>
<?php endif; ?>

</div>

</div>

</div>


<div class="ai-score-box">

<div>

<div
    style="
        font-size:10px;
        color:#B3E5FC;
    "
>
ক্রেডিট ঝুঁকি বিশ্লেষণ
</div>

<div
    style="
        font-size:15px;
        font-weight:bold;
        color:#fff;
    "
>
<?php echo h($risk_level); ?>
</div>

<small>
লিমিট:
৳<?php echo taka($credit_limit); ?>
</small>

</div>

<div class="score-circle">
<?php echo $risk_score; ?>
</div>

</div>

</div>

<?php if (!empty($customer['is_customer']) && !empty($customer['is_supplier'])): ?>
<div style="background:#E3F2FD; border:1px solid #90CAF9; border-radius:8px; padding:12px 15px; margin-top:15px; font-size:12px; color:#0D47A1; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
    <span>⚖️ এই ব্যক্তি একইসাথে <strong>ক্রেতা ও সাপ্লায়ার</strong> — উভয় দিকের লেনদেন ইতিমধ্যে একই ব্যালেন্সে স্বয়ংক্রিয়ভাবে মিলিত হচ্ছে (নিচের ব্যালেন্স নেট ফলাফল)।</span>
    <a href="netting.php?id=<?php echo (int)$customer_id; ?>" class="btn btn-primary no-print" style="white-space:nowrap;">📋 সেটেলমেন্ট স্টেটমেন্ট দেখুন</a>
</div>
<?php endif; ?>

</div>


<!-- =====================================================
     BALANCE
====================================================== -->

<?php $ame_customer_has_deposit = $net_balance < 0; ?>

<?php if ($ame_customer_has_deposit): ?>
<div class="no-print" style="margin-bottom:10px;">
    <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#2E7D32;cursor:pointer;">
        <input type="checkbox" id="ameShowDepositToggle" onchange="ameToggleDepositBox(this)">
        👁️ এই কাস্টমারের জমার তথ্য দেখান
    </label>
</div>
<?php endif; ?>

<div class="bank-grid">

<div
    class="bank-box"
    style="
        border-left-color:
        <?php echo $net_balance >= 0
            ? '#B71C1C'
            : '#2E7D32'; ?>;
    "
>

<?php if ($ame_customer_has_deposit): ?>

<!-- ⭐ নতুন: এই কাস্টমারের জমা থাকলে সেটা ডিফল্টভাবে লুকানো থাকে —
     উপরের চেকবক্স মার্ক করলেই দেখা যাবে। এটা শুধু এই পেজে দেখানোর
     ব্যাপার, ব্যালেন্স/লেজার হিসাব কোনোভাবেই বদলায় না। -->
<div id="ameDepositHiddenNotice">
<h5>🔒 জমার তথ্য লুকানো আছে</h5>
<div class="sub">দেখতে উপরের চেকবক্সে টিক দিন</div>
</div>

<div id="ameDepositRealInfo" style="display:none;">
<h5>দোকান কাস্টমারের কাছে ঋণী</h5>
<div class="amount cr">৳<?php echo taka(abs($net_balance)); ?></div>
<div class="sub">দোকান দেনাদার</div>
</div>

<?php else: ?>

<h5>কাস্টমার দোকানের কাছে ঋণী</h5>
<div class="amount dr">৳<?php echo taka(abs($net_balance)); ?></div>
<div class="sub">দোকান পাবে</div>

<?php endif; ?>

</div>


<div
    class="bank-box"
    style="border-left-color:#E65100;"
>

<h5>
ভাড়া জামানত ওয়ালেট
</h5>

<div
    class="amount"
    style="color:#E65100;"
>
৳<?php echo taka($deposit_held); ?>
</div>

<div class="sub">
কাস্টমার বকেয়ার অংশ নয় — আলাদা ডিপোজিট ব্যালেন্স
</div>

</div>

</div>

<?php if (!empty($hire_summary)): ?>
<div class="card no-print" style="margin-top:12px;">
<h4 style="margin:0 0 10px;">🤝 ভাড়া সারাংশ / Rental summary</h4>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
<div>চুক্তি<br><b><?php echo (int)$hire_summary['total']; ?></b></div>
<div>চলমান<br><b><?php echo (int)$hire_summary['active']; ?></b></div>
<div>সম্পন্ন<br><b><?php echo (int)$hire_summary['completed']; ?></b></div>
<div style="color:#DC2626;">মেয়াদোত্তীর্ণ<br><b><?php echo (int)$hire_summary['overdue']; ?></b></div>
<div>ভাড়া আয়<br><b>৳<?php echo number_format($hire_summary['income'], 2); ?></b></div>
<div>পরিশোধ<br><b>৳<?php echo number_format($hire_summary['paid'], 2); ?></b></div>
<div>ভাড়া বকেয়া<br><b>৳<?php echo number_format($hire_summary['due'], 2); ?></b></div>
<div style="color:#E65100;">জামানত<br><b>৳<?php echo number_format($hire_summary['deposit_balance'], 2); ?></b></div>
</div>
<p style="margin:10px 0 0;font-size:12px;color:#667085;">জমা ৳<?php echo number_format($hire_summary['deposit'], 2); ?> · ব্যবহার ৳<?php echo number_format($hire_summary['deposit_used'], 2); ?> · ফেরত ৳<?php echo number_format($hire_summary['deposit_refunded'], 2); ?> — জামানত খাতার বকেয়ায় মেশে না।</p>
<p style="margin:8px 0 0;">
<a href="/modules/customers/hire/index.php?customer_id=<?php echo (int)$customer_id; ?>" class="btn btn-primary">🏠 দৈনিক ভাড়া</a>
<a href="/modules/customers/hire/create.php?customer_id=<?php echo (int)$customer_id; ?>" class="btn">নতুন ভাড়া</a>
</p>
</div>
<?php endif; ?>
<?php if ($ame_customer_has_deposit): ?>
<script>
function ameToggleDepositBox(checkbox) {
    var hidden = document.getElementById('ameDepositHiddenNotice');
    var real = document.getElementById('ameDepositRealInfo');
    if (!hidden || !real) {
        return;
    }
    hidden.style.display = checkbox.checked ? 'none' : '';
    real.style.display = checkbox.checked ? '' : 'none';
}
</script>
<?php endif; ?>


<!-- ⭐ উপরের "কিস্তি"/"ভাড়া" ব্যাজে ক্লিক করলে এই বক্সে দেখাবে — নিচের
     profile_installments.php-এর সাথে হুবহু একই এডিট সিস্টেম পুনর্ব্যবহার
     করা হয়েছে (ameOpenRentalDepositEdit / ameOpenInstallmentEdit), শুধু
     উপরে থেকেই দ্রুত খোলার জন্য একটা তালিকা বক্স যোগ করা হলো। -->
<div id="ameProfileFeatureListBackdrop" class="ame-pf-backdrop" hidden>
    <div class="ame-pf-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 id="ameProfileFeatureListTitle" style="font-size:15px;"></h3>
            <span style="cursor:pointer;font-size:20px;color:#888;" onclick="ameProfileCloseFeatureList()">&times;</span>
        </div>
        <div id="ameProfileFeatureListBody"></div>
    </div>
</div>
<script>
var AME_PROFILE_CUSTOMER_ID = <?php echo (int)$customer_id; ?>;
var AME_PROFILE_RENTALS = <?php echo json_encode(array_map(static function ($r) {
    return [
        'id' => (int)($r['id'] ?? 0),
        'item_name' => (string)($r['item_name'] ?? ''),
        'rental_no' => (string)($r['rental_no'] ?? ''),
        'security_deposit' => (float)($r['security_deposit'] ?? 0),
        'status' => (string)($r['status'] ?? ''),
    ];
}, $rentals), JSON_UNESCAPED_UNICODE); ?>;
var AME_PROFILE_INSTALLMENTS = <?php echo json_encode(array_map(static function ($i) {
    return [
        'id' => (int)($i['id'] ?? 0),
        'contract_no' => (string)($i['contract_no'] ?? ''),
        'product_details' => (string)($i['product_details'] ?? ''),
        'product_condition' => (string)($i['product_condition'] ?? 'NEW'),
        'total_amount' => (float)($i['total_amount'] ?? 0),
        'down_payment' => (float)($i['down_payment'] ?? 0),
        'total_installments' => (int)($i['total_installments'] ?? 1),
        'installment_type' => (string)($i['installment_type'] ?? 'MONTHLY'),
        'remaining_balance' => (float)($i['remaining_balance'] ?? 0),
        'status' => (string)($i['status'] ?? ''),
    ];
}, $installments), JSON_UNESCAPED_UNICODE); ?>;

function ameProfileEscapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function ameProfileFeatureClick(type) {
    var list = (type === 'rental') ? AME_PROFILE_RENTALS : AME_PROFILE_INSTALLMENTS;
    document.getElementById('ameProfileFeatureListTitle').textContent =
        (type === 'rental' ? '🚜 ভাড়া নেওয়া পণ্যসমূহ' : '🏦 কিস্তিতে নেওয়া পণ্যসমূহ');
    var body = document.getElementById('ameProfileFeatureListBody');
    body.innerHTML = '';
    if (!list || !list.length) {
        body.innerHTML = '<p style="color:#888;">কোনো তথ্য পাওয়া যায়নি।</p>';
    } else {
        list.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'ame-pf-list-row';

            var info = document.createElement('div');
            if (type === 'rental') {
                info.innerHTML = '<strong>' + ameProfileEscapeHtml(item.item_name || '—') + '</strong>'
                    + '<div style="font-size:11px;color:#64748b;">' + ameProfileEscapeHtml(item.rental_no || '')
                    + ' · জামানত ৳' + item.security_deposit.toFixed(2)
                    + ' · ' + ameProfileEscapeHtml(item.status || '') + '</div>';
            } else {
                info.innerHTML = '<strong>' + ameProfileEscapeHtml(item.product_details || '—') + '</strong>'
                    + '<div style="font-size:11px;color:#64748b;">' + ameProfileEscapeHtml(item.contract_no || '')
                    + ' · অবশিষ্ট ৳' + item.remaining_balance.toFixed(2)
                    + ' · ' + ameProfileEscapeHtml(item.status || '') + '</div>';
            }

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn';
            btn.style.background = '#0D47A1';
            btn.textContent = '✏️ এডিট';
            btn.addEventListener('click', function () {
                ameProfileCloseFeatureList();
                if (type === 'rental') {
                    ameOpenRentalDepositEdit(item.id, AME_PROFILE_CUSTOMER_ID, item.security_deposit, item.item_name || '');
                } else {
                    ameOpenInstallmentEdit(item.id, AME_PROFILE_CUSTOMER_ID, item);
                }
            });

            row.appendChild(info);
            row.appendChild(btn);
            body.appendChild(row);
        });
    }
    document.getElementById('ameProfileFeatureListBackdrop').hidden = false;
}

function ameProfileCloseFeatureList() {
    document.getElementById('ameProfileFeatureListBackdrop').hidden = true;
}

(function () {
    var toggles = Array.prototype.slice.call(document.querySelectorAll('.ame-wholesale-price-toggle:not(.is-readonly)'));
    var badges = Array.prototype.slice.call(document.querySelectorAll('.ame-wholesale-price-badge'));
    if (!toggles.length) {
        return;
    }
    var token = window.AME_CSRF_TOKEN
        || (document.querySelector('input[name="csrf_token"]') || {}).value
        || (document.querySelector('meta[name="csrf-token"]') || {}).content
        || '';
    var busy = false;

    function paint(on, msg) {
        badges.forEach(function (badge) {
            badge.classList.toggle('is-on', !!on);
            badge.dataset.on = on ? '1' : '0';
            badge.title = on
                ? 'পাইকারি দাম চালু — বিক্রয় ডেস্কে সব পণ্য পাইকারি রেটে (অ্যাডমিন বন্ধ না করা পর্যন্ত)'
                : 'পাইকারি দাম বন্ধ — ক্লিক করে চালু করলে বিক্রয়ে সবসময় পাইকারি রেট';
            var text = badge.querySelector('.ame-wholesale-price-text');
            if (text) {
                text.textContent = on ? 'সবসময় পাইকারি' : 'পাইকারি দাম';
            }
            if (badge.id === 'ameWholesalePriceToolbarBtn') {
                badge.style.background = on ? 'linear-gradient(135deg,#0ea5e9,#0369a1)' : '#e2e8f0';
                badge.style.color = on ? '#fff' : '#334155';
                badge.style.borderColor = on ? '#0284c7' : '#94a3b8';
                badge.innerHTML = '<span aria-hidden="true">P</span> ' + (on ? 'সবসময় পাইকারি (চালু)' : 'পাইকারি দাম মোড');
            }
        });
        if (msg) {
            window.alert(msg);
        }
    }

    function toggleFrom(el) {
        if (busy) {
            return;
        }
        var next = el.dataset.on !== '1';
        var ok = window.confirm(
            next
                ? 'এই কাস্টমারের জন্য «সবসময় পাইকারি» চালু করবেন?\n\nবিক্রয় ডেস্কে এই কাস্টমার নিলে সব পণ্য পাইকারি দামে বিক্রি হবে। কোনো টাকা/ব্যালেন্স বদলাবে না।'
                : '«সবসময় পাইকারি» বন্ধ করবেন?\n\nআবার সাধারণ POS দাম মোড অনুযায়ী বিক্রি হবে।'
        );
        if (!ok) {
            return;
        }
        busy = true;
        var body = new URLSearchParams();
        body.set('id', el.dataset.customerId || toggles[0].dataset.customerId || '');
        body.set('on', next ? '1' : '0');
        if (token) {
            body.set('csrf_token', token);
        }
        fetch('actions/toggle_always_wholesale.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': token
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.ok) {
                    if (data && data.csrf_refresh) {
                        window.AME_CSRF_TOKEN = data.csrf_refresh;
                    }
                    throw new Error((data && data.message) ? data.message : 'সংরক্ষণ ব্যর্থ');
                }
                paint(!!data.always_wholesale, data.message || '');
            });
        }).catch(function (err) {
            window.alert(err.message || 'সংরক্ষণ ব্যর্থ হয়েছে।');
        }).finally(function () {
            busy = false;
        });
    }

    function readCsrfFromPage() {
        return window.AME_CSRF_TOKEN
            || (document.querySelector('input[name="csrf_token"]') || {}).value
            || '';
    }

    toggles.forEach(function (el) {
        el.addEventListener('click', function () {
            token = readCsrfFromPage();
            toggleFrom(el);
        });
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                token = readCsrfFromPage();
                toggleFrom(el);
            }
        });
    });
})();
</script>

<?php
require_once __DIR__ . '/profile_transaction_form.php';
if (function_exists('ame_party_purge_assets')) {
    ame_party_purge_assets();
}
?>
