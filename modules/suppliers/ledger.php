<?php 
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
ensure_purchase_schema($db);

$sup_id = (int)($_GET['id'] ?? 0);

if ($sup_id <= 0) {
    if (!function_exists('ame_supplier_live_sql')) {
        require_once '../../includes/supplier_functions.php';
    }
    ame_supplier_schema_boot($db);
    $live = ame_supplier_live_sql($db);
    $pick_stmt = $db->query("SELECT id, name, phone, address, total_due FROM suppliers WHERE {$live} ORDER BY name ASC LIMIT 500");
    $pick_list = $pick_stmt ? $pick_stmt->fetch_all(MYSQLI_ASSOC) : [];
    ?>
    <!DOCTYPE html>
    <html lang="bn">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>সাপ্লায়ার লেজার — সাপ্লায়ার বাছাই করুন</title>
    <link rel="stylesheet" href="/assets/css/theme.css">
    <style>
        body { font-family: sans-serif; background: #ffffff; padding:15px; margin:0; color: var(--pos-c-333333, #333); }
        .card { background: var(--pos-c-ffffff, #fff); border-radius:10px; padding:18px; box-shadow:0 2px 8px var(--pos-rgba-black-08, rgba(0,0,0,0.08)); max-width:600px; margin:0 auto; }
        input[type=text] { width:100%; box-sizing:border-box; padding:12px; border:1.5px solid var(--pos-c-cfe3f7, #cfe3f7); border-radius:8px; margin-bottom:14px; font-size:14px; }
        .row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--pos-c-eeeeee, #eee); cursor:pointer; color: var(--pos-c-333333, #333); }
        .row b { color: var(--dark-blue, #0B1E4D); }
        .bal { color: var(--brand-danger, #D32F2F); font-weight:700; font-size:13px; }
        .empty-msg { color: var(--pos-c-888888, #888); text-align:center; padding:20px 0; }
    </style>

</head>
    <body>
        <div class="card">
            <h3 style="margin-top:0; color: var(--dark-blue, #0B1E4D);">📒 সাপ্লায়ার লেজার — সাপ্লায়ার বাছাই করুন</h3>
            <input type="text" id="searchBox" placeholder="নাম বা ফোন নম্বর দিয়ে খুঁজুন..." autocomplete="off">
            <div id="resultsList"></div>
            <div id="emptyMsg" class="empty-msg" style="display:none;">কোনো সাপ্লায়ার পাওয়া যায়নি।</div>
            <a href="list.php" style="display:block; text-align:center; margin-top:16px; color: var(--pos-c-666666, #666);">⬅️ ফিরে যান</a>
        </div>
        <script>
            const AME_ALL_SUPPLIERS = <?php echo json_encode(array_map(function ($p) {
                return ['id' => (int)$p['id'], 'name' => $p['name'], 'phone' => $p['phone'] ?? '', 'balance' => (float)$p['total_due']];
            }, $pick_list), JSON_UNESCAPED_UNICODE); ?>;
            const searchBox = document.getElementById('searchBox');
            const resultsList = document.getElementById('resultsList');
            const emptyMsg = document.getElementById('emptyMsg');
            function renderRows(list) {
                resultsList.innerHTML = '';
                emptyMsg.style.display = list.length === 0 ? 'block' : 'none';
                list.slice(0, 100).forEach(function (c) {
                    const row = document.createElement('div');
                    row.className = 'row';
                    row.innerHTML = '<span><b>' + c.name + '</b><br><small>' + (c.phone || '—') + '</small></span><span class="bal">' + c.balance.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ৳</span>';
                    row.addEventListener('click', function () { window.location.href = 'ledger.php?id=' + c.id; });
                    resultsList.appendChild(row);
                });
            }
            searchBox.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                if (q === '') { renderRows(AME_ALL_SUPPLIERS); return; }
                renderRows(AME_ALL_SUPPLIERS.filter(function (c) {
                    return c.name.toLowerCase().includes(q) || (c.phone || '').includes(q);
                }));
            });
            renderRows(AME_ALL_SUPPLIERS);
        </script>
    </body>
    </html>
    <?php
    exit();
}

$ame_body_class = 'ame-supplier-ledger';
include '../../partials/header.php'; 
$sup_stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$sup_stmt->bind_param("i", $sup_id);
$sup_stmt->execute();
$sup_info = $sup_stmt->get_result()->fetch_assoc();

$ledger_rows = ame_supplier_ledger_rows($db, $sup_id);
$due_now = $ledger_rows ? (float)$ledger_rows[0]['_bal'] : 0.0;

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'supplier-ledger-' . $sup_id,
    'title' => $shop . ' · ' . ame_l('সাপ্লায়ার লেজার', 'Supplier ledger'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('সাপ্লায়ার লেজার', 'Supplier ledger'),
        (string) ($sup_info['name'] ?? ''),
        ame_l('বর্তমান বকেয়া', 'Current due') . ': ৳' . number_format(abs($due_now), 2),
    ])),
    'shop' => $shop,
];
?>

<div class="ame-sup-ledger-page" style="padding: 15px;">
    <?php include '../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color: var(--dark-blue, #0B1E4D);"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color: var(--pos-c-555555, #555);"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color: var(--pos-c-555555, #555);"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <div class="card" style="background:#ffffff; color:#0B1E4D; margin-bottom: 15px; border:1px solid #d6deea;">
        <h3 style="margin:0;"><?php echo htmlspecialchars($sup_info['name'] ?? ''); ?></h3>
        <p style="margin:5px 0 0 0;">
            <?php if ($due_now > 0.009): ?>
                মোট বকেয়া: <span style="color:#b91c1c; font-weight:bold;"><?php echo number_format($due_now, 2); ?> ৳</span>
            <?php elseif ($due_now < -0.009): ?>
                অগ্রিম: <span style="color:#0e8f52; font-weight:bold;"><?php echo number_format(abs($due_now), 2); ?> ৳</span>
            <?php else: ?>
                ব্যালেন্স সমান
            <?php endif; ?>
        </p>
    </div>
    <div class="card ame-ledger-master" style="padding: 0; overflow-x: auto; background: var(--pos-c-ffffff, white);">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#ffffff; color:#0B3A82;">
                    <th style="padding:12px; color:#0B3A82;"><?php echo ame_l('তারিখ', 'Date'); ?></th>
                    <th style="padding:12px; text-align:center; color:#0B3A82;"><?php echo ame_l('মেমো নং', 'Memo No'); ?></th>
                    <th style="padding:12px; color:#0B3A82;"><?php echo ame_l('বিবরণ', 'Description'); ?></th>
                    <th style="padding:12px; text-align:right; color:#0B3A82;"><?php echo ame_l('ডেবিট', 'Debit'); ?></th>
                    <th style="padding:12px; text-align:right; color:#0B3A82;"><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th>
                    <th style="padding:12px; text-align:right; color:#0B3A82;"><?php echo ame_l('রানিং ব্যালেন্স', 'Running Balance'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$ledger_rows): ?>
                <tr><td colspan="6" style="padding:20px; text-align:center; color: var(--pos-c-64748b, #64748b);"><?php echo ame_l('এখনো কোনো লেনদেন নেই।', 'No transactions yet.'); ?></td></tr>
                <?php else: foreach ($ledger_rows as $row):
                    $ame_kind = (string)($row['kind'] ?? '');
                    $ame_amt = (float)$row['amount'];
                    $ame_debit = (!empty($row['_plus']) || in_array($ame_kind, ['purchase', 'opening', 'reversal'], true)) ? $ame_amt : 0.0;
                    $ame_credit = (!empty($row['_minus']) || in_array($ame_kind, ['payment', 'return', 'adjustment'], true)) ? $ame_amt : 0.0;
                    if ($ame_kind === 'reversal') {
                        $ame_debit = $ame_amt;
                        $ame_credit = 0.0;
                    }
                ?>
                <tr style="border-bottom: 1px solid var(--pos-c-eeeeee, #eee);">
                    <td style="padding:12px;"><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                    <td style="padding:12px; text-align:center; white-space:nowrap;">
                        <?php $ame_ledger_memo = trim((string)($row['ref'] ?? $row['ref_no'] ?? '')); ?>
                        <?php if ($ame_ledger_memo !== ''): ?>
                            <a href="../purchase/memo_digital.php?memo=<?php echo urlencode($ame_ledger_memo); ?>" style="color: var(--pos-c-1565c0, #1565C0); font-weight:800; text-decoration:underline; font-size:13px;">#<?php echo htmlspecialchars($ame_ledger_memo, ENT_QUOTES, 'UTF-8'); ?></a>
                            <br>
                            <a href="../purchase/add.php?edit_memo=<?php echo urlencode($ame_ledger_memo); ?>" style="color: var(--pos-c-c62828, #C62828); font-weight:700; text-decoration:none; font-size:11px;"><?php echo ame_l('✏️ এডিট', '✏️ Edit'); ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($row['note'] ?? ''); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:800; color:#9a3412;"><?php echo ame_statement_signed_amount($ame_debit, '+'); ?></td>
                    <td style="padding:12px; text-align:right; color:#0B6B3A; font-weight:800;"><?php echo ame_statement_signed_amount($ame_credit, '-'); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:800; color:#0B3A82;"><?php echo number_format((float)$row['_bal'], 2); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    <a href="list.php" class="btn-success" style="display:block; text-align:center; margin-top:15px; text-decoration:none; background:var(--dark-blue);">ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>