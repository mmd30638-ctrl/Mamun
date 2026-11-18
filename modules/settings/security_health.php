<?php
require_once '../../includes/config.php';
require_once '../../includes/backup_functions.php';
admin_only();
include '../../partials/header.php';

echo '<div style="max-width:720px;margin:12px auto;padding:12px 16px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;font-size:13.5px;line-height:1.5;">
<b>আন্তর্জাতিক মান (v10.98.857 — সম্পূর্ণ):</b> নীতিমালা মডিউল শেষ। 
বিস্তারিত দেখুন <a href="/modules/settings/international_standards.php" style="color:#0d47a1;font-weight:700;">আন্তর্জাতিক মান</a> পেজে।
</div>';

$app_env = strtolower((string)(getenv('APP_ENV') ?: 'production'));
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$guard = function_exists('ame_file_guard_status') ? ame_file_guard_status() : null;
$wipe_allowed = strtolower((string)(getenv('AME_ALLOW_INSTALL_WIPE') ?: '')) === '1';
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '?';

$failed_logins = null;
$login_table = @$db->query("SHOW TABLES LIKE 'login_attempts'");
if ($login_table && $login_table->num_rows > 0) {
    $res = @$db->query(
        "SELECT COUNT(*) AS c FROM login_attempts
         WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    if ($res) {
        $failed_logins = (int)(($res->fetch_assoc()['c'] ?? 0));
    }
}

$cron_file = dirname(__DIR__, 2) . '/includes/cron_backup.php';
$cron_hint = is_file($cron_file)
    ? 'includes/cron_backup.php আছে — hPanel Cron Jobs-এ প্রতিদিন CLI দিয়ে চালান।'
    : 'cron_backup.php পাওয়া যায়নি।';

$rows = [];
$rows[] = [
    'APP_ENV',
    $app_env === 'production',
    $app_env === 'production' ? 'production (ঠিক)' : ($app_env . ' — লাইভে production রাখুন'),
];
$rows[] = [
    'HTTPS',
    $is_https,
    $is_https ? 'চালু' : 'বন্ধ — Hostinger SSL চালু করুন',
];
if ($guard) {
    $env_ok = !empty($guard['env_outside']) && empty($guard['env_in_webroot']);
    $rows[] = [
        '.env ওয়েব-রিডেবল নয়',
        $env_ok,
        $env_ok
            ? 'public_html-এর বাইরে (ঠিক)'
            : (!empty($guard['env_in_webroot']) ? 'সতর্কতা: public_html/.env আছে' : 'বাইরের .env পাওয়া যায়নি'),
    ];
} else {
    $rows[] = ['.env হিন্ট', true, 'guard ফাংশন নেই — ম্যানুয়ালি পাবলিক রুটে .env রাখবেন না'];
}
$rows[] = [
    'ইনস্টল ওয়াইপ',
    !$wipe_allowed,
    $wipe_allowed ? 'চালু (AME_ALLOW_INSTALL_WIPE=1) — সাধারণত বন্ধ রাখুন' : 'নিষ্ক্রিয় (ঠিক)',
];
if ($failed_logins !== null) {
    $rows[] = [
        '২৪ ঘণ্টায় ব্যর্থ লগইন',
        $failed_logins < 50,
        (string)$failed_logins . ($failed_logins >= 50 ? ' — বেশি, পাসওয়ার্ড/রেট লিমিট দেখুন' : ' — স্বাভাবিক সীমার মধ্যে'),
    ];
} else {
    $rows[] = ['ব্যর্থ লগইন লগ', true, 'login_attempts টেবিল নেই / পড়া যায়নি'];
}
$rows[] = ['ব্যাকআপ cron হিন্ট', true, $cron_hint];
$rows[] = ['অ্যাপ সংস্করণ', true, $ver];
?>
<style>
.sec-wrap{padding:15px;max-width:640px;margin:0 auto;}
.sec-card{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(11,30,77,.08);overflow:hidden;}
.sec-head{padding:18px 16px 10px;}
.sec-head h3{margin:0;color:#0B1E4D;font-size:18px;}
.sec-head p{margin:6px 0 0;color:#64748B;font-size:13px;line-height:1.5;}
.sec-row{display:flex;justify-content:space-between;gap:12px;padding:12px 16px;border-top:1px solid #F1F5F9;font-size:13px;}
.sec-row b{color:#0B1E4D;}
.sec-ok{color:#0E8F52;font-weight:800;}
.sec-bad{color:#C62828;font-weight:800;}
.sec-note{padding:12px 16px 16px;color:#475569;font-size:12.5px;line-height:1.55;border-top:1px solid #F1F5F9;}
.sec-links{display:grid;gap:8px;padding:0 16px 16px;}
.sec-links a{display:block;text-align:center;padding:12px;border-radius:10px;background:#0B1E4D;color:#fff;font-weight:800;text-decoration:none;font-size:14px;min-height:44px;box-sizing:border-box;}
.sec-links a.quiet{background:#fff;color:#0B1E4D;border:1px solid #90caf9;}
</style>
<div class="sec-wrap">
  <div class="sec-card">
    <div class="sec-head">
      <h3>সুরক্ষা স্বাস্থ্য</h3>
      <p>শুধু পড়ে দেখায় — কিছু কাটে না। লাইভ দোকানের নিরাপত্তা চেকলিস্ট।</p>
    </div>
    <?php foreach ($rows as $r): ?>
    <div class="sec-row">
      <b><?php echo htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8'); ?></b>
      <span class="<?php echo $r[1] ? 'sec-ok' : 'sec-bad'; ?>"><?php echo htmlspecialchars($r[2], ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php endforeach; ?>
    <div class="sec-note">
      টিপ: <code>.env</code> সবসময় <code>public_html</code>-এর বাইরে রাখুন। ওয়াইপ টুল শুধু ইচ্ছাকৃত ফ্যাক্টরি রিসেটে চালু করুন।
      ব্যাকআপ cron: <code>php …/public_html/includes/cron_backup.php</code> (CLI)।
    </div>
    <div class="sec-links">
      <a href="system_health.php">সিস্টেম হেলথ</a>
      <a class="quiet" href="update_safety.php">আপডেট সুরক্ষা</a>
      <a class="quiet" href="tools.php">টুলসে ফিরুন</a>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
