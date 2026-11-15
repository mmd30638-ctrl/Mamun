<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_settlement.php';
ame_spay_ensure($db);

$filters = [
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
    'method' => trim((string)($_GET['method'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'user_id' => (int)($_GET['user_id'] ?? 0),
    'amount_min' => trim((string)($_GET['amount_min'] ?? '')),
    'amount_max' => trim((string)($_GET['amount_max'] ?? '')),
    'invoice' => trim((string)($_GET['invoice'] ?? '')),
];
$history = ame_spay_history($db, $filters);
if (!function_exists('ame_supplier_live_sql')) {
    require_once '../../includes/supplier_functions.php';
}
ame_supplier_schema_boot($db);
$live = ame_supplier_live_sql($db);
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE {$live} ORDER BY name ASC LIMIT 800");
$supplier_rows = $suppliers ? $suppliers->fetch_all(MYSQLI_ASSOC) : [];
$users = $db->query('SELECT DISTINCT u.id, u.username FROM supplier_settlements s INNER JOIN users u ON u.id = s.created_by ORDER BY u.username ASC');
$user_rows = $users ? $users->fetch_all(MYSQLI_ASSOC) : [];
$methods = ame_spay_methods();
$statuses = ame_spay_statuses();

$ame_body_class = 'ame-spay-page';
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/supplier-settlement.css?v=<?php echo htmlspecialchars((string)AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/supplier-pay-list.css?v=<?php echo htmlspecialchars((string)AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="spay spay-list">
    <h1><?php echo te('spay_hist'); ?></h1>
    <form class="spay-card" method="get">
        <div class="spay-grid">
            <div><label for="fFrom"><?php echo te('spay_from'); ?></label><input id="fFrom" type="date" name="from" value="<?php echo htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label for="fTo"><?php echo te('spay_to'); ?></label><input id="fTo" type="date" name="to" value="<?php echo htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div>
                <label for="fSup"><?php echo te('spay_company'); ?></label>
                <select id="fSup" name="supplier_id">
                    <option value="0">—</option>
                    <?php foreach ($supplier_rows as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo $filters['supplier_id'] === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fMethod"><?php echo te('spay_method'); ?></label>
                <select id="fMethod" name="method">
                    <option value="">—</option>
                    <?php foreach ($methods as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['method'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fStatus"><?php echo te('spay_status'); ?></label>
                <select id="fStatus" name="status">
                    <option value="">—</option>
                    <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['status'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fUser"><?php echo te('spay_user'); ?></label>
                <select id="fUser" name="user_id">
                    <option value="0">—</option>
                    <?php foreach ($user_rows as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $filters['user_id'] === (int)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label for="fMin"><?php echo te('spay_amount'); ?> ≥</label><input id="fMin" type="number" step="0.01" name="amount_min" value="<?php echo htmlspecialchars($filters['amount_min'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label for="fMax"><?php echo te('spay_amount'); ?> ≤</label><input id="fMax" type="number" step="0.01" name="amount_max" value="<?php echo htmlspecialchars($filters['amount_max'], ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label for="fInv"><?php echo te('spay_invoice'); ?></label><input id="fInv" type="text" name="invoice" value="<?php echo htmlspecialchars($filters['invoice'], ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
        <button class="spay-btn primary" type="submit" style="margin-top:10px;"><?php echo te('spay_filter'); ?></button>
    </form>

    <div class="spay-card spay-table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?php echo te('spay_id'); ?></th>
                    <th><?php echo te('spay_date'); ?></th>
                    <th><?php echo te('spay_company'); ?></th>
                    <th><?php echo te('spay_amount'); ?></th>
                    <th><?php echo te('spay_method'); ?></th>
                    <th><?php echo te('spay_ref'); ?></th>
                    <th><?php echo te('spay_invoice'); ?></th>
                    <th><?php echo te('spay_by'); ?></th>
                    <th><?php echo te('spay_status'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$history['rows'] && !$history['legacy']): ?>
                <tr class="spay-empty"><td colspan="10"><?php echo te('spay_empty'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($history['rows'] as $p): ?>
                <tr>
                    <td data-k="<?php echo htmlspecialchars(te('spay_id'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)$p['payment_no'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_date'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)$p['payment_date'], ENT_QUOTES, 'UTF-8'); ?><br><span class="spay-muted"><?php echo htmlspecialchars(date('H:i', strtotime((string)$p['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_company'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)$p['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_amount'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo number_format((float)$p['amount_bdt'], 2); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_method'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars($methods[$p['method']] ?? $p['method'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_ref'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)($p['reference_no'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_invoice'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)($p['invoices'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_by'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)($p['created_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_status'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars($statuses[$p['status']] ?? $p['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(function_exists('ame_ui') ? ame_ui('কাজ', 'Actions') : 'কাজ', ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val spay-acts">
                        <a href="receipt.php?id=<?php echo (int)$p['id']; ?>"><?php echo te('spay_view'); ?></a>
                        <a href="receipt.php?id=<?php echo (int)$p['id']; ?>&amp;share=1"><?php echo te('spay_share'); ?></a>
                        <?php if (($p['status'] ?? '') === 'paid'): ?>
                            <a href="receipt.php?id=<?php echo (int)$p['id']; ?>#spReverse"><?php echo te('spay_reverse'); ?></a>
                        <?php elseif (in_array((string)($p['status'] ?? ''), ['draft', 'pending'], true)): ?>
                            <a href="receipt.php?id=<?php echo (int)$p['id']; ?>#spReverse"><?php echo te('spay_cancel'); ?></a>
                        <?php endif; ?>
                    </span></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($history['legacy'] as $p): ?>
                <tr>
                    <td data-k="<?php echo htmlspecialchars(te('spay_id'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val">#<?php echo (int)$p['id']; ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_date'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$p['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_company'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)$p['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_amount'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo number_format((float)$p['amount'], 2); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_method'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo htmlspecialchars((string)($p['account_name'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_ref'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val">—</span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_invoice'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val">—</span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_by'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val">—</span></td>
                    <td data-k="<?php echo htmlspecialchars(te('spay_status'), ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val"><?php echo te('spay_st_paid'); ?></span></td>
                    <td data-k="<?php echo htmlspecialchars(function_exists('ame_ui') ? ame_ui('কাজ', 'Actions') : 'কাজ', ENT_QUOTES, 'UTF-8'); ?>"><span class="spay-val spay-acts"><a href="ledger.php?id=<?php echo (int)$p['supplier_id']; ?>"><?php echo te('nav_sup_ledger'); ?></a></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="spay-no-print"><a class="spay-btn" href="pay.php"><?php echo te('spay_title'); ?></a></p>
</div>
<?php include '../../partials/footer.php'; ?>
