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
$pa_pack_active = 'outflow';

$outflow = ['lines' => [], 'total' => 0.0, 'by_type' => [], 'reconciled' => true];
$days = [];
$daySlice = ['days' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'per' => pa_stmt_days_per_page(), 'unit' => 'day'];
$allDays = [];
$book = $ctx['book'];
$partner = $ctx['partner'];
if ($book) {
    $outflow = pa_stmt_outflow_from_book($book);
    $allDays = pa_stmt_group_days($outflow['lines'], 'amount');
    $daySlice = pa_stmt_page_slice_days($allDays, $pg);
    $days = $daySlice['days'];
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="pa-page">
    <div class="pa-hero no-print">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">পার্টনার স্টেটমেন্ট প্যাকেজ · খরচ ও মাইনাস</div>
                <h1><?php echo htmlspecialchars($bounds['label']); ?></h1>
                <p>এক স্টেটমেন্টে <strong>পার্টনারশিপ খরচ</strong>, <strong>উত্তোলন/পরিশোধ</strong>, <strong>মূলধন/অন্যান্য মাইনাস</strong> — দিনভিত্তি; মোট পূর্ণ লেজারের মোট মাইনাসের সমান।</p>
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

    <?php if ($partner && $book): ?>
        <article class="pa-stmt pa-pack-sheet" id="ame-slip">
            <div class="pa-stmt-head">
                <div>
                    <h1><?php echo htmlspecialchars($shop); ?></h1>
                    <div>খরচ ও মাইনাস বিবরণী · <?php echo htmlspecialchars($bounds['label']); ?></div>
                </div>
                <div class="pa-stmt-meta">
                    <div>পার্টনার: <strong><?php echo htmlspecialchars((string)$partner['name']); ?></strong></div>
                    <div>তারিখ: <?php echo date('d-m-Y'); ?></div>
                    <div>প্রস্তুত: <?php echo htmlspecialchars($prepared); ?></div>
                </div>
            </div>

            <?php if (!$outflow['reconciled']): ?>
                <div class="pa-alert bad no-print">সতর্ক: মাইনাস যোগফল লেজার মোটের সাথে মিলছে না।</div>
            <?php endif; ?>

            <div class="pa-outflow-legend no-print">
                <span class="pa-tag expense">খরচ</span>
                <span class="pa-tag withdrawal">উত্তোলন</span>
                <span class="pa-tag capital">মূলধন</span>
                <span class="pa-tag other">অন্যান্য</span>
            </div>

            <div class="pa-outflow-type-totals">
                <?php
                $bt = $outflow['by_type'];
                $labels = [
                    'expense' => 'পার্টনারশিপ খরচ',
                    'withdrawal' => 'উত্তোলন / পরিশোধ',
                    'capital' => 'মূলধন উত্তোলন',
                    'other' => 'অন্যান্য মাইনাস',
                ];
                foreach ($labels as $key => $lab):
                    $val = pa_money($bt[$key] ?? 0);
                    if ($val < 0.009) {
                        continue;
                    }
                    ?>
                    <div class="pa-type-pill" data-type="<?php echo htmlspecialchars($key); ?>">
                        <span><?php echo htmlspecialchars($lab); ?></span>
                        <strong class="num pa-minus">−<?php echo number_format($val, 2); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="pa-stmt-note">খরচ + উত্তোলন — প্রতি পৃষ্ঠায় <strong><?php echo pa_stmt_days_per_page(); ?> দিনের</strong> হিসাব। ট্যাগ রঙে ধরন চেনা যাবে।</p>
            <?php
            $pa_slice = $daySlice;
            $pa_pager_script = 'partner_accounting_outflow.php';
            include '../../partials/partner_statement_pager.php';
            $serial = pa_stmt_serial_base($allDays, $pg);
            ?>

            <?php if ($days === []): ?>
                <div class="pa-empty">এই মাসে কোনো মাইনাস নেই।</div>
            <?php else: ?>
                <?php foreach ($days as $day): ?>
                    <section class="pa-day-block">
                        <header class="pa-day-head">
                            <span><?php echo htmlspecialchars($day['show']); ?></span>
                            <strong class="pa-minus">−<?php echo number_format((float)$day['subtotal'], 2); ?></strong>
                        </header>
                        <div class="pa-day-lines">
                            <?php foreach ($day['lines'] as $ln): ?>
                                <div class="pa-outflow-row" data-type="<?php echo htmlspecialchars((string)$ln['type']); ?>">
                                    <span class="pa-serial-badge"><?php echo htmlspecialchars(pa_stmt_serial_label($serial)); ?></span>
                                    <?php $serial++; ?>
                                    <span class="pa-tag <?php echo htmlspecialchars((string)$ln['type']); ?>"><?php echo htmlspecialchars((string)$ln['type_label']); ?></span>
                                    <div class="pa-outflow-body">
                                        <div class="pa-outflow-desc"><?php echo htmlspecialchars((string)$ln['description']); ?></div>
                                        <?php if (($ln['reason'] ?? '') !== ''): ?>
                                            <div class="pa-outflow-reason"><?php echo htmlspecialchars((string)$ln['reason']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="num pa-minus pa-outflow-amt">−<?php echo number_format((float)$ln['amount'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php include '../../partials/partner_statement_pager.php'; ?>
            <div class="box pa-pack-total">
                <span>মাসের মোট মাইনাস (লেজার মিল)</span>
                <span class="num pa-minus"><?php echo pa_book_signed((float)$outflow['total'], '-'); ?></span>
            </div>
        </article>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>