<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

$partners = $db->query("SELECT id, name, share_percentage, status FROM partners ORDER BY status = 'active' DESC, name ASC");
$partner_list = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];

$selected_id = (int)($_GET['partner_id'] ?? ($partner_list[0]['id'] ?? 0));
$selected_partner = null;
foreach ($partner_list as $p) {
    if ((int)$p['id'] === $selected_id) { $selected_partner = $p; break; }
}

$capital_ledger = [];
$current_ledger = [];
$profit_share_total = 0;

if ($selected_partner) {
    // ================= Capital Account লেজার =================
    $stmt = $db->prepare(
        "SELECT transaction_date as entry_date, type, amount, note
         FROM partner_capital_transactions
         WHERE partner_id = ?
         ORDER BY transaction_date ASC, id ASC"
    );
    $stmt->bind_param('i', $selected_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $running = 0;
    foreach ($rows as $r) {
        $signed = $r['type'] === 'capital_withdrawal' ? -1 * (float)$r['amount'] : (float)$r['amount'];
        $running += $signed;
        $capital_ledger[] = [
            'date' => $r['entry_date'],
            'label' => [
                'investment' => 'মূলধন জমা',
                'capital_withdrawal' => 'মূলধন উত্তোলন',
                'loan_to_business' => 'ব্যবসাকে ঋণ',
            ][$r['type']] ?? $r['type'],
            'amount' => $signed,
            'note' => $r['note'],
            'balance' => $running,
        ];
    }
    $capital_closing_balance = $running;

    // ================= Current Account লেজার (লাভের ভাগ + উত্তোলন) =================
    // আন্তর্জাতিক হিসাববিজ্ঞান রীতি: Capital Account স্থায়ী মালিকানা,
    // Current Account প্রতি সময়কালের অনুমোদিত লাভ-ভাগ ও উত্তোলনের
    // চলমান হিসাব। ধাপ ৪ থেকে — প্রতিটা "approved" বণ্টন এখানে
    // পৃথক এন্ট্রি হিসেবে যুক্ত হয় (সময়কাল-ভিত্তিক, সঠিক পদ্ধতি)।
    $approved_allocations = get_partner_approved_allocations($db, $selected_id);

    $wstmt = $db->prepare(
        "SELECT withdrawal_date as entry_date, amount, note
         FROM partner_withdrawals
         WHERE partner_id = ?
         ORDER BY withdrawal_date ASC, id ASC"
    );
    $wstmt->bind_param('i', $selected_id);
    $wstmt->execute();
    $wrows = $wstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $wstmt->close();

    $running2 = 0;
    $profit_share_total = 0;

    if (!empty($approved_allocations)) {
        // ধাপ ৪-এ অনুমোদিত সময়কাল-ভিত্তিক এন্ট্রি ব্যবহার করা হচ্ছে
        foreach ($approved_allocations as $alloc) {
            $amt = (float)$alloc['allocated_amount'];
            $running2 += $amt;
            $profit_share_total += $amt;
            $current_ledger[] = [
                'date' => $alloc['period_end'],
                'label' => 'লাভের ভাগ — ' . $alloc['period_label'],
                'amount' => $amt,
                'note' => $alloc['share_percentage_used'] . '% হারে অনুমোদিত',
                'balance' => $running2,
            ];
        }
    } else {
        // এখনো কোনো সময়কাল-ভিত্তিক বণ্টন অনুমোদিত হয়নি — পুরনো
        // all-time অনুমান দেখানো হচ্ছে (শুধু তথ্যের জন্য)
        $net_profit = get_partnership_net_profit($db);
        $profit_share_total = round($net_profit * ((float)$selected_partner['share_percentage'] / 100), 2);
        $running2 = $profit_share_total;
        $current_ledger[] = [
            'date' => null,
            'label' => 'আনুমানিক লাভের ভাগ (এখনো কোনো সময়কাল অনুমোদিত হয়নি)',
            'amount' => $profit_share_total,
            'note' => 'সঠিক হিসাবের জন্য "লাভ বণ্টন" থেকে একটা সময়কাল অনুমোদন করুন',
            'balance' => $running2,
            'is_summary' => true,
        ];
    }
    foreach ($wrows as $r) {
        $running2 -= (float)$r['amount'];
        $current_ledger[] = [
            'date' => $r['entry_date'],
            'label' => 'লাভ থেকে উত্তোলন',
            'amount' => -1 * (float)$r['amount'],
            'note' => $r['note'],
            'balance' => $running2,
        ];
    }
    $current_closing_balance = $running2;
}
?>
<style>
    .pa-wrap { padding: 15px; }
    .pa-header h2 { margin:0 0 14px; color:#0B1E4D; font-size:19px; }
    .pa-partner-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
    .pa-partner-tab {
        padding:9px 16px; border-radius:20px; font-size:13px; font-weight:700;
        text-decoration:none; background:#EAF4FF; color:#0B1E4D; border:1px solid #CFE3F7;
    }
    .pa-partner-tab.active { background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; border-color:transparent; }

    .pa-summary-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:14px; margin-bottom:22px; }
    .pa-summary-card {
        border-radius:16px; padding:18px; color:#fff; box-shadow:0 6px 18px rgba(11,30,77,0.18);
    }
    .pa-summary-card .lbl { font-size:12px; opacity:.85; }
    .pa-summary-card .val { font-size:24px; font-weight:800; margin-top:6px; }

    .pa-section-title { color:#0B1E4D; font-size:15px; font-weight:800; margin:20px 0 10px; }
    .pa-table-wrap { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom: 20px; }
    .pa-table { width:100%; border-collapse:collapse; font-size:13px; }
    .pa-table th { background:#0B1E4D; color:#fff; padding:11px; text-align:left; }
    .pa-table td { padding:10px 11px; border-bottom:1px solid #F1F5F9; }
    .pa-amount-pos { color:#0B8043; font-weight:700; }
    .pa-amount-neg { color:#C62828; font-weight:700; }
    .pa-summary-row { background:#EAF4FF; }
    .pa-empty { text-align:center; color:#999; padding:30px; font-size:13px; }
</style>

<div class="pa-wrap">
    <div class="pa-header"><h2>🏦 পার্টনার অ্যাকাউন্ট (সম্পূর্ণ লেজার)</h2></div>

    <div class="pa-partner-tabs">
        <?php foreach ($partner_list as $p): ?>
            <a href="?partner_id=<?php echo (int)$p['id']; ?>" class="pa-partner-tab <?php echo ($p['id'] == $selected_id) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($p['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$selected_partner): ?>
        <div class="pa-empty">কোনো পার্টনার পাওয়া যায়নি। <a href="/modules/partners/add.php">প্রথমে একজন পার্টনার যোগ করুন</a>।</div>
    <?php else: ?>

        <div class="pa-summary-grid">
            <div class="pa-summary-card" style="background:linear-gradient(135deg,#0B1E4D,#0091EA);">
                <div class="lbl">💰 Capital Account ব্যালেন্স</div>
                <div class="val">৳<?php echo number_format($capital_closing_balance, 2); ?></div>
            </div>
            <div class="pa-summary-card" style="background:linear-gradient(135deg,#0B8043,#2ECC71);">
                <div class="lbl">📈 Current Account ব্যালেন্স (অবণ্টিত লাভ)</div>
                <div class="val">৳<?php echo number_format($current_closing_balance, 2); ?></div>
            </div>
            <div class="pa-summary-card" style="background:linear-gradient(135deg,#0B1E4D,#0091EA); opacity:0.92;">
                <div class="lbl">🧮 সর্বমোট প্রাপ্য (দুই হিসাব একত্রে)</div>
                <div class="val">৳<?php echo number_format($capital_closing_balance + $current_closing_balance, 2); ?></div>
            </div>
        </div>

        <div class="pa-section-title">💼 Capital Account — মূলধন লেজার</div>
        <div class="pa-table-wrap">
            <?php if (empty($capital_ledger)): ?>
                <div class="pa-empty">এখনো কোনো মূলধন লেনদেন নেই।</div>
            <?php else: ?>
            <table class="pa-table">
                <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>পরিমাণ</th><th>নোট</th><th>ব্যালেন্স</th></tr></thead>
                <tbody>
                <?php foreach ($capital_ledger as $row): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['label']); ?></td>
                        <td class="<?php echo $row['amount'] >= 0 ? 'pa-amount-pos' : 'pa-amount-neg'; ?>">
                            <?php echo ($row['amount'] >= 0 ? '+' : '') . number_format((float)($row['amount'] ?? 0), 2); ?> ৳
                        </td>
                        <td style="color:#64748B;"><?php echo htmlspecialchars($row['note'] ?? ''); ?></td>
                        <td style="font-weight:700;">৳<?php echo number_format((float)($row['balance'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="pa-section-title">📊 Current Account — লাভ ও উত্তোলনের লেজার</div>
        <div class="pa-table-wrap">
            <table class="pa-table">
                <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>পরিমাণ</th><th>নোট</th><th>ব্যালেন্স</th></tr></thead>
                <tbody>
                <?php foreach ($current_ledger as $row): ?>
                    <tr class="<?php echo !empty($row['is_summary']) ? 'pa-summary-row' : ''; ?>">
                        <td><?php echo $row['date'] ? date('d M Y', strtotime($row['date'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($row['label']); ?></td>
                        <td class="<?php echo $row['amount'] >= 0 ? 'pa-amount-pos' : 'pa-amount-neg'; ?>">
                            <?php echo ($row['amount'] >= 0 ? '+' : '') . number_format((float)($row['amount'] ?? 0), 2); ?> ৳
                        </td>
                        <td style="color:#64748B; font-size:12px;"><?php echo htmlspecialchars($row['note'] ?? ''); ?></td>
                        <td style="font-weight:700;">৳<?php echo number_format((float)($row['balance'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p style="font-size:11.5px; color:#94A3B8; margin-top:-8px;">
            ⓘ Current Account-এর প্রতিটা "লাভের ভাগ" এন্ট্রি এখন <a href="/modules/partners/profit-allocation/index.php" style="color:#0091EA;">লাভ বণ্টন</a> পেজ থেকে অনুমোদিত সময়কাল-ভিত্তিক হিসাব থেকে আসে। নতুন সময়কাল অনুমোদন না করা পর্যন্ত শুধু একটা আনুমানিক হিসাব দেখানো হয়।
        </p>

    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="/modules/partners/capital/index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← মূলধন তালিকায় ফিরে যান</a>
    </div>
</div>
