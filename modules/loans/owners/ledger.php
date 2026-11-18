<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$owner_id = (int)($_GET['owner_id'] ?? 0);
$date_from = clean_input($_GET['from'] ?? '');
$date_to = clean_input($_GET['to'] ?? '');
$txn_type = clean_input($_GET['txn_type'] ?? '');

$where = [];
$params = [];
$types = '';
if ($owner_id > 0) { $where[] = 'otl.owner_id = ?'; $params[] = $owner_id; $types .= 'i'; }
if ($date_from !== '') { $where[] = 'otl.txn_date >= ?'; $params[] = $date_from; $types .= 's'; }
if ($date_to !== '') { $where[] = 'otl.txn_date <= ?'; $params[] = $date_to; $types .= 's'; }
if ($txn_type !== '') { $where[] = 'otl.txn_type = ?'; $params[] = $txn_type; $types .= 's'; }

$sql = "SELECT otl.*, so.owner_name, so.shop_name FROM owner_transaction_ledger otl
    JOIN shop_owners so ON so.id = otl.owner_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY otl.id DESC LIMIT 500";

if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}
$owners = ame_oloan_owner_options($db);

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-page-wrap { padding: 15px; }
.oln-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; }
.oln-toolbar input, .oln-toolbar select {
    padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px; background: var(--brand-white);
}
.oln-add-btn {
    display:inline-flex; align-items:center; gap:6px; background: var(--brand-navy); color: var(--brand-white) !important;
    padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; white-space:nowrap; border:none; cursor:pointer;
}
.oln-table-wrap { overflow-x:auto; }
.oln-table { width:100%; border-collapse: collapse; text-align:left; }
.oln-table thead tr { background: var(--brand-navy); color: var(--brand-white); }
.oln-table th { padding:12px; font-size:13px; }
.oln-table td { padding:12px; border-bottom:1px solid var(--brand-border); font-size:13.5px; }
.oln-credit { color: var(--oln-ledger-credit); font-weight:700; }
.oln-debit { color: var(--oln-ledger-debit); font-weight:700; }
.oln-empty { text-align:center; padding:30px 0; color: var(--brand-muted); }
.oln-page-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-page-wrap">
    <div class="card" style="padding:0; overflow:visible;">
        <div style="padding:18px 18px 0;">
            <h3 style="color: var(--brand-navy);">📒 <?php echo te('nav_oloan_ledger'); ?></h3>
            <form method="GET" class="oln-toolbar">
                <select name="owner_id">
                    <option value=""><?php echo ame_l('সব মালিক', 'All owners'); ?></option>
                    <?php foreach ($owners as $o): ?>
                    <option value="<?php echo (int)$o['id']; ?>" <?php echo $owner_id === (int)$o['id'] ? 'selected' : ''; ?>><?php echo esc_html($o['owner_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="txn_type">
                    <option value=""><?php echo ame_l('সব ধরন', 'All types'); ?></option>
                    <?php foreach (['loan_disbursement','loan_payment','savings_deposit','savings_withdrawal'] as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo $txn_type === $t ? 'selected' : ''; ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="from" value="<?php echo esc_html($date_from); ?>">
                <input type="date" name="to" value="<?php echo esc_html($date_to); ?>">
                <button type="submit" class="oln-add-btn"><?php echo ame_l('ফিল্টার', 'Filter'); ?></button>
            </form>
        </div>
        <div class="oln-table-wrap">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('তারিখ', 'Date'); ?></th>
                <th><?php echo ame_l('মালিক', 'Owner'); ?></th>
                <th><?php echo ame_l('ধরন', 'Type'); ?></th>
                <th><?php echo ame_l('বিবরণ', 'Description'); ?></th>
                <th><?php echo ame_l('ডেবিট', 'Debit'); ?></th>
                <th><?php echo ame_l('ক্রেডিট', 'Credit'); ?></th>
                <th><?php echo ame_l('ব্যালেন্স', 'Balance'); ?></th>
                <th><?php echo ame_l('রেফারেন্স', 'Reference'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="oln-empty"><?php echo ame_l('কোনো লেনদেন পাওয়া যায়নি।', 'No transactions found.'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['txn_date']); ?></td>
                    <td><?php echo esc_html($r['owner_name']); ?></td>
                    <td><?php echo esc_html($r['txn_type']); ?></td>
                    <td><?php echo esc_html($r['description']); ?></td>
                    <td class="oln-debit"><?php echo (float)$r['debit'] > 0 ? number_format((float)$r['debit'], 2) : '—'; ?></td>
                    <td class="oln-credit"><?php echo (float)$r['credit'] > 0 ? number_format((float)$r['credit'], 2) : '—'; ?></td>
                    <td><?php echo number_format((float)$r['balance'], 2); ?></td>
                    <td><?php echo esc_html($r['reference']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
