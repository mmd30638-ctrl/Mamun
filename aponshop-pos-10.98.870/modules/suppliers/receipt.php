<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_due_ui.php';
$ameMemoLang = __DIR__ . '/../../includes/memo_lang.php';
if (is_file($ameMemoLang)) {
    require_once $ameMemoLang;
}
ame_spay_ensure($db);

$id = (int)($_GET['id'] ?? 0);
$row = ame_spay_fetch($db, $id);
if (!$row) {
    header('Location: payments-list.php');
    exit;
}
$error = (string)($_SESSION['spay_error'] ?? '');
unset($_SESSION['spay_error']);
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$addr = function_exists('shop_address') ? shop_address() : '';
$phone = function_exists('shop_phone') ? shop_phone() : '';
$methods = ame_spay_methods();
$statuses = ame_spay_statuses();
$fileBase = 'supplier-pay-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$row['payment_no']);
$ledger = ame_spay_memo_ledger($row);
$amePayTs = strtotime((string)($row['payment_date'] ?? '') . ' ' . date('H:i:s', strtotime((string)($row['created_at'] ?? 'now'))));
if ($amePayTs === false) {
    $amePayTs = time();
}
if (function_exists('ame_ui_date') && function_exists('ame_ui_time')) {
    $amePayWhen = ame_ui_date((int)$amePayTs) . ' ' . ame_ui_time((int)$amePayTs);
} else {
    $amePayWhen = trim((string)$row['payment_date'] . ' ' . date('H:i', strtotime((string)$row['created_at'])));
}
$amePaidShow = !empty($ledger['posted']) ? (float)$ledger['paid'] : 0.0;
$ameRemainShow = (float)($ledger['remaining_due'] ?? max(0, $ledger['remain']));
$ameAdvanceShow = (float)($ledger['advance'] ?? 0);
$ameDueShow = (float)$ledger['due'];
$ameTotalShow = $ameAdvanceShow > 0.009 ? $ameAdvanceShow : $ameRemainShow;
$ameTotalClass = $ameAdvanceShow > 0.009 ? 'spay-money-adv' : 'spay-money-left';
$ameTotalRow = $ameAdvanceShow > 0.009 ? 'spay-row-adv' : 'spay-row-left';
$waPhone = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
if (str_starts_with($waPhone, '0') && strlen($waPhone) >= 11) {
    $waPhone = '880' . substr($waPhone, 1);
} elseif ($waPhone !== '' && !str_starts_with($waPhone, '880') && strlen($waPhone) === 10) {
    $waPhone = '880' . $waPhone;
}
$waText = $shop . "\n"
    . ame_spay_t('পেমেন্ট রসিদ', 'Payment receipt') . ' ' . (string)$row['payment_no'] . "\n"
    . ame_spay_t('তারিখ', 'Date') . ': ' . $amePayWhen . "\n"
    . ame_spay_t('সাপ্লায়ার', 'Supplier') . ': ' . (string)($row['supplier_name'] ?: '') . "\n"
    . ame_spay_t('সাবেক বকেয়া', 'Previous due') . ': +৳' . number_format($ameDueShow, 2) . "\n"
    . ame_spay_t('পরিশোধ', 'Paid') . ': −৳' . number_format($amePaidShow, 2) . "\n"
    . ame_spay_t('অবশিষ্ট বকেয়া', 'Remaining due') . ': ৳' . number_format($ameRemainShow, 2) . "\n"
    . ame_spay_t('অগ্রিম জমা', 'Advance held') . ': ৳' . number_format($ameAdvanceShow, 2) . "\n"
    . (string)($ledger['after_label'] ?? '') . ' · ' . (string)$ledger['formula'];
