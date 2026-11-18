<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php';
}
/**
 * ক্রয় মেমো — বিক্রয় ইনভয়েসের ধরন। হিসাব আগের সূত্রই।
 */
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/memo_functions.php';
require_once '../../includes/purchase_memo_sheet.php';
if (is_file(__DIR__ . '/../../includes/invoice_functions.php')) {
    require_once __DIR__ . '/../../includes/invoice_functions.php';
}

ame_purchase_require_access($db);
ensure_purchase_schema($db);

$memo_no = trim((string) ($_GET['memo'] ?? ''));
$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$memo = $memo_no !== '' ? ame_purchase_memo_load($db, $memo_no, $supplier_id) : null;
if ($memo) {
    $supplier_id = (int)($memo['supplier_id'] ?? $supplier_id);
}
$shop_name = function_exists('shop_name') ? shop_name() : (string) ($shop['shop_name'] ?? 'APONSHOP');
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.549', ENT_QUOTES, 'UTF-8');
$book = $memo ? ame_purchase_memo_paper_book($memo) : [];
$wa_text = $memo ? ame_purchase_memo_wa_text($memo, $book, $shop_name) : '';
$partyRow = $memo ? ame_purchase_memo_party($memo) : ['name' => '', 'phone' => '', 'role' => 'supplier', 'code' => ''];
$phone = preg_replace('/\D+/', '', (string) ($partyRow['phone'] ?? ''));
if ($phone !== '' && str_starts_with($phone, '0') && strlen($phone) >= 11) {
    $phone = '880' . substr($phone, 1);
} elseif ($phone !== '' && !str_starts_with($phone, '880') && strlen($phone) === 10) {
    $phone = '880' . $phone;
}
$host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
$view_url = 'https://' . ($host !== '' ? $host : 'localhost')
    . '/modules/purchase/memo_digital.php?memo=' . rawurlencode($memo_no)
    . '&supplier_id=' . $supplier_id;
