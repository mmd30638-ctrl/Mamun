<?php
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
require_once '../../includes/partner_statement_pack.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$only = (int)($_GET['partner_id'] ?? 0);
$pg = max(1, (int)($_GET['pg'] ?? 1));
$pa_pack_active = 'ledger';
$pa_partner_id = $only;
$bounds_ym = '';
$dash = pa_dashboard($db, $ym);
$bounds = $dash['month']['bounds'];
$bounds_ym = $bounds['ym'];
$targets = $dash['active'];
if ($only > 0) {
    $targets = array_values(array_filter($targets, static function ($a) use ($only) {
        return (int)$a['partner']['id'] === $only;
    }));
}
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$prepared = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? ''));
if ($prepared === '') {
    $prepared = 'অ্যাডমিন';
}
$ok = (string)($_GET['saved'] ?? '');
$err = (string)($_GET['err'] ?? '');
$pa_start = pa_books_start($db);
$pa_start_date = (string)($pa_start['date'] ?? '');
$pa_start_show = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pa_start_date) ? date('d-m-Y', strtotime($pa_start_date)) : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'partner-statement-' . $bounds['ym'] . ($only > 0 ? ('-p' . $only) : ''),
    'title' => $shop . ' · ' . ame_l('পার্টনার স্টেটমেন্ট', 'Partner statement'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পার্টনার স্টেটমেন্ট', 'Partner statement'),
        $bounds['label'] ?? $bounds['ym'],
    ])),
    'shop' => $shop,
];
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="pa-page">
    <div class="pa-hero no-print">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">পার্টনার স্টেটমেন্ট প্যাকেজ · পূর্ণ লেজার</div>
                <h1><?php echo htmlspecialchars($bounds['label']); ?></h1>
                <p>ক্রমিক লেজার — সাবেক, প্লাস, মাইনাস, চলতি। লাভ/খরচ/সারাংশ পৃথক ট্যাবে; এই পেজ অডিট ট্রেইল।</p>
            </div>
            <div class="pa-month-form">
                <a class="pa-btn ghost" href="partner_accounting.php?month=<?php echo htmlspecialchars($bounds['ym']); ?>">← ড্যাশবোর্ড</a>
            </div>
        </div>
    </div>

    <?php include '../../partials/partner_statement_pack_nav.php'; ?>

    <?php if ($ok === 'start'): ?>
        <div class="pa-alert ok">এই তারিখ থেকে খাতা শুরু। আগের সব এমাউন্ট শূন্য। এখন থেকে লাভ যোগ ও খরচ কাটা হবে।</div>
    <?php elseif ($err === 'start'): ?>
        <div class="pa-alert bad">শুরুর তারিখ দিন।</div>
    <?php endif; ?>

    <section class="pa-panel no-print pa-start">
        <form method="POST" action="actions/partner_accounting.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_books_start">
            <input type="hidden" name="month" value="<?php echo htmlspecialchars($bounds['ym']); ?>">
            <input type="hidden" name="back" value="statement">
            <p>যে তারিখ বাছবেন, তার আগের সব এমাউন্ট শূন্য। সেই তারিখ থেকে লাভ যোগ হবে এবং খরচ কাটবে।<?php if ($pa_start_show !== ''): ?> এখন শুরু <?php echo htmlspecialchars($pa_start_show); ?>।<?php endif; ?></p>
            <input type="date" name="start_date" required value="<?php echo htmlspecialchars($pa_start_date); ?>">
            <button type="submit" class="pa-btn primary">এই তারিখ থেকে শুরু</button>
        </form>
    </section>

    <?php include '../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">

    <?php
    $books = [];
    foreach ($dash['active'] as $a) {
        $books[] = pa_partner_book($db, $a['partner'], $bounds['ym']);
    }
    ?>
    <?php if (!$books): ?>
        <div class="pa-panel"><div class="pa-empty">এই মাসে দেখানোর মতো সক্রিয় পার্টনার নেই।</div></div>
    <?php else: ?>
        <div class="pa-openings">
            <?php foreach ($books as $book): ?>
                <div class="pa-opening">
                    <span><?php echo htmlspecialchars($book['name']); ?> · প্রথম ব্যালেন্স</span>
                    <b><?php echo pa_taka($book['opening']); ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($books as $book):
        $linesAll = array_reverse($book['lines']);
        $lineSlice = pa_stmt_page_slice($linesAll, $pg);
        $lines = $lineSlice['items'];
        $pa_slice = $lineSlice;
        $pa_pager_script = 'partner_accounting_statement.php';
        ?>
        <article class="pa-stmt" id="pa-book-<?php echo (int)$book['partner_id']; ?>">
            <div class="pa-stmt-head">
                <div>
                    <h1><?php echo htmlspecialchars(shop_name()); ?></h1>
                    <div><?php echo htmlspecialchars(shop_address()); ?></div>
                    <div style="font-size:13px;margin-top:4px;">পার্টনার স্টেটমেন্ট · প্লাস মাইনাস</div>
                </div>
                <div class="pa-stmt-meta">
                    <div>সময়কাল: <?php echo htmlspecialchars($bounds['label']); ?></div>
                    <div>তারিখ: <?php echo date('d-m-Y'); ?></div>
                    <div>প্রস্তুত: <?php echo htmlspecialchars($prepared); ?></div>
                    <div>স্ট্যাটাস: <?php echo $dash['closed'] ? 'বন্ধ' : 'খোলা'; ?></div>
                </div>
            </div>
            <div class="pa-stmt-who">
                <strong><?php echo htmlspecialchars($book['name']); ?></strong>
                · শেয়ার <?php echo number_format((float)$book['share_pct'], 2); ?>%
                · প্রথম ব্যালেন্স <?php echo pa_taka($book['opening']); ?>
            </div>
            <?php
            $monthNo = (int)date('n', strtotime($bounds['from'] . ' 12:00:00'));
            $periodFrom = date('d-m-Y', strtotime($bounds['from']));
            $periodTo = date('d-m-Y', strtotime($bounds['to']));
            ?>
            <p class="pa-stmt-period">
                <strong><?php echo $monthNo; ?> নং মাস (<?php echo htmlspecialchars($bounds['label']); ?>)</strong>
                — <?php echo htmlspecialchars($periodFrom); ?> থেকে <?php echo htmlspecialchars($periodTo); ?> পর্যন্ত
                এই পার্টনারের মোট লাভ <strong><?php echo pa_taka($book['plus']); ?></strong>
                এবং মোট মাইনাস <strong><?php echo pa_taka($book['minus']); ?></strong> হয়েছে।
            </p>
            <p class="pa-stmt-note">প্রতি সারিতে <strong>সাবেক</strong> = প্লাস/মাইনাসের আগের চলতি ব্যালেন্স, তারপর প্লাস·মাইনাস, শেষে নতুন চলতি। মাইনাসের কারণ আলাদা কলামে। প্রতি পৃষ্ঠায় সর্বোচ্চ <strong><?php echo pa_stmt_rows_per_page(); ?></strong>টি ক্রমিক সারি।</p>
            <?php include '../../partials/partner_statement_pager.php'; ?>
            <div class="pa-table-wrap">
                <table class="pa-table pa-book pa-rows-15">
                    <thead>
                        <tr>
                            <th class="pa-th-serial">ক্রমিক</th>
                            <th>তারিখ</th>
                            <th>বিবরণ</th>
                            <th class="num pa-th-prev">সাবেক</th>
                            <th class="num">প্লাস</th>
                            <th class="num">মাইনাস</th>
                            <th>মাইনাসের কারণ</th>
                            <th class="num">চলতি</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$lines): ?>
                            <tr><td colspan="8" data-label="">এই মাসে প্লাস বা মাইনাস নেই।</td></tr>
                        <?php else: foreach ($lines as $i => $line):
                            $when = (string)$line['date'];
                            $show = preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) ? date('d-m-Y', strtotime($when)) : '';
                            $serial = $lineSlice['serial_start'] + $i;
                            ?>
                            <tr class="pa-stmt-row">
                                <td class="num pa-serial" data-label="ক্রমিক"><?php echo htmlspecialchars(pa_stmt_serial_label($serial)); ?></td>
                                <td data-label="তারিখ"><?php echo htmlspecialchars($show); ?></td>
                                <td data-label="বিবরণ"><?php echo htmlspecialchars($line['description']); ?></td>
                                <td class="num pa-prev" data-label="সাবেক"><?php echo pa_taka((float)($line['previous'] ?? 0)); ?></td>
                                <td class="num pa-plus" data-label="প্লাস"><?php echo pa_book_signed((float)$line['plus'], '+'); ?></td>
                                <td class="num pa-minus" data-label="মাইনাস"><?php echo pa_book_signed((float)$line['minus'], '-'); ?></td>
                                <td data-label="মাইনাসের কারণ"><?php echo htmlspecialchars((string)($line['minus_reason'] ?? '')); ?></td>
                                <td class="num pa-run" data-label="চলতি"><?php echo pa_taka($line['running']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if ($lines): ?>
                    <tfoot>
                        <tr>
                            <td data-label=""></td>
                            <td colspan="2" data-label="মোট">মোট</td>
                            <td class="num pa-prev" data-label="সাবেক"><?php echo pa_taka($book['opening']); ?></td>
                            <td class="num pa-plus" data-label="প্লাস"><?php echo pa_book_signed((float)$book['plus'], '+'); ?></td>
                            <td class="num pa-minus" data-label="মাইনাস"><?php echo pa_book_signed((float)$book['minus'], '-'); ?></td>
                            <td data-label=""></td>
                            <td class="num pa-run" data-label="মোট এমাউন্ট"><?php echo pa_taka($book['total']); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <?php include '../../partials/partner_statement_pager.php'; ?>
            <div class="box"><span>মোট এমাউন্ট</span><span><?php echo pa_taka($book['total']); ?></span></div>
            <p class="pa-stmt-note"><?php echo pa_taka($book['opening']); ?> + <?php echo pa_book_signed((float)$book['plus'], '+'); ?> − <?php echo pa_book_signed((float)$book['minus'], '-'); ?> = <?php echo pa_taka($book['total']); ?></p>
            <div class="signs">
                <div><?php echo htmlspecialchars($book['name']); ?></div>
                <div>অনুমোদনকারী</div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</div>
<style>
.pa-stmt-period {
    margin: 10px 0 8px;
    padding: 10px 12px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
    color: #0c4a6e;
}
.pa-table .pa-minus-reason {
    font-size: 13px;
    max-width: 220px;
}
.pa-table.pa-book .pa-prev {
    font-weight: 600;
}
@media print {
    body * { visibility: hidden; }
    .pa-stmt, .pa-stmt * { visibility: visible; }
    .pa-stmt { position: relative; box-shadow: none; page-break-after: always; }
    .no-print, header, .sidebar, .acc-share-bar, nav { display: none !important; }
}
</style>
<?php include '../../partials/footer.php'; ?>