$waUrl = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waText);
$ame_body_class = 'ame-spay-page ame-paper-a4';
include '../../partials/header.php';
ame_supplier_due_print_assets();
?>
<link rel="stylesheet" href="/assets/css/supplier-settlement.css?v=10.98.847">
<link rel="stylesheet" href="/assets/css/memo-clear.css?v=10.98.847">
<div class="spay<?php echo isset($_GET['slip']) ? ' spay-slip' : ''; ?>" id="spReceipt">
    <?php if ($error !== ''): ?><div class="spay-error spay-no-print no-print"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <nav class="inv-action-bar no-print spay-no-print" id="spayShareBar" aria-label="<?php echo ame_spay_t('পেমেন্ট রসিদ', 'Payment receipt'); ?>">
        <p class="nx-pay-hint" id="nxPayHint" hidden><?php echo ame_spay_t('শেয়ার চাপলে মেমোর ছবি যাবে — নিজে চাপুন, নিজে থেকে পাঠাবে না।', 'Tap Share to send the memo image. It will not send by itself.'); ?></p>
        <button type="button" class="inv-btn inv-btn-print" data-spay-act="print-image"><?php echo ame_spay_t('প্রিন্ট', 'Print'); ?></button>
        <button type="button" class="inv-btn inv-btn-pdf" data-spay-act="download"><?php echo ame_spay_t('পিডিএফ', 'PDF'); ?></button>
        <button type="button" class="inv-btn inv-btn-img" data-spay-act="download-image"><?php echo ame_spay_t('ছবি', 'Image'); ?></button>
        <button type="button" class="inv-btn inv-btn-share" data-spay-act="share" id="nxShareBtn"><?php echo ame_spay_t('শেয়ার', 'Share'); ?></button>
        <a class="inv-btn inv-btn-wa" href="<?php echo htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo ame_spay_t('হোয়াটসঅ্যাপ', 'WhatsApp'); ?></a>
        <button type="button" class="inv-btn inv-btn-tg" data-spay-act="share-telegram"><?php echo ame_spay_t('টেলিগ্রাম', 'Telegram'); ?></button>
    </nav>
    <div id="spayMemo" class="spay-memo memo-wrapper" data-ame-capture="clone">
        <div class="memo-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(56) : ''; ?>
            <h1 class="memo-shop-name"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($addr !== ''): ?><p class="memo-shop-desc"><?php echo htmlspecialchars($addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if ($phone !== ''): ?><p class="memo-shop-phone">📞 <?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <div class="memo-badge"><?php echo te('spay_receipt'); ?></div>
        </div>
        <div class="memo-meta-grid">
            <div class="memo-meta-item spay-c-navy"><span class="lbl"><?php echo te('spay_id'); ?></span><span class="val"><?php echo htmlspecialchars((string)$row['payment_no'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-blue"><span class="lbl"><?php echo te('spay_date'); ?></span><span class="val"><?php echo htmlspecialchars($amePayWhen, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-green"><span class="lbl"><?php echo te('spay_status'); ?></span><span class="val"><?php echo htmlspecialchars($statuses[$row['status']] ?? $row['status'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-navy"><span class="lbl"><?php echo te('spay_company'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['business_name'] ?: $row['supplier_name']), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-ink"><span class="lbl"><?php echo te('spay_code'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['party_code'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-blue"><span class="lbl"><?php echo te('spay_mobile'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['phone'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-green"><span class="lbl"><?php echo te('spay_method'); ?></span><span class="val"><?php echo htmlspecialchars($methods[$row['method']] ?? $row['method'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-navy"><span class="lbl"><?php echo te('spay_account'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['account_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-red"><span class="lbl"><?php echo ame_spay_t('সাপ্লায়ারের নাম', 'Supplier name'); ?></span><span class="val memo-customer-name"><?php echo htmlspecialchars((string)($row['supplier_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-ink"><span class="lbl"><?php echo te('spay_ref'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['reference_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-blue"><span class="lbl"><?php echo te('spay_cheque'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['cheque_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-navy"><span class="lbl"><?php echo te('spay_by'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['created_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="memo-meta-item spay-c-green"><span class="lbl"><?php echo te('spay_auth'); ?></span><span class="val"><?php echo htmlspecialchars((string)($row['approved_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <?php if (!empty($row['lines'])): ?>
        <table class="memo-table">
            <thead>
                <tr>
                    <th><?php echo te('spay_invoice'); ?></th>
                    <th class="tr"><?php echo te('spay_alloc'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($row['lines'] as $line): ?>
                <tr>
                    <td><?php echo htmlspecialchars($line['memo_no'] !== '' ? $line['memo_no'] : ame_spay_t('সাধারণ বকেয়া', 'General due'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="tr"><?php echo number_format((float)$line['allocated'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php if (trim((string)$row['note']) !== ''): ?>
            <p class="memo-note"><?php echo htmlspecialchars((string)$row['note'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="memo-summary">
            <div class="ame-paper-box memo-sum-row sabek-box" id="spayLedgerBox">
                <table class="spay-ledger">
                    <tbody>
                    <tr class="sabek-line spay-row-due">
                        <td><?php echo ame_spay_t('সাবেক বকেয়া', 'Previous due'); ?></td>
                        <td class="num sup-due--payable"><span class="sup-due-amt">+৳<?php echo number_format($ameDueShow, 2); ?></span></td>
                    </tr>
                    <tr class="sabek-line spay-row-paid">
                        <td><?php echo ame_spay_t('পরিশোধ (−)', 'Paid (−)'); ?></td>
                        <td class="num spay-money-paid">−৳<?php echo number_format($amePaidShow, 2); ?></td>
                    </tr>
                    <tr class="sabek-line spay-total-line <?php echo $ameTotalRow; ?>">
                        <td><?php echo htmlspecialchars((string)($ledger['after_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php $tot_signed = $ameAdvanceShow > 0.009 ? -$ameAdvanceShow : $ameRemainShow; ?>
                        <td class="num <?php echo htmlspecialchars(ame_supplier_due_presentation($tot_signed)['class'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="sup-due-amt"><?php echo htmlspecialchars(ame_supplier_due_format_taka($tot_signed), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                    </tr>
                    <tr class="sabek-line spay-row-left">
                        <td><?php echo ame_spay_t('মেমো শেষে অবশিষ্ট বকেয়া', 'Due left on this memo'); ?></td>
                        <td class="num <?php echo htmlspecialchars(ame_supplier_due_presentation($ameRemainShow)['class'], ENT_QUOTES, 'UTF-8'); ?>"><span class="sup-due-amt"><?php echo htmlspecialchars(ame_supplier_due_format_taka($ameRemainShow), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    </tr>
                    <tr class="sabek-line spay-row-adv">
                        <td><?php echo ame_spay_t('অগ্রিম জমা', 'Advance held'); ?></td>
                        <td class="num <?php echo htmlspecialchars(ame_supplier_due_presentation(-$ameAdvanceShow)['class'], ENT_QUOTES, 'UTF-8'); ?>"><span class="sup-due-amt"><?php echo htmlspecialchars(ame_supplier_due_format_taka(-$ameAdvanceShow), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    </tr>
                    </tbody>
                </table>
                <div class="ame-paper-proof spay-ledger-proof"><?php echo htmlspecialchars($ledger['formula'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <div class="memo-foot">
            <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int)($row['created_by'] ?? 0)) : ''; ?>
        </div>
    </div>

    <div class="spay-more no-print spay-no-print">
        <button class="spay-btn" type="button" data-spay-act="view"><?php echo te('spay_view'); ?></button>
        <?php if (!empty($row['attachment'])): ?>
            <a class="spay-btn" href="/<?php echo htmlspecialchars((string)$row['attachment'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo te('spay_file'); ?></a>
        <?php endif; ?>
        <?php if (in_array($row['status'], ['draft', 'pending'], true)): ?>
            <a class="spay-btn" href="pay.php?id=<?php echo (int)$row['supplier_id']; ?>&amp;edit=<?php echo (int)$row['id']; ?>"><?php echo te('spay_edit'); ?></a>
        <?php endif; ?>
        <a class="spay-btn" href="payments-list.php"><?php echo te('spay_hist'); ?></a>
    </div>

    <div class="spay-card spay-no-print no-print">
        <h2><?php echo te('spay_audit'); ?></h2>
        <div class="spay-table-wrap">
            <table>
                <thead><tr><th><?php echo te('spay_date'); ?></th><th><?php echo te('spay_status'); ?></th><th><?php echo te('spay_by'); ?></th><th><?php echo te('spay_note'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($row['audit'] as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$a['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$a['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)($a['username'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)($a['note'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($row['status'] === 'pending'): ?>
    <form class="spay-card spay-no-print" method="post" action="settlement_action.php" id="spReverse">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <button class="spay-btn primary" name="action" value="approve" type="submit"><?php echo te('spay_approve'); ?></button>
        <?php if (function_exists('ame_pay_sec_box')) echo ame_pay_sec_box('supplier_cancel', (int)$row['id'], 'খসড়া বাতিল করতে কোড লিখুন। অনুমোদনে কোড লাগে না।', 'Type the code only to cancel. Approve does not need it.', false); ?>
        <button class="spay-btn" name="action" value="cancel" type="submit"><?php echo te('spay_cancel'); ?></button>
    </form>
    <?php elseif ($row['status'] === 'draft'): ?>
    <form class="spay-card spay-no-print" method="post" action="settlement_action.php" id="spReverse">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <?php if (function_exists('ame_pay_sec_box')) echo ame_pay_sec_box('supplier_cancel', (int)$row['id'], 'খসড়া বাতিল করতে কোড লিখুন।', 'Type the code to cancel this draft.'); ?>
        <button class="spay-btn" name="action" value="cancel" type="submit"><?php echo te('spay_cancel'); ?></button>
    </form>
    <?php elseif ($row['status'] === 'paid'): ?>
    <form class="spay-card spay-no-print" method="post" action="settlement_action.php" id="spReverse">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <input type="hidden" name="action" value="reverse">
        <h2><?php echo te('spay_reverse'); ?></h2>
        <p class="spay-muted"><?php echo ame_spay_t('ভুল পেমেন্ট হলে এখানে বাতিল করুন। টাকা অ্যাকাউন্টে ফিরে আসবে, সাপ্লায়ারের বকেয়া বাড়বে।', 'Cancel a mistaken payment here. Cash returns to the account and supplier due goes back up.'); ?></p>
        <label for="spReason"><?php echo te('spay_reason'); ?></label>
        <input type="text" id="spReason" name="reason" required maxlength="255" placeholder="<?php echo htmlspecialchars(ame_spay_t('যেমন: একই পেমেন্ট দুবার হয়ে গেছে', 'e.g. paid twice by mistake'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (function_exists('ame_pay_sec_box')) echo ame_pay_sec_box('supplier_reverse', (int)$row['id'], 'বাতিল করতে পর্দার কোড লিখুন।', 'Type the on-screen code to reverse this payment.'); ?>
        <button class="spay-btn warn" type="submit" style="margin-top:8px;"><?php echo te('spay_reverse'); ?></button>
    </form>
    <?php elseif ($row['status'] === 'reversed'): ?>
    <p class="spay-error spay-no-print" id="spReverse"><?php echo ame_spay_t('এই পেমেন্ট বাতিল হয়ে গেছে।', 'This payment has been reversed.'); ?></p>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
<link rel="stylesheet" href="/assets/css/supplier-settlement.css?v=10.98.847" id="spayInkLock">
<link rel="stylesheet" href="/assets/css/memo-clear.css?v=10.98.847">
<script>
document.addEventListener('DOMContentLoaded', function () {
    var fileBase = <?php echo json_encode($fileBase, JSON_UNESCAPED_UNICODE); ?>;
    function run(act) {
        if (act === 'view') {
            var memo = document.getElementById('spayMemo');
            if (memo && memo.scrollIntoView) memo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture(act, fileBase, 'spayMemo');
        }
    }
    document.querySelectorAll('[data-spay-act]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            run(btn.getAttribute('data-spay-act'));
        });
    });
    var params = new URLSearchParams(window.location.search);
    var act = '';
    if (params.get('print') === '1') act = 'print-image';
    else if (params.get('download') === '1') act = 'download';
    else if (params.get('image') === '1') act = 'download-image';
    else if (params.get('tg') === '1' || params.get('telegram') === '1') act = 'share-telegram';
    if (act) {
        window.setTimeout(function () { run(act); }, 400);
    }
    if (params.get('share') === '1') {
        var hint = document.getElementById('nxPayHint');
        if (hint) hint.hidden = false;
        var shareBtn = document.getElementById('nxShareBtn');
        if (shareBtn) shareBtn.classList.add('is-pulse');
        var bar = document.getElementById('spayShareBar');
        if (bar && bar.scrollIntoView) bar.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
