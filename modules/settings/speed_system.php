<?php
require_once '../../includes/config.php';
admin_only();

$speedFile = dirname(__DIR__, 2) . '/SPEED.txt';
$note = is_file($speedFile) ? (string)file_get_contents($speedFile) : 'SPEED.txt পাওয়া যায়নি। আপডেট ZIP-এ এই ফাইল থাকতে হবে।';
$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : (is_file(dirname(__DIR__, 2) . '/VERSION.txt') ? trim((string)file_get_contents(dirname(__DIR__, 2) . '/VERSION.txt')) : '?');

include '../../partials/header.php';
?>
<style>
.spd { max-width: 720px; margin: 0 auto; padding: 12px 12px 28px; }
.spd h1 { margin: 8px 0 6px; font-size: 22px; color: #003f8f; }
.spd .lead { margin: 0 0 14px; font-weight: 700; color: #334155; font-size: 14px; line-height: 1.55; }
.spd-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; }
.spd-card pre {
  margin: 0; white-space: pre-wrap; word-break: break-word;
  font-family: "Noto Sans Bengali", "Noto Sans", ui-sans-serif, sans-serif;
  font-size: 14px; line-height: 1.55; font-weight: 650; color: #0f172a;
}
.spd-meta { margin-top: 12px; font-size: 13px; font-weight: 700; color: #64748b; }
.spd-meta a { color: #003f8f; font-weight: 800; }
</style>
<div class="spd">
  <h1>স্পিড সিস্টেম</h1>
  <p class="lead">প্রজেক্ট দ্রুত ও হালকা রাখার স্থায়ী নোট। পরের ডেভেলপার বা যেকোনো আপডেট এই নিয়মে কাজ করবে। ফাইল <code>SPEED.txt</code> — মুছবেন না।</p>
  <div class="spd-card"><pre><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></pre></div>
  <p class="spd-meta">ভার্সন <?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?> · <a href="/modules/settings/project_care.php">প্রজেক্ট রক্ষা</a> · <a href="/modules/settings/easy_update.php">সহজ আপডেট</a></p>
</div>
<?php include '../../partials/footer.php'; ?>
