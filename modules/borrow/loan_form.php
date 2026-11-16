<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
include '../../partials/header.php';
?>
<style>
.bf { max-width:880px; margin:0 auto; padding:12px 12px 40px; }
.bf form { display:grid; grid-template-columns:1fr 1fr; gap:10px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px; }
.bf label { display:block; font-size:12px; font-weight:700; color:#475569; }
.bf input, .bf select, .bf textarea { width:100%; box-sizing:border-box; padding:10px; border:1px solid #dbe4f0; border-radius:10px; }
.bf .wide { grid-column:1 / -1; }
.bf button { background:#0b1e4d; color:#fff; border:0; border-radius:10px; padding:12px; font-weight:800; }
@media (max-width:700px) { .bf form { grid-template-columns:1fr; } }
</style>
<div class="bf">
    <h1>নতুন ব্যবসায়িক ধার</h1>
    <p>ব্যাংক বা এনজিওর কিস্তি মূল টাকা কমায়। ব্যক্তির মাসিক ফি মূল টাকা কমায় না। মূল ফেরত আলাদা।</p>
    <form method="post" action="process_loan.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div><label>উৎসের নাম</label><input name="provider_name" required></div>
        <div><label>ধরন</label>
            <select name="provider_type" id="ptype" onchange="bfStyle()">
                <option value="bank">Bank</option>
                <option value="ngo">NGO</option>
                <option value="financial">Financial institution</option>
                <option value="individual">ব্যক্তি</option>
            </select>
        </div>
        <div><label>শাখা / ঠিকানা</label><input name="branch"></div>
        <div><label>ফোন</label><input name="phone"></div>
        <div><label>অ্যাকাউন্ট / লোন নম্বর</label><input name="account_no"></div>
        <div><label>রেফারেন্স</label><input name="loan_ref"></div>
        <div><label>যোগাযোগের ব্যক্তি</label><input name="contact_person"></div>
        <div><label>চুক্তির তারিখ</label><input type="date" name="agreement_date" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label>শুরু</label><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label>শেষ</label><input type="date" name="maturity_date"></div>
        <div><label>মূল টাকা</label><input type="number" step="0.01" name="principal" required></div>
        <div><label>সুদের হার % (ব্যাংক/এনজিও)</label><input type="number" step="0.01" name="interest_rate" value="0"></div>
        <div><label>প্রসেসিং ফি</label><input type="number" step="0.01" name="processing_fee" value="0"></div>
        <div><label>কিস্তির ধরন</label>
            <select name="frequency"><option value="monthly">মাসিক</option><option value="weekly">সাপ্তাহিক</option></select>
        </div>
        <div><label>কিস্তি সংখ্যা</label><input type="number" name="installment_count" value="12"></div>
        <div><label>ব্যক্তির মাসিক ফি</label><input type="number" step="0.01" name="monthly_fee" value="0"></div>
        <div><label>প্রথম কিস্তি</label><input type="date" name="first_payment_date" value="<?php echo date('Y-m-d'); ?>"></div>
        <div><label>জামানত</label><input type="number" step="0.01" name="security_amount" value="0"></div>
        <div class="wide"><label>নোট</label><textarea name="notes" rows="2"></textarea></div>
        <div><label>টাকা জমা হবে যে অ্যাকাউন্টে</label>
            <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required>
                <?php
                echo ame_render_account_options(ame_active_accounts($db));
                ?>
            </select>
        </div>
        <div class="wide"><button type="submit">ধার নিন</button></div>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>
