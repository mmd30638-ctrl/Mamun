<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);
include __DIR__ . '/../../../partials/header.php';

function safe_val($db, $sql, $params = [], $types = '') {
    try {
        if ($types) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
        } else {
            $row = $db->query($sql)->fetch_assoc();
        }
        return $row;
    } catch (Throwable $e) {
        error_log('Purchase return dashboard query failed: ' . $e->getMessage());
        return [];
    }
}

$today = date('Y-m-d');
$today_returns = safe_val($db, "SELECT COUNT(*) c FROM purchase_returns WHERE DATE(created_at) = ?", [$today], 's')['c'] ?? 0;
$pending_approvals = safe_val($db, "SELECT COUNT(*) c FROM purchase_returns WHERE status = 'pending_approval'")['c'] ?? 0;
$total_recovery = safe_val($db, "SELECT COALESCE(SUM(return_amount),0) c FROM purchase_returns WHERE status = 'completed'")['c'] ?? 0;
$outstanding_credit = safe_val($db, "SELECT COALESCE(SUM(credit_balance),0) c FROM suppliers")['c'] ?? 0;
$disputed = safe_val($db, "SELECT COUNT(*) c FROM purchase_returns WHERE supplier_response = 'disputed'")['c'] ?? 0;
$awaiting_dispatch = safe_val($db, "SELECT COUNT(*) c FROM purchase_returns WHERE status = 'approved' AND dispatch_date IS NULL")['c'] ?? 0;

// ================= Aging (কতদিন pending) =================
$ageing_rows = [];
try {
    $result = $db->query(
        "SELECT return_no, supplier_name, return_amount, status, created_at,
                DATEDIFF(NOW(), created_at) as days_pending
         FROM purchase_returns
         WHERE status IN ('pending_approval', 'approved')
         ORDER BY created_at ASC LIMIT 10"
    );
    $ageing_rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Throwable $e) { error_log('Ageing query failed: ' . $e->getMessage()); }

$recent_result = $db->query("SELECT * FROM purchase_returns ORDER BY id DESC LIMIT 8");
$recent = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];

$status_meta = [
    'draft' => ['label' => 'খসড়া', 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'pending_approval' => ['label' => 'অনুমোদনের অপেক্ষায়', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => 'অনুমোদিত', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'completed' => ['label' => 'সম্পন্ন', 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'cancelled' => ['label' => 'বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
?>
<style>
    .pd-wrap { padding: 15px; }
    .pd-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
    .pd-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .pd-add-btn { background: linear-gradient(135deg, #7B1FA2, #AB47BC); color:#fff; text-decoration:none; padding:11px 18px; border-radius:10px; font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(123,31,162,0.25); }
    .pd-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:20px; }
    .pd-kpi-box { border-radius:14px; padding:14px; color:#fff; box-shadow:0 6px 16px rgba(11,30,77,0.15); }
    .pd-kpi-box .lbl { font-size:11px; opacity:.85; }
    .pd-kpi-box .val { font-size:18px; font-weight:800; margin-top:5px; }

    .pd-alert { background:#FFF3E0; border:1px solid #FFE0B2; color:#8D6E00; border-radius:12px; padding:12px 16px; font-size:12.5px; margin-bottom:14px; }

    .pd-nav-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:22px; }
    .pd-nav-card { background:#fff; border-radius:14px; padding:14px; text-decoration:none; box-shadow:0 4px 14px rgba(11,30,77,0.08); border:1px solid #F3E5F5; }
    .pd-nav-card .icon { font-size:20px; margin-bottom:6px; }
    .pd-nav-card .title { font-weight:800; color:#0B1E4D; font-size:13px; }

    .pd-section { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); margin-bottom:16px; }
    .pd-section h3 { margin:0 0 12px; color:#0B1E4D; font-size:14.5px; }
    .pd-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .pd-badge { padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:800; }
    .pd-empty { text-align:center; color:#999; padding:30px; font-size:13px; }
</style>

<div class="pd-wrap">
    <div class="pd-header">
        <h2>🔄 Purchase Product Return — ড্যাশবোর্ড</h2>
        <a href="create.php" class="pd-add-btn">➕ নতুন রিটার্ন</a>
    </div>

    <?php if ($pending_approvals > 0): ?>
        <div class="pd-alert">🔔 <strong><?php echo (int)$pending_approvals; ?> টা</strong> রিটার্ন অনুমোদনের অপেক্ষায়। <a href="register.php?status=pending_approval" style="color:#8D6E00; text-decoration:underline;">দেখুন</a></div>
    <?php endif; ?>
    <?php if ($disputed > 0): ?>
        <div class="pd-alert" style="background:#FFEBEE; border-color:#FFCDD2; color:#C62828;">⚠️ <strong><?php echo (int)$disputed; ?> টা</strong> রিটার্ন নিয়ে সাপ্লায়ারের সাথে dispute আছে।</div>
    <?php endif; ?>

    <div class="pd-kpi-grid">
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#7B1FA2,#AB47BC);"><div class="lbl">আজকের রিটার্ন</div><div class="val"><?php echo (int)$today_returns; ?> টা</div></div>
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#E65100,#FF9800);"><div class="lbl">অপেক্ষমাণ অনুমোদন</div><div class="val"><?php echo (int)$pending_approvals; ?> টা</div></div>
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#0091EA,#4FC3F7);"><div class="lbl">ডিসপ্যাচের অপেক্ষায়</div><div class="val"><?php echo (int)$awaiting_dispatch; ?> টা</div></div>
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#0B8043,#2ECC71);"><div class="lbl">মোট রিকভারি (সম্পন্ন)</div><div class="val">৳<?php echo number_format($total_recovery, 0); ?></div></div>
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#455A64,#78909C);"><div class="lbl">সাপ্লায়ার ক্রেডিট (মোট)</div><div class="val">৳<?php echo number_format($outstanding_credit, 0); ?></div></div>
        <div class="pd-kpi-box" style="background:linear-gradient(135deg,#C62828,#E53935);"><div class="lbl">Disputed</div><div class="val"><?php echo (int)$disputed; ?> টা</div></div>
    </div>

    <div class="pd-nav-grid">
        <a href="create.php" class="pd-nav-card"><div class="icon">🧾</div><div class="title">নতুন Purchase Return</div></a>
        <a href="register.php" class="pd-nav-card"><div class="icon">📋</div><div class="title">Return Register</div></a>
        <a href="analytics.php" class="pd-nav-card"><div class="icon">📊</div><div class="title">Analytics Dashboard</div></a>
    </div>

    <?php if (!empty($ageing_rows)): ?>
    <div class="pd-section">
        <h3>⏳ Return Aging (কতদিন ধরে অমীমাংসিত)</h3>
        <?php foreach ($ageing_rows as $r): ?>
            <div class="pd-row">
                <span><?php echo htmlspecialchars($r['return_no']); ?> — <?php echo htmlspecialchars($r['supplier_name'] ?: 'N/A'); ?></span>
                <span style="font-weight:700; color:<?php echo $r['days_pending'] > 15 ? '#C62828' : '#0B1E4D'; ?>;"><?php echo (int)$r['days_pending']; ?> দিন</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="pd-section">
        <h3>🕒 সাম্প্রতিক লেনদেন</h3>
        <?php if (empty($recent)): ?>
            <div class="pd-empty">এখনো কোনো Purchase Return এন্ট্রি নেই।</div>
        <?php else: foreach ($recent as $r):
            $meta = $status_meta[$r['status']] ?? $status_meta['draft'];
        ?>
            <div class="pd-row">
                <span><?php echo htmlspecialchars($r['return_no']); ?> — <?php echo htmlspecialchars($r['supplier_name'] ?: 'N/A'); ?></span>
                <span style="display:flex; align-items:center; gap:8px;">
                    <span style="font-weight:700; color:#0B1E4D;">৳<?php echo number_format((float)$r['return_amount'], 2); ?></span>
                    <span class="pd-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span>
                </span>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
