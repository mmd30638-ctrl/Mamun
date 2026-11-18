<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$acc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$acc) {
    include '../../partials/header.php';
    echo '<div class="acc-edit-wrap"><p>অ্যাকাউন্ট খুঁজে পাওয়া যায়নি। <a href="list.php">ফিরে যান</a></p></div>';
    include '../../partials/footer.php';
    exit;
}

$opening = ame_account_opening_sum($db, $id);
$current = round((float) ($acc['balance'] ?? 0), 2);
$type = (string) ($acc['account_type'] ?? 'Cash');

include '../../partials/header.php';
?>
<style>
.acc-edit-wrap {
    width: 100%;
    max-width: none;
    padding: 15px;
    box-sizing: border-box;
    background: #fff;
    color: #111;
}
.acc-edit-wrap h3 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 800;
    color: #111;
}
.acc-edit-lead { margin: 0 0 14px; font-size: 13px; color: #525252; }
.acc-edit-card.card {
    padding: 16px 16px 18px !important;
    margin-bottom: 0;
    width: 100%;
    box-sizing: border-box;
}
.acc-edit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 16px;
    align-items: start;
}
.acc-edit-span { grid-column: 1 / -1; }
.acc-edit-section {
    grid-column: 1 / -1;
    margin: 4px 0 0;
    padding-bottom: 6px;
    border-bottom: 1px solid #e5e5e5;
    font-size: 12px;
    font-weight: 800;
    color: #111;
}
.acc-edit-group { min-width: 0; }
.acc-edit-group label {
    display: block;
    font-weight: 800;
    font-size: 13px;
    color: #111;
    margin: 0 0 6px;
}
.acc-edit-group input,
.acc-edit-group select {
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
.acc-edit-group input:focus,
.acc-edit-group select:focus {
    outline: 2px solid #111;
    outline-offset: 1px;
    border-color: #111;
}
.acc-edit-group small {
    display: block;
    margin-top: 5px;
    color: #525252;
    font-size: 12px;
    line-height: 1.4;
}
.acc-edit-now {
    grid-column: 1 / -1;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 12px 14px;
    background: #fafafa;
}
.acc-edit-now small { display: block; font-size: 11px; font-weight: 700; color: #525252; }
.acc-edit-now b { display: block; margin-top: 2px; font-size: 20px; font-weight: 800; color: #111; }
.acc-edit-actions {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.acc-edit-submit {
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
.acc-edit-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 12px 18px;
    color: #111;
    font-weight: 800;
    text-decoration: underline;
}
@media (max-width: 720px) {
    .acc-edit-wrap { padding: 12px 10px 20px; }
    .acc-edit-grid { grid-template-columns: 1fr; }
    .acc-edit-span,
    .acc-edit-section,
    .acc-edit-now,
    .acc-edit-actions { grid-column: 1; }
    .acc-edit-submit,
    .acc-edit-back { width: 100%; flex: 1 1 100%; }
}
</style>
<div class="acc-edit-wrap">
    <h3>অ্যাকাউন্ট তথ্য সম্পাদনা</h3>
    <p class="acc-edit-lead">নাম, ধরণ, ওপেনিং ব্যালেন্স ও সতর্কতা এখান থেকে বসান। ওপেনিং লেজারে জমা/কাটা হবে।</p>

    <form class="card acc-edit-card" action="actions/process_edit_account.php" method="POST" data-ame-once="1">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $acc['id']; ?>">
        <div class="acc-edit-grid">
            <div class="acc-edit-section">মূল তথ্য</div>

            <div class="acc-edit-group">
                <label for="acc-name">অ্যাকাউন্টের নাম</label>
                <input id="acc-name" type="text" name="account_name" value="<?php echo htmlspecialchars((string) $acc['account_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="acc-edit-group">
                <label for="acc-type">অ্যাকাউন্টের ধরণ</label>
                <select id="acc-type" name="account_type">
                    <?php foreach (['Cash' => 'Cash (নগদ)', 'Bank' => 'Bank (ব্যাংক)', 'Mobile' => 'Mobile Banking (বিকাশ/নগদ)'] as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php echo $type === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="acc-edit-section">ওপেনিং ব্যালেন্স</div>
            <div class="acc-edit-now">
                <small>বর্তমান ব্যালেন্স</small>
                <b><?php echo ame_balance_label($current); ?></b>
            </div>
            <div class="acc-edit-group acc-edit-span">
                <label for="acc-opening">ওপেনিং ব্যালেন্স</label>
                <input id="acc-opening" type="number" step="0.01" name="opening_balance" value="<?php echo htmlspecialchars(number_format($opening, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" autofocus>
                <small>খোলার সময়ের টাকা। এখানে লিখলে লেজারে ওপেনিং এন্ট্রি বসবে, বর্তমান ব্যালেন্স সেই অনুযায়ী বাড়বে বা কমবে। পরে POS/ট্রান্সফারের টাকা আলাদা থাকবে।</small>
            </div>

            <div class="acc-edit-section">সতর্কতা সেটিংস</div>
            <div class="acc-edit-group">
                <label for="acc-threshold">কম ব্যালেন্স সতর্কতার সীমা (ঐচ্ছিক)</label>
                <input id="acc-threshold" type="number" step="0.01" name="low_balance_threshold" value="<?php echo htmlspecialchars((string) ($acc['low_balance_threshold'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="যেমন: ৫০০০">
            </div>
            <div class="acc-edit-group">
                <label for="acc-phone">সতর্কতা SMS পাঠানোর নম্বর (ঐচ্ছিক)</label>
                <input id="acc-phone" type="text" name="alert_phone" value="<?php echo htmlspecialchars((string) ($acc['alert_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="01700000000">
            </div>

            <div class="acc-edit-actions">
                <button type="submit" class="acc-edit-submit">পরিবর্তন সংরক্ষণ করুন</button>
                <a class="acc-edit-back" href="list.php">ফিরে যান</a>
            </div>
        </div>
    </form>
</div>
<?php include '../../partials/footer.php'; ?>
