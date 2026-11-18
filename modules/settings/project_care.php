<?php
require_once '../../includes/config.php';
require_once '../../includes/backup_functions.php';
admin_only();
include '../../partials/header.php';

$g = ame_file_guard_status();
$backup_ok = $g['latest_backup'] && $g['latest_backup_age'] !== null && $g['latest_backup_age'] <= 2 * 86400;
$backup_label = 'এখনো হয়নি';
if ($g['latest_backup']) {
    $backup_label = $g['latest_backup']['name'] . ' · ' . date('d-m-Y h:i A', $g['latest_backup']['mtime']);
}
$env_ok = $g['env_outside'] && !$g['env_in_webroot'];
?>
<style>
.pcare { padding: 14px; max-width: 640px; margin: 0 auto; }
.pcare-card { background:#fff; border-radius:16px; box-shadow:0 8px 24px rgba(11,30,77,.08); overflow:hidden; margin-bottom:14px; }
.pcare-head { padding:18px 16px 10px; }
.pcare-head h3 { margin:0; color:#0B1E4D; font-size:18px; }
.pcare-head p { margin:6px 0 0; color:#64748B; font-size:13px; line-height:1.5; }
.pcare-row { display:flex; justify-content:space-between; gap:12px; padding:11px 16px; border-top:1px solid #F1F5F9; font-size:13px; }
.pcare-row b { color:#0B1E4D; }
.pcare-ok { color:#0E8F52; font-weight:800; }
.pcare-bad { color:#C62828; font-weight:800; }
.pcare-body { padding:4px 16px 16px; }
.pcare-body h4 { margin:14px 0 6px; color:#0B1E4D; font-size:14px; }
.pcare-body ol, .pcare-body ul { margin:0; padding-left:1.2em; color:#334155; font-size:13.5px; line-height:1.65; }
.pcare-body li { margin:4px 0; }
.pcare-warn { background:#FFF8E1; border:1px solid #F6C445; border-radius:10px; padding:10px 12px; font-size:13px; color:#0B1E4D; margin-top:10px; }
.pcare-danger { background:#FFEBEE; border:1px solid #EF9A9A; border-radius:10px; padding:10px 12px; font-size:13px; color:#B71C1C; margin-top:10px; }
.pcare-btns { display:grid; gap:8px; margin-top:12px; }
.pcare-btns a { display:block; text-align:center; padding:12px; border-radius:10px; background:#0B1E4D; color:#fff; font-weight:800; text-decoration:none; font-size:14px; }
.pcare-btns a.sec { background:#1565c0; }
.pcare-btns a.quiet { background:#fff; color:#0B1E4D; border:1px solid #90caf9; }
</style>
<div class="pcare">
  <div class="pcare-card">
    <div class="pcare-head">
      <h3>🛡️ প্রজেক্ট রক্ষা</h3>
      <p>কোড আর হিসাব দুই আলাদা জিনিস। দুটো মিলিয়ে এক ফাইলে রাখলে বা পুরনো ডাম্প চাপলে দোকান নষ্ট হয়।</p>
    </div>
    <div class="pcare-row">
      <b>সফটওয়্যার ভার্সন</b>
      <span class="pcare-ok"><?php echo esc_html($g['app_version'] ?: '—'); ?></span>
    </div>
    <div class="pcare-row">
      <b>.env ওয়েবরুটের বাইরে</b>
      <span class="<?php echo $env_ok ? 'pcare-ok' : 'pcare-bad'; ?>"><?php echo $env_ok ? 'ঠিক' : ($g['env_in_webroot'] ? 'public_html-এ আছে — সরিয়ে ফেলুন' : 'পাওয়া যায়নি'); ?></span>
    </div>
    <div class="pcare-row">
      <b>রাতের SQL ব্যাকআপ</b>
      <span class="<?php echo $backup_ok ? 'pcare-ok' : 'pcare-bad'; ?>"><?php echo esc_html($backup_label); ?></span>
    </div>
    <div class="pcare-row">
      <b>uploads লেখা যায়</b>
      <span class="<?php echo $g['uploads_writable'] ? 'pcare-ok' : 'pcare-bad'; ?>"><?php echo $g['uploads_writable'] ? 'ঠিক' : 'বন্ধ'; ?></span>
    </div>
  </div>

  <div class="pcare-card">
    <div class="pcare-head">
      <h3>দুই রকম ফাইল — মিশাবেন না</h3>
    </div>
    <div class="pcare-body">
      <h4>১. কোড (সফটওয়্যার)</h4>
      <ul>
        <li>PHP, CSS, JS — ZIP / সহজ আপডেট দিয়ে যায়।</li>
        <li>এতে দোকানের বিক্রয়-ক্রয় হিসাব থাকে না।</li>
        <li><code>.env</code> কখনো <code>public_html</code>-এ রাখবেন না। Hostinger-এ ওয়েবরুটের <b>এক ধাপ উপরে</b>।</li>
      </ul>
      <h4>২. হিসাব (ডাটা)</h4>
      <ul>
        <li>ডাটাবেজ — সেটিংস থেকে <b>.sql ব্যাকআপ</b>।</li>
        <li>ছবি — <code>uploads/</code> ফোল্ডার। ZIP এক্সট্রাক্ট করলে এই ফোল্ডার মুছবেন না।</li>
        <li>পুরনো POS-এর phpMyAdmin ডাম্প সরাসরি APONSHOP-এর <code>accounts</code> টেবিলে চালাবেন না।</li>
      </ul>
    </div>
  </div>

  <div class="pcare-card">
    <div class="pcare-head">
      <h3>নিয়মিত যা করবেন</h3>
    </div>
    <div class="pcare-body">
      <h4>প্রতি সপ্তাহে</h4>
      <ol>
        <li>এখান থেকে SQL ব্যাকআপ ফোনে/গুগল ড্রাইভে রাখুন।</li>
        <li>একটা কোড ZIP-ও ড্রাইভে রাখুন — litterbox লিংক ~৭২ ঘণ্টায় মরে।</li>
      </ol>
      <h4>Hostinger Cron (রোজ রাত)</h4>
      <ol>
        <li>hPanel → Advanced → Cron Jobs।</li>
        <li>কমান্ড: <code>php /home/…/public_html/includes/cron_backup.php</code> — File Manager থেকে সঠিক পাথ বসান।</li>
        <li>সময়: রাত ২টা। ফাইল ওয়েবরুটের বাইরে <code>db_backups/</code>-এ জমে, ৭ দিন রাখে।</li>
      </ol>
      <h4>নতুন ZIP তোলার আগে-পরে</h4>
      <ol>
        <li>আগে SQL ব্যাকআপ নিন।</li>
        <li>সেটিংস → সহজ আপডেট — অথবা ZIP-এর <b>ভিতরের ফাইল</b> লাইভ <code>public_html</code>-এ। <code>shop</code> নামে আরেক ফোল্ডার বানাবেন না।</li>
        <li>স্কিমা সিঙ্ক চালান (টেবিল মুছে না, শুধু যা নেই তা যোগ)।</li>
        <li>ব্রাউজার হার্ড রিফ্রেশ। একবার <a href="/offline-fix.html">/offline-fix.html</a> খুললে পুরনো PWA ক্যাশ ছাড়ে।</li>
      </ol>
      <div class="pcare-btns">
        <a href="backup.php">💾 এখনই SQL ব্যাকআপ</a>
        <a class="sec" href="/download.php">📦 কোড ZIP</a>
        <a class="quiet" href="schema_sync.php">🗄️ স্কিমা সিঙ্ক</a>
        <a class="quiet" href="money_guard.php">৳ টাকা মিল</a>
        <a class="quiet" href="system_health.php">🔍 সিস্টেম হেলথ</a>
        <a class="quiet" href="speed_system.php">⚡ স্পিড সিস্টেম</a>
        <a class="quiet" href="easy_update.php">🚀 সহজ আপডেট</a>
      </div>
    </div>
  </div>

  <div class="pcare-card">
    <div class="pcare-head">
      <h3>এগুলো করলে সমস্যা হয়</h3>
    </div>
    <div class="pcare-body">
      <div class="pcare-danger">
        রিস্টোর / ডাটা ক্লিয়ার বর্তমান হিসাব মুছে দেয়। চাপার আগে তাজা SQL ব্যাকআপ ছাড়া নয়।
      </div>
      <div class="pcare-warn">
        File Manager-এ ZIP খুলে <code>uploads</code> ওভাররাইট/ডিলিট করবেন না। সহজ আপডেট .env ও uploads রেখে কোড বদলায়।
      </div>
      <ul>
        <li>একই সাপ্লায়ারের একই ইনভয়েস নম্বর দুবার দেবেন না — সফটওয়্যার আটকায়।</li>
        <li>খুচরা মূল্য ক্রয়ের কম হলে ক্রয় সেভ হয় না — ইচ্ছা করে নয়, হিসাব রক্ষায়।</li>
        <li>লাইভ সাইট আর ফোনের ZIP আলাদা ভার্সন হলে আগে ভার্সন নম্বর মিলিয়ে নিন (এখন v<?php echo esc_html($g['app_version']); ?>)।</li>
      </ul>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
