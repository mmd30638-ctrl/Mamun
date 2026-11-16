<?php
require_once __DIR__ . '/includes/config.php';
$stamp = strtoupper(trim((string)($_GET['s'] ?? $_POST['s'] ?? '')));
$row = null;
$ok = false;
if ($stamp !== '' && isset($db) && $db instanceof mysqli) {
    try {
        $row = ame_provenance_by_stamp($db, $stamp);
        $ok = $row ? ame_provenance_verify_row($db, $row) : false;
    } catch (Throwable $e) {
        $row = null;
    }
}
$logged_in = isset($_SESSION['user_id']);
$ver = htmlspecialchars((string)($sys_version ?? AME_ASSET_VERSION), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>প্রুভেন্যান্স যাচাই</title>
  <link rel="stylesheet" href="/assets/css/studio.css?v=<?php echo $ver; ?>">
  <link rel="stylesheet" href="/assets/css/premium-saas.css?v=<?php echo $ver; ?>">
</head>
<body class="studio-page" style="margin:0;min-height:100vh;">
  <div class="studio-hero">
    <div class="studio-kicker">Digital provenance · 2026</div>
    <h1>এই স্লিপ কি <em>আসল</em>?</h1>
    <div class="studio-hairline"></div>
    <p>স্ট্যাম্প দিলে খাতার চেইন মিলিয়ে বলা হয় — নাম-ফোন-টাকা এখানে দেখায় না।</p>
  </div>
  <div class="studio-shell">
    <form class="studio-panel" method="get" action="/verify.php">
      <label class="lead" for="s">প্রুভেন্যান্স কোড</label>
      <input id="s" name="s" value="<?php echo esc_html($stamp); ?>" placeholder="AP-XXXXXXXXXX" style="width:100%;padding:12px;margin:8px 0 14px;border:1px solid rgba(20,17,14,.12);font-size:16px;letter-spacing:.08em;">
      <button class="btn-ink" type="submit">যাচাই করুন</button>
    </form>
    <?php if ($stamp !== ''): ?>
    <div class="studio-panel" style="margin-top:1rem;">
      <?php if ($ok && $row): ?>
        <div class="studio-kicker">Chain intact</div>
        <h2>মিলছে</h2>
        <p class="lead">এই স্ট্যাম্প খাতায় আছে এবং হ্যাশ মিলেছে। স্লিপটি আসল বলে ধরা যায়।</p>
        <?php if ($logged_in): ?>
        <p class="lead" style="margin:0;"><?php echo esc_html($row['kind']); ?> · <?php echo esc_html($row['label']); ?> · <?php echo number_format((float)$row['amount'], 2); ?> ৳ · <?php echo esc_html($row['created_at']); ?></p>
        <?php endif; ?>
      <?php elseif ($row && !$ok): ?>
        <h2>চেইন ভাঙা</h2>
        <p class="lead" style="margin:0;">কোড আছে, কিন্তু হ্যাশ মিলছে না। এই স্লিপ বিশ্বাস করবেন না।</p>
      <?php else: ?>
        <h2>পাওয়া যায়নি</h2>
        <p class="lead" style="margin:0;">এই কোড খাতায় নেই। ভুল টাইপ, বা পুরনো বিল এখনো স্ট্যাম্প হয়নি।</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
