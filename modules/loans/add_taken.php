<?php
require_once '../../includes/config.php';
include '../../partials/header.php';
?>
<div style="padding: 15px;">
    <div class="card" style="max-width:480px; margin:0 auto;">
        <h3>➕ নতুন গৃহীত ঋণ (দোকান যা ধার নিচ্ছে)</h3>
        <p style="font-size:12px; color:#666; margin-top:-8px;">গ্রাহককে ধার দেওয়ার জন্য <a href="applications_add.php">নতুন লোন আবেদন</a> ব্যবহার করুন — এটা শুধু দোকানের নিজের ধার নেওয়ার জন্য।</p>
        <?php if (isset($_GET['error'])): ?>
        <div class="brand-badge danger" style="display:flex; margin-bottom:12px;">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <form action="add_taken_process.php" method="POST">
            <?php echo csrf_field(); ?>
            <label>কার কাছ থেকে ধার নিচ্ছেন? *</label>
            <input type="text" name="person_name" class="input-style" required style="margin-bottom:14px;">

            <label>পরিমাণ (৳) *</label>
            <input type="number" step="0.01" name="amount" class="input-style" required style="margin-bottom:14px;">

            <label>কোন অ্যাকাউন্টে টাকা জমা হবে? *</label>
            <select name="account_id" class="ame-account-select input-style" required style="margin-bottom:14px;" data-ame-account-select="1" data-ame-direction="receive">
                <?php echo ame_render_account_options(ame_active_accounts($db)); ?>
            </select>

            <button type="submit" class="btn-success" style="width:100%;">সেভ করুন</button>
        </form>
        <a href="list.php" style="display:block; text-align:center; margin-top:14px;">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
