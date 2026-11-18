<?php
require_once '../../includes/config.php';
require_once '../../includes/damage_functions.php';
require_permission($db, 'm-inv');
ame_damage_ensure($db);

$today = date('Y-m-d');
$range = (string) ($_GET['range'] ?? 'month');
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $range = in_array($range, ['today', 'yesterday', 'week', 'month', 'all'], true) ? $range : 'month';
    if ($range === 'today') {
        $from = $to = $today;
    } elseif ($range === 'yesterday') {
        $from = $to = date('Y-m-d', strtotime('-1 day'));
    } elseif ($range === 'week') {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = $today;
    } elseif ($range === 'all') {
        $from = '2000-01-01';
        $to = $today;
    } else {
        $range = 'month';
        $from = date('Y-m-01');
        $to = $today;
    }
} else {
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($from === $today && $to === $today) {
        $range = 'today';
    } elseif ($from === $yesterday && $to === $yesterday) {
        $range = 'yesterday';
    } elseif ($from === date('Y-m-d', strtotime('-6 days')) && $to === $today) {
        $range = 'week';
    } elseif ($from === date('Y-m-01') && $to === $today) {
        $range = 'month';
    } elseif ($from === '2000-01-01' && $to === $today) {
        $range = 'all';
    } else {
        $range = 'custom';
    }
}

