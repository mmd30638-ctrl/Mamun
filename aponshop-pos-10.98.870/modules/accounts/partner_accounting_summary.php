<?php
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
require_once '../../includes/partner_statement_pack.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$partnerId = (int)($_GET['partner_id'] ?? 0);
$ctx = pa_stmt_pack_context($db, $ym, $partnerId);
$bounds = $ctx['bounds'];
$dash = $ctx['dash'];
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$prepared = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'অ্যাডমিন'));
$pa_pack_active = 'summary';

$book = $ctx['book'];
$partner = $ctx['partner'];
$summary = null;
$outflow = ['by_type' => [], 'reconciled' => true];
$profitTotal = 0.0;
if ($book) {
    $outflow = pa_stmt_outflow_from_book($book);
    $summary = pa_stmt_summary_figures($book, $outflow);
    $profitTotal = (float)$book['plus'];
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="pa-page">
    <div class="pa-hero no-print">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">পার্টনার স্টেটমেন্ট প্যাকেজ · সারাংশ</div>
                <h1><?php echo htmlspecialchars($bounds['label']); ?></h1>
                <p>প্রথম ব্যালেন্স (সাবেক) + মাসের লাভ − মাসের সব মাইনাস = চলতি। লাভ ও খরচ পেজের সাথে সংযুক্ত।</p>
            </div>
            <form class="pa-month-form" method="get">
                <input type="month" name="month" value="<?php echo htmlspecialchars($bounds['ym']); ?>">
                <?php if ($partnerId > 0): ?>
                    <input type="hidden" name="partner_id" value="<?php echo $partnerId; ?>">
                <?php endif; ?>
                <button type="submit" class="pa-btn ghost">মাস</button>
            </form>
        </div>
    </div>

    <?php
    $pa_partner_id = $partnerId;
    $bounds_ym = $bounds['ym'];
    include '../../partials/partner_statement_pack_nav.php';
    ?>

    <?php if ($partner && $book && $summary): ?>
        <article class="pa-stmt pa-pack-sheet pa-summary-sheet" id="ame-slip">
            <div class="pa-stmt-head">
                <div>
                    <h1><?php echo htmlspecialchars($shop); ?></h1>
                    <div>মাসিক সারাংশ · <?php echo htmlspecialchars($bounds['label']); ?></div>
                </div>
                <div class="pa-stmt-meta">
                    <div>পার্টনার: <strong><?php echo htmlspecialchars((string)$partner['name']); ?></strong></div>
                    <div>শেয়ার <?php echo number_format((float)($book['share_pct'] ?? 0), 2); ?>%</div>
                    <div>তারিখ: <?php echo date('d-m-Y'); ?></div>
                    <div>প্রস্তুত: <?php echo htmlspecialchars($prepared); ?></div>
                </div>
            </div>

            <table class="pa-table pa-summary-table">
                <tbody>
                    <tr>
                        <th scope="row">প্রথম ব্যালেন্স (সাবেক)</th>
                        <td class="num pa-prev"><?php echo pa_taka($summary['opening']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">মাসের মোট লাভ</th>
                        <td class="num pa-plus"><?php echo pa_book_signed($summary['plus'], '+'); ?></td>
                    </tr>
                    <tr class="pa-summary-divider">
                        <th scope="row">মাইনাস — পার্টনারশিপ খরচ</th>
                        <td class="num pa-minus"><?php echo pa_book_signed((float)($summary['by_type']['expense'] ?? 0), '-'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">মাইনাস — উত্তোলন / পরিশোধ</th>
                        <td class="num pa-minus"><?php echo pa_book_signed((float)($summary['by_type']['withdrawal'] ?? 0), '-'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">মাইনাস — মূলধন উত্তোলন</th>
                        <td class="num pa-minus"><?php echo pa_book_signed((float)($summary['by_type']['capital'] ?? 0), '-'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">মাইনাস — অন্যান্য</th>
                        <td class="num pa-minus"><?php echo pa_book_signed((float)($summary['by_type']['other'] ?? 0), '-'); ?></td>
                    </tr>
                    <tr class="pa-summary-total-row">
                        <th scope="row">মোট মাইনাস</th>
                        <td class="num pa-minus"><?php echo pa_book_signed($summary['minus'], '-'); ?></td>
                    </tr>
                    <tr class="pa-summary-grand">
                        <th scope="row">চলতি ব্যালেন্স (মোট এমাউন্ট)</th>
                        <td class="num pa-run"><?php echo pa_taka($summary['total']); ?></td>
                    </tr>
                </tbody>
            </table>

            <p class="pa-stmt-note pa-summary-formula"><?php echo $summary['formula']; ?></p>

            <div class="pa-summary-links no-print">
                <a class="pa-live-link" href="<?php echo htmlspecialchars(pa_stmt_nav_href('partner_accounting_profit.php', $bounds['ym'], $partnerId), ENT_QUOTES, 'UTF-8'); ?>">লাভ বিবরণী (১৫ দিন/পৃষ্ঠা) — <?php echo pa_book_signed($profitTotal, '+'); ?></a>
                <a class="pa-live-link" href="<?php echo htmlspecialchars(pa_stmt_nav_href('partner_accounting_outflow.php', $bounds['ym'], $partnerId), ENT_QUOTES, 'UTF-8'); ?>">খরচ ও উত্তোলন (১৫ দিন/পৃষ্ঠা)</a>
                <a class="pa-live-link" href="<?php echo htmlspecialchars(pa_stmt_nav_href('partner_accounting_statement.php', $bounds['ym'], $partnerId), ENT_QUOTES, 'UTF-8'); ?>">পূর্ণ লেজার (সাবেক/চলতি)</a>
            </div>
            <?php if (!$summary['reconciled']): ?>
                <div class="pa-alert bad no-print">রিকনসাইল ব্যর্থ — ডেভেলপার চেক করুন।</div>
            <?php else: ?>
                <p class="pa-pack-ok no-print">✓ লাভ, খরচ ও সারাংশ একই লেজার বই থেকে — intl-100 মিল।</p>
            <?php endif; ?>

            <div class="signs">
                <div><?php echo htmlspecialchars((string)$partner['name']); ?></div>
                <div>অনুমোদনকারী</div>
            </div>
        </article>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>