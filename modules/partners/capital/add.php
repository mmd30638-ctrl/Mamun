<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
include __DIR__ . '/../../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$partners = $db->query("SELECT id, name FROM partners WHERE status != 'exited' ORDER BY name ASC");
$partner_list = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];
?>
<style>
    .cap-form-card {
        max-width: 520px; margin: 15px auto; background:#fff; border-radius:18px;
        box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:hidden;
    }
    .cap-form-header {
        background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff;
        padding: 22px 26px;
    }
    .cap-form-header h2 { margin:0; font-size:19px; }
    .cap-form-body { padding: 24px 26px 28px; }
    .cap-form-group { margin-bottom: 16px; }
    .cap-form-group label { display:block; font-weight:700; font-size:13px; color:#0B1E4D; margin-bottom:6px; }
    .cap-form-group input, .cap-form-group select, .cap-form-group textarea {
        width:100%; padding:12px 14px; border:1.5px solid #CFE3F7; border-radius:10px;
        font-size:14.5px; background:#fbfdff; box-sizing:border-box;
    }
    .cap-form-group input:focus, .cap-form-group select:focus, .cap-form-group textarea:focus {
        outline:none; border-color:#0091EA; box-shadow:0 0 0 4px rgba(0,145,234,0.12); background:#fff;
    }
    .cap-submit-btn {
        width:100%; padding:14px; border:none; border-radius:12px;
        background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff;
        font-weight:800; font-size:15px; cursor:pointer; margin-top:8px;
        box-shadow: 0 6px 18px rgba(11,30,77,0.25);
    }
</style>

<div class="cap-form-card">
    <div class="cap-form-header">
        <h2>➕ মূলধন লেনদেন যোগ করুন</h2>
    </div>
    <div class="cap-form-body">
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($partner_list)): ?>
            <p style="color:#999; text-align:center;">প্রথমে একজন পার্টনার যোগ করুন। <a href="/modules/partners/add.php">এখানে ক্লিক করুন</a></p>
        <?php else: ?>
        <form action="actions/process_add.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="cap-form-group">
                <label>পার্টনার নির্বাচন করুন *</label>
                <select name="partner_id" required>
                    <option value="">-- নির্বাচন করুন --</option>
                    <?php foreach ($partner_list as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cap-form-group">
                <label>লেনদেনের ধরন *</label>
                <select name="type" required>
                    <option value="investment">💰 মূলধন জমা (Investment)</option>
                    <option value="capital_withdrawal">📤 মূলধন উত্তোলন</option>
                    <option value="loan_to_business">🤝 ব্যবসাকে ঋণ প্রদান</option>
                </select>
            </div>

            <div class="cap-form-group">
                <label>পরিমাণ (৳) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" required>
            </div>

            <div class="cap-form-group">
                <label>তারিখ *</label>
                <input type="date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="cap-form-group">
                <label>নোট (ঐচ্ছিক)</label>
                <textarea name="note" rows="2" maxlength="255" placeholder="যেমন: নতুন যন্ত্রপাতি কেনার জন্য মূলধন"></textarea>
            </div>

            <button type="submit" class="cap-submit-btn">✅ সংরক্ষণ করুন</button>
        </form>
        <?php endif; ?>

        <div style="margin-top:16px; text-align:center;">
            <a href="index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← মূলধন তালিকায় ফিরে যান</a>
        </div>
    </div>
</div>
