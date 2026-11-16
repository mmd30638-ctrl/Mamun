<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);
include '../../partials/header.php';

$current_threshold = get_app_setting($db, 'transfer_approval_threshold', '');
$pin_is_set = get_app_setting($db, 'transfer_approval_pin_hash', '') !== '';
?>
<div class="card" style="margin:15px;">
    <h3 style="color:var(--dark-blue); border-bottom: 2px solid var(--dark-blue); padding-bottom: 10px;">🔐 হাই-ভ্যালু ট্রান্সফার অনুমোদন সেটিংস</h3>

    <div style="background:#f4f7f9; padding:10px; border-radius:6px; font-size:12px; color:#555; margin-bottom:15px;">
        নির্দিষ্ট অঙ্কের বেশি টাকা ট্রান্সফার করতে গেলে এই PIN চাওয়া হবে — ভুল ব্যক্তির হাতে বড় অঙ্কের ট্রান্সফার ঠেকাতে।
        বর্তমানে PIN <?php echo $pin_is_set ? '<strong style="color:#2E7D32;">সেট করা আছে</strong>' : '<strong style="color:#B71C1C;">সেট করা নেই (অনুমোদন বন্ধ)</strong>'; ?>।
    </div>

    <form action="actions/process_approval_settings.php" method="POST">
        <label>থ্রেশহোল্ড অঙ্ক (৳) — এর বেশি হলে PIN লাগবে</label>
        <input type="number" step="0.01" name="threshold" value="<?php echo htmlspecialchars($current_threshold); ?>" placeholder="যেমন: ৫০০০০" class="input-style" style="width:100%; padding:10px; margin:10px 0;">

        <label>নতুন PIN (৪-৬ ডিজিট) — খালি রাখলে PIN অপরিবর্তিত থাকবে</label>
        <input type="password" name="pin" placeholder="নতুন PIN দিন" pattern="[0-9]{4,6}" class="input-style" style="width:100%; padding:10px; margin:10px 0;">

        <label>PIN আবার লিখুন</label>
        <input type="password" name="pin_confirm" placeholder="PIN আবার লিখুন" pattern="[0-9]{4,6}" class="input-style" style="width:100%; padding:10px; margin:10px 0;">

        <button type="submit" class="btn-success" style="width:100%; padding:15px; margin-top:10px;">সংরক্ষণ করুন</button>
        <a href="list.php" style="display:block; text-align:center; margin-top:15px; color: var(--dark-blue); text-decoration:none;">ফিরে যান</a>
    </form>
</div>
