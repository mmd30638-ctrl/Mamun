<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

$partners = $db->query("SELECT id, name, partner_type, share_percentage, phone, email, join_date, status FROM partners ORDER BY status = 'active' DESC, name ASC");
$partner_list = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];

$selected_id = (int)($_GET['partner_id'] ?? ($partner_list[0]['id'] ?? 0));
$selected_partner = null;
foreach ($partner_list as $p) {
    if ((int)$p['id'] === $selected_id) { $selected_partner = $p; break; }
}

$capital_rows = [];
$current_rows = [];
$capital_balance = 0;
$current_balance = 0;

if ($selected_partner) {
    $stmt = $db->prepare("SELECT transaction_date as d, type, amount, note FROM partner_capital_transactions WHERE partner_id = ? ORDER BY transaction_date ASC, id ASC");
    $stmt->bind_param('i', $selected_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $labels = ['investment' => 'মূলধন জমা', 'capital_withdrawal' => 'মূলধন উত্তোলন', 'loan_to_business' => 'ব্যবসাকে ঋণ'];
    foreach ($rows as $r) {
        $signed = $r['type'] === 'capital_withdrawal' ? -1 * (float)$r['amount'] : (float)$r['amount'];
        $capital_balance += $signed;
        $capital_rows[] = ['date' => $r['d'], 'label' => $labels[$r['type']] ?? $r['type'], 'amount' => $signed, 'note' => $r['note'], 'balance' => $capital_balance];
    }

    $allocations = get_partner_approved_allocations($db, $selected_id);
    foreach ($allocations as $a) {
        $current_balance += (float)$a['allocated_amount'];
        $current_rows[] = ['date' => $a['period_end'], 'label' => 'লাভের ভাগ — ' . $a['period_label'], 'amount' => (float)$a['allocated_amount'], 'note' => $a['share_percentage_used'] . '%', 'balance' => $current_balance];
    }

    $wstmt = $db->prepare("SELECT withdrawal_date as d, amount, note FROM partner_withdrawals WHERE partner_id = ? ORDER BY withdrawal_date ASC, id ASC");
    $wstmt->bind_param('i', $selected_id);
    $wstmt->execute();
    $wrows = $wstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $wstmt->close();
    foreach ($wrows as $r) {
        $current_balance -= (float)$r['amount'];
        $current_rows[] = ['date' => $r['d'], 'label' => 'লাভ থেকে উত্তোলন', 'amount' => -1 * (float)$r['amount'], 'note' => $r['note'], 'balance' => $current_balance];
    }
    // তারিখ অনুযায়ী সাজানো (দুই উৎস মিশ্রিত হওয়ায়)
    usort($current_rows, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
    // sort করার পর balance পুনরায় হিসাব
    $running = 0;
    foreach ($current_rows as &$row) { $running += $row['amount']; $row['balance'] = $running; }
    unset($row);
    $current_balance = $running;
}

$shop_name = $shop['shop_name'] ?? 'Aponshop';

// এই স্টেটমেন্টে Capital ও Current Account-এর ব্যালেন্স শুরু থেকে (সব লেনদেন ASC
// অর্ডারে যোগ করে) হিসাব হয় — তারিখ-রেঞ্জ ফিল্টার যোগ করলে মাঝপথের এন্ট্রি বাদ
// পড়ে রানিং ব্যালেন্স ভুল হয়ে যাওয়ার ঝুঁকি থাকে, তাই এখানে তারিখ ফিল্টার যোগ করা
// হয়নি — সম্পূর্ণ ইতিহাস অপরিবর্তিত রেখে শুধু প্রিন্ট/শেয়ার টুলবার যোগ হয়েছে।
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'partner-statement-' . $selected_id . '-' . date('Y-m-d'),
    'title' => $shop_name . ' · ' . ame_l('পার্টনার স্টেটমেন্ট', 'Partner statement'),
    'text' => implode("\n", array_filter([
        $shop_name,
        ame_l('পার্টনার স্টেটমেন্ট', 'Partner statement'),
        $selected_partner ? (string) $selected_partner['name'] : '',
    ])),
    'shop' => $shop_name,
];
?>
<style>
    @media print {
        .no-print { display: none !important; }
        .stmt-wrap { padding: 0 !important; }
        body { background: #fff !important; }
    }
    .stmt-wrap { padding: 15px; max-width: 720px; margin: 0 auto; }
    .stmt-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
    .stmt-select { padding:10px 14px; border-radius:10px; border:1.5px solid #CFE3F7; font-size:13.5px; }
    .stmt-print-btn { background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; border:none; padding:10px 18px; border-radius:10px; font-weight:800; cursor:pointer; font-size:13px; }

    .stmt-doc { background:#fff; border-radius:16px; padding:30px; box-shadow: 0 8px 30px rgba(11,30,77,0.1); }
    .stmt-doc-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #0B1E4D; padding-bottom:16px; margin-bottom:20px; }
    .stmt-doc-header h1 { margin:0; color:#0B1E4D; font-size:18px; }
    .stmt-doc-header .sub { font-size:12px; color:#64748B; margin-top:4px; }
    .stmt-meta { text-align:right; font-size:12px; color:#64748B; }

    .stmt-info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px 20px; margin-bottom:22px; font-size:13px; }
    .stmt-info-grid strong { color:#0B1E4D; }

    .stmt-section-title { color:#0B1E4D; font-weight:800; font-size:14px; margin: 18px 0 8px; border-bottom:1px solid #EAF4FF; padding-bottom:6px; }
    .stmt-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .stmt-table th { background:#0B1E4D; color:#fff; padding:8px 10px; text-align:left; }
    .stmt-table td { padding:8px 10px; border-bottom:1px solid #F1F5F9; }
    .stmt-pos { color:#0B8043; font-weight:700; }
    .stmt-neg { color:#C62828; font-weight:700; }

    .stmt-summary { display:flex; gap:14px; margin-top:20px; flex-wrap:wrap; }
    .stmt-summary-box { flex:1; min-width:180px; background:#EAF4FF; border-radius:12px; padding:14px; }
    .stmt-summary-box .lbl { font-size:11px; color:#64748B; }
    .stmt-summary-box .val { font-size:19px; font-weight:800; color:#0B1E4D; margin-top:4px; }
</style>

<div class="stmt-wrap">
    <div class="stmt-toolbar no-print">
        <form method="GET" style="margin:0;">
            <select name="partner_id" class="stmt-select" onchange="this.form.submit()">
                <?php foreach ($partner_list as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo $p['id'] == $selected_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($selected_partner): ?>
    <?php include __DIR__ . '/../../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <div class="stmt-doc">
        <div class="stmt-doc-header">
            <div>
                <h1><?php echo htmlspecialchars($shop_name); ?></h1>
                <div class="sub">পার্টনার স্টেটমেন্ট</div>
            </div>
            <div class="stmt-meta">
                তৈরির তারিখ: <?php echo date('d M Y'); ?><br>
                স্টেটমেন্ট আইডি: PS-<?php echo str_pad($selected_id, 4, '0', STR_PAD_LEFT); ?>-<?php echo date('Ymd'); ?>
            </div>
        </div>

        <div class="stmt-info-grid">
            <div><strong>নাম:</strong> <?php echo htmlspecialchars($selected_partner['name']); ?></div>
            <div><strong>শেয়ার:</strong> <?php echo number_format((float)$selected_partner['share_percentage'], 2); ?>%</div>
            <div><strong>ফোন:</strong> <?php echo htmlspecialchars($selected_partner['phone'] ?? '—'); ?></div>
            <div><strong>যোগদান:</strong> <?php echo !empty($selected_partner['join_date']) ? date('d M Y', strtotime($selected_partner['join_date'])) : '—'; ?></div>
        </div>

        <div class="stmt-summary">
            <div class="stmt-summary-box"><div class="lbl">💼 Capital Account ব্যালেন্স</div><div class="val">৳<?php echo number_format($capital_balance, 2); ?></div></div>
            <div class="stmt-summary-box"><div class="lbl">📊 Current Account ব্যালেন্স</div><div class="val">৳<?php echo number_format($current_balance, 2); ?></div></div>
            <div class="stmt-summary-box" style="background:linear-gradient(135deg,#0B1E4D,#0091EA);"><div class="lbl" style="color:#dbeafe;">🧮 সর্বমোট প্রাপ্য</div><div class="val" style="color:#fff;">৳<?php echo number_format($capital_balance + $current_balance, 2); ?></div></div>
        </div>

        <div class="stmt-section-title">💼 Capital Account</div>
        <table class="stmt-table">
            <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>পরিমাণ</th><th>ব্যালেন্স</th></tr></thead>
            <tbody>
                <?php if (empty($capital_rows)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#999;">কোনো লেনদেন নেই</td></tr>
                <?php else: foreach ($capital_rows as $r): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td><?php echo htmlspecialchars($r['label']); ?></td>
                        <td class="<?php echo $r['amount'] >= 0 ? 'stmt-pos' : 'stmt-neg'; ?>"><?php echo ($r['amount'] >= 0 ? '+' : '') . number_format((float)($r['amount'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="stmt-section-title">📊 Current Account</div>
        <table class="stmt-table">
            <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>পরিমাণ</th><th>ব্যালেন্স</th></tr></thead>
            <tbody>
                <?php if (empty($current_rows)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#999;">কোনো লেনদেন নেই</td></tr>
                <?php else: foreach ($current_rows as $r): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                        <td><?php echo htmlspecialchars($r['label']); ?></td>
                        <td class="<?php echo $r['amount'] >= 0 ? 'stmt-pos' : 'stmt-neg'; ?>"><?php echo ($r['amount'] >= 0 ? '+' : '') . number_format((float)($r['amount'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <p style="font-size:10.5px; color:#94A3B8; margin-top:22px; text-align:center;">এই স্টেটমেন্টটি <?php echo htmlspecialchars($shop_name); ?>-এর অভ্যন্তরীণ সিস্টেম থেকে স্বয়ংক্রিয়ভাবে তৈরি করা হয়েছে।</p>
    </div>
    </div>
    <?php else: ?>
        <p style="text-align:center; color:#999;">কোনো পার্টনার পাওয়া যায়নি।</p>
    <?php endif; ?>

    <div class="no-print" style="margin-top:16px; text-align:center;">
        <a href="/modules/partners/accounts/index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনার অ্যাকাউন্টে ফিরে যান</a>
    </div>
</div>
