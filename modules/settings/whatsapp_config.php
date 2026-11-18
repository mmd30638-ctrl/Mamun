<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/whatsapp_functions.php';
admin_only();
include __DIR__ . '/../../partials/header.php';

ensure_whatsapp_schema($db);
$wa = ame_whatsapp_settings($db);
?>
<style>
    .sms-wrap { padding: 15px; }
    .sms-card { background:#fff; border-radius:14px; max-width:520px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .sms-topbar { display:flex; height:6px; }
    .sms-topbar span { flex:1; }
    .sms-c1{background:var(--brand-navy);} .sms-c2{background:#25D366;} .sms-c3{background:var(--brand-gold);}
    .sms-header { text-align:center; padding:20px 15px 15px; }
    .sms-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .sms-header small { color:#666; font-size:12px; line-height:1.45; display:block; margin-top:6px; }
    .sms-body { padding:0 20px 20px; }
    .sms-group { margin-bottom:14px; }
    .sms-group label { display:block; font-weight:600; font-size:13px; color:#000; margin-bottom:6px; }
    .sms-group input, .sms-group select, .sms-group textarea { width:100%; padding:11px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; font-size:14px; background:#fafafa; }
    .sms-group small { display:block; margin-top:6px; color:#78909C; font-size:12px; line-height:1.45; }
    .sms-submit { width:100%; padding:14px; border:none; border-radius:8px; background:linear-gradient(135deg,#128C7E,#25D366); color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:8px; }
    .sms-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
    .sms-ok { background:#E8F5E9; color:#1B5E20; padding:12px 20px; margin:0 20px 15px; border-radius:8px; font-size:13px; font-weight:600; }
</style>

<div class="sms-wrap">
    <div class="sms-card">
        <div class="sms-topbar"><span class="sms-c1"></span><span class="sms-c2"></span><span class="sms-c3"></span></div>
        <div class="sms-header">
            <h3>💬 WhatsApp OTP সেটআপ</h3>
            <small>পাসওয়ার্ড রিসেট OTP WhatsApp-এ পাঠানোর জন্য Meta WhatsApp Business Cloud API কনফিগার করুন।</small>
        </div>
        <?php if (isset($_GET['saved'])): ?>
        <div class="sms-ok">✅ WhatsApp সেটিংস সেভ হয়েছে।</div>
        <?php endif; ?>
        <form action="process_whatsapp.php" method="POST" class="sms-body">
            <?php echo csrf_field(); ?>
            <div class="sms-group">
                <label>API Base URL</label>
                <input type="text" name="api_url" value="<?php echo htmlspecialchars($wa['api_url']); ?>" placeholder="https://graph.facebook.com/v21.0">
                <small>সাধারণত পরিবর্তন লাগে না — Meta Graph API URL।</small>
            </div>
            <div class="sms-group">
                <label>Phone Number ID</label>
                <input type="text" name="phone_number_id" value="<?php echo htmlspecialchars($wa['phone_number_id']); ?>" placeholder="123456789012345">
                <small>Meta Developer → WhatsApp → API Setup থেকে Phone Number ID।</small>
            </div>
            <div class="sms-group">
                <label>Permanent Access Token</label>
                <textarea name="access_token" rows="3" placeholder="<?php echo !empty($wa['has_token']) ? '•••• সেভ আছে — বদলাতে নতুন টোকেন লিখুন' : 'EAAxxxx...'; ?>"></textarea>
                <small>টোকেন ডাটাবেজে থাকে না। .env-এ <code>WHATSAPP_ACCESS_TOKEN</code> বা প্রাইভেট ফাইল। খালি সাবমিট করলে আগেরটা থাকবে।</small>
            </div>
            <div class="sms-group">
                <label>স্ট্যাটাস</label>
                <select name="status">
                    <option value="active" <?php echo ($wa['status'] ?? '') === 'active' ? 'selected' : ''; ?>>চালু (Active)</option>
                    <option value="inactive" <?php echo ($wa['status'] ?? '') !== 'active' ? 'selected' : ''; ?>>বন্ধ (Inactive)</option>
                </select>
                <small>বন্ধ থাকলে OTP শুধু লগে জমা হবে (টেস্ট মোড) — লাইভে চালু করুন।</small>
            </div>
            <button type="submit" class="sms-submit">✅ সেভ করুন</button>
        </form>
        <a href="dashboard_settings.php" class="sms-back">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
