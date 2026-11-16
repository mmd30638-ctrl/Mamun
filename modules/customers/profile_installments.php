<!-- profile_installments.php: কিস্তি চুক্তির তালিকা + পেজ-শেষের JS (profile_transaction_form.php থেকে require হয়) -->
<!-- =====================================================
     INSTALLMENTS
====================================================== -->

<?php if (count($installments) > 0): ?>

<div class="card">

<h4>
🏦 কিস্তি চুক্তির তালিকা
</h4>

<div class="table-wrap">

<table class="ledger-table">

<thead>

<tr>

<th>কন্ট্রাক্ট নং</th>

<th>অবস্থা</th>

<th>পণ্যের বিবরণ</th>

<th>মোট মূল্য</th>

<th>অবশিষ্ট বকেয়া</th>

<th>স্ট্যাটাস</th>

</tr>

</thead>

<tbody>

<?php foreach ($installments as $ins): ?>

<tr
    class="ame-inst-row"
    style="cursor:pointer;"
    onclick="ameOpenInstallmentEdit(<?php echo (int)($ins['id'] ?? 0); ?>, <?php echo (int)$customer_id; ?>, JSON.parse(this.getAttribute('data-ins')))"
    data-ins="<?php echo htmlspecialchars(json_encode($ins, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
>

<td>

<strong>
<?php
echo h(
    $ins['contract_no'] ?? '—'
);
?>
</strong>

</td>

<td>
<?php
echo h(
    $ins['product_condition']
    ?? '—'
);
?>
</td>

<td>
<?php
echo h(
    $ins['product_details']
    ?? '—'
);
?>
</td>

<td>

৳<?php
echo taka(
    $ins['total_amount'] ?? 0
);
?>

</td>

<td class="dr">

৳<?php
echo taka(
    $ins['remaining_balance'] ?? 0
);
?>

</td>

<td>

<span class="role-badge bg-ins">

<?php
echo h(
    $ins['status'] ?? 'UNKNOWN'
);
?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<!-- ⭐ নতুন: কন্ট্রাক্টে ক্লিক করলে পুরো ডিটেলস + এডিট বক্স খুলবে। মোট মূল্য বা
     ডাউন পেমেন্ট বদলালে সিস্টেম নতুন "অবশিষ্ট বকেয়া" হিসাব করে পুরনো ও নতুন
     বকেয়ার পার্থক্যটা কাস্টমারের লেজারে (বাকি/জমা) সমন্বয় করে দেবে। -->
<style>
/* ⭐ বাগ-ফিক্স: এই CSS আগে শুধু "ভাড়া চুক্তির তালিকা" অংশে ছিল (নিচে), তাই যে
   কাস্টমারের ভাড়া চুক্তি নেই কিন্তু কিস্তি চুক্তি আছে, তার এই এডিট বক্সটা কোনো
   স্টাইলিং ছাড়াই পেজের মাঝে সাধারণ লেখা হিসেবে "খোলা" অবস্থায় আটকে থাকতো
   (পপ-আপ/ওভারলে না হয়ে)। এখন এই CSS এখানেও যোগ করা হলো যাতে সবসময় কাজ করে। */
.ame-rdep-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 16px;
}
/* ⭐ বাগ-ফিক্স: শুধু ".ame-rdep-backdrop { display:flex }" লিখলে HTML-এর
   "hidden" অ্যাট্রিবিউট (যেটার নিজস্ব display:none নিয়ম আছে) হেরে যায় —
   কারণ দুটোরই CSS specificity সমান, তাই যেটা পরে লেখা থাকে সেটাই জেতে।
   ফলে বক্সটা পেজ লোড হওয়ার সাথে সাথেই "খোলা" দেখাচ্ছিল। এই নিয়মটা
   [hidden]-কে অগ্রাধিকার দিয়ে এই বাগ ঠিক করে দিলো। */
.ame-rdep-backdrop[hidden] {
    display: none;
}
.ame-rdep-card {
    background: #fff; border-radius: 14px; padding: 20px;
    max-width: 380px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}
.ame-rdep-card h3 { margin: 0 0 6px; }
</style>
<div id="ameInstEditBackdrop" class="ame-rdep-backdrop" hidden>
    <div class="ame-rdep-card" style="max-width:440px;">
        <h3>✏️ কিস্তি চুক্তির বিস্তারিত</h3>
        <p id="ameInstContractLabel" style="color:#546E7A;font-size:13px;margin:0 0 10px;"></p>
        <form action="actions/process_installment_edit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="installment_id" id="ameInstId">
            <input type="hidden" name="customer_id" id="ameInstCustomerId">

            <label style="font-weight:800;display:block;margin:8px 0 4px;">পণ্যের বিবরণ</label>
            <input type="text" name="product_details" id="ameInstProductDetails" maxlength="500" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">

            <label style="font-weight:800;display:block;margin:8px 0 4px;">পণ্যের অবস্থা</label>
            <select name="product_condition" id="ameInstCondition" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                <option value="NEW">নতুন পণ্য</option>
                <option value="OLD_REFURBISHED">পুরাতন/এক্সচেঞ্জ</option>
            </select>

            <div style="display:flex;gap:8px;">
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">মোট মূল্য (৳)</label>
                    <input type="number" step="0.01" min="0.01" name="total_amount" id="ameInstTotal" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">ডাউন পেমেন্ট (৳)</label>
                    <input type="number" step="0.01" min="0" name="down_payment" id="ameInstDown" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">মোট কিস্তি সংখ্যা</label>
                    <input type="number" min="1" name="total_installments" id="ameInstCount" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">কিস্তির ধরন</label>
                    <select name="installment_type" id="ameInstType" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                        <option value="MONTHLY">মাসিক</option>
                        <option value="WEEKLY">সাপ্তাহিক</option>
                        <option value="DAILY">দৈনিক</option>
                    </select>
                </div>
            </div>

            <p id="ameInstPreview" style="background:#F0F7FF;border-radius:8px;padding:8px;font-size:13px;font-weight:700;color:#0D47A1;margin-top:10px;"></p>

            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="submit" class="btn" style="background:#2E7D32;flex:1;">💾 সংরক্ষণ করুন</button>
                <button type="button" class="btn" style="background:#757575;flex:1;" onclick="ameCloseInstallmentEdit()">বাতিল</button>
            </div>
        </form>
    </div>
</div>
<script>
function ameOpenInstallmentEdit(instId, customerId, data) {
    document.getElementById('ameInstId').value = instId;
    document.getElementById('ameInstCustomerId').value = customerId;
    document.getElementById('ameInstContractLabel').textContent = 'কন্ট্রাক্ট: ' + (data.contract_no || '—') + ' — বর্তমান অবশিষ্ট বকেয়া ৳' + (parseFloat(data.remaining_balance || 0)).toFixed(2);
    document.getElementById('ameInstProductDetails').value = data.product_details || '';
    document.getElementById('ameInstCondition').value = data.product_condition || 'NEW';
    document.getElementById('ameInstTotal').value = data.total_amount || 0;
    document.getElementById('ameInstDown').value = data.down_payment || 0;
    document.getElementById('ameInstCount').value = data.total_installments || 1;
    document.getElementById('ameInstType').value = data.installment_type || 'MONTHLY';
    ameUpdateInstPreview();
    document.getElementById('ameInstEditBackdrop').hidden = false;
}
function ameCloseInstallmentEdit() {
    document.getElementById('ameInstEditBackdrop').hidden = true;
}
function ameUpdateInstPreview() {
    const total = parseFloat(document.getElementById('ameInstTotal').value || 0);
    const down = parseFloat(document.getElementById('ameInstDown').value || 0);
    const remaining = Math.max(0, total - down);
    document.getElementById('ameInstPreview').textContent = 'নতুন অবশিষ্ট বকেয়া হবে: ৳' + remaining.toFixed(2);
}
(function () {
    const total = document.getElementById('ameInstTotal');
    const down = document.getElementById('ameInstDown');
    if (total) total.addEventListener('input', ameUpdateInstPreview);
    if (down) down.addEventListener('input', ameUpdateInstPreview);
})();
</script>

