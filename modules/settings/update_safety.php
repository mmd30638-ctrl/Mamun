<?php
require_once '../../includes/config.php';
admin_only();
include '../../partials/header.php';
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '?';
$cron = is_file(dirname(__DIR__, 2) . '/includes/cron_backup.php');
?>
<style>
.us-wrap{padding:15px;max-width:640px;margin:0 auto;}
.us-card{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(11,30,77,.08);overflow:hidden;margin-bottom:12px;}
.us-head{padding:18px 16px 8px;}
.us-head h3{margin:0;color:#0B1E4D;font-size:18px;}
.us-head p{margin:6px 0 0;color:#64748B;font-size:13px;line-height:1.5;}
.us-body{padding:8px 16px 16px;color:#334155;font-size:13.5px;line-height:1.65;}
.us-body ol,.us-body ul{margin:0;padding-left:1.2em;}
.us-warn{margin:0 16px 16px;padding:12px;border-radius:12px;background:#fff8e1;border:1px solid #ffb300;color:#9a5b00;font-size:13px;line-height:1.5;}
.us-links{display:grid;gap:8px;padding:0 16px 16px;}
.us-links a{display:block;text-align:center;padding:12px;border-radius:10px;background:#0B1E4D;color:#fff;font-weight:800;text-decoration:none;min-height:44px;box-sizing:border-box;}
.us-links a.quiet{background:#fff;color:#0B1E4D;border:1px solid #90caf9;}
</style>
<div class="us-wrap">
  <div class="us-card">
    <div class="us-head">
      <h3>আপডেট সুরক্ষা</h3>
      <p>বর্তমান কোড: <b><?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></b> — আপডেটের আগে পড়ুন।</p>
    </div>
    <div class="us-warn">আগে ব্যাকআপ নিন। লাইভে সরাসরি বড় পরীক্ষা না করে সম্ভব হলে স্টেজিং/কপি ফোল্ডারে যাচাই করুন।</div>
    <div class="us-body">
      <ol>
        <li><b>Critical zip</b> (<code>APONSHOP_STABLE_CRITICAL.zip</code>) — কম ফাইল, File Manager-এ <code>public_html</code>-এর ভিতরে Extract + Overwrite। দ্রুত ফিক্সের জন্য।</li>
        <li><b>Full Tools zip</b> (<code>Aponshop POS STACK UPDATE.zip</code>) — উপরে <code>public_html/</code> ফোল্ডারসহ; Easy Update বা ডোমেইন রুটে Extract।</li>
        <li>Extract শেষে <a href="/v.php">/v.php</a> ও <a href="/VERSION.txt">VERSION.txt</a> দেখুন — দুটিতে <b><?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></b> হতে হবে।</li>
        <li>Hard refresh (Ctrl+F5)। হেডার আইকন রঙিন থাকবে; পুরনো ১৫KB viewport শেল নয়।</li>
        <li><b>Safe Update:</b> সহজ আপডেট আগে PHP syntax preflight, update lock, file+DB backup এবং atomic file replace চালায়। কোনো ধাপে ব্যর্থ হলে আংশিক file নিজে rollback করে।</li>
        <li><b>রোলব্যাক:</b> সহজ আপডেটের snapshot তালিকা থেকে code ফেরান; DB migration/data বদলালে যাচাই করে আলাদাভাবে “DB-ও ফেরান” নির্বাচন করুন।</li>
      </ol>
      <?php if ($cron): ?>
      <p style="margin-top:12px;">স্বয়ংক্রিয় ব্যাকআপ: <code>includes/cron_backup.php</code> — hPanel → Cron Jobs → প্রতিদিন CLI।</p>
      <?php endif; ?>
    </div>
    <div class="us-links">
      <a href="easy_update.php">সহজ আপডেট</a>
      <a class="quiet" href="backup.php">ব্যাকআপ ডাউনলোড</a>
      <a class="quiet" href="security_health.php">সুরক্ষা স্বাস্থ্য</a>
      <a class="quiet" href="tools.php">টুলস</a>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
