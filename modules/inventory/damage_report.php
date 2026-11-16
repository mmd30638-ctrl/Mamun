<?php
require_once '../../includes/config.php';
require_once '../../includes/damage_functions.php';
require_permission($db, 'm-inv');
ame_damage_ensure($db);

$today = date('Y-m-d');
$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? $today));
$search = trim((string) ($_GET['q'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$rep = ame_damage_report($db, $from, $to, $search);
$reasons = $rep['reasons'];
$back = http_build_query(['from' => $from, 'to' => $to, 'q' => $search]);
$flash = (string) ($_SESSION['dmg_flash'] ?? '');
$flashOk = !empty($_SESSION['dmg_ok']);
unset($_SESSION['dmg_flash'], $_SESSION['dmg_ok']);
$ame_body_class = 'ame-damage-page';
include '../../partials/header.php';
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.545';
?>
<link rel="stylesheet" href="/assets/css/damage-control.css?v=<?php echo htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="dmg">
    <header class="dmg-hero">
        <div>
            <h1>ক্ষতিগ্রস্ত পণ্য — রিপোর্ট</h1>
            <p><?php echo htmlspecialchars($from . ' — ' . $to, ENT_QUOTES, 'UTF-8'); ?> · চালু <?php echo (int) $rep['n']; ?> · ক্ষতি ৳<?php echo number_format((float) $rep['loss'], 2); ?></p>
        </div>
        <nav class="dmg-tiles" style="width:min(100%,320px);grid-template-columns:1fr 1fr;">
            <a class="dmg-tile t1" href="damage.php">ডেস্কে ফিরুন</a>
            <button type="button" class="dmg-tile t2" onclick="window.print()">প্রিন্ট</button>
        </nav>
    </header>
    <?php if ($flash !== ''): ?>
        <div class="dmg-flash<?php echo $flashOk ? ' is-ok' : ''; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form class="dmg-filters" method="get" action="damage_report.php">
        <div class="dmg-grid">
            <label class="dmg-field"><span>শুরু</span><input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="dmg-field"><span>শেষ</span><input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="dmg-field"><span>খুঁজুন</span><input type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <button class="dmg-btn edit" type="submit">রিপোর্ট দেখুন</button>
        </div>
    </form>
    <div class="dmg-kpis">
        <article class="dmg-kpi k1"><span>চালু</span><b><?php echo (int) $rep['n']; ?></b></article>
        <article class="dmg-kpi k2"><span>পরিমাণ</span><b><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $rep['qty']), ENT_QUOTES, 'UTF-8'); ?></b></article>
        <article class="dmg-kpi k3"><span>ক্ষতি</span><b>৳<?php echo number_format((float) $rep['loss'], 2); ?></b></article>
        <article class="dmg-kpi k4"><span>বাতিল</span><b><?php echo (int) $rep['void_n']; ?></b></article>
    </div>
    <div class="dmg-reason-grid">
        <?php foreach ($rep['by_reason'] as $box): ?>
            <article class="dmg-reason-card">
                <span><?php echo htmlspecialchars((string) $box['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <b><?php echo (int) $box['n']; ?></b>
                <small><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $box['qty']), ENT_QUOTES, 'UTF-8'); ?> · ৳<?php echo number_format((float) $box['loss'], 2); ?></small>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="dmg-sheet">
        <?php if (!$rep['rows']): ?>
            <div class="dmg-empty">এই সময়ে রিপোর্টে কিছু নেই।</div>
        <?php endif; ?>
        <?php foreach ($rep['rows'] as $row):
            $rid = (int) $row['id'];
            $voided = (string) $row['status'] !== 'posted';
            $reasonLabel = $reasons[$row['reason']] ?? (string) $row['reason'];
            ?>
            <article class="dmg-row<?php echo $voided ? ' is-void' : ''; ?>">
                <div><?php echo htmlspecialchars((string) $row['damage_date'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="dmg-who">
                    <strong><?php echo htmlspecialchars((string) $row['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small><?php echo htmlspecialchars((string) ($row['code'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <div><span class="dmg-reason<?php echo $voided ? ' void' : ''; ?>"><?php echo htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $row['qty']), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="dmg-loss">৳<?php echo number_format((float) $row['loss_amount'], 2); ?></div>
                <div class="dmg-ops">
                    <?php if (!$voided): ?>
                        <a class="dmg-btn edit" href="damage-edit.php?id=<?php echo $rid; ?>&amp;back=<?php echo rawurlencode($back); ?>&amp;from=report">এডিট</a>
                        <form method="post" action="process_damage.php" onsubmit="return confirm('স্টক ফিরিয়ে বাতিল হবে?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="void">
                            <input type="hidden" name="id" value="<?php echo $rid; ?>">
                            <input type="hidden" name="from" value="report">
                            <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="dmg-btn void">বাতিল</button>
                        </form>
                    <?php else: ?>
                        <span class="dmg-hint">বাতিল</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