$rep = ame_damage_report($db, $from, $to, $search);
$reasons = $rep['reasons'];
$rows = $rep['rows'];
$flash = (string) ($_SESSION['dmg_flash'] ?? '');
$flashOk = !empty($_SESSION['dmg_ok']);
unset($_SESSION['dmg_flash'], $_SESSION['dmg_ok']);
$keep = ['from' => $from, 'to' => $to, 'q' => $search, 'range' => $range];
$back = http_build_query(array_filter($keep, static fn($v) => $v !== ''));
$chip = static function (string $key, string $label) use ($range, $search): string {
    $q = http_build_query(['range' => $key, 'q' => $search]);
    $on = $range === $key ? ' is-on' : '';
    return '<a class="' . trim($on) . '" href="damage.php?' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
};
$span = $from === '2000-01-01' ? 'সব তারিখ' : ($from . ' — ' . $to);
$ame_body_class = 'ame-damage-page';
include '../../partials/header.php';
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.545';
?>
<link rel="stylesheet" href="/assets/css/damage-control.css?v=<?php echo htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8'); ?>">
<div class="dmg dmg-desk">
    <header class="dmg-hero">
        <div>
            <h1>ক্ষতিগ্রস্ত পণ্য</h1>
            <p>চার্জ বক্সে নাম বা কোড দিন — বিক্রয়ের মতো পণ্য উঠবে। এন্ট্রি, বাতিল ও রিপোর্ট এক ডেস্কে।</p>
        </div>
        <nav class="dmg-tiles" aria-label="ক্ষতি ডেস্ক">
            <a class="dmg-tile t1" href="#dmgCharge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h10M4 17h7"/><circle cx="18" cy="16" r="3"/></svg>
                চার্জ করে নিন
                <small>পণ্য খুঁজে তালিকায়</small>
            </a>
            <a class="dmg-tile t2" href="damage_report.php?<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5h4v14H4zm6 0V9h4v10h-4zm6 0v-7h4v7h-4z"/></svg>
                রিপোর্ট
                <small><?php echo (int) $rep['n']; ?>টি চালু এন্ট্রি</small>
            </a>
            <a class="dmg-tile t3" href="#dmgSheet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14M5 12h14M5 17h9"/></svg>
                খাতা
                <small><?php echo htmlspecialchars($span, ENT_QUOTES, 'UTF-8'); ?></small>
            </a>
            <a class="dmg-tile t4" href="#dmgSheet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6L6 18"/></svg>
                বাতিল
                <small>প্রতি লাইন থেকে</small>
            </a>
        </nav>
    </header>

    <?php if ($flash !== ''): ?>
        <div class="dmg-flash<?php echo $flashOk ? ' is-ok' : ''; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="dmg-charge" id="dmgChargeBox">
        <h2>পণ্য চার্জ বক্স</h2>
        <div class="dmg-charge-box">
            <input type="search" id="dmgCharge" placeholder="নাম, কোড বা বারকোড লিখে চার্জ করুন — দুই অক্ষরের পর পণ্য দেখাবে" autocomplete="off" autofocus>
            <div class="dmg-hits" id="dmgHits" hidden></div>
        </div>
        <p class="dmg-hint">বিক্রয় অপশনের মতো খোঁজার পর পণ্য কার্ড উঠবে। ক্লিক করলে নিচের তালিকায় বসবে। এন্টারে প্রথম পণ্য যাবে।</p>
    </section>

    <form class="dmg-cart" method="post" action="process_damage.php" id="dmgCartForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
        <h2>ক্ষতির তালিকা</h2>
        <label>তারিখ
            <input type="date" name="damage_date" required value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <table>
            <thead>
                <tr><th>পণ্য</th><th>পরিমাণ</th><th>কারণ</th><th>নোট</th><th>ক্ষতি</th><th></th></tr>
            </thead>
            <tbody id="dmgCartBody"></tbody>
        </table>
        <p class="dmg-empty" id="dmgCartEmpty">এখনো পণ্য নেই। উপরে চার্জ করুন।</p>
        <div class="dmg-cart-foot">
            <div>মোট ক্ষতি <b id="dmgCartTotal">৳0.00</b></div>
            <div>
                <button type="button" class="dmg-btn ghost" id="dmgCancelAll">সব বাতিল</button>
                <button type="submit" class="dmg-btn save">স্টক থেকে বাদ দিন</button>
            </div>
        </div>
    </form>
    <script type="application/json" id="dmgReasonsJson"><?php echo json_encode($reasons, JSON_UNESCAPED_UNICODE); ?></script>

    <div class="dmg-kpis">
        <article class="dmg-kpi k1"><span>চালু এন্ট্রি</span><b><?php echo (int) $rep['n']; ?></b><small>বাতিল বাদ</small></article>
        <article class="dmg-kpi k2"><span>মোট পরিমাণ</span><b><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $rep['qty']), ENT_QUOTES, 'UTF-8'); ?></b><small>স্টক থেকে বাদ</small></article>
        <article class="dmg-kpi k3"><span>মোট ক্ষতি</span><b>৳<?php echo number_format((float) $rep['loss'], 2); ?></b><small>পরিমাণ × ক্রয়মূল্য</small></article>
        <article class="dmg-kpi k4"><span>বাতিল</span><b><?php echo (int) $rep['void_n']; ?></b><small>স্টক ফিরেছে</small></article>
    </div>

    <section class="dmg-report">
        <h2>এই সময়ের রিপোর্ট</h2>
        <div class="dmg-reason-grid">
            <?php foreach ($rep['by_reason'] as $box): ?>
                <article class="dmg-reason-card">
                    <span><?php echo htmlspecialchars((string) $box['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <b><?php echo (int) $box['n']; ?></b>
                    <small><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $box['qty']), ENT_QUOTES, 'UTF-8'); ?> · ৳<?php echo number_format((float) $box['loss'], 2); ?></small>
                </article>
            <?php endforeach; ?>
        </div>
        <a class="dmg-btn edit" href="damage_report.php?<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">পূর্ণ রিপোর্ট খুলুন</a>
    </section>

    <form class="dmg-filters" method="get" action="damage.php" id="dmgFilter">
        <input type="hidden" name="range" value="custom">
        <div class="dmg-ranges">
            <?php echo $chip('today', 'আজ'); ?>
            <?php echo $chip('yesterday', 'গতকাল'); ?>
            <?php echo $chip('week', '৭ দিন'); ?>
            <?php echo $chip('month', 'এই মাস'); ?>
            <?php echo $chip('all', 'সব'); ?>
        </div>
        <div class="dmg-grid">
            <label class="dmg-field"><span>শুরুর তারিখ</span><input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="dmg-field"><span>শেষ তারিখ</span><input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="dmg-field"><span>খাতায় খুঁজুন</span><input type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="পণ্য, কোড বা নোট" autocomplete="off"></label>
            <button class="dmg-btn edit" type="submit">খুঁজুন</button>
        </div>
    </form>

    <div class="dmg-sheet" id="dmgSheet">
        <div class="dmg-row dmg-h"><span>তারিখ</span><span>পণ্য</span><span>কারণ</span><span>পরিমাণ</span><span>ক্ষতি</span><span>কাজ</span></div>
        <?php if (!$rows): ?>
            <div class="dmg-empty">এই সময়ে কোনো ড্যামেজ এন্ট্রি নেই।</div>
        <?php endif; ?>
        <?php foreach ($rows as $row):
            $rid = (int) $row['id'];
            $voided = (string) $row['status'] !== 'posted';
            $reasonLabel = $reasons[$row['reason']] ?? (string) $row['reason'];
            ?>
            <article class="dmg-row<?php echo $voided ? ' is-void' : ''; ?>">
                <div data-label="তারিখ"><?php echo htmlspecialchars((string) $row['damage_date'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="dmg-who" data-label="পণ্য">
                    <strong><?php echo htmlspecialchars((string) $row['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small><?php echo htmlspecialchars((string) (($row['code'] ?? '') !== '' ? $row['code'] : '—'), ENT_QUOTES, 'UTF-8'); ?><?php echo trim((string) ($row['note'] ?? '')) !== '' ? ' · ' . htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') : ''; ?></small>
                </div>
                <div data-label="কারণ"><span class="dmg-reason<?php echo $voided ? ' void' : ''; ?>"><?php echo htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $voided ? ' · বাতিল' : ''; ?></span></div>
                <div data-label="পরিমাণ"><?php echo htmlspecialchars(ame_damage_fmt_qty((float) $row['qty']), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="dmg-loss" data-label="ক্ষতি">৳<?php echo number_format((float) $row['loss_amount'], 2); ?></div>
                <div class="dmg-ops">
                    <?php if (!$voided): ?>
                        <a class="dmg-btn edit" href="damage-edit.php?id=<?php echo $rid; ?>&amp;back=<?php echo rawurlencode($back); ?>">এডিট</a>
                        <form method="post" action="process_damage.php" onsubmit="return confirm('স্টক ফিরিয়ে এই ড্যামেজ বাতিল হবে?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="void">
                            <input type="hidden" name="id" value="<?php echo $rid; ?>">
                            <input type="hidden" name="back" value="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="dmg-btn void">বাতিল</button>
                        </form>
                    <?php else: ?>
                        <span class="dmg-hint">বন্ধ</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<script src="/assets/js/damage-desk.js?v=<?php echo htmlspecialchars((string) $ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include '../../partials/footer.php'; ?>
