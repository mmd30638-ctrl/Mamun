<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/loan_functions.php';
admin_only();
ensure_bank_loan_schema($db);

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM bank_loans WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    die('❌ লোন খুঁজে পাওয়া যায়নি। <a href="list.php">তালিকায় ফিরুন</a>');
}

$installments = $db->prepare("SELECT * FROM loan_installments WHERE loan_id = ? ORDER BY installment_no ASC");
$installments->bind_param('i', $id);
$installments->execute();
$installment_list = $installments->get_result()->fetch_all(MYSQLI_ASSOC);

$txns = $db->prepare("SELECT lt.*, a.account_name FROM loan_transactions lt LEFT JOIN accounts a ON lt.account_id = a.id WHERE lt.loan_id = ? ORDER BY lt.id DESC");
$txns->bind_param('i', $id);
$txns->execute();
$txn_list = $txns->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../../partials/header.php';

$due = (float)$loan['principal_amount'] - (float)$loan['paid_principal'];
$total_interest_paid = array_sum(array_column($txn_list, 'interest_part'));

$status_meta = [
    'active' => ['label' => '🟢 চলমান', 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'completed' => ['label' => '✅ সম্পূর্ণ পরিশোধ', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'overdue' => ['label' => '🔴 বকেয়া', 'bg' => '#FFEBEE', 'color' => '#C62828'],
    'closed' => ['label' => '⚪ বন্ধ', 'bg' => '#F1F5F9', 'color' => '#64748B'],
];
$meta = $status_meta[$loan['status']] ?? $status_meta['active'];
?>
<style>
    :root { --ln-navy:#0B1E4D; --ln-indigo:#4F46E5; --ln-bg:#EEF2FF; }
    .ln-wrap { padding: 14px; max-width: 800px; margin: 0 auto; }
    .ln-top-card { background: linear-gradient(135deg,var(--ln-navy),var(--ln-indigo)); color:#fff; border-radius:18px; padding:20px; margin-bottom:14px; }
    .ln-top-card h2 { margin:0 0 4px; font-size:18px; }
    .ln-badge { display:inline-block; margin-top:6px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:800; }
    .ln-kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:14px; }
    @media (max-width:600px) { .ln-kpi-row { grid-template-columns:repeat(2,1fr); } }
    .ln-kpi-cell { background:rgba(255,255,255,0.12); border-radius:10px; padding:10px; text-align:center; }
    .ln-kpi-cell .lbl { font-size:10px; opacity:.85; }
    .ln-kpi-cell .val { font-size:14px; font-weight:800; margin-top:3px; }

    .ln-section { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom:14px; }
    .ln-section h3 { margin:0 0 12px; color:var(--ln-navy); font-size:14px; }
    .ln-info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px 16px; font-size:12.5px; }
    .ln-info-grid strong { color:var(--ln-navy); }

    .ln-table { width:100%; border-collapse:collapse; font-size:11.5px; }
    .ln-table th { background:var(--ln-navy); color:#fff; padding:7px; text-align:left; }
    .ln-table td { padding:7px; border-bottom:1px solid #F1F5F9; }
    .ln-status-pill { padding:2px 8px; border-radius:12px; font-size:10px; font-weight:800; }

    .ln-actions { display:flex; gap:8px; margin-top:14px; }
    .ln-btn { flex:1; padding:12px; border:none; border-radius:10px; font-weight:800; font-size:13px; cursor:pointer; text-align:center; text-decoration:none; }
    .ln-btn-pay { background: linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; }
    .ln-btn-back { background:#F1F5F9; color:#64748B; }
</style>

<div class="ln-wrap">
    <div class="ln-top-card">
        <h2><?php echo htmlspecialchars($loan['loan_name']); ?></h2>
        <span style="opacity:.85; font-size:12.5px;">🏦 <?php echo htmlspecialchars($loan['bank_name']); ?></span>
        <span class="ln-badge" style="background:rgba(255,255,255,0.2);"><?php echo $meta['label']; ?></span>

        <div class="ln-kpi-row">
            <div class="ln-kpi-cell"><div class="lbl">মূল ঋণ</div><div class="val">৳<?php echo number_format((float)$loan['principal_amount'], 0); ?></div></div>
            <div class="ln-kpi-cell"><div class="lbl">মোট পরিশোধ</div><div class="val">৳<?php echo number_format((float)$loan['paid_principal'], 0); ?></div></div>
            <div class="ln-kpi-cell"><div class="lbl">মোট বাকি</div><div class="val">৳<?php echo number_format($due, 0); ?></div></div>
            <div class="ln-kpi-cell"><div class="lbl">সুদ পরিশোধ</div><div class="val">৳<?php echo number_format($total_interest_paid, 0); ?></div></div>
        </div>

        <?php if ($loan['status'] === 'active'): ?>
        <div class="ln-actions">
            <a href="pay.php?id=<?php echo (int)$loan['id']; ?>" class="ln-btn ln-btn-pay">💳 কিস্তি পরিশোধ করুন</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E8F5E9; color:#0B8043; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="ln-section">
        <h3>ℹ️ লোনের তথ্য</h3>
        <div class="ln-info-grid">
            <div><strong>ধরন:</strong> <?php echo htmlspecialchars($loan['loan_type']); ?></div>
            <div><strong>সুদের হার:</strong> <?php echo number_format((float)$loan['interest_rate'], 2); ?>%</div>
            <div><strong>নেওয়ার তারিখ:</strong> <?php echo date('d M Y', strtotime($loan['loan_date'])); ?></div>
            <div><strong>মেয়াদ:</strong> <?php echo (int)$loan['tenure_months']; ?> মাস</div>
            <div><strong>কিস্তির সংখ্যা:</strong> <?php echo (int)$loan['installment_count']; ?> টি</div>
            <div><strong>প্রতি কিস্তি:</strong> ৳<?php echo number_format((float)$loan['installment_amount'], 2); ?></div>
        </div>
        <?php if (!empty($loan['notes'])): ?>
        <div style="background:var(--ln-bg); border-radius:10px; padding:10px 12px; margin-top:12px; font-size:12px;"><strong>নোট:</strong> <?php echo nl2br(htmlspecialchars($loan['notes'])); ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($installment_list)): ?>
    <div class="ln-section">
        <h3>📅 কিস্তির সময়সূচি</h3>
        <div style="overflow-x:auto;">
        <table class="ln-table">
            <thead><tr><th>#</th><th>তারিখ</th><th style="text-align:right;">পরিমাণ</th><th>অবস্থা</th></tr></thead>
            <tbody>
                <?php foreach ($installment_list as $inst): ?>
                <tr>
                    <td><?php echo (int)$inst['installment_no']; ?></td>
                    <td><?php echo date('d M Y', strtotime($inst['due_date'])); ?></td>
                    <td style="text-align:right;">৳<?php echo number_format((float)$inst['installment_amount'], 2); ?></td>
                    <td>
                        <?php if ($inst['status'] === 'paid'): ?>
                            <span class="ln-status-pill" style="background:#E8F5E9; color:#0B8043;">✅ পরিশোধিত</span>
                        <?php elseif (strtotime($inst['due_date']) < strtotime(date('Y-m-d'))): ?>
                            <span class="ln-status-pill" style="background:#FFEBEE; color:#C62828;">⏰ মেয়াদোত্তীর্ণ</span>
                        <?php else: ?>
                            <span class="ln-status-pill" style="background:#FFF3E0; color:#E65100;">⏳ বকেয়া</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="ln-section">
        <h3>🕒 লেনদেনের ইতিহাস</h3>
        <?php if (empty($txn_list)): ?>
            <p style="text-align:center; color:#94A3B8; font-size:12.5px;">এখনো কোনো লেনদেন নেই।</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="ln-table">
            <thead><tr><th>তারিখ</th><th>ধরন</th><th style="text-align:right;">মোট</th><th style="text-align:right;">আসল</th><th style="text-align:right;">সুদ</th><th>অ্যাকাউন্ট</th></tr></thead>
            <tbody>
                <?php foreach ($txn_list as $t): ?>
                <tr>
                    <td><?php echo date('d-m-Y', strtotime($t['txn_date'])); ?></td>
                    <td><?php echo $t['txn_type'] === 'disbursement' ? '⬆️ গ্রহণ' : '⬇️ কিস্তি'; ?></td>
                    <td style="text-align:right; font-weight:700;">৳<?php echo number_format((float)$t['total_amount'], 2); ?></td>
                    <td style="text-align:right;">৳<?php echo number_format((float)$t['principal_part'], 2); ?></td>
                    <td style="text-align:right;">৳<?php echo number_format((float)$t['interest_part'], 2); ?></td>
                    <td><?php echo htmlspecialchars($t['account_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <a href="list.php" class="ln-btn ln-btn-back" style="display:block;">← তালিকায় ফিরে যান</a>
</div>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
