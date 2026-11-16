<?php
require_once '../../includes/config.php';
require_once '../../includes/backup_functions.php';
require_once '../../includes/easy_update_functions.php';
require_once '../../includes/update_backup_functions.php';
admin_only();
require_once '../../includes/lang_helper.php';
include '../../partials/header.php';
$guard = function_exists('ame_file_guard_status') ? ame_file_guard_status() : null;

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$uploads = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
$diag = is_file(__DIR__ . '/../customers/diag_payment.php')
    ? (string)file_get_contents(__DIR__ . '/../customers/diag_payment.php')
    : '';
$diag_locked = $diag === '' || str_contains($diag, 'http_response_code(404)');
$schema_ready = function_exists('ame_schema_version_ready') && ame_schema_version_ready($db);
$update_snapshots = function_exists('ame_update_backup_list') ? ame_update_backup_list() : [];

$tables = [
    'products', 'accounts', 'sales', 'sale_items', 'suppliers', 'customers',
    'customer_payments', 'employees', 'loans', 'expenses', 'account_ledger',
    'display_widgets', 'dashboard_banners', 'idempotency_keys', 'activity_logs',
];
$ok = 0;
$missing = [];
foreach ($tables as $table) {
    $check = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    if ($check && $check->num_rows > 0) {
        $ok++;
    } else {
        $missing[] = $table;
    }
}