$wa_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($wa_text);
$tg_url = 'https://t.me/share/url?url=' . rawurlencode($view_url) . '&text=' . rawurlencode($wa_text);
$file_base = 'purchase-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($memo['memo_no'] ?? 'memo'));
$party = (string) ($partyRow['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="bn" data-ame-memo-standalone="1" class="ame-memo-standalone-fit">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover">
    <script>
    (function () {
        var root = document.documentElement;
        root.setAttribute('data-ame-memo-standalone', '1');
        root.classList.add('ame-memo-standalone-fit');
        var meta = document.querySelector('meta[name="viewport"]');
        if (meta) {
            meta.setAttribute('content', 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=5, user-scalable=yes, viewport-fit=cover');
        }
    })();
    </script>
    <script src="/assets/js/ame-desktop-site.js?v=<?php echo $ver; ?>"></script>
    <script>
    if (typeof window.ameApplySaleDesktopViewport === 'function') { window.ameApplySaleDesktopViewport(); }
    </script>
    <title><?php echo $memo ? 'ক্রয় মেমো #' . ame_purchase_memo_h($memo['memo_no']) : 'ক্রয় মেমো'; ?> - <?php echo ame_purchase_memo_h($shop_name); ?></title>
    <link rel="stylesheet" href="/assets/css/ame-front-colors.css">
    <link rel="stylesheet" href="/assets/css/memo-clear.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/ame-memo-sheet.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="/assets/css/invoice-print-fix.css?v=<?php echo $ver; ?>">
</head>
<body class="ame-memo-sheet-page ame-paper-80mm" data-paper="80mm" data-ame-memo-standalone="1">
    <div class="ame-invoice-viewport-bg" aria-hidden="true"></div>
<?php if (!$memo): ?>
    <div class="ame-memo-missing">
        <p>ক্রয়ের মেমো পাওয়া যায়নি।</p>
        <a href="add.php">নতুন ক্রয়</a>
    </div>
<?php else: ?>
    <div class="ame-invoice-memo-shell">
    <?php if (!empty($_GET['ok'])): ?>
    <div class="ame-pay-ok no-print">
        <div style="font-size:36px;line-height:1;">✓</div>
        <h2>পণ্য ক্রয় সংরক্ষণ হয়েছে</h2>
        <p><?php echo ame_purchase_memo_h($memo['memo_no']); ?> · <?php echo ame_purchase_memo_h($party); ?></p>
    </div>
    <?php endif; ?>

    <nav class="inv-action-bar no-print" aria-label="ক্রয় মেমো অপশন">
        <button type="button" class="inv-btn inv-btn-print" data-inv-act="print-image" title="প্রিন্ট করুন">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V4h10v4" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="5" y="8" width="14" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 14h10v6H7z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>প্রিন্ট</span>
        </button>
        <button type="button" class="inv-btn inv-btn-pdf" data-inv-act="download" title="PDF ডাউনলোড">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m8 11 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 19h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span>পিডিএফ</span>
        </button>
        <button type="button" class="inv-btn inv-btn-img" data-inv-act="download-image" title="ছবি ডাউনলোড">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><path d="m21 15-5-5L5 21" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>ছবি</span>
        </button>
        <button type="button" class="inv-btn inv-btn-share" data-inv-act="share" title="শেয়ার করুন">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="17" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m8 11 7-3M8 13l7 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <span>শেয়ার</span>
        </button>
        <a class="inv-btn inv-btn-wa" href="<?php echo ame_purchase_memo_h($wa_url); ?>" target="_blank" rel="noopener">WhatsApp</a>
        <button type="button" class="inv-btn inv-btn-tg" data-inv-act="share-telegram">Telegram</button>
        <a class="inv-btn inv-btn-edit" href="add.php?edit_memo=<?php echo rawurlencode((string) $memo['memo_no']); ?>&amp;supplier_id=<?php echo $supplier_id; ?>">এডিট</a>
        <a class="inv-btn inv-btn-back" href="add.php">ডেস্ক</a>
    </nav>

    <?php ame_purchase_memo_render_sheet($db, $memo); ?>
    </div>

    <script src="/assets/js/ame-memo-file.js?v=<?php echo $ver; ?>"></script>
    <script>
    (function () {
        var fileBase = <?php echo json_encode($file_base, JSON_UNESCAPED_UNICODE); ?>;
        document.querySelectorAll('[data-inv-act]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-inv-act');
                if (typeof window.ameMemoCapture === 'function') {
                    window.ameMemoCapture(act, fileBase, 'memo-container');
                }
            });
        });
        window.addEventListener('DOMContentLoaded', function () {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('print-image', fileBase, 'memo-container');
                    } else {
                        window.print();
                    }
                }, 400);
            } else if (urlParams.get('share') === '1') {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('share', fileBase, 'memo-container');
                    }
                }, 400);
            } else if (urlParams.get('download') === '1') {
                setTimeout(function () {
                    if (typeof window.ameMemoCapture === 'function') {
                        window.ameMemoCapture('download', fileBase, 'memo-container');
                    }
                }, 400);
            }
        });
    })();
    </script>
    <script>
    (function () {
        function amePurchaseMemoDesktopFill() {
            var body = document.body;
            if (!body || !body.classList.contains('ame-memo-sheet-page')) {
                return;
            }
            var root = document.documentElement;
            root.setAttribute('data-ame-memo-standalone', '1');
            root.classList.add('ame-memo-standalone-fit');
            root.classList.remove('ame-phone-desktop');
            if (typeof window.ameMemoStandaloneViewportReset === 'function') {
                window.ameMemoStandaloneViewportReset();
            } else if (typeof window.ameApplySaleDesktopViewport === 'function') {
                window.ameApplySaleDesktopViewport();
            }
        }
        amePurchaseMemoDesktopFill();
        document.addEventListener('DOMContentLoaded', amePurchaseMemoDesktopFill);
        window.addEventListener('resize', amePurchaseMemoDesktopFill);
        window.addEventListener('orientationchange', function () {
            setTimeout(amePurchaseMemoDesktopFill, 50);
        });
    })();
    </script>
    <?php include __DIR__ . '/../../partials/confirm_actions.php'; ?>
<?php endif; ?>
</body>
</html>
