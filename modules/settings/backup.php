<?php
require_once '../../includes/config.php';
require_once '../../includes/daily_backup.php';
admin_only();

$dir = ame_daily_backup_dir();
$files = ame_daily_backup_files($dir);
$latest = $files[0] ?? null;
$todayDone = ame_daily_backup_today_done($dir);
$wait = ame_manual_backup_wait_seconds($dir);
$cron = ame_daily_backup_cron_lines();
$flashOk = (string) ($_SESSION['da_bak_ok'] ?? '');
$flashErr = (string) ($_SESSION['da_bak_err'] ?? '');
unset($_SESSION['da_bak_ok'], $_SESSION['da_bak_err']);
$en = function_exists('is_en') && is_en();

include '../../partials/header.php';
?>
<style>
.ame-bak{max-width:920px;margin:18px auto 40px;padding:0 12px;font-family:"Noto Sans Bengali","Segoe UI",sans-serif}
.ame-bak h1{margin:0 0 8px;font-size:26px;font-weight:850;color:#047857}
.ame-bak-lead{margin:0 0 16px;font-weight:700;color:#1D4ED8;line-height:1.55}
.ame-bak-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 16px}
.ame-bak .ame-bak-card,.ame-bak .ame-bak-flash,.ame-bak .ame-bak-cron,.ame-bak .ame-bak-sha,.ame-bak .ame-bak-btn,.ame-bak code,.ame-bak td,.ame-bak th{
  background:#ffffff !important;
}
.ame-bak-card{border:1px solid #E6ECF4;border-radius:16px;padding:16px;box-shadow:0 8px 18px rgba(27,42,74,.06)}
.ame-bak-card h2{margin:0 0 8px;font-size:16px;font-weight:850}
.ame-bak-h-now{color:#047857}
.ame-bak-h-auto{color:#1D4ED8}
.ame-bak-h-list{color:#7C3AED}
.ame-bak-card p,.ame-bak-card li{margin:0 0 8px;font-size:14px;font-weight:650;color:#1D4ED8;line-height:1.5}
.ame-bak-ok{border-color:#86efac}
.ame-bak-wait{border-color:#fdba74}
.ame-bak-flash{border-radius:12px;padding:10px 12px;font-weight:800;margin:0 0 12px;border:1.5px solid #E6ECF4}
.ame-bak-flash.is-ok{color:#047857;border-color:#86efac}
.ame-bak-flash.is-err{color:#B91C1C;border-color:#FECACA}
.ame-bak-cron{display:block;margin-top:8px;padding:10px 12px;border-radius:10px;border:1px solid #E6ECF4;color:#7C3AED;font-size:12px;font-weight:700;word-break:break-all}
.ame-bak-sha{display:inline-block;padding:4px 8px;border-radius:8px;border:1px solid #E6ECF4;color:#C026D3;font-size:12px;font-weight:800;letter-spacing:.02em}
.ame-bak-list{width:100%;border-collapse:collapse}
.ame-bak-list th,.ame-bak-list td{text-align:left;padding:8px 6px;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:middle;background:#ffffff}
.ame-bak-list th:nth-child(1){color:#047857;font-size:11px}
.ame-bak-list th:nth-child(2){color:#1D4ED8;font-size:11px}
.ame-bak-list th:nth-child(3){color:#0F766E;font-size:11px}
.ame-bak-list th:nth-child(4){color:#C026D3;font-size:11px}
.ame-bak-list th:nth-child(5){color:#B45309;font-size:11px}
.ame-bak-amt{font-family:"Noto Sans Bengali","Nirmala UI",sans-serif;font-weight:850;color:#047857;letter-spacing:0}
.ame-bak-time{font-weight:750;color:#1D4ED8}
.ame-bak-size{font-weight:850;color:#0F766E}
.ame-bak-actions{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0}
.ame-bak-btn{min-height:44px;border:1.5px solid #047857;border-radius:10px;padding:0 16px;color:#047857 !important;-webkit-text-fill-color:#047857 !important;font-weight:800;cursor:pointer}
.ame-bak-btn.primary{border-color:#047857;color:#047857 !important;-webkit-text-fill-color:#047857 !important}
.ame-bak-btn:disabled{opacity:.45;cursor:not-allowed}
.ame-bak-btn.ghost{border-color:#1D4ED8;color:#1D4ED8 !important;-webkit-text-fill-color:#1D4ED8 !important}
.ame-bak-btn.dl{border-color:#B45309;color:#B45309 !important;-webkit-text-fill-color:#B45309 !important}
.ame-bak-note{font-size:12px;font-weight:700;color:#64748B;margin-top:10px}
.ame-bak-wait-note{color:#C2410C}
@media (max-width:720px){.ame-bak-grid{grid-template-columns:1fr}}
</style>
<div class="ame-bak">
  <h1><?php echo $en ? 'Backup — download a full ledger copy' : 'ব্যাকআপ — পুরো হিসাব নামান'; ?></h1>
  <p class="ame-bak-lead"><?php echo $en
    ? 'Create a fresh SQL snapshot now and save it on this phone or computer. Nightly backup still runs and keeps 30 days. Photos stay in uploads. This page does not restore the database and does not change customer or supplier amounts.'
    : 'এখনই নতুন SQL কপি তৈরি করে ফোন বা কম্পিউটারে নামান। রাতের অটো ব্যাকআপ আগের মতো চলবে এবং ৩০ দিন রাখা হবে। ছবি uploads ফোল্ডারেই থাকে। এই পাতা থেকে ডাটাবেস ফিরিয়ে বসানো হয় না। কাস্টমার ও সাপ্লায়ারের অ্যামাউন্ট বদলায় না।'; ?></p>

  <?php if ($flashOk !== ''): ?><div class="ame-bak-flash is-ok"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="ame-bak-flash is-err"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

  <div class="ame-bak-grid">
    <section class="ame-bak-card <?php echo $todayDone ? 'ame-bak-ok' : 'ame-bak-wait'; ?>">
      <h2 class="ame-bak-h-now"><?php echo $en ? 'Download now' : 'এখনই নামান'; ?></h2>
      <p><?php echo $en
        ? 'Admin only. CSRF-protected. File is streamed from a private folder — no public link.'
        : 'শুধু অ্যাডমিন। নিরাপত্তা টোকেন লাগে। ফাইল প্রাইভেট ফোল্ডার থেকে সরাসরি নামে — পাবলিক লিংক নেই।'; ?></p>
      <form class="ame-bak-actions" method="post" action="/modules/settings/process_run_backup.php" autocomplete="off">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="download" value="1">
        <button class="ame-bak-btn primary" type="submit" <?php echo $wait > 0 ? 'disabled' : ''; ?>>
          <?php echo $en ? 'Create & download' : 'তৈরি করুন ও নামান'; ?>
        </button>
      </form>
      <form class="ame-bak-actions" method="post" action="/modules/settings/process_run_backup.php" autocomplete="off">
        <?php echo csrf_field(); ?>
        <button class="ame-bak-btn ghost" type="submit" <?php echo $wait > 0 ? 'disabled' : ''; ?>>
          <?php echo $en ? 'Create on server only' : 'শুধু সার্ভারে রাখুন'; ?>
        </button>
      </form>
      <?php if ($wait > 0): ?>
      <p class="ame-bak-note ame-bak-wait-note"><?php echo $en ? ('Wait ' . $wait . 's before another new dump. Existing files can still be downloaded.') : ('নতুন ডাম্পের আগে ' . $wait . ' সেকেন্ড অপেক্ষা। তালিকার ফাইল নামানো যায়।'); ?></p>
      <?php endif; ?>
    </section>
    <section class="ame-bak-card">
      <h2 class="ame-bak-h-auto"><?php echo $en ? 'Nightly automatic copy' : 'রাতের অটো কপি'; ?></h2>
      <?php if ($todayDone && $latest): ?>
      <p><?php echo $en ? 'Today’s automatic file is ready.' : 'আজকের অটো ফাইল তৈরি আছে।'; ?></p>
      <p class="ame-bak-amt"><?php echo htmlspecialchars((string) $latest['file'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p><span class="ame-bak-time"><?php echo date('d M Y, h:i A', (int) $latest['time']); ?></span> · <span class="ame-bak-size"><?php echo number_format(((int) $latest['bytes']) / 1048576, 2); ?> MB</span></p>
      <?php else: ?>
      <p><?php echo $en ? 'The midnight cron still writes a copy at 00:00 Asia/Dhaka.' : 'মধ্যরাতের ক্রন বাংলাদেশ সময় রাত ১২টায় কপি রাখে।'; ?></p>
      <?php endif; ?>
      <code class="ame-bak-cron"><?php echo htmlspecialchars($cron['midnight'], ENT_QUOTES, 'UTF-8'); ?></code>
    </section>
  </div>

  <section class="ame-bak-card">
    <h2 class="ame-bak-h-list"><?php echo $en ? 'Stored copies (7 days)' : 'সার্ভারে রাখা কপি (৭ দিন)'; ?></h2>
    <?php if (!$files): ?>
    <p><?php echo $en ? 'No file yet. Use Create & download.' : 'এখনো ফাইল নেই। «তৈরি করুন ও নামান» চাপুন।'; ?></p>
    <?php else: ?>
    <table class="ame-bak-list">
      <thead>
        <tr>
          <th><?php echo $en ? 'File' : 'ফাইল'; ?></th>
          <th><?php echo $en ? 'Taken' : 'সময়'; ?></th>
          <th><?php echo $en ? 'Size' : 'সাইজ'; ?></th>
          <th>SHA-256</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($files as $row):
        $fname = ame_daily_backup_safe_name((string) $row['file']);
        if ($fname === '') {
            continue;
        }
        $sha = ame_backup_file_sha256((string) $row['path']);
        $shaShort = $sha !== '' ? substr($sha, 0, 12) . '…' : '—';
      ?>
        <tr>
          <td class="ame-bak-amt"><?php echo htmlspecialchars($fname, ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="ame-bak-time"><?php echo date('d M Y, h:i A', (int) $row['time']); ?></td>
          <td class="ame-bak-size"><?php echo number_format(((int) $row['bytes']) / 1048576, 2); ?> MB</td>
          <td title="<?php echo htmlspecialchars($sha, ENT_QUOTES, 'UTF-8'); ?>"><code class="ame-bak-sha"><?php echo htmlspecialchars($shaShort, ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td>
            <form method="post" action="/modules/settings/process_backup_download.php" autocomplete="off">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="file" value="<?php echo htmlspecialchars($fname, ENT_QUOTES, 'UTF-8'); ?>">
              <button class="ame-bak-btn dl" type="submit"><?php echo $en ? 'Download' : 'নামান'; ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <p class="ame-bak-note"><?php echo $en
      ? 'Private folder, not web-public. Pictures stay in uploads. Restore is a separate admin page.'
      : 'প্রাইভেট ফোল্ডার — ওয়েবে খোলা যায় না। ছবি uploads-এই থাকে। রিস্টোর আলাদা অ্যাডমিন পেজ।'; ?></p>
  </section>
</div>
<?php include '../../partials/footer.php'; ?>
