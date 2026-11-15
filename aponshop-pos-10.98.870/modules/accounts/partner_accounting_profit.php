<?php
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
require_once '../../includes/partner_statement_pack.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$partnerId = (int)($_GET['partner_id'] ?? 0);
$pg = max(1, (int)($_GET['pg'] ?? 1));
$ctx = pa_stmt_pack_context($db, $ym, $partnerId);
$bounds = $ctx['bounds'];
$dash = $ctx['dash'];
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$prepared = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'অ্যাডমিন'));
$pa_pack_active = 'profit';

$profit = ['lines' => [], 'total' => 0.0, 'computed' => 0.0, 'reconciled' => true, 'more' => false];
$days = [];
$lineSlice = ['items' => [], 'serial_start' => 1, 'page' => 1, 'pages' => 1, 'total' => 0, 'per' => pa_stmt_rows_per_page()];
$book = $ctx['book'];
$partner = $ctx['partner'];
if ($book && $partner) {
    $profit = pa_stmt_profit_for_partner($db, $partner, $bounds, $book);
    $lineSlice = pa_stmt_page_slice($profit['lines'], $pg);
    $days = pa_stmt_group_days($lineSlice['items'], 'profit_share');
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="pa-page">
    <div class="pa-hero no-print">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">পার্টনার স্টেটমেন্ট প্যাকেজ · লাভ</div>
                <h1><?php echo htmlspecialchars($bounds['label']); ?></h1>
                <p>দিনভিত্তি বিক্রয় লাভ — কাস্টমার, পণ্য, পার্টনার শেয়ার। শুধু পড়া; মোট লাভ মাসিক সারাংশের সাথে মিলবে।</p>
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

    <?php if ($ctx['error'] !== '' && $partnerId > 0): ?>
        <div class="pa-alert bad"><?php echo htmlspecialchars($ctx['error']); ?></div>
    <?php endif; ?>

    <?php if ($partner && $book): ?>
        <article class="pa-stmt pa-pack-sheet" id="ame-slip">
            <div class="pa-stmt-head">
                <div>
                    <h1><?php echo htmlspecialchars($shop); ?></h1>
                    <div>লাভ বিবরণী · <?php echo htmlspecialchars($bounds['label']); ?></div>
                </div>
                <div class="pa-stmt-meta">
                    <div>পার্টনার: <strong><?php echo htmlspecialchars((string)$partner['name']); ?></strong></div>
                    <div>তারিখ: <?php echo date('d-m-Y'); ?></div>
                    <div>প্রস্তুত: <?php echo htmlspecialchars($prepared); ?></div>
                </div>
            </div>

            <?php if (!$profit['reconciled']): ?>
                <div class="pa-alert bad no-print">সতর্ক: লাইন যোগফল মাসিক মোট লাভের সাথে মিলছে না — অ্যাডমিনকে জানান।</div>
            <?php endif; ?>
            <?php if ($profit['more']): ?>
                <div class="pa-alert no-print">২০০০+ লাইন — কিছু পুরনো লাইন লুকানো; মোট তারপরও সারাংশের মোট লাভের সমান।</div>
            <?php endif; ?>

            <p class="pa-stmt-note">বামে ক্রমিক ও কাস্টমার, ডানে পণ্য ও লাভ। প্রতি পৃষ্ঠায় <strong><?php echo pa_stmt_rows_per_page(); ?></strong> সারি।</p>
            <?php
            $pa_slice = $lineSlice;
            $pa_pager_script = 'partner_accounting_profit.php';
            include '../../partials/partner_statement_pager.php';
            $serial = $lineSlice['serial_start'];
            ?>

            <?php if ($days === []): ?>
                <div class="pa-empty">এই মাসে লাভের লাইন নেই।</div>
            <?php else: ?>
                <?php foreach ($days as $day): ?>
                    <section class="pa-day-block">
                        <header class="pa-day-head">
                            <span><?php echo htmlspecialchars($day['show']); ?></span>
                            <strong><?php echo pa_taka($day['subtotal']); ?></strong>
                        </header>
                        <div class="pa-day-lines">
                            <?php foreach ($day['lines'] as $ln): ?>
                                <div class="pa-profit-row">
                                    <span class="pa-serial-badge"><?php echo htmlspecialchars(pa_stmt_serial_label($serial)); ?></span>
                                    <?php $serial++; ?>
                                    <div class="pa-profit-who">
                                        <span class="pa-profit-cust"><?php echo htmlspecialchars((string)$ln['customer_label']); ?></span>
                                        <?php if (($ln['invoice_no'] ?? '') !== ''): ?>
                                            <span class="pa-profit-inv"><?php echo htmlspecialchars((string)$ln['invoice_no']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pa-profit-prod"><?php echo htmlspecialchars((string)$ln['product_name']); ?></div>
                                    <div class="pa-profit-amt num pa-plus"><?php echo pa_book_signed((float)$ln['profit_share'], '+'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php include '../../partials/partner_statement_pager.php'; ?>
            <div class="box pa-pack-total">
                <span>মাসের মোট লাভ (এই পার্টনার)</span>
                <span class="num pa-plus"><?php echo pa_book_signed((float)$profit['total'], '+'); ?></span>
            </div>
        </article>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>