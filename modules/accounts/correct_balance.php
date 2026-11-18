<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);
include '../../partials/header.php';

$accounts = ame_active_accounts($db);
?>
<div class="card" style="margin:15px;">
    <h3 style="color: var(--dark-blue); border-bottom: 2px solid var(--dark-blue); padding-bottom: 10px;">🛠️ ব্যালেন্স সংশোধন (Correction Entry)</h3>

    <div style="background:#FFF3E0; border:1px solid #ffe0b2; padding:10px; border-radius:6px; font-size:12px; color:#E65100; margin-bottom:15px;">
        সাধারণ লেনদেনের বদলে শুধু তখনই এটা ব্যবহার করুন যখন সিস্টেমের হিসাব আর হাতে-গোনা/ব্যাংক স্টেটমেন্টের প্রকৃত ব্যালেন্সে গরমিল থাকে। এটা একটা "Correction" নামে লেজার এন্ট্রি তৈরি করবে, অডিট ট্রেইলে স্পষ্ট থাকবে।
    </div>

    <form action="actions/process_correct_balance.php" method="POST">
        <label>অ্যাকাউন্ট নির্বাচন করুন</label>
        <select name="account_id" id="accSelect" required class="ame-account-select input-style" style="width:100%; padding:10px; margin:10px 0;" onchange="showCurrentBalance()" data-ame-account-select="1" data-ame-direction="pay">
            <option value="">-- অ্যাকাউন্ট বেছে নিন --</option>
            <?php foreach ($accounts as $a): ?>
            <option value="<?php echo (int)$a['id']; ?>" data-balance="<?php echo ame_balance_number_masked($a['balance']); ?>"><?php echo htmlspecialchars($a['account_name']); ?></option>
            <?php endforeach; ?>
        </select>

        <div id="currentBalanceHint" style="font-size:13px; color:#666; margin-bottom:10px;"></div>

        <label>প্রকৃত/সঠিক ব্যালেন্স (৳)</label>
        <input type="number" step="0.01" name="correct_balance" required class="input-style" style="width:100%; padding:10px; margin:10px 0;" placeholder="হাতে গণনা/ব্যাংক স্টেটমেন্ট অনুযায়ী প্রকৃত ব্যালেন্স">

        <label>সংশোধনের কারণ (নোট)</label>
        <input type="text" name="note" required class="input-style" style="width:100%; padding:10px; margin:10px 0;" placeholder="যেমন: হাতে গণনা করে সমন্বয় করা হলো">

        <button type="submit" class="btn-success" style="width:100%; padding:15px; margin-top:10px; background:#455A64;">✅ সংশোধন নিশ্চিত করুন</button>
        <a href="../list.php" style="display:block; text-align:center; margin-top:15px; color: var(--dark-blue); text-decoration:none;">ফিরে যান</a>
    </form>
</div>

<script>
function showCurrentBalance() {
    const sel = document.getElementById('accSelect');
    const opt = sel.options[sel.selectedIndex];
    const hint = document.getElementById('currentBalanceHint');
    // ⭐ ব্যালেন্স সংখ্যা এখানে দেখানো হয় না (মালিকের অনুরোধে) — শুধুমাত্র
    // modules/accounts/list.php পেজেই আসল ব্যালেন্স দেখা যাবে।
    if (opt && opt.value) {
        hint.innerText = 'বর্তমান সিস্টেম ব্যালেন্স: ৳••••';
    } else {
        hint.innerText = '';
    }
}
</script>
