<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<style>
    .al-form-card { max-width: 480px; margin: 15px auto; background:#fff; border-radius:18px; box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:hidden; }
    .al-form-header { background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; padding: 22px 26px; }
    .al-form-header h2 { margin:0; font-size:18px; }
    .al-form-body { padding: 24px 26px 28px; }
    .al-form-group { margin-bottom: 16px; }
    .al-form-group label { display:block; font-weight:700; font-size:13px; color:#0B1E4D; margin-bottom:6px; }
    .al-form-group input { width:100%; padding:12px 14px; border:1.5px solid #CFE3F7; border-radius:10px; font-size:14.5px; background:#fbfdff; box-sizing:border-box; }
    .al-form-group input:focus { outline:none; border-color:#0091EA; box-shadow:0 0 0 4px rgba(0,145,234,0.12); background:#fff; }
    .al-submit-btn { width:100%; padding:14px; border:none; border-radius:12px; background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; font-weight:800; font-size:15px; cursor:pointer; margin-top:8px; box-shadow: 0 6px 18px rgba(11,30,77,0.25); }
    .al-note { background:#FFF3E0; color:#8D6E00; padding:12px 14px; border-radius:10px; font-size:12px; margin-top:16px; }
</style>

<div class="al-form-card">
    <div class="al-form-header"><h2>➕ নতুন সময়কাল লাভ বণ্টন</h2></div>
    <div class="al-form-body">
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="actions/process_create.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="al-form-group">
                <label>সময়কালের নাম *</label>
                <input type="text" name="period_label" required maxlength="100" placeholder="যেমন: জানুয়ারি ২০২৬">
            </div>
            <div class="al-form-group">
                <label>শুরু তারিখ *</label>
                <input type="date" name="period_start" required value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="al-form-group">
                <label>শেষ তারিখ *</label>
                <input type="date" name="period_end" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <button type="submit" class="al-submit-btn">📊 হিসাব করে প্রস্তাব তৈরি করুন</button>
        </form>

        <div class="al-note">
            ⓘ এই তারিখের মধ্যে সিস্টেম নিজে থেকেই গ্রস প্রফিট, খরচ ও সমন্বয় হিসাব করে প্রতিটা পার্টনারের প্রাপ্য অংশ প্রস্তাব করবে। এটা সাথে সাথে চূড়ান্ত হবে না — পরের পেজে দেখে **অনুমোদন** করতে হবে।
        </div>

        <div style="margin-top:16px; text-align:center;">
            <a href="index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← তালিকায় ফিরে যান</a>
        </div>
    </div>
</div>
