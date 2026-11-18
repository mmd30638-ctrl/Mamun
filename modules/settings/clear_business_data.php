<?php
require_once '../../includes/config.php';
require_once '../../includes/factory_reset_functions.php';
require_once '../../includes/critical_action_otp.php';
admin_only();

if (empty($_SESSION['danger_tools_unlocked'])) {
    header('Location: tools.php');
    exit();
}

$otp_sent = !empty($_SESSION['cbd_otp_pending']) || isset($_GET['otp_sent']);
include '../../partials/header.php';
$keeps = ame_factory_reset_keeps();
?>
<style>
    .cbd-wrap { padding: 15px; }
    .cbd-card { background:#fff; border-radius:14px; max-width:520px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .cbd-topbar { display:flex; height:6px; }
    .cbd-topbar span { flex:1; }
    .cbd-c1{background:var(--brand-navy);} .cbd-c2{background:var(--brand-accent);} .cbd-c3{background:var(--brand-gold);}
    .cbd-header { text-align:center; padding:20px 15px 15px; }
    .cbd-header h3 { margin:0; font-size:19px; color:var(--brand-danger); }
    .cbd-header small { color:#666; font-size:12px; line-height:1.5; display:block; margin-top:6px; }
    .cbd-body { padding:0 20px 20px; }
    .cbd-warning { background:var(--brand-danger-soft); border:1px solid var(--brand-danger); color:#7a1c1c; border-radius:10px; padding:14px; font-size:13px; margin-bottom:16px; line-height:1.6; }
    .cbd-otp-box { background:#FFF8E1; border:1px solid #FFB300; color:#9A5B00; border-radius:10px; padding:14px; font-size:13px; margin-bottom:16px; line-height:1.6; }
    .cbd-otp-preview { font-size:26px; font-weight:800; letter-spacing:8px; margin:8px 0; color:#004D40; }
    .cbd-list { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; font-size:13px; margin-bottom:14px; line-height:1.7; }
    .cbd-list h4 { margin:0 0 8px; font-size:13px; color:#0B1E4D; }
    .cbd-list ul { margin:0; padding-left:18px; }
    .cbd-list.keep ul { color:#1b5e20; }
    .cbd-list.del ul { color:#7a1c1c; }
    .cbd-check { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#333; margin-bottom:12px; }
    .cbd-check input { width:18px; height:18px; margin-top:2px; flex-shrink:0; }
    .cbd-group { margin-bottom:14px; }
    .cbd-group label { display:block; font-weight:700; font-size:13px; margin-bottom:6px; }
    .cbd-group input { width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; box-sizing:border-box; }
    .cbd-otp-input { letter-spacing:10px; text-align:center; font-size:22px; font-weight:800; }
    .cbd-submit { width:100%; padding:14px; border:none; border-radius:8px; background:linear-gradient(135deg,var(--brand-danger),#e53935); color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-bottom:8px; }
    .cbd-submit:disabled { opacity:0.45; cursor:not-allowed; }
    .cbd-otp-btn { background:linear-gradient(135deg,#004D40,#00796B); }
    .cbd-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>

<div class="cbd-wrap">
    <div class="cbd-card">
        <div class="cbd-topbar"><span class="cbd-c1"></span><span class="cbd-c2"></span><span class="cbd-c3"></span></div>
        <div class="cbd-header">
            <h3>🗑️ ব্যবসায়িক ডাটা মুছুন</h3>
            <small>নতুন করে সাজানোর জন্য — WhatsApp/SMS OTP যাচাই বাধ্যতামূলক</small>
        </div>
        <?php if (isset($_GET['error'])): ?>
        <div style="margin:0 20px 15px; background:var(--brand-danger-soft); color:var(--brand-danger); padding:12px 15px; border-radius:8px; font-size:13px; font-weight:600;">
            ❌ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['done'])): ?>
        <div style="margin:0 20px 15px; background:var(--brand-success-soft); color:var(--brand-success); padding:14px 15px; border-radius:8px; font-size:13px; font-weight:600; line-height:1.6;">
            ✅ ব্যবসায়িক ডাটা মুছে ফেলা হয়েছে।<br>
            <?php echo (int)($_GET['n'] ?? 0); ?>টি টেবিল ক্লিয়ার হয়েছে। এখন নতুন করে ডাটা এন্ট্রি বা পুরনো ডাটা ইমপোর্ট করতে পারবেন।
        </div>
        <?php endif; ?>
        <div class="cbd-body">
            <div class="cbd-warning">
                <b>⚠️ সতর্কতা:</b> এই কাজ <b>ফিরিয়ে আনা যাবে না</b>। আগে Settings → Tools থেকে ব্যাকআপ (.sql) ডাউনলোড করে রাখুন।
            </div>

            <?php if ($otp_sent): ?>
            <div class="cbd-otp-box">
                📲 OTP পাঠানো হয়েছে <?php echo !empty($_SESSION['cbd_otp_mask']) ? '— ' . htmlspecialchars((string)$_SESSION['cbd_otp_mask']) : ''; ?>
                <?php if (!empty($_SESSION['cbd_otp_preview'])): ?>
                <div class="cbd-otp-preview"><?php echo htmlspecialchars((string)$_SESSION['cbd_otp_preview']); ?></div>
                <small>গেটওয়ে সেটআপ নেই — উপরের টেস্ট OTP ব্যবহার করুন।</small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="cbd-list del">
                <h4>মুছে যাবে</h4>
                <ul>
                    <li>সমস্ত কাস্টমার, পণ্য, সাপ্লায়ার, খাত</li>
                    <li>সমস্ত বিক্রয়, ক্রয়, পেমেন্ট, বকেয়া, লেজার</li>
                    <li>সমস্ত লেনদেন, রিপোর্ট, অ্যাক্টিভিটি লগ</li>
                    <li>ওয়ার্কশপ, HRM, লোন, পার্টনারশিপ, পারিবারিক খাতা</li>
                    <li>অ্যাকাউন্ট ব্যালেন্স ০ হবে (অ্যাকাউন্ট তালিকা থাকবে)</li>
                </ul>
            </div>

            <div class="cbd-list keep">
                <h4>থাকবে (মুছবে না)</h4>
                <ul>
                    <?php foreach ($keeps as $k): ?>
                    <li><?php echo htmlspecialchars($k); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!$otp_sent): ?>
            <form action="process_clear_business_data.php" method="POST" id="cbdOtpForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="send_otp">
                <label class="cbd-check">
                    <input type="checkbox" name="confirm1" id="cbdConfirm1" value="1" required>
                    <span>আমি বুঝেছি — সব ব্যবসায়িক ডাটা মুছে যাবে এবং এটা ফিরানো যাবে না।</span>
                </label>
                <label class="cbd-check">
                    <input type="checkbox" name="confirm2" id="cbdConfirm2" value="1" required>
                    <span>আমি ব্যাকআপ ডাউনলোড করেছি (অথবা ব্যাকআপ দরকার নেই)।</span>
                </label>
                <div class="cbd-group">
                    <label>নিশ্চিত করতে টাইপ করুন: <b>মুছুন</b></label>
                    <input type="text" name="confirm_text" id="cbdConfirmText" placeholder="মুছুন" autocomplete="off">
                </div>
                <button type="submit" class="cbd-submit cbd-otp-btn" id="cbdOtpBtn" disabled>📲 WhatsApp/SMS OTP পাঠান</button>
            </form>
            <?php else: ?>
            <form action="process_clear_business_data.php" method="POST" id="cbdForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="clear">
                <input type="hidden" name="confirm1" value="1">
                <input type="hidden" name="confirm2" value="1">
                <input type="hidden" name="confirm_text" value="মুছুন">
                <div class="cbd-group">
                    <label for="cbdOtp">৬-অঙ্কের OTP লিখুন</label>
                    <input type="text" name="otp" id="cbdOtp" class="cbd-otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required autofocus>
                </div>
                <button type="submit" class="cbd-submit" id="cbdBtn">🗑️ OTP যাচাই করে ডাটা মুছুন</button>
            </form>
            <form action="process_clear_business_data.php" method="POST" style="margin-top:8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="send_otp">
                <input type="hidden" name="confirm1" value="1">
                <input type="hidden" name="confirm2" value="1">
                <input type="hidden" name="confirm_text" value="মুছুন">
                <button type="submit" class="cbd-submit cbd-otp-btn" style="font-size:13px;padding:10px;">OTP আবার পাঠান</button>
            </form>
            <?php endif; ?>
        </div>
        <a href="tools.php" class="cbd-back">⬅️ টুলসে ফিরে যান</a>
    </div>
</div>
<script>
(function () {
    var c1 = document.getElementById('cbdConfirm1');
    var c2 = document.getElementById('cbdConfirm2');
    var txt = document.getElementById('cbdConfirmText');
    var btn = document.getElementById('cbdOtpBtn');
    if (!btn) return;
    function upd() {
        btn.disabled = !(c1.checked && c2.checked && txt.value.trim() === 'মুছুন');
    }
    c1.addEventListener('change', upd);
    c2.addEventListener('change', upd);
    txt.addEventListener('input', upd);
    document.getElementById('cbdOtpForm').addEventListener('submit', function () {
        btn.disabled = true;
        btn.textContent = '⏳ OTP পাঠানো হচ্ছে...';
    });
    var clearBtn = document.getElementById('cbdBtn');
    if (clearBtn) {
        document.getElementById('cbdForm').addEventListener('submit', function () {
            clearBtn.disabled = true;
            clearBtn.textContent = '⏳ মুছে ফেলা হচ্ছে...';
        });
    }
})();
</script>
<?php include '../../partials/footer.php'; ?>