<?php endif; ?>


<!-- =====================================================
     RENTALS
====================================================== -->

<?php if (count($rentals) > 0): ?>

<div class="card">

<div class="flex-between">

<h4 style="margin:0;">
🚜 ভাড়া চুক্তির তালিকা
</h4>

<span style="font-size:11px;color:#777;">
মোট:
<?php echo count($rentals); ?>
টি
</span>

</div>

<div class="table-wrap">

<table class="ledger-table">

<thead>

<tr>

<th>রেন্টাল নং</th>

<th>আইটেমের নাম</th>

<th>মালিক / সোর্স</th>

<th>জামানত</th>

<th>ভাড়া রেট</th>

<th>শেষ চার্জের তারিখ</th>

<th>স্ট্যাটাস</th>

<th class="no-print">অ্যাকশন</th>

</tr>

</thead>

<tbody>

<?php foreach ($rentals as $r): ?>

<?php

$rental_id =
    (int)($r['id'] ?? 0);

$status =
    strtoupper(
        trim(
            (string)(
                $r['status'] ?? ''
            )
        )
    );

$is_active =
    ($status === 'ACTIVE');

$new_status =
    $is_active
        ? 'COMPLETED'
        : 'ACTIVE';

$button_text =
    $is_active
        ? '⏸️ নিষ্ক্রিয় করুন'
        : '▶️ সক্রিয় করুন';

$button_color =
    $is_active
        ? '#B71C1C'
        : '#2E7D32';

$badge =
    $is_active
        ? 'bg-ren'
        : 'bg-sup';

?>

<tr>

<td>

<strong>
<?php
echo h(
    $r['rental_no'] ?? '—'
);
?>
</strong>

</td>

<td>

<?php
echo h(
    $r['item_name'] ?? '—'
);
?>

</td>

<td>
<?php
$oid = (int)($r['owner_id'] ?? 0);
if ($oid > 0 && function_exists('rental_owner')) {
    $oo = rental_owner($db, $oid);
    if ($oo) {
        echo '<a href="/modules/rental/statement.php?id=' . $oid . '">' . h($oo['name']) . '</a>';
        $plate = trim((string)($r['vehicle_no'] ?? $oo['vehicle_no'] ?? ''));
        if ($plate !== '') {
            echo '<div style="font-size:11px;color:#777;">গাড়ি ' . h($plate) . '</div>';
        }
        echo '<div style="font-size:11px;color:#777;">মালিক ' . h(number_format((float)($r['owner_rate'] ?? 0), 2)) . ' / ' . h($r['owner_cycle'] ?? '') . '</div>';
    } else {
        echo '—';
    }
} else {
    echo 'নিজের';
}
?>
</td>

<td>

৳<?php
echo taka(
    $r['security_deposit'] ?? 0
);
?>

</td>

<td>

৳<?php
echo taka(
    $r['rent_rate'] ?? 0
);
?>

/

<?php
echo h(
    $r['rate_type'] ?? '—'
);
?>

</td>

<td>

<?php
echo h(
    $r['last_charged_date']
    ?? '—'
);
?>

</td>

<td>

<span class="role-badge <?php echo h($badge); ?>">

<?php
echo h(
    $r['status'] ?? 'UNKNOWN'
);
?>

</span>

</td>

<td class="no-print">

<?php if ($rental_id > 0): ?>

<form
    action="actions/toggle_rental_status.php"
    method="POST"
    style="margin:0;"
    onsubmit="return confirmRental();"
>

<input
    type="hidden"
    name="rental_id"
    value="<?php echo $rental_id; ?>"
>

<input
    type="hidden"
    name="customer_id"
    value="<?php echo (int)$customer_id; ?>"
>

<input
    type="hidden"
    name="new_status"
    value="<?php echo h($new_status); ?>"
>

<button
    type="submit"
    class="btn"
    style="
        background:<?php echo h($button_color); ?>;
    "
