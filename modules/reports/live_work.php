<?php
require_once '../../includes/config.php';
require_once '../../includes/live_work.php';
ame_live_work_guard($db);

$ame_body_class = trim((string)($ame_body_class ?? '') . ' ame-live-work');
$bootIn = [
    'mode' => (string)($_GET['mode'] ?? 'live'),
    'date' => (string)($_GET['date'] ?? ''),
    'month' => (string)($_GET['month'] ?? ''),
    'year' => (string)($_GET['year'] ?? ''),
    'user' => (int)($_GET['user'] ?? 0),
];
$pack = ame_live_work_payload($db, $bootIn);
$span = $pack['span'];
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/live-work.css?v=<?php echo $ver; ?>">
<div class="lw" id="lwRoot">
  <header class="lw-hero">
    <div class="lw-hero-copy">
      <p class="lw-kicker"><span class="lw-dot<?php echo !empty($span['live']) ? ' on' : ''; ?>"></span> লাইভ ড্যাশবোর্ড</p>
      <h1>কে কী করলো</h1>
      <p>প্রতিটি ব্যবহারকারীর সারাদিনের কাজ — দিন, তারিখ, মাস ও বছর ধরে। চলমান ড্যাশবোর্ড নিজেই সতেজ হয়।</p>
    </div>
    <div class="lw-hero-clock">
      <small id="lwDateLabel"><?php echo htmlspecialchars($span['from'] === $span['to'] ? $span['from'] : ($span['from'] . ' → ' . $span['to']), ENT_QUOTES, 'UTF-8'); ?></small>
      <b id="lwNow"><?php echo htmlspecialchars($pack['now_clock'], ENT_QUOTES, 'UTF-8'); ?></b>
      <em id="lwLiveTag"><?php echo !empty($span['live']) ? 'লাইভ' : 'স্থির দৃশ্য'; ?></em>
    </div>
  </header>

  <nav class="lw-modes" id="lwModes">
    <?php
    $modes = [
        'live' => 'লাইভ',
        'today' => 'আজ',
        'yesterday' => 'গতকাল',
        'week' => 'সপ্তাহ',
        'month' => 'মাস',
        'year' => 'বছর',
        'date' => 'তারিখ',
    ];
    foreach ($modes as $k => $lab) {
        $on = ($span['mode'] === $k) ? ' on' : '';
        echo '<button type="button" class="lw-chip' . $on . '" data-mode="' . $k . '">' . $lab . '</button>';
    }
    ?>
  </nav>

  <form class="lw-filters" id="lwForm">
    <label>তারিখ<input type="date" name="date" id="lwDate" value="<?php echo htmlspecialchars($span['date'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>মাস<input type="month" name="month" id="lwMonth" value="<?php echo htmlspecialchars($span['month'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>বছর
      <select name="year" id="lwYear">
        <?php
        $yy = (int)date('Y');
        for ($y = $yy + 1; $y >= $yy - 6; $y--) {
            $sel = ((string)$y === (string)$span['year']) ? ' selected' : '';
            echo '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
        }
        ?>
      </select>
    </label>
    <label>ব্যবহারকারী
      <select name="user" id="lwUser">
        <option value="0">সবাই</option>
        <?php foreach (($pack['directory'] ?? $pack['people']) as $p): ?>
          <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)$pack['user'] === (int)$p['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($p['name'] . ' · ' . $p['label'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="button" class="ame-date-apply" id="lwApply">তারিখ অনুযায়ী দেখুন</button>
    <a class="lw-book" href="/modules/settings/activity_logs.php">খাতা</a>
  </form>

  <section class="lw-kpis" id="lwKpis"></section>
  <section class="lw-layout">
    <aside class="lw-people" id="lwPeople"></aside>
    <div class="lw-main">
      <div class="lw-chart-card">
        <div class="lw-chart-head">
          <h2 id="lwChartTitle">ঘণ্টার ছন্দ</h2>
          <p id="lwChartHint">কোন ঘণ্টায় কত কাজ</p>
        </div>
        <div class="lw-bars" id="lwBars"></div>
      </div>
      <div class="lw-feed-card">
        <div class="lw-chart-head">
          <h2>এখন যা চলছে</h2>
          <p>নতুন কাজ উপরে আসে</p>
        </div>
        <ol class="lw-feed" id="lwFeed"></ol>
      </div>
    </div>
  </section>
</div>
<script>
window.LW_BOOT = <?php echo json_encode($pack, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/assets/js/live-work.js?v=<?php echo $ver; ?>" defer></script>
<?php include '../../partials/footer.php'; ?>
