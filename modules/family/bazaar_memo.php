<?php
require_once __DIR__ . '/_boot.php';

$db = fam_db();
$month = fam_bazaar_month_norm((string) ($_GET['m'] ?? date('Y-m')));
$cat = trim((string) ($_GET['cat'] ?? ''));
if ($cat !== '' && !fam_bazaar_cat_ok($cat)) {
    $cat = '';
}
$demand = fam_bazaar_find($db, $month);
$lines = fam_bazaar_lines($db, (int) ($demand['id'] ?? 0), $cat);
$printAll = isset($_GET['all']) && (string) $_GET['all'] === '1';
if (!$printAll) {
    $remain = array_values(array_filter($lines, static function ($row) {
        return empty($row['is_bought']);
    }));
    if ($remain) {
        $lines = $remain;
    }
}
$stats = fam_bazaar_stats($lines);
$memoNo = (string) ($demand['memo_no'] ?? ('BZR-' . str_replace('-', '', $month)));
$shopName = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shopAddr = function_exists('shop_address') ? shop_address() : '';
$shopPh = function_exists('shop_phone') ? shop_phone() : '';
$badge = $cat !== '' ? $cat : 'বাজার চাহিদা';
$fileBase = $memoNo . ($cat !== '' ? '-' . $cat : '');
$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1';
$back = '/modules/family/bazaar.php?m=' . rawurlencode($month) . ($cat !== '' ? '&cat=' . rawurlencode($cat) : '');
$printOn = isset($_GET['print']) && (string) $_GET['print'] === '1';
$itemCount = count($lines);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo htmlspecialchars($memoNo . ' · ' . fam_bazaar_month_label($month), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/assets/css/ame-memo-sheet.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
    .memo-table .bz-sl {
        width: 64px;
        min-width: 52px;
        text-align: left;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: #0B1E4D;
        letter-spacing: 0.02em;
    }
    .memo-table th.bz-sl { color: #003F8F; }
    .bz-count-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin: 0 0 10px;
        padding: 8px 10px;
        background: #F1F5F9;
        border: 1px solid #CBD5E1;
        border-radius: 8px;
        font-weight: 800;
        color: #0B1E4D;
        font-size: 13px;
    }
    </style>
</head>
<body class="ame-memo-sheet-page ame-paper-a4" data-paper="a4">
    <nav class="inv-action-bar no-print" aria-label="মেমো অপশন">
        <button type="button" class="inv-btn inv-btn-print" data-inv-act="print-image"><span>প্রিন্ট</span></button>
        <button type="button" class="inv-btn inv-btn-pdf" data-inv-act="download"><span>পিডিএফ</span></button>
        <button type="button" class="inv-btn inv-btn-img" data-inv-act="download-image"><span>ছবি</span></button>
        <button type="button" class="inv-btn inv-btn-share" data-inv-act="share"><span>শেয়ার</span></button>
        <a class="inv-btn inv-btn-back" href="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>#bz-add"><span>মেমো এডিট</span></a>
        <a class="inv-btn inv-btn-back" href="<?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?>"><span>চাহিদায় ফিরুন</span></a>
    </nav>

    <div id="memo-container" class="memo-wrapper" data-ame-capture="clone">
        <div class="memo-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(56) : ''; ?>
            <h1 class="memo-shop-name"><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($shopAddr !== ''): ?>
            <p class="memo-shop-desc"><?php echo htmlspecialchars($shopAddr, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($shopPh !== ''): ?>
            <p class="memo-shop-phone">📞 <?php echo htmlspecialchars($shopPh, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="memo-badge"><?php echo htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (fam_bazaar_house_entered($demand)): ?>
            <p class="memo-shop-desc" style="font-weight:800;color:#0B1E4D">এন্ট্রি করা হয়েছিল</p>
            <?php endif; ?>
        </div>

        <div class="memo-meta-grid">
            <div class="memo-meta-item">
                <span class="lbl">মেমো</span>
                <span class="val">#<?php echo htmlspecialchars($memoNo, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl">মাস</span>
                <span class="val"><?php echo htmlspecialchars(fam_bazaar_month_label($month), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl">তারিখ</span>
                <span class="val"><?php echo htmlspecialchars(date('d-m-Y'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl">মোট পণ্য</span>
                <span class="val"><?php echo htmlspecialchars(fam_bazaar_bn_num($itemCount), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="bz-count-bar">
            <span>বাম সারি · সিরিয়াল</span>
            <span>মোট পণ্য: <?php echo htmlspecialchars(fam_bazaar_bn_num($itemCount), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <table class="memo-table">
            <thead>
                <tr>
                    <th class="bz-sl">সিরিয়াল</th>
                    <th>বিবরণ</th>
                    <th class="tc" style="width:88px">পরিমাণ</th>
                    <th class="tr" style="width:80px">আনুমানিক</th>
                    <th class="tc" style="width:36px">✓</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$lines): ?>
                <tr><td colspan="5" class="tc">এই মাসে চাহিদা নেই</td></tr>
            <?php else: ?>
                <?php $sl = 1; foreach ($lines as $row): ?>
                <tr>
                    <td class="bz-sl"><?php echo htmlspecialchars(fam_bazaar_bn_num($sl) . '।', ENT_QUOTES, 'UTF-8'); $sl++; ?></td>
                    <td>
                        <span style="font-weight:600"><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php
                        $note = trim((string) ($row['notes'] ?? ''));
                        if ($note !== ''):
                        ?>
                        <div style="font-size:10px;color:#64748b"><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="tc"><?php echo htmlspecialchars(fam_bazaar_qty_txt($row), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="tr"><?php echo (float) $row['est_price'] > 0.009 ? number_format((float) $row['est_price'], 2) : '—'; ?></td>
                    <td class="tc"><?php echo !empty($row['is_bought']) ? '✓' : '☐'; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="memo-summary">
            <div class="memo-sum-row bold"><span>মোট পণ্য</span><span><?php echo htmlspecialchars(fam_bazaar_bn_num($itemCount), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-sum-row highlight"><span>আনুমানিক বাজার</span><span><?php echo '৳' . number_format($stats['est'], 2); ?></span></div>
        </div>
    </div>

    <script src="/assets/js/ame-memo-file.js?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
    (function () {
        var fileBase = <?php echo json_encode($fileBase, JSON_UNESCAPED_UNICODE); ?>;
        document.querySelectorAll('[data-inv-act]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-inv-act') || 'print-image';
                if (typeof window.ameMemoCapture === 'function') {
                    window.ameMemoCapture(act, fileBase, 'memo-container');
                } else if (act.indexOf('print') === 0) {
                    window.print();
                }
            });
        });
        <?php if ($printOn): ?>
        window.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                if (typeof window.ameMemoCapture === 'function') {
                    window.ameMemoCapture('print-image', fileBase, 'memo-container');
                } else { window.print(); }
            }, 400);
        });
        <?php endif; ?>
    })();
    </script>
</body>
</html>