>
<?php echo h($button_text); ?>
</button>

</form>

<button
    type="button"
    class="btn"
    style="background:#0D47A1;margin-top:6px;"
    onclick="ameOpenRentalDepositEdit(<?php echo $rental_id; ?>, <?php echo (int)$customer_id; ?>, <?php echo (float)($r['security_deposit'] ?? 0); ?>, '<?php echo h(addslashes((string)($r['item_name'] ?? ''))); ?>')"
>
✏️ জামানত এডিট
</button>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<!-- ⭐ নতুন: জামানতের পরিমাণ ভুল হলে এখান থেকে ঠিক করার ফর্ম। নতুন পরিমাণ
     বসালে সিস্টেম নিজে থেকেই পুরনো ও নতুন জামানতের পার্থক্যটা কাস্টমারের
     লেজারে (বাকি/জমা) সমন্বয় করে দেবে — অন্য কোনো হিসাব ছোঁয় না। -->
<div id="ameRentalDepositEditBackdrop" class="ame-rdep-backdrop" hidden>
    <div class="ame-rdep-card">
        <h3>✏️ জামানত এডিট করুন</h3>
        <p id="ameRentalDepositItemLabel" style="color:#546E7A;font-size:13px;margin:0 0 10px;"></p>
        <form action="actions/process_rental_deposit_edit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="rental_id" id="ameRentalDepositRentalId">
            <input type="hidden" name="customer_id" id="ameRentalDepositCustomerId">
            <label style="font-weight:800;display:block;margin-bottom:6px;">নতুন জামানতের পরিমাণ (৳)</label>
            <input type="number" step="0.01" min="0" name="new_deposit" id="ameRentalDepositAmountInput" required style="width:100%;padding:10px;border-radius:8px;border:1.5px solid #90CAF9;font-size:16px;font-weight:700;">
            <div style="display:flex;gap:10px;margin-top:14px;">
                <button type="submit" class="btn" style="background:#2E7D32;flex:1;">💾 সংরক্ষণ করুন</button>
                <button type="button" class="btn" style="background:#757575;flex:1;" onclick="ameCloseRentalDepositEdit()">বাতিল</button>
            </div>
        </form>
    </div>
</div>
<style>
.ame-rdep-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; padding: 16px;
}
.ame-rdep-backdrop[hidden] {
    display: none;
}
.ame-rdep-card {
    background: #fff; border-radius: 14px; padding: 20px;
    max-width: 380px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}
.ame-rdep-card h3 { margin: 0 0 6px; }
</style>
<script>
function ameOpenRentalDepositEdit(rentalId, customerId, currentAmount, itemName) {
    document.getElementById('ameRentalDepositRentalId').value = rentalId;
    document.getElementById('ameRentalDepositCustomerId').value = customerId;
    document.getElementById('ameRentalDepositAmountInput').value = currentAmount;
    document.getElementById('ameRentalDepositItemLabel').textContent = itemName ? ('আইটেম: ' + itemName) : '';
    document.getElementById('ameRentalDepositEditBackdrop').hidden = false;
}
function ameCloseRentalDepositEdit() {
    document.getElementById('ameRentalDepositEditBackdrop').hidden = true;
}
</script>

</div>

<?php else: ?>

<div class="card">

<h4>
🚜 ভাড়া চুক্তির তালিকা
</h4>

<div class="empty">
এই কাস্টমারের কোনো ভাড়া চুক্তি নেই।
</div>

</div>

<?php endif; ?>


</div>


<script>

/* =========================================================
   FORM TOGGLE
========================================================= */

function toggleChargeForm(type, card) {

    const forms = {
        credit: 'form-credit',
        installment: 'form-installment',
        rental: 'form-rental'
    };

    const targetId = forms[type];

    if (!targetId) {
        return;
    }

    const target =
        document.getElementById(targetId);

    if (!target) {
        return;
    }

    const alreadyOpen =
        target.classList.contains('open');

    document
        .querySelectorAll('.charge-form-box')
        .forEach(function(box) {

            box.classList.remove('open');

        });

    document
        .querySelectorAll('.charge-card')
        .forEach(function(item) {

            item.classList.remove('active');

        });

    if (!alreadyOpen) {

        target.classList.add('open');

        if (card) {
            card.classList.add('active');
        }

    }

}


/* =========================================================
   CREDIT: অগ্রিম বক্স টগল
========================================================= */

function ameToggleCreditAdvanceBox(checkbox) {

    const box = document.getElementById('creditAdvanceBox');

    if (!box) {
        return;
    }

    box.style.display = checkbox.checked ? 'block' : 'none';

    if (!checkbox.checked) {
        const amt = document.getElementById('creditAdvanceAmount');
        const acc = document.getElementById('creditAdvanceAccount');
        if (amt) { amt.value = ''; }
        if (acc) { acc.value = '0'; }
    }
}


/* =========================================================
   CREDIT VALIDATION
========================================================= */

function validateCredit(form) {

    const amount =
        parseFloat(
            form.amount.value
        );

    if (!Number.isFinite(amount) || amount <= 0) {

        alert(
            'বিক্রয় মূল্য ০-এর বেশি হতে হবে।'
        );

        form.amount.focus();

        return false;
    }

    if (form.has_advance && form.has_advance.checked) {

        const advance = parseFloat(form.advance_amount.value);

        if (!Number.isFinite(advance) || advance <= 0) {
            alert('অগ্রিম টাকার সঠিক পরিমাণ দিন, অথবা "অগ্রিম দিয়েছে" আনচেক করুন।');
            form.advance_amount.focus();
            return false;
        }

        const accountId = parseInt(form.advance_account_id.value, 10);

        if (!accountId) {
            alert('অগ্রিম টাকা কোন অ্যাকাউন্টে (ক্যাশ/ব্যাংক) জমা হবে সেটা বাছাই করুন।');
            form.advance_account_id.focus();
            return false;
        }
    }

    return true;
}


/* =========================================================
   INSTALLMENT VALIDATION
========================================================= */

function validateInstallment(form) {

    const total =
        parseFloat(
            form.total_amount.value
        );

    const down =
        parseFloat(
            form.down_payment.value || 0
        );

    const count =
        parseInt(
            form.total_installments.value,
            10
        );

    if (!Number.isFinite(total) || total <= 0) {

        alert(
            'মোট মূল্য সঠিকভাবে দিন।'
        );

        form.total_amount.focus();

        return false;
    }

    if (!Number.isFinite(down) || down < 0) {

        alert(
            'ডাউন পেমেন্ট সঠিকভাবে দিন।'
        );

        form.down_payment.focus();

        return false;
    }

    if (down > total) {

        alert(
            'ডাউন পেমেন্ট মোট মূল্যের চেয়ে বেশি হতে পারবে না।'
        );

        form.down_payment.focus();

        return false;
    }

    if (!Number.isInteger(count) || count < 1) {

        alert(
            'কিস্তির সংখ্যা কমপক্ষে ১ হতে হবে।'
        );

        form.total_installments.focus();

        return false;
    }

    return true;
}


/* =========================================================
   RENTAL VALIDATION
========================================================= */

function validateRental(form) {

    const deposit =
        parseFloat(
            form.security_deposit.value || 0
        );

    const rate =
        parseFloat(
            form.rent_rate.value
        );

    if (!Number.isFinite(deposit) || deposit < 0) {

        alert(
            'সিকিউরিটি জামানত সঠিকভাবে দিন।'
        );

        form.security_deposit.focus();

        return false;
    }

    if (!Number.isFinite(rate) || rate <= 0) {

        alert(
            'ভাড়ার হার ০-এর বেশি হতে হবে।'
        );

        form.rent_rate.focus();

        return false;
    }

    return true;
}


/* =========================================================
   RENTAL STATUS CONFIRMATION
========================================================= */

function confirmRental() {

    return confirm(
        'আপনি কি এই ভাড়া চুক্তির স্ট্যাটাস পরিবর্তন করতে চান?'
    );

}

</script>

</body>
</html>
