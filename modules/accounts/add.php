<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);
include '../../partials/header.php';
?>
<style>
.acc-add-wrap {
    width: 100%;
    max-width: none;
    padding: 15px;
    box-sizing: border-box;
    background: #fff;
    color: #111;
}
.acc-add-wrap h3 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 800;
    color: #111;
}
.acc-add-lead { margin: 0 0 14px; font-size: 13px; color: #525252; }
.acc-add-card.card {
    padding: 16px 16px 18px !important;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
}
.acc-add-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 16px;
    align-items: start;
}
.acc-add-span { grid-column: 1 / -1; }
.acc-add-section {
    grid-column: 1 / -1;
    margin: 4px 0 0;
    padding-bottom: 6px;
    border-bottom: 1px solid #e5e5e5;
    font-size: 12px;
    font-weight: 800;
    color: #111;
    letter-spacing: 0.02em;
}
.acc-add-group { min-width: 0; }
.acc-add-group label {
    display: block;
    font-weight: 800;
    font-size: 13px;
    color: #111;
    margin: 0 0 6px;
}
.acc-add-group input,
.acc-add-group select {
    display: block;
    width: 100%;
    box-sizing: border-box;
    min-height: 46px;
    padding: 10px 12px;
    border: 1px solid #d4d4d4;
    border-radius: 8px;
    background: #fff;
    color: #111;
    font-size: 15px;
}
.acc-add-group input:focus,
.acc-add-group select:focus {
    outline: 2px solid #111;
    outline-offset: 1px;
    border-color: #111;
}
.acc-add-group small {
    display: block;
    margin-top: 5px;
    color: #525252;
    font-size: 12px;
    line-height: 1.4;
}
.acc-add-bank {
    grid-column: 1 / -1;
    display: none;
    grid-template-columns: 1fr 1fr;
    gap: 14px 16px;
    padding: 12px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    background: #fafafa;
}
.acc-add-bank.on { display: grid; }
.acc-add-actions {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-top: 4px;
}
.acc-add-submit {
    border: 0;
    border-radius: 8px;
    background: #111;
    color: #fff;
    font-weight: 800;
    font-size: 16px;
    min-height: 48px;
    padding: 12px 22px;
    cursor: pointer;
    flex: 1 1 220px;
}
.acc-add-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 12px 18px;
    color: #111;
    font-weight: 800;
    text-decoration: underline;
    flex: 0 0 auto;
}
@media (max-width: 720px) {
    .acc-add-wrap { padding: 12px 10px 20px; }
    .acc-add-grid,
    .acc-add-bank.on { grid-template-columns: 1fr; }
    .acc-add-span,
    .acc-add-section,
    .acc-add-actions { grid-column: 1; }
    .acc-add-submit,
    .acc-add-back { width: 100%; flex: 1 1 100%; }
}
</style>
<div class="acc-add-wrap">
    <h3>নতুন অ্যাকাউন্ট যোগ করুন</h3>
    <p class="acc-add-lead">ক্যাশ, ব্যাংক বা মোবাইল ব্যাংকিং খাত যুক্ত করুন। পেজটা স্ক্রিনের পুরো প্রস্থে খুলবে।</p>

    <form class="card acc-add-card" action="process_add.php" method="POST" data-ame-once="1">
        <?php echo csrf_field(); ?>
        <div class="acc-add-grid">
            <div class="acc-add-section">মূল তথ্য</div>

            <div class="acc-add-group">
                <label for="acc-name">অ্যাকাউন্টের নাম</label>
                <input id="acc-name" type="text" name="account_name" placeholder="উদা: ডাচ বাংলা ব্যাংক / বিকাশ" required>
            </div>
            <div class="acc-add-group">
                <label for="accTypeSelect">অ্যাকাউন্টের ধরণ</label>
                <select name="account_type" id="accTypeSelect">
                    <option value="Cash">Cash (নগদ)</option>
                    <option value="Bank">Bank (ব্যাংক)</option>
                    <option value="Mobile">Mobile Banking (বিকাশ/নগদ)</option>
                </select>
            </div>

            <div class="acc-add-bank" id="bankFieldsBox">
                <div class="acc-add-group">
                    <label for="acc-number">হিসাব নম্বর (ঐচ্ছিক)</label>
                    <input id="acc-number" type="text" name="account_number" placeholder="যেমন: 1251350036939">
                </div>
                <div class="acc-add-group">
                    <label for="acc-branch">শাখার নাম (ঐচ্ছিক)</label>
                    <input id="acc-branch" type="text" name="bank_branch" placeholder="যেমন: হবিগঞ্জ শাখা">
                </div>
            </div>

            <div class="acc-add-group acc-add-span">
                <label for="acc-balance">ওপেনিং ব্যালেন্স (ঐচ্ছিক)</label>
                <input id="acc-balance" type="number" step="0.01" name="balance" value="0" placeholder="খোলার সময়ের টাকা">
            </div>

            <div class="acc-add-section">সতর্কতা সেটিংস</div>

            <div class="acc-add-group">
                <label for="acc-threshold">কম ব্যালেন্স সতর্কতার সীমা (ঐচ্ছিক)</label>
                <input id="acc-threshold" type="number" step="0.01" name="low_balance_threshold" placeholder="যেমন: ৫০০০">
                <small>ব্যালেন্স এই সংখ্যার নিচে নামলে সতর্কবার্তা দেখাবে।</small>
            </div>
            <div class="acc-add-group">
                <label for="acc-phone">সতর্কতা SMS পাঠানোর নম্বর (ঐচ্ছিক)</label>
                <input id="acc-phone" type="text" name="alert_phone" placeholder="01700000000">
                <small>খালি রাখলে শুধু অ্যাপে দেখাবে, SMS যাবে না।</small>
            </div>

            <div class="acc-add-actions">
                <button type="submit" class="acc-add-submit">অ্যাকাউন্ট তৈরি করুন</button>
                <a class="acc-add-back" href="list.php">ফিরে যান</a>
            </div>
        </div>
    </form>
</div>
<script>
function toggleBankFields() {
    var box = document.getElementById('bankFieldsBox');
    var sel = document.getElementById('accTypeSelect');
    if (!box || !sel) return;
    box.classList.toggle('on', sel.value === 'Bank');
}
var accType = document.getElementById('accTypeSelect');
if (accType) {
    accType.addEventListener('change', toggleBankFields);
}
toggleBankFields();
</script>
<?php include '../../partials/footer.php'; ?>
