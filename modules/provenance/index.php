<?php
require_once '../../includes/config.php';
admin_only();
ensure_provenance_schema($db);
$msg = '';
if (($_POST['action'] ?? '') === 'backfill') {
    csrf_verify();
    $n = ame_provenance_backfill($db, 60);
    $msg = $n . 'টা খাতায় স্ট্যাম্প বসানো হয়েছে (যা আগে ছিল না)।';
}
$recent = [];
$res = $db->query("SELECT * FROM ledger_provenance ORDER BY id DESC LIMIT 30");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $r['ok'] = ame_provenance_verify_row($db, $r);
        $recent[] = $r;
    }
}
$chain = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM ledger_provenance", 0);
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/studio.css?v=<?php echo $sys_version ?? '3.3'; ?>">
<link rel="stylesheet" href="/assets/css/pulse.css?v=<?php echo $sys_version ?? '3.3'; ?>">

<div class="studio-page">
  <div class="studio-hero">
    <div class="studio-kicker">Digital provenance</div>
    <h1>বিলের <em>জন্মসূত্র</em></h1>
    <div class="studio-hairline"></div>
    <p>প্রতিটি নতুন সেল ও পেমেন্ট একটা স্ট্যাম্প ও হ্যাশ-চেইন পায়। কাস্টমার `/verify.php` দিয়ে আসল কি না দেখতে পারে — ফোন নম্বর ছাড়া।</p>
  </div>
  <div class="studio-shell">
    <?php if ($msg): ?><div class="flash-ok"><?php echo esc_html($msg); ?></div><?php endif; ?>
    <div class="studio-panel">
      <h2>চেইনে <?php echo $chain; ?>টা স্ট্যাম্প</h2>
      <p class="lead">পুরনো বিল স্ট্যাম্প করতে একবার চাপুন। নতুন সেল/পেমেন্ট আপনাআপনি বসবে।</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="backfill">
        <button class="btn-ink" type="submit">শেষ ৬০টা খাতায় স্ট্যাম্প</button>
        <a class="btn-ghost" href="/verify.php" style="margin-left:8px;text-decoration:none;display:inline-block;">পাবলিক যাচাই</a>
      </form>
    </div>
    <div class="studio-panel" style="margin-top:1rem;">
      <h2>সাম্প্রতিক</h2>
      <?php if (!$recent): ?>
      <p class="lead" style="margin:0;">এখনো খালি — একটা সেল করুন, বা উপরে ব্যাকফিল চাপুন।</p>
      <?php else: foreach ($recent as $r): ?>
      <div class="due-row">
        <div>
          <div class="nm"><?php echo esc_html($r['stamp']); ?> <?php echo $r['ok'] ? '' : '· ভাঙা'; ?></div>
          <div class="meta"><?php echo esc_html($r['kind'] . ' · ' . $r['label'] . ' · ' . $r['created_at']); ?></div>
        </div>
        <div class="due-amt"><?php echo number_format((float)$r['amount'], 0); ?></div>
        <div class="pulse-btns"><a href="/verify.php?s=<?php echo rawurlencode($r['stamp']); ?>">যাচাই</a></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
