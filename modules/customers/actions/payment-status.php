<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);

$days = (int)($_GET['days'] ?? 30);
if ($days < 1) { $days = 30; }

$sql = "SELECT c.id, c.customer_code, c.name, c.phone, c.receivable_balance,
        (SELECT MAX(txn_date) FROM customer_ledger WHERE customer_id = c.id) AS last_txn_date
        FROM customers c
        WHERE c.is_customer = 1 AND c.receivable_balance > 0
        HAVING last_txn_date IS NULL OR DATEDIFF(CURDATE(), last_txn_date) >= ?
        LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $days);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ⭐ প্রতিটার নিষ্ক্রিয়তা-দিন ও উর্জেন্সি স্কোর হিসাব
$max_balance = 0;
foreach ($customers as $c) { $max_balance = max($max_balance, (float)$c['receivable_balance']); }

foreach ($customers as &$c) {
    if ($c['last_txn_date'] === null) {
        $c['days_inactive'] = null;
        $days_for_score = 365;
    } else {
        $c['days_inactive'] = (new DateTime())->diff(new DateTime($c['last_txn_date']))->days;
        $days_for_score = min($c['days_inactive'], 365);
    }
    $balance_part = $max_balance > 0 ? ((float)$c['receivable_balance'] / $max_balance) : 0;
    $c['urgency_score'] = round(($days_for_score / 365) * 50 + $balance_part * 50);
}
unset($c);

usort($customers, fn($a, $b) => $b['urgency_score'] <=> $a['urgency_score']);

$total_count = count($customers);
$total_due = array_sum(array_column($customers, 'receivable_balance'));

function urgency_badge($score) {
    if ($score >= 70) return ['label' => 'অতি জরুরি', 'bg' => '#B71C1C'];
    if ($score >= 40) return ['label' => 'মাঝারি', 'bg' => '#E65100'];
    return ['label' => 'স্বাভাবিক', 'bg' => '#2E7D32'];
}

function wa_link($phone, $name, $balance) {
    $digits = preg_replace('/\D/', '', $phone);
    if (substr($digits, 0, 1) === '0') { $digits = '88' . $digits; }
    elseif (substr($digits, 0, 3) !== '880') { $digits = '880' . $digits; }
    $msg = "প্রিয় $name, আপনার কাছে আমাদের ৳" . number_format($balance, 2) . " টাকা বকেয়া রয়েছে। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ - " . shop_name();
    return "https://wa.me/$digits?text=" . urlencode($msg);
}

