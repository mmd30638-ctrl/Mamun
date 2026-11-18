<?php
require_once '../../includes/config.php';
admin_only();
include '../../partials/header.php';
?>
<style>
    .rst-wrap { padding: 15px; }
    .rst-card { background:#fff; border-radius:14px; max-width:480px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .rst-topbar { display:flex; height:6px; }
    .rst-topbar span { flex:1; }
    .rst-c1{background:var(--brand-navy);} .rst-c2{background:var(--brand-accent);} .rst-c3{background:var(--brand-gold);}
    .rst-header { text-align:center; padding:20px 15px 15px; }
    .rst-header h3 { margin:0; font-size:19px; color:var(--brand-navy); }
    .rst-header small { color:#666; font-size:12px; }
    .rst-body { padding:0 20px 20px; }
    .rst-warning {
        background: var(--brand-danger-soft); border: 1px solid var(--brand-danger);
        color: #7a1c1c; border-radius: 10px; padding: 14px; font-size: 13px;
        margin-bottom: 18px; line-height: 1.6;
    }
    .rst-warning b { color: var(--brand-danger); }
    .rst-group { margin-bottom: 16px; }
    .rst-group label { display:block; font-weight:700; font-size:13px; color:#000; margin-bottom:8px; }
    .rst-checkbox-row { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#333; margin-bottom:18px; }
    .rst-checkbox-row input { width:18px; height:18px; margin-top:2px; flex-shrink:0; }
    .rst-submit {
        width:100%; padding:14px; border:none; border-radius:8px;
        background: linear-gradient(135deg, var(--brand-danger), #e53935);
        color:#fff; font-weight:700; font-size:15px; cursor:pointer;
    }
    .rst-submit:disabled { opacity: 0.5; cursor: not-allowed; }
    .rst-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>

<div class="rst-wrap">
    <div class="rst-card">
        <div class="rst-topbar"><span class="rst-c1"></span><span class="rst-c2"></span><span class="rst-c3"></span></div>
        <div class="rst-header">
            <h3>♻️ ব্যাকআপ থেকে রিস্টোর</h3>
            <small>পূর্বে ডাউনলোড করা .sql ফাইল থেকে ডাটা ফিরিয়ে আনুন</small>
        </div>
        <?php if (isset($_GET['error'])): ?>
        <div style="margin:0 20px 15px; background:var(--brand-danger-soft); color:var(--brand-danger); padding:12px 15px; border-radius:8px; font-size:13px; font-weight:600;">
            ❌ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        <div class="rst-body">
            <div class="rst-warning">
                <b>⚠️ গুরুত্বপূর্ণ সতর্কতা:</b> রিস্টোর করলে আপনার বর্তমান ডাটাবেজের সবকিছু <b>মুছে গিয়ে</b> ব্যাকআপ ফাইলের ডাটা দিয়ে প্রতিস্থাপিত হবে। এই কাজ <b>ফিরিয়ে আনা যাবে না</b>। রিস্টোর করার আগে বর্তমান ডাটার একটা নতুন ব্যাকআপ ডাউনলোড করে রাখার পরামর্শ দেওয়া হচ্ছে।
            </div>

            <form action="process_restore.php" method="POST" enctype="multipart/form-data" id="restoreForm">
                <?php echo csrf_field(); ?>
                <div class="rst-group">
                    <label>ব্যাকআপ ফাইল নির্বাচন করুন (.sql)</label>
                    <input type="file" name="backup_file" accept=".sql" required class="input-style">
                </div>

                <label class="rst-checkbox-row">
                    <input type="checkbox" id="confirmChk" required>
                    <span>আমি বুঝেছি এই কাজ বর্তমান সব ডাটা মুছে ফেলবে এবং এটা ফিরিয়ে আনা যাবে না। আমি নিশ্চিত করছি যে আমি এগোতে চাই।</span>
                </label>

                <button type="submit" class="rst-submit" id="restoreBtn" disabled>♻️ রিস্টোর শুরু করুন</button>
            </form>
        </div>
        <a href="tools.php" class="rst-back">⬅️ ফিরে যান</a>
    </div>
</div>

<script>
    document.getElementById('confirmChk').addEventListener('change', function () {
        document.getElementById('restoreBtn').disabled = !this.checked;
    });
    document.getElementById('restoreForm').addEventListener('submit', function () {
        var btn = document.getElementById('restoreBtn');
        btn.disabled = true;
        btn.textContent = '⏳ রিস্টোর হচ্ছে... পেজ বন্ধ করবেন না';
    });
</script>
<?php include '../../partials/footer.php'; ?>
