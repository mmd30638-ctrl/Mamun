<?php 
require_once '../../includes/config.php';
require_once '../../includes/sms_log_functions.php';
admin_only();
ensure_sms_log_schema($db);
include '../../partials/header.php'; 

$logs = $db->query("SELECT * FROM sms_log ORDER BY id DESC LIMIT 100");
?>
<style>
    .smslog-wrap { padding: 15px; }
    .smslog-card { background:#fff; border-radius:14px; max-width:640px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .smslog-topbar { display:flex; height:6px; }
    .smslog-topbar span { flex:1; }
    .smslog-c1{background:var(--brand-navy);} .smslog-c2{background:var(--brand-accent);} .smslog-c3{background:var(--brand-gold);}
    .smslog-header { text-align:center; padding:20px 15px 15px; }
    .smslog-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .smslog-header small { color:#666; font-size:12px; }
    .smslog-list { padding:0 20px 20px; }
    .smslog-item { padding:12px 14px; background:#fafafa; border-radius:10px; margin-bottom:8px; border:1px solid #eee; }
    .smslog-top-row { display:flex; justify-content:space-between; align-items:center; }
    .smslog-recipient { font-weight:700; color:#000; font-size:14px; }
    .smslog-badge { font-size:10px; font-weight:700; padding:2px 10px; border-radius:10px; }
    .smslog-badge.sent { background:#E8F5E9; color:#1B5E20; }
    .smslog-badge.queued { background:#FFF8E1; color:#F57F17; }
    .smslog-msg { font-size:12px; color:#555; margin-top:6px; }
    .smslog-meta { font-size:11px; color:#999; margin-top:4px; }
    .smslog-empty { text-align:center; color:#999; font-size:13px; padding:20px; }
    .smslog-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
</style>

<div class="smslog-wrap">
    <div class="smslog-card">
        <div class="smslog-topbar"><span class="smslog-c1"></span><span class="smslog-c2"></span><span class="smslog-c3"></span></div>
        <div class="smslog-header">
            <h3>📲 SMS সাকসেস লগ</h3>
            <small>সর্বশেষ ১০০টা পাঠানো SMS-এর হিসাব</small>
        </div>
        <div class="smslog-list">
            <?php if ($logs && $logs->num_rows > 0): while($l = $logs->fetch_assoc()): ?>
            <div class="smslog-item">
                <div class="smslog-top-row">
                    <span class="smslog-recipient">📱 <?php echo htmlspecialchars($l['recipient']); ?></span>
                    <span class="smslog-badge <?php echo htmlspecialchars($l['status']); ?>"><?php
                        echo $l['status'] === 'sent' ? 'পাঠানো হয়েছে' : ($l['status'] === 'queued' ? 'সিম কিউ' : 'ব্যর্থ');
                    ?></span>
                </div>
                <div class="smslog-msg"><?php echo htmlspecialchars(mb_substr($l['message'] ?? '', 0, 80)); ?><?php echo (mb_strlen($l['message'] ?? '') > 80) ? '...' : ''; ?></div>
                <div class="smslog-meta">🏷️ <?php echo htmlspecialchars($l['purpose'] ?? 'সাধারণ'); ?> · 🕒 <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?></div>
            </div>
            <?php endwhile; else: ?>
            <div class="smslog-empty">এখনো কোনো SMS পাঠানো হয়নি।</div>
            <?php endif; ?>
        </div>
        <a href="dashboard_settings.php" class="smslog-back">⬅️ ফিরে যান</a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