$checks = [
    ['PHP ৮+', version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP ' . PHP_VERSION],
    ['HTTPS', $is_https, $is_https ? 'চালু' : 'বন্ধ — Hostinger-এ SSL চালু করুন'],
    ['আপলোড ফোল্ডার লেখার যোগ্য', is_dir($uploads) && is_writable($uploads), $uploads],
    ['ডায়াগ পেজ বন্ধ', $diag_locked, $diag_locked ? 'লক করা' : 'খোলা আছে — বিপদ'],
    ['সেশন টাইমআউট', true, '২০ মিনিট অলস থাকলে লগআউট'],
    ['অ্যাপ সংস্করণ', true, AME_APP_VERSION],
    ['Central error log', is_dir(dirname(ma_diagnostic_log_path())) && is_writable(dirname(ma_diagnostic_log_path())), ma_diagnostic_log_path()],
    ['ZIP update engine', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'ZipArchive চালু' : 'ZipArchive নেই'],
    ['Update rollback storage', is_dir(ame_update_backups_dir()) ? is_writable(ame_update_backups_dir()) : is_writable(dirname(ame_update_backups_dir())), ame_update_backups_dir()],
    ['Schema version sync', $schema_ready, $schema_ready ? 'অ্যাপ ভার্সনের সঙ্গে মিলেছে' : 'Schema sync প্রয়োজন'],
    ['Update recovery checkpoints', count($update_snapshots) > 0, count($update_snapshots) . '/5 সংরক্ষিত'],
    ['Staging শনাক্তকরণ', function_exists('ame_is_staging_app'), function_exists('ame_is_staging_app') && ame_is_staging_app() ? 'এই instance staging' : 'এই instance live; staging আলাদা রাখুন'],
];
$module_states = [];
if (ame_module_control_ensure($db)) {
    $module_result = $db->query("SELECT module_key,reason FROM module_controls WHERE enabled=0 ORDER BY module_key");
    if ($module_result) {
        $module_states = $module_result->fetch_all(MYSQLI_ASSOC);
    }
}
$recent_errors = function_exists('ame_recent_error_count') ? ame_recent_error_count(24) : 0;
$update_locked = function_exists('ame_update_is_actively_locked') && ame_update_is_actively_locked();
?>
<style>
.sh-wrap { padding: 15px; }
.sh-card { background:#fff; border-radius:16px; max-width:560px; margin:0 auto; overflow:hidden; box-shadow:0 8px 24px rgba(11,30,77,.08); }
.sh-head { padding:20px 18px 8px; }
.sh-head h3 { margin:0; color:#0B1E4D; }
.sh-head small { color:#64748B; }
.sh-row { display:flex; justify-content:space-between; gap:12px; padding:12px 18px; border-top:1px solid #F1F5F9; font-size:13px; }
.sh-row b { color:#0B1E4D; }
.sh-ok { color:#0E8F52; font-weight:800; }
.sh-bad { color:#C62828; font-weight:800; }
</style>
<div class="sh-wrap">
  <div class="sh-card">
    <div class="sh-head">
      <h3>সিস্টেম হেলথ</h3>
      <small>দোকান চালু রাখার জন্য দরকারি চেক</small>
    </div>
    <?php foreach ($checks as $c): ?>
    <div class="sh-row">
      <b><?php echo esc_html($c[0]); ?></b>
      <span class="<?php echo $c[1] ? 'sh-ok' : 'sh-bad'; ?>"><?php echo esc_html($c[2]); ?></span>
    </div>
    <?php endforeach; ?>
    <div class="sh-row">
      <b>ডাটাবেজ টেবিল</b>
      <span class="<?php echo $missing ? 'sh-bad' : 'sh-ok'; ?>"><?php echo $ok; ?>/<?php echo count($tables); ?> ঠিক আছে</span>
    </div>
    <?php if ($missing): ?>
    <div class="sh-row"><span class="sh-bad">নেই: <?php echo esc_html(implode(', ', $missing)); ?></span></div>
    <?php endif; ?>
    <div class="sh-row">
      <b>গত ২৪ ঘণ্টার error log</b>
      <span class="<?php echo $recent_errors === 0 ? 'sh-ok' : 'sh-bad'; ?>"><?php echo (int)$recent_errors; ?>টি</span>
    </div>
    <div class="sh-row">
      <b>Safe update lock</b>
      <span class="<?php echo $update_locked ? 'sh-bad' : 'sh-ok'; ?>"><?php echo $update_locked ? 'আপডেট চলছে' : 'খালি'; ?></span>
    </div>
    <div class="sh-row">
      <b>সাময়িক বন্ধ মডিউল</b>
      <span class="<?php echo $module_states ? 'sh-bad' : 'sh-ok'; ?>"><?php echo $module_states ? esc_html(implode(', ', array_column($module_states, 'module_key'))) : 'কোনোটিই নয়'; ?></span>
    </div>
    <?php if ($guard): ?>
    <div class="sh-row">
      <b>.env ওয়েবরুটের বাইরে</b>
      <span class="<?php echo ($guard['env_outside'] && !$guard['env_in_webroot']) ? 'sh-ok' : 'sh-bad'; ?>">
        <?php echo ($guard['env_outside'] && !$guard['env_in_webroot']) ? 'ঠিক' : ($guard['env_in_webroot'] ? 'public_html-এ আছে' : 'নেই'); ?>
      </span>
    </div>
    <div class="sh-row">
      <b>সর্বশেষ SQL ব্যাকআপ</b>
      <?php
        $age = $guard['latest_backup_age'];
        $fresh = $age !== null && $age <= 2 * 86400;
        $blabel = $guard['latest_backup']
            ? date('d-m-Y H:i', $guard['latest_backup']['mtime'])
            : 'এখনো হয়নি — Cron বা ম্যানুয়াল ব্যাকআপ দিন';
      ?>
      <span class="<?php echo $fresh ? 'sh-ok' : 'sh-bad'; ?>"><?php echo esc_html($blabel); ?></span>
    </div>
    <div class="sh-row">
      <b>প্রজেক্ট রক্ষা</b>
      <span><a href="project_care.php" style="font-weight:800;color:#0B1E4D;">নিয়ম খুলুন</a></span>
    </div>
    <div class="sh-row">
      <b>টাকা মিল</b>
      <span><a href="money_guard.php" style="font-weight:800;color:#0B1E4D;">কাস্টমার/ক্যাশ/সাপ্লায়ার</a></span>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
