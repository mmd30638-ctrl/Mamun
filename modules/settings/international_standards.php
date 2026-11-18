<?php
require_once '../../includes/config.php';
admin_only();

$stdFile = dirname(__DIR__, 2) . '/INTERNATIONAL_STANDARDS.txt';
$note = is_file($stdFile) ? (string)file_get_contents($stdFile) : 'INTERNATIONAL_STANDARDS.txt পাওয়া যায়নি।';
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '?';

include '../../partials/header.php';
?>
<style>
.std { max-width: 720px; margin: 0 auto; padding: 12px 12px 28px; }
.std h1 { margin: 8px 0 6px; font-size: 22px; color: #003f8f; }
.std .lead { margin: 0 0 14px; font-weight: 700; color: #334155; font-size: 14px; line-height: 1.55; }
.std-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; }
.std-card pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: "Noto Sans Bengali", sans-serif; font-size: 14px; line-height: 1.55; font-weight: 650; color: #0f172a; }
.std-meta { margin-top: 12px; font-size: 13px; font-weight: 700; color: #64748b; }
.std-meta a { color: #003f8f; font-weight: 800; }
</style>
<div class="std">
  <h1>আন্তর্জাতিক মান</h1>
  <p class="lead">প্রজেক্টকে ধাপে ধাপে ১০০% আন্তর্জাতিক মানের দিকে নিয়ে যাওয়ার স্থায়ী নীতিমালা। ফাইল <code>INTERNATIONAL_STANDARDS.txt</code> — মুছবেন না।</p>
  <div class="std-card"><pre><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></pre></div>
  <p class="std-meta">ভার্সন <?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?> · <a href="/modules/settings/speed_system.php">স্পিড সিস্টেম</a> · <a href="/modules/settings/project_care.php">প্রজেক্ট রক্ষা</a> · <a href="/modules/settings/security_health.php">সিকিউরিটি হেলথ</a></p>
</div>
<?php include '../../partials/footer.php'; ?>
