<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$id = (int)($_GET['id'] ?? 0);
$owner = ame_oloan_owner_fetch($db, $id);
if (!$owner) {
    die('❌ ' . ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.') . ' <a href="list.php">' . ame_l('তালিকায় ফিরে যান', 'Back to list') . '</a>');
}
ame_owner_loan_recalc_installment_statuses($db);
$summary = ame_owner_financial_summary($db, $id);

$loans_stmt = $db->prepare("SELECT ol.*, b.bank_name FROM owner_loans ol LEFT JOIN banks b ON b.id = ol.bank_id WHERE ol.owner_id = ? ORDER BY ol.id DESC");
$loans_stmt->bind_param('i', $id);
$loans_stmt->execute();
$loans = $loans_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$loans_stmt->close();
$loan_ids = array_map(fn($l) => (int)$l['id'], $loans);
$loan_ids_in = $loan_ids ? implode(',', $loan_ids) : '0';

$disbursements = $db->query("SELECT d.* FROM owner_loan_disbursements d WHERE d.loan_id IN ($loan_ids_in) ORDER BY d.id DESC")->fetch_all(MYSQLI_ASSOC);
$installments = $db->query("SELECT oi.*, oi.loan_id FROM owner_loan_installments oi WHERE oi.loan_id IN ($loan_ids_in) ORDER BY oi.due_date ASC")->fetch_all(MYSQLI_ASSOC);
$payments = $db->query("SELECT p.* FROM owner_loan_payments p WHERE p.loan_id IN ($loan_ids_in) ORDER BY p.id DESC")->fetch_all(MYSQLI_ASSOC);

$savings_stmt = $db->prepare("SELECT * FROM owner_savings_accounts WHERE owner_id = ? ORDER BY id ASC");
$savings_stmt->bind_param('i', $id);
$savings_stmt->execute();
$savings_accounts = $savings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$savings_stmt->close();

$sav_deposits_stmt = $db->prepare("SELECT * FROM owner_savings_transactions WHERE owner_id = ? AND txn_type = 'deposit' ORDER BY id DESC");
$sav_deposits_stmt->bind_param('i', $id);
$sav_deposits_stmt->execute();
$sav_deposits = $sav_deposits_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sav_deposits_stmt->close();

$sav_withdrawals_stmt = $db->prepare("SELECT * FROM owner_savings_withdrawals WHERE owner_id = ? ORDER BY id DESC");
$sav_withdrawals_stmt->bind_param('i', $id);
$sav_withdrawals_stmt->execute();
$sav_withdrawals = $sav_withdrawals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sav_withdrawals_stmt->close();

$ledger_stmt = $db->prepare("SELECT * FROM owner_transaction_ledger WHERE owner_id = ? ORDER BY id DESC LIMIT 200");
$ledger_stmt->bind_param('i', $id);
$ledger_stmt->execute();
$ledger_rows = $ledger_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ledger_stmt->close();

$documents_stmt = $db->prepare("SELECT * FROM owner_documents WHERE owner_id = ? ORDER BY id DESC");
$documents_stmt->bind_param('i', $id);
$documents_stmt->execute();
$documents = $documents_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$documents_stmt->close();

$flash_ok = (string)($_GET['saved'] ?? '');
$flash_err = (string)($_GET['err'] ?? '');
$status_class_map = ['upcoming' => 'upcoming', 'partial' => 'partial', 'overdue' => 'overdue', 'paid' => 'paid'];

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-profile-wrap { padding: 15px; max-width: 1080px; margin: 0 auto; }
.oln-profile-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-profile-head h3 { margin:0; color: var(--brand-navy); }
.oln-back-link { color: var(--brand-accent); font-weight:600; font-size:13px; text-decoration:none; }
.oln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-weight:600; font-size:13.5px; }
.oln-flash.ok { background: var(--brand-success-soft); color: var(--brand-success); }
.oln-flash.err { background: var(--brand-danger-soft); color: var(--brand-danger); }
.oln-actionbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.oln-actionbar a { background: var(--brand-gradient); color: var(--brand-white) !important; text-decoration:none; padding:9px 16px; border-radius:8px; font-weight:700; font-size:12.5px; }
.oln-actionbar a.ghost { background: var(--brand-white); color: var(--brand-accent) !important; border:1.5px solid var(--brand-border); }
.oln-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:18px; }
.oln-summary-item { background: var(--brand-white); border:1px solid var(--brand-border); border-radius:10px; padding:12px; }
.oln-summary-item .l { font-size:11.5px; color: var(--brand-muted); font-weight:700; }
.oln-summary-item .v { font-size:15px; font-weight:800; color: var(--brand-navy); }
.oln-tabbar { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; border-bottom:2px solid var(--brand-border); }
.oln-tab-btn { background:transparent; border:none; padding:9px 14px; cursor:pointer; font-weight:700; font-size:12.5px; color: var(--brand-muted); border-bottom:3px solid transparent; margin-bottom:-2px; }
.oln-tab-btn.is-active { color: var(--brand-navy); border-bottom-color: var(--brand-accent); background: var(--oln-tab-active-bg); }
.oln-tab-panel { display:none; }
.oln-tab-panel.is-active { display:block; }
.oln-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px 18px; }
.oln-info-item .l { font-size:11.5px; color: var(--brand-muted); font-weight:700; }
.oln-info-item .v { font-size:14px; color: var(--brand-navy); font-weight:600; }
.oln-table { width:100%; border-collapse: collapse; font-size:12.5px; }
.oln-table th { text-align:left; padding:8px; background: var(--brand-navy); color: var(--brand-white); }
.oln-table td { padding:8px; border-bottom:1px solid var(--brand-border); }
.oln-empty { text-align:center; padding:16px; color: var(--brand-muted); }
.oln-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
.oln-badge.upcoming { background: var(--oln-status-upcoming-bg); color: var(--oln-status-upcoming-fg); }
.oln-badge.partial { background: var(--oln-status-partial-bg); color: var(--oln-status-partial-fg); }
.oln-badge.overdue { background: var(--oln-status-overdue-bg); color: var(--oln-status-overdue-fg); }
.oln-badge.paid { background: var(--oln-status-paid-bg); color: var(--oln-status-paid-fg); }
.oln-credit { color: var(--oln-ledger-credit); font-weight:700; }
.oln-debit { color: var(--oln-ledger-debit); font-weight:700; }
.oln-doc-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid var(--brand-border); }
.oln-doc-row:last-child { border-bottom:none; }
.oln-doc-name { font-weight:700; color: var(--brand-navy); text-decoration:none; }
.oln-doc-del { color: var(--brand-danger); text-decoration:none; font-size:12.5px; font-weight:700; }
.oln-doc-upload-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:14px; padding-top:14px; border-top:1px dashed var(--brand-border); }
.oln-doc-upload-form input[type="text"] { flex:1; min-width:140px; padding:9px 11px; border:1.5px solid var(--brand-border); border-radius:8px; }
.oln-profile-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-profile-wrap">
    <div class="oln-profile-head">
        <h3>🏪 <?php echo esc_html($owner['owner_name']); ?> — <?php echo esc_html($owner['shop_name']); ?></h3>
        <a href="list.php" class="oln-back-link">⬅️ <?php echo ame_l('তালিকায় ফিরে যান', 'Back to list'); ?></a>
    </div>
    <?php if ($flash_ok === '1'): ?><div class="oln-flash ok">✅ <?php echo ame_l('সংরক্ষণ করা হয়েছে।', 'Saved successfully.'); ?></div><?php endif; ?>
    <?php if ($flash_err !== ''): ?><div class="oln-flash err">⚠️ <?php echo esc_html($flash_err); ?></div><?php endif; ?>

    <div class="oln-actionbar">
        <a href="add.php?id=<?php echo (int)$id; ?>">✏️ <?php echo ame_l('প্রোফাইল এডিট', 'Edit profile'); ?></a>
        <a href="loan_add.php?owner_id=<?php echo (int)$id; ?>">+ <?php echo ame_l('নতুন ঋণ', 'New loan'); ?></a>
        <a href="payment_entry.php?owner_id=<?php echo (int)$id; ?>" class="ghost">💰 <?php echo ame_l('আদায় করুন', 'Collect payment'); ?></a>
        <a href="savings_withdraw.php?owner_id=<?php echo (int)$id; ?>" class="ghost">🏧 <?php echo ame_l('সঞ্চয় উত্তোলন', 'Savings withdrawal'); ?></a>
        <a href="statement.php?owner_id=<?php echo (int)$id; ?>" class="ghost">🖨️ <?php echo ame_l('স্টেটমেন্ট প্রিন্ট', 'Print statement'); ?></a>
    </div>

    <div class="oln-summary-grid">
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট ঋণ অনুমোদিত', 'Total approved'); ?></div><div class="v">৳<?php echo number_format($summary['total_loan_approved'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট বিতরণ', 'Total disbursed'); ?></div><div class="v">৳<?php echo number_format($summary['total_loan_disbursed'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট আদায়', 'Total collected'); ?></div><div class="v">৳<?php echo number_format($summary['total_collected'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মোট বকেয়া', 'Total outstanding'); ?></div><div class="v" style="color:var(--brand-danger);">৳<?php echo number_format($summary['total_outstanding'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('সঞ্চয় ব্যালেন্স', 'Savings balance'); ?></div><div class="v" style="color:var(--brand-success);">৳<?php echo number_format($summary['savings_balance'], 2); ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('পরবর্তী কিস্তি', 'Next installment'); ?></div><div class="v"><?php echo $summary['next_installment_due'] ? ('৳' . number_format($summary['next_installment_amount'], 2) . ' — ' . esc_html($summary['next_installment_due'])) : '—'; ?></div></div>
        <div class="oln-summary-item"><div class="l"><?php echo ame_l('মেয়াদোত্তীর্ণ', 'Overdue'); ?></div><div class="v" style="color:var(--brand-danger);">৳<?php echo number_format($summary['overdue_amount'], 2); ?></div></div>
    </div>

    <div class="card">
        <div class="oln-tabbar" role="tablist">
            <button type="button" class="oln-tab-btn is-active" data-oln-tab="basic"><?php echo ame_l('সাধারণ তথ্য', 'Basic info'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="business"><?php echo ame_l('ব্যবসার তথ্য', 'Business info'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="documents"><?php echo ame_l('ডকুমেন্টস', 'Documents'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="loans"><?php echo ame_l('ঋণ সারসংক্ষেপ', 'Loan summary'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="disbursements"><?php echo ame_l('বিতরণ ইতিহাস', 'Disbursements'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="schedule"><?php echo ame_l('কিস্তির শিডিউল', 'Installment schedule'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="payments"><?php echo ame_l('পেমেন্ট ইতিহাস', 'Payment history'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="savings"><?php echo ame_l('সঞ্চয় হিসাব', 'Savings account'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="sav_deposits"><?php echo ame_l('সঞ্চয় জমা ইতিহাস', 'Savings deposits'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="sav_withdrawals"><?php echo ame_l('সঞ্চয় উত্তোলন ইতিহাস', 'Savings withdrawals'); ?></button>
            <button type="button" class="oln-tab-btn" data-oln-tab="ledger"><?php echo ame_l('সম্পূর্ণ লেজার', 'Complete ledger'); ?></button>
        </div>

        <div class="oln-tab-panel is-active" data-oln-panel="basic">
            <div class="oln-grid-2">
                <div class="oln-info-item"><div class="l"><?php echo ame_l('মালিকের নাম', "Owner's name"); ?></div><div class="v"><?php echo esc_html($owner['owner_name']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('পিতা/অভিভাবক', 'Parent/guardian'); ?></div><div class="v"><?php echo esc_html($owner['parent_name']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('এনআইডি', 'NID'); ?></div><div class="v"><?php echo esc_html($owner['nid_no']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('মোবাইল', 'Mobile'); ?></div><div class="v"><?php echo esc_html($owner['mobile']); ?></div></div>
                <div class="oln-info-item" style="grid-column:1/-1;"><div class="l"><?php echo ame_l('ঠিকানা', 'Address'); ?></div><div class="v"><?php echo esc_html($owner['address']); ?></div></div>
                <?php if (!empty($owner['photo'])): ?>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('ছবি', 'Photo'); ?></div><div class="v"><img src="/<?php echo esc_html(ltrim($owner['photo'], '/')); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:1px solid var(--brand-border);"></div></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="business">
            <div class="oln-grid-2">
                <div class="oln-info-item"><div class="l"><?php echo ame_l('দোকানের নাম', 'Shop name'); ?></div><div class="v"><?php echo esc_html($owner['shop_name']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('দোকানের ধরন', 'Shop type'); ?></div><div class="v"><?php echo esc_html($owner['shop_type']); ?></div></div>
                <div class="oln-info-item" style="grid-column:1/-1;"><div class="l"><?php echo ame_l('ব্যবসার ঠিকানা', 'Business address'); ?></div><div class="v"><?php echo esc_html($owner['business_address']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('ট্রেড লাইসেন্স', 'Trade license'); ?></div><div class="v"><?php echo esc_html($owner['trade_license_no']); ?></div></div>
                <div class="oln-info-item"><div class="l"><?php echo ame_l('অবস্থা', 'Status'); ?></div><div class="v"><?php echo (int)$owner['status'] === 1 ? ame_l('সক্রিয়', 'Active') : ame_l('নিষ্ক্রিয়', 'Inactive'); ?></div></div>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="documents">
            <?php if (!$documents): ?>
                <div class="oln-empty"><?php echo ame_l('কোনো ডকুমেন্ট আপলোড করা হয়নি।', 'No documents uploaded yet.'); ?></div>
            <?php else: foreach ($documents as $d): ?>
                <div class="oln-doc-row">
                    <a class="oln-doc-name" href="/<?php echo esc_html(ltrim($d['file_path'], '/')); ?>" target="_blank"><?php echo esc_html($d['title']); ?></a>
                    <div>
                        <span style="font-size:11.5px;color:var(--brand-muted);"><?php echo esc_html($d['doc_category']); ?> · <?php echo esc_html($d['created_at']); ?></span>
                        <form action="process_document.php" method="POST" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="owner_id" value="<?php echo (int)$id; ?>">
                            <input type="hidden" name="doc_id" value="<?php echo (int)$d['id']; ?>">
                            <button type="submit" class="oln-doc-del" style="background:none;border:none;cursor:pointer;">🗑️ <?php echo ame_l('মুছুন', 'Delete'); ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <form action="process_document.php" method="POST" enctype="multipart/form-data" class="oln-doc-upload-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="owner_id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="doc_category" value="owner">
                <input type="text" name="title" placeholder="<?php echo ame_l('ডকুমেন্টের নাম', 'Document title'); ?>" maxlength="150" required>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                <button type="submit" class="btn-success" style="padding:9px 16px;">⬆️ <?php echo ame_l('আপলোড', 'Upload'); ?></button>
            </form>
        </div>

        <div class="oln-tab-panel" data-oln-panel="loans">
            <div class="oln-table-wrap" style="overflow-x:auto;">
            <table class="oln-table"><thead><tr>
                <th>#</th><th><?php echo ame_l('ব্যাংক', 'Bank'); ?></th><th><?php echo ame_l('অনুমোদিত', 'Approved'); ?></th><th><?php echo ame_l('বকেয়া', 'Outstanding'); ?></th><th><?php echo ame_l('স্ট্যাটাস', 'Status'); ?></th><th></th>
            </tr></thead><tbody>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td>#<?php echo (int)$l['id']; ?></td>
                    <td><?php echo esc_html($l['bank_name'] ?? '—'); ?></td>
                    <td>৳<?php echo number_format((float)$l['approved_amount'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$l['outstanding_amount'], 2); ?></td>
                    <td><?php echo esc_html($l['status']); ?></td>
                    <td><a href="loan_view.php?id=<?php echo (int)$l['id']; ?>"><?php echo ame_l('বিস্তারিত', 'Detail'); ?></a></td>
                </tr>
            <?php endforeach; if (!$loans): ?><tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো ঋণ নেই।', 'No loans yet.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="disbursements">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('বিতরণ', 'Disbursed'); ?></th><th><?php echo ame_l('নিট', 'Net'); ?></th><th></th></tr></thead><tbody>
            <?php foreach ($disbursements as $r): ?>
                <tr><td><?php echo esc_html($r['disbursement_date']); ?></td><td>#<?php echo (int)$r['loan_id']; ?></td><td>৳<?php echo number_format((float)$r['disbursed_amount'],2); ?></td><td>৳<?php echo number_format((float)$r['net_amount_received'],2); ?></td><td><a href="receipt.php?type=disbursement&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td></tr>
            <?php endforeach; if (!$disbursements): ?><tr><td colspan="5" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="schedule">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th>#</th><th><?php echo ame_l('মেয়াদ', 'Due'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th><?php echo ame_l('পরিশোধিত', 'Paid'); ?></th><th><?php echo ame_l('বাকি', 'Remaining'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr></thead><tbody>
            <?php foreach ($installments as $r): $cls = $status_class_map[$r['status']] ?? 'upcoming'; ?>
                <tr>
                    <td>#<?php echo (int)$r['loan_id']; ?></td><td><?php echo (int)$r['installment_no']; ?></td><td><?php echo esc_html($r['due_date']); ?></td>
                    <td><?php echo number_format((float)$r['total_installment'],2); ?></td><td><?php echo number_format((float)$r['paid_amount'],2); ?></td><td><?php echo number_format((float)$r['remaining_amount'],2); ?></td>
                    <td><span class="oln-badge <?php echo $cls; ?>"><?php echo esc_html($r['status']); ?></span></td>
                </tr>
            <?php endforeach; if (!$installments): ?><tr><td colspan="7" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="payments">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ঋণ', 'Loan'); ?></th><th><?php echo ame_l('আসল', 'Principal'); ?></th><th><?php echo ame_l('সুদ', 'Interest'); ?></th><th><?php echo ame_l('মোট', 'Total'); ?></th><th></th></tr></thead><tbody>
            <?php foreach ($payments as $r): ?>
                <tr><td><?php echo esc_html($r['payment_date']); ?></td><td>#<?php echo (int)$r['loan_id']; ?></td><td>৳<?php echo number_format((float)$r['principal_paid'],2); ?></td><td>৳<?php echo number_format((float)$r['interest_paid'],2); ?></td><td>৳<?php echo number_format((float)$r['total_payment'],2); ?></td><td><a href="receipt.php?type=payment&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td></tr>
            <?php endforeach; if (!$payments): ?><tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="savings">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('খোলার তারিখ', 'Opened'); ?></th><th><?php echo ame_l('বর্তমান ব্যালেন্স', 'Current balance'); ?></th><th><?php echo ame_l('অবস্থা', 'Status'); ?></th></tr></thead><tbody>
            <?php foreach ($savings_accounts as $r): ?><tr><td><?php echo esc_html($r['account_opening_date']); ?></td><td>৳<?php echo number_format((float)$r['current_balance'],2); ?></td><td><?php echo esc_html($r['status']); ?></td></tr>
            <?php endforeach; if (!$savings_accounts): ?><tr><td colspan="3" class="oln-empty"><?php echo ame_l('কোনো সঞ্চয় হিসাব নেই।', 'No savings account yet.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="sav_deposits">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('পরিমাণ', 'Amount'); ?></th><th><?php echo ame_l('নতুন ব্যালেন্স', 'New balance'); ?></th><th></th></tr></thead><tbody>
            <?php foreach ($sav_deposits as $r): ?><tr><td><?php echo esc_html($r['txn_date']); ?></td><td>৳<?php echo number_format((float)$r['amount'],2); ?></td><td>৳<?php echo number_format((float)$r['new_balance'],2); ?></td><td><a href="receipt.php?type=savings_deposit&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td></tr>
            <?php endforeach; if (!$sav_deposits): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="sav_withdrawals">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('পরিমাণ', 'Amount'); ?></th><th><?php echo ame_l('কারণ', 'Reason'); ?></th><th></th></tr></thead><tbody>
            <?php foreach ($sav_withdrawals as $r): ?><tr><td><?php echo esc_html($r['withdrawal_date']); ?></td><td>৳<?php echo number_format((float)$r['amount'],2); ?></td><td><?php echo esc_html($r['reason']); ?></td><td><a href="receipt.php?type=savings_withdrawal&id=<?php echo (int)$r['id']; ?>"><?php echo ame_l('রসিদ', 'Receipt'); ?></a></td></tr>
            <?php endforeach; if (!$sav_withdrawals): ?><tr><td colspan="4" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>

        <div class="oln-tab-panel" data-oln-panel="ledger">
            <div style="overflow-x:auto;">
            <table class="oln-table"><thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('ধরন', 'Type'); ?></th><th><?php echo ame_l('বিবরণ', 'Description'); ?></th><th><?php echo ame_l('ডেবিট', 'Debit'); ?></th><th><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th><th><?php echo ame_l('ব্যালেন্স', 'Balance'); ?></th></tr></thead><tbody>
            <?php foreach ($ledger_rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['txn_date']); ?></td><td><?php echo esc_html($r['txn_type']); ?></td><td><?php echo esc_html($r['description']); ?></td>
                    <td class="oln-debit"><?php echo (float)$r['debit'] > 0 ? number_format((float)$r['debit'],2) : '—'; ?></td>
                    <td class="oln-credit"><?php echo (float)$r['credit'] > 0 ? number_format((float)$r['credit'],2) : '—'; ?></td>
                    <td><?php echo number_format((float)$r['balance'],2); ?></td>
                </tr>
            <?php endforeach; if (!$ledger_rows): ?><tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো তথ্য নেই।', 'No data.'); ?></td></tr><?php endif; ?>
            </tbody></table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var buttons = document.querySelectorAll('[data-oln-tab]');
    var panels = document.querySelectorAll('[data-oln-panel]');
    function activate(name) {
        buttons.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-oln-tab') === name); });
        panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-oln-panel') === name); });
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () { activate(btn.getAttribute('data-oln-tab')); });
    });
    if (window.location.hash) {
        var h = window.location.hash.replace('#', '');
        if (document.querySelector('[data-oln-panel="' + h + '"]')) { activate(h); }
    }
})();
</script>
<?php include '../../../partials/footer.php'; ?>