$presets = [10, 20, 30, 60, 90];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>পেমেন্ট স্ট্যাটাস - নিষ্ক্রিয় বকেয়া</title>
<style>
    body { font-family:sans-serif; background:#f4f7f9; padding:15px; margin:0; color:#212121; }
    .card { background:#fff; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px; }
    .summary-box { border-radius:10px; padding:14px; background:#fff; border:1px solid #0D47A1; border-top:3px solid #0D47A1; }
    .summary-box .num { font-size:20px; font-weight:800; color:#0D47A1; }
    .summary-box .lbl { font-size:11px; color:#212121; }
    .bg2 { border-color:#B71C1C; border-top-color:#B71C1C; }
    .bg2 .num { color:#B71C1C; }
    .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; align-items:center; }
    .preset-btn { padding:7px 14px; border-radius:20px; border:1px solid #0D47A1; background:#fff; color:#0D47A1; font-size:12px; font-weight:600; text-decoration:none; }
    .preset-btn.active { background:#0D47A1; color:#fff; }
    .custom-form { display:flex; gap:6px; align-items:center; }
    .custom-form input { width:70px; padding:6px; border:1px solid #90A4AE; border-radius:6px; font-size:12px; }
    .custom-form button, .print-btn { padding:6px 12px; background:#212121; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; }
    .table-scroll { overflow-x:auto; }
    table { border-collapse:collapse; width:100%; min-width:820px; font-size:12px; }
    th { background:#0D47A1; color:#fff; padding:8px; text-align:left; white-space:nowrap; font-weight:600; }
    td { padding:8px; border-bottom:1px solid #eee; white-space:nowrap; }
    .status-badge { padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; color:#fff; }
    .amt { color:#B71C1C; font-weight:700; }
    .cmd-btn { display:inline-block; padding:5px 9px; border-radius:6px; text-decoration:none; font-size:12px; margin-right:3px; color:#fff; }
    .cmd-call { background:#2E7D32; } .cmd-wa { background:#1B8A4B; }
    .bulk-bar { position:sticky; bottom:0; background:#fff; border-top:2px solid #0D47A1; padding:10px 15px; margin-top:10px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 10px 10px; }
    .bulk-btn { background:#0D47A1; color:#fff; border:none; padding:9px 16px; border-radius:8px; font-weight:600; font-size:12px; cursor:pointer; }

    .overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
    .overlay.open { display:flex; }
    .modal-card { background:#fff; border-radius:14px; padding:20px; width:90%; max-width:380px; }
    .modal-card h3 { color:#0D47A1; }
    .modal-card label { display:block; font-weight:600; margin:10px 0 5px; font-size:13px; color:#212121; }
    .modal-card input { width:100%; padding:9px; border:1px solid #90A4AE; border-radius:6px; box-sizing:border-box; font-size:13px; }
    .modal-save { background:#0D47A1; color:#fff; padding:10px; border:none; border-radius:8px; margin-top:15px; width:100%; font-weight:600; cursor:pointer; }

    @media print { .no-print { display:none !important; } }
</style>

</head>
<body>
<div class="card">
    <h2 style="margin-top:0;">📊 নিষ্ক্রিয় বকেয়া কাস্টমার</h2>
    <p style="color:#666; font-size:12px; margin-top:-8px;">যাদের বকেয়া আছে কিন্তু নির্দিষ্ট দিন ধরে কোনো লেনদেন হয়নি, উর্জেন্সি অনুযায়ী সাজানো</p>

    <div class="summary-grid">
        <div class="summary-box bg1"><div class="num"><?php echo $total_count; ?></div><div class="lbl">মোট নিষ্ক্রিয় বকেয়া কাস্টমার</div></div>
        <div class="summary-box bg2"><div class="num">৳<?php echo number_format($total_due, 2); ?></div><div class="lbl">মোট বকেয়া</div></div>
    </div>

    <div class="filter-bar no-print">
        <?php foreach ($presets as $p): ?>
        <a href="?days=<?php echo $p; ?>" class="preset-btn <?php echo $days === $p ? 'active' : ''; ?>"><?php echo $p; ?> দিন</a>
        <?php endforeach; ?>
        <form method="GET" class="custom-form">
            <input type="number" name="days" min="1" placeholder="কাস্টম" value="<?php echo (!in_array($days, $presets)) ? $days : ''; ?>">
            <button type="submit">খুঁজুন</button>
        </form>
        <button type="button" class="print-btn" onclick="window.print()">🖨️ প্রিন্ট</button>
    </div>

    <form id="bulkForm">
    <div class="table-scroll">
    <table>
        <thead><tr>
            <th class="no-print"><input type="checkbox" onclick="toggleAll(this)"></th>
            <th>কোড</th><th>নাম</th><th>ফোন</th><th>বকেয়া (৳)</th><th>নিষ্ক্রিয়তা</th><th>উর্জেন্সি</th><th class="no-print">কমান্ড</th>
        </tr></thead>
        <tbody>
            <?php if (count($customers) > 0): foreach ($customers as $c):
                $badge = urgency_badge($c['urgency_score']);
                $inactive_text = $c['days_inactive'] === null
                    ? 'কখনো লেনদেন হয়নি'
                    : ((int)$c['days_inactive'] . ' দিন (' . esc_html($c['last_txn_date']) . ')');
            ?>
            <tr>
                <td class="no-print"><input type="checkbox" class="cust-check" value="<?php echo (int)$c['id']; ?>"></td>
                <td><?php echo htmlspecialchars($c['customer_code']); ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['phone']); ?></td>
                <td class="amt">৳<?php echo number_format((float)$c['receivable_balance'], 2); ?></td>
                <td><?php echo $inactive_text; ?></td>
                <td><span class="status-badge" style="background:<?php echo $badge['bg']; ?>;"><?php echo $badge['label']; ?> (<?php echo $c['urgency_score']; ?>)</span></td>
                <td class="no-print">
                    <a href="tel:<?php echo htmlspecialchars($c['phone']); ?>" class="cmd-btn cmd-call">📞</a>
                    <a href="<?php echo wa_link($c['phone'], $c['name'], $c['receivable_balance']); ?>" target="_blank" class="cmd-btn cmd-wa">💬</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center; color:#888;"><?php echo $days; ?> দিনের বেশি নিষ্ক্রিয় কোনো বকেয়া কাস্টমার পাওয়া যায়নি।</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if (count($customers) > 0): ?>
    <div class="bulk-bar no-print">
        <span id="selCount" style="font-size:12px; color:#666;">০ জন নির্বাচিত</span>
        <button type="button" class="bulk-btn" onclick="openBulkModal()">⏰ বাল্ক রিমাইন্ডার সেট করুন</button>
    </div>
    <?php endif; ?>
    </form>
</div>

<div class="overlay" id="bulkOverlay" onclick="if(event.target===this) closeBulkModal()">
    <div class="modal-card">
        <h3 style="margin-top:0;">⏰ বাল্ক রিমাইন্ডার সেট করুন</h3>
        <div id="bulkSelInfo" style="font-size:12px; color:#666;"></div>
        <label>পরবর্তী কবে টাকা দেবে? *</label>
        <input type="date" id="bulkDate">
        <label>নোট</label>
        <input type="text" id="bulkNote" placeholder="যেমন: কালেকশন কল">
        <button type="button" class="modal-save" onclick="submitBulk()">✅ সবার জন্য সেট করুন</button>
        <div id="bulkMsg" style="font-size:12px; margin-top:8px; text-align:center;"></div>
    </div>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.cust-check').forEach(cb => cb.checked = master.checked);
    updateSelCount();
}
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('cust-check')) updateSelCount();
});
function updateSelCount() {
    const n = document.querySelectorAll('.cust-check:checked').length;
    document.getElementById('selCount').innerText = n + ' জন নির্বাচিত';
}
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.cust-check:checked')).map(cb => cb.value);
}
function openBulkModal() {
    const ids = getSelectedIds();
    if (ids.length === 0) { alert('অন্তত একজন কাস্টমার নির্বাচন করুন।'); return; }
    document.getElementById('bulkSelInfo').innerText = ids.length + ' জন কাস্টমারের জন্য প্রযোজ্য হবে।';
    document.getElementById('bulkMsg').innerText = '';
    document.getElementById('bulkOverlay').classList.add('open');
}
function closeBulkModal() { document.getElementById('bulkOverlay').classList.remove('open'); }

async function submitBulk() {
    const date = document.getElementById('bulkDate').value;
    const note = document.getElementById('bulkNote').value;
    const msg = document.getElementById('bulkMsg');
    if (!date) { msg.style.color = '#C62828'; msg.innerText = 'তারিখ দেওয়া আবশ্যক।'; return; }

    const ids = getSelectedIds();
    msg.style.color = '#333';
    let done = 0;
    for (const id of ids) {
        const fd = new FormData();
        fd.append('customer_id', id);
        fd.append('next_due_date', date);
        fd.append('due_note', note);
        fd.append('csrf_token', <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
        try {
            await fetch('set_reminder.php', { method: 'POST', body: fd });
            done++;
            msg.innerText = `সংরক্ষণ হচ্ছে... (${done}/${ids.length})`;
        } catch (e) {}
    }
    msg.style.color = '#2E7D32';
    msg.innerText = `✅ ${done} জনের রিমাইন্ডার সেট হয়েছে। রিলোড হচ্ছে...`;
    setTimeout(() => location.reload(), 900);
}
</script>
<?php include __DIR__ . '/../../../partials/confirm_actions.php'; ?>
</body>
</html>
