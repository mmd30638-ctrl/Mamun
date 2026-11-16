<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);
include __DIR__ . '/../../../partials/header.php';

$filter_status = trim($_GET['status'] ?? '');
$filter_type = trim($_GET['type'] ?? '');

$where = [];
$params = [];
$types = '';

if ($filter_status !== '') {
    $where[] = 'r.status = ?';
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_type !== '') {
    $where[] = 'r.return_type = ?';
    $params[] = $filter_type;
    $types .= 's';
}

$sql = "SELECT r.*, uc.username as created_by_name, ua.username as approved_by_name,
               a.account_name as account_name
        FROM sale_returns_v2 r
        LEFT JOIN users uc ON r.created_by = uc.id
        LEFT JOIN users ua ON r.approved_by = ua.id
        LEFT JOIN accounts a ON r.account_id = a.id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.id DESC LIMIT 300';

$rows = [];
try {
    if ($types) {
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        $result = $db->query($sql);
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch (Throwable $e) {
    error_log('Return register query failed: ' . $e->getMessage());
}

$status_meta = [
    'draft' => ['label' => 'খসড়া', 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'pending_approval' => ['label' => 'অনুমোদনের অপেক্ষায়', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => 'অনুমোদিত', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'completed' => ['label' => 'সম্পন্ন', 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'cancelled' => ['label' => 'বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
$type_labels = [
    'invoice_based' => '🧾 Invoice-based',
    'manual' => '✍️ Manual',
    'exchange' => '🔄 Exchange',
];
?>
<style>
    .rr-wrap { padding: 15px; }
    .rr-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
    .rr-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .rr-filters { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
    .rr-filters select { padding:9px 12px; border-radius:9px; border:1.5px solid #CFE3F7; font-size:12.5px; }
    .rr-table-wrap { background:#fff; border-radius:16px; overflow-x:auto; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .rr-table { width:100%; border-collapse:collapse; font-size:12px; min-width:900px; }
    .rr-table th { background:#0B1E4D; color:#fff; padding:10px 8px; text-align:left; white-space:nowrap; }
    .rr-table td { padding:9px 8px; border-bottom:1px solid #F1F5F9; white-space:nowrap; }
    .rr-badge { padding:3px 9px; border-radius:16px; font-size:10px; font-weight:800; }
    .rr-empty { text-align:center; color:#999; padding:40px; font-size:13px; }
    .rr-link { color:#0091EA; text-decoration:none; font-weight:700; }
</style>

<div class="rr-wrap">
    <div class="rr-header">
        <h2>📋 Return Register</h2>
        <a href="dashboard.php" class="rr-link">← ড্যাশবোর্ডে ফিরে যান</a>
    </div>

    <form method="GET" class="rr-filters">
        <select name="status" onchange="this.form.submit()">
            <option value="">সব স্ট্যাটাস</option>
            <?php foreach ($status_meta as $key => $meta): ?>
                <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>><?php echo $meta['label']; ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" onchange="this.form.submit()">
            <option value="">সব ধরন</option>
            <?php foreach ($type_labels as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="rr-table-wrap">
        <?php if (empty($rows)): ?>
            <div class="rr-empty">কোনো রিটার্ন এন্ট্রি পাওয়া যায়নি।</div>
        <?php else: ?>
        <table class="rr-table">
            <thead>
                <tr>
                    <th>Return No.</th>
                    <th>Invoice No.</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Return Amount</th>
                    <th>Exchange Amount</th>
                    <th>Adjustment</th>
                    <th>Payment</th>
                    <th>Created By</th>
                    <th>Approved By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $meta = $status_meta[$r['status']] ?? $status_meta['draft'];
                ?>
                <tr>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($r['return_no']); ?></td>
                    <td><?php echo $r['sale_id'] ? '#' . (int)$r['sale_id'] : '—'; ?></td>
                    <td><?php echo htmlspecialchars($r['customer_name'] ?: 'N/A'); ?> <span style="color:#94A3B8; font-size:10px;">(<?php echo htmlspecialchars($r['sales_type']); ?>)</span></td>
                    <td><?php echo $type_labels[$r['return_type']] ?? htmlspecialchars($r['return_type']); ?></td>
                    <td>৳<?php echo number_format((float)$r['return_amount'], 2); ?></td>
                    <td>৳<?php echo number_format((float)$r['exchange_amount'], 2); ?></td>
                    <td style="color:<?php echo $r['customer_adjustment'] >= 0 ? '#0B8043' : '#C62828'; ?>; font-weight:700;">
                        <?php echo ($r['customer_adjustment'] >= 0 ? '+' : '') . number_format((float)$r['customer_adjustment'], 2); ?>
                    </td>
                    <td>
                        <?php if (!empty($r['account_name']) && ($r['refund_method'] ?? '') !== 'customer_balance'): ?>
                            <span style="color:#C62828; font-weight:800;">🏦 <?php echo htmlspecialchars($r['account_name']); ?> (−৳<?php echo number_format((float)$r['return_amount'], 2); ?>)</span>
                        <?php elseif (($r['refund_method'] ?? '') === 'customer_balance'): ?>
                            <span style="color:#1E40AF; font-weight:700;">👤 গ্রাহক ব্যালেন্স সমন্বয়</span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($r['payment_status']); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($r['created_by_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['approved_by_name'] ?? '—'); ?></td>
                    <td><?php echo date('d-m-y', strtotime($r['created_at'])); ?></td>
                    <td><span class="rr-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span></td>
                    <td><a href="view.php?id=<?php echo (int)$r['id']; ?>" class="rr-link">দেখুন</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
