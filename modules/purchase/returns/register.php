<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);
include __DIR__ . '/../../../partials/header.php';

$filter_status = trim($_GET['status'] ?? '');

$sql = "SELECT pr.*, uc.username as created_by_name, ua.username as approved_by_name
        FROM purchase_returns pr
        LEFT JOIN users uc ON pr.created_by = uc.id
        LEFT JOIN users ua ON pr.approved_by = ua.id";
$params = [];
$types = '';
if ($filter_status !== '') {
    $sql .= ' WHERE pr.status = ?';
    $params[] = $filter_status;
    $types = 's';
}
$sql .= ' ORDER BY pr.id DESC LIMIT 300';

$rows = [];
try {
    if ($types) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $db->query($sql);
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch (Throwable $e) { error_log('Purchase return register query failed: ' . $e->getMessage()); }

$status_meta = [
    'draft' => ['label' => 'খসড়া', 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'pending_approval' => ['label' => 'অনুমোদনের অপেক্ষায়', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => 'অনুমোদিত', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'completed' => ['label' => 'সম্পন্ন', 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'cancelled' => ['label' => 'বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
$response_labels = ['pending' => '⏳ অপেক্ষমাণ', 'accepted' => '✅ গৃহীত', 'rejected' => '❌ প্রত্যাখ্যাত', 'disputed' => '⚠️ Dispute'];
?>
<style>
    .pr-wrap { padding: 15px; }
    .pr-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
    .pr-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .pr-filters { margin-bottom:16px; }
    .pr-filters select { padding:9px 12px; border-radius:9px; border:1.5px solid #F3E5F5; font-size:12.5px; }
    .pr-table-wrap { background:#fff; border-radius:16px; overflow-x:auto; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .pr-table { width:100%; border-collapse:collapse; font-size:12px; min-width:1000px; }
    .pr-table th { background:#7B1FA2; color:#fff; padding:10px 8px; text-align:left; white-space:nowrap; }
    .pr-table td { padding:9px 8px; border-bottom:1px solid #F1F5F9; white-space:nowrap; }
    .pr-badge { padding:3px 9px; border-radius:16px; font-size:10px; font-weight:800; }
    .pr-empty { text-align:center; color:#999; padding:40px; font-size:13px; }
    .pr-link { color:#7B1FA2; text-decoration:none; font-weight:700; }
</style>

<div class="pr-wrap">
    <div class="pr-header">
        <h2>📋 Purchase Return Register</h2>
        <a href="dashboard.php" class="pr-link">← ড্যাশবোর্ডে ফিরে যান</a>
    </div>

    <form method="GET" class="pr-filters">
        <select name="status" onchange="this.form.submit()">
            <option value="">সব স্ট্যাটাস</option>
            <?php foreach ($status_meta as $key => $meta): ?>
                <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>><?php echo $meta['label']; ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="pr-table-wrap">
        <?php if (empty($rows)): ?>
            <div class="pr-empty">কোনো Purchase Return এন্ট্রি পাওয়া যায়নি।</div>
        <?php else: ?>
        <table class="pr-table">
            <thead>
                <tr>
                    <th>Return No.</th><th>Memo No.</th><th>Supplier</th><th>Amount</th>
                    <th>Refund Type</th><th>Inspection</th><th>Supplier Response</th>
                    <th>Created By</th><th>Approved By</th><th>Date</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $meta = $status_meta[$r['status']] ?? $status_meta['draft'];
                    $resp = $response_labels[$r['supplier_response']] ?? $r['supplier_response'];
                ?>
                <tr>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($r['return_no']); ?></td>
                    <td><?php echo htmlspecialchars($r['memo_no'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['supplier_name'] ?: 'N/A'); ?></td>
                    <td>৳<?php echo number_format((float)$r['return_amount'], 2); ?></td>
                    <td><?php
                        $rt_labels = ['cash_refund' => '💵 নগদ ফেরত', 'bank_transfer' => '🏦 ব্যাংক', 'mobile_banking' => '📱 মোবাইল ব্যাংকিং', 'supplier_credit' => '📝 ক্রেডিট নোট'];
                        echo $rt_labels[$r['refund_type']] ?? htmlspecialchars($r['refund_type']);
                    ?></td>
                    <td><?php echo htmlspecialchars($r['inspection_status']); ?></td>
                    <td><?php echo $resp; ?></td>
                    <td><?php echo htmlspecialchars($r['created_by_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['approved_by_name'] ?? '—'); ?></td>
                    <td><?php echo date('d-m-y', strtotime($r['created_at'])); ?></td>
                    <td><span class="pr-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span></td>
                    <td><a href="view.php?id=<?php echo (int)$r['id']; ?>" class="pr-link">দেখুন</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
