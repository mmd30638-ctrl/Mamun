<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
require_once __DIR__ . '/../sales_return_module.php';
ensure_return_v2_schema($db);
include __DIR__ . '/../../../partials/header.php';
ame_sales_return_include_nav('dashboard');

$today = date('Y-m-d');

function safe_count($db, $sql, $params = [], $types = '') {
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
        error_log('Return dashboard query failed: ' . $e->getMessage());
        return [];
    }
}

$today_returns = safe_count($db, "SELECT COUNT(*) c FROM sale_returns_v2 WHERE DATE(created_at) = ?", [$today], 's')['c'] ?? 0;
$wholesale_returns = safe_count($db, "SELECT COUNT(*) c FROM sale_returns_v2 WHERE sales_type = 'wholesale' AND DATE(created_at) = ?", [$today], 's')['c'] ?? 0;
$retail_returns = safe_count($db, "SELECT COUNT(*) c FROM sale_returns_v2 WHERE sales_type = 'retail' AND DATE(created_at) = ?", [$today], 's')['c'] ?? 0;
$exchanges_today = safe_count($db, "SELECT COUNT(*) c FROM sale_returns_v2 WHERE return_type = 'exchange' AND DATE(created_at) = ?", [$today], 's')['c'] ?? 0;

$total_return_amount = safe_count($db, "SELECT COALESCE(SUM(return_amount),0) c FROM sale_returns_v2 WHERE status IN ('approved','completed') AND DATE(created_at) = ?", [$today], 's')['c'] ?? 0;
$total_refund = safe_count($db, "SELECT COALESCE(SUM(ABS(customer_adjustment)),0) c FROM sale_returns_v2 WHERE customer_adjustment < 0 AND status IN ('approved','completed')")['c'] ?? 0;
$receivable_adjustment = safe_count($db, "SELECT COALESCE(SUM(return_amount),0) c FROM sale_returns_v2 WHERE status = 'completed' AND customer_id IS NOT NULL")['c'] ?? 0;
$pending_approvals = safe_count($db, "SELECT COUNT(*) c FROM sale_returns_v2 WHERE status = 'pending_approval'")['c'] ?? 0;

$recent_result = $db->query(
    "SELECT * FROM sale_returns_v2 ORDER BY id DESC LIMIT 8"
);
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
    .rd-wrap { padding: 15px; }
    .rd-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
    .rd-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .rd-add-btn {
        background: linear-gradient(135deg, #0B1E4D, #0091EA);
        color:#fff; text-decoration:none; padding:11px 18px; border-radius:10px;
        font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(11,30,77,0.25);
    }
    .rd-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:20px; }
    .rd-kpi-box { border-radius:14px; padding:14px; color:#fff; box-shadow:0 6px 16px rgba(11,30,77,0.15); }
    .rd-kpi-box .lbl { font-size:11px; opacity:.85; }
    .rd-kpi-box .val { font-size:18px; font-weight:800; margin-top:5px; }

    .rd-alert { background:#FFF3E0; border:1px solid #FFE0B2; color:#8D6E00; border-radius:12px; padding:12px 16px; font-size:12.5px; margin-bottom:18px; }

    .rd-nav-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:22px; }
    .rd-nav-card { background:#fff; border-radius:14px; padding:14px; text-decoration:none; box-shadow:0 4px 14px rgba(11,30,77,0.08); border:1px solid #EAF4FF; }
    .rd-nav-card .icon { font-size:20px; margin-bottom:6px; }
    .rd-nav-card .title { font-weight:800; color:#0B1E4D; font-size:13px; }

    .rd-recent { background:#fff; border-radius:16px; padding:16px; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .rd-recent h3 { margin:0 0 12px; color:#0B1E4D; font-size:14.5px; }
    .rd-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .rd-badge { padding:3px 10px; border-radius:20px; font-size:10.5px; font-weight:800; }
    .rd-empty { text-align:center; color:#999; padding:30px; font-size:13px; }
</style>

<div class="rd-wrap">
    <div class="rd-header">
        <h2>📦 <?php echo htmlspecialchars(function_exists('te') ? te('nav_sale_return') : 'বিক্রয় ফেরত', ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars(function_exists('te') ? te('nav_return_dash') : 'ড্যাশবোর্ড', ENT_QUOTES, 'UTF-8'); ?></h2>
        <a href="quick_return.php" class="rd-add-btn">⚡ দ্রুত ফেরত</a>
    </div>

    <?php if ($pending_approvals > 0): ?>
        <div class="rd-alert">🔔 <strong><?php echo (int)$pending_approvals; ?> টা</strong> রিটার্ন অনুমোদনের অপেক্ষায় আছে। <a href="register.php?status=pending_approval" style="color:#8D6E00; text-decoration:underline;">এখনই দেখুন</a></div>
    <?php endif; ?>

    <div class="rd-kpi-grid">
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#0B1E4D,#0091EA);"><div class="lbl">আজকের রিটার্ন</div><div class="val"><?php echo (int)$today_returns; ?> টা</div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#7B1FA2,#AB47BC);"><div class="lbl">Wholesale রিটার্ন</div><div class="val"><?php echo (int)$wholesale_returns; ?> টা</div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#F57C00,#FFB74D);"><div class="lbl">Retail রিটার্ন</div><div class="val"><?php echo (int)$retail_returns; ?> টা</div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#00838F,#26C6DA);"><div class="lbl">প্রোডাক্ট এক্সচেঞ্জ</div><div class="val"><?php echo (int)$exchanges_today; ?> টা</div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#0B8043,#2ECC71);"><div class="lbl">মোট রিটার্ন পরিমাণ (আজ)</div><div class="val">৳<?php echo number_format($total_return_amount, 0); ?></div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#C62828,#E53935);"><div class="lbl">মোট রিফান্ড/ক্রেডিট</div><div class="val">৳<?php echo number_format($total_refund, 0); ?></div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#455A64,#78909C);"><div class="lbl">Receivable সমন্বয়</div><div class="val">৳<?php echo number_format($receivable_adjustment, 0); ?></div></div>
        <div class="rd-kpi-box" style="background:linear-gradient(135deg,#E65100,#FF9800);"><div class="lbl">অপেক্ষমাণ অনুমোদন</div><div class="val"><?php echo (int)$pending_approvals; ?> টা</div></div>
    </div>

    <div class="rd-nav-grid">
        <?php foreach (ame_sales_return_sections() as $sec):
            if (($sec['id'] ?? '') === 'dashboard') {
                continue;
            }
            $isEn = function_exists('current_lang') && current_lang() === 'en';
            $desc = $isEn ? ($sec['desc_en'] ?? '') : ($sec['desc_bn'] ?? '');
            ?>
        <a href="<?php echo htmlspecialchars((string)$sec['href'], ENT_QUOTES, 'UTF-8'); ?>" class="rd-nav-card">
            <div class="icon"><?php echo htmlspecialchars((string)($sec['icon'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="title"><?php echo htmlspecialchars(ame_sales_return_label($sec), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if ($desc !== ''): ?><div style="font-size:11px;color:#64748B;margin-top:4px;"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="rd-recent">
        <h3>🕒 সাম্প্রতিক লেনদেন</h3>
        <?php if (empty($recent)): ?>
            <div class="rd-empty">এখনো কোনো রিটার্ন এন্ট্রি নেই।</div>
        <?php else: foreach ($recent as $r):
            $meta = $status_meta[$r['status']] ?? $status_meta['draft'];
        ?>
            <div class="rd-row">
                <span><?php echo htmlspecialchars($r['return_no']); ?> — <?php echo htmlspecialchars($r['customer_name'] ?: 'গ্রাহকের নাম নেই'); ?></span>
                <span style="display:flex; align-items:center; gap:8px;">
                    <span style="font-weight:700; color:#0B1E4D;">৳<?php echo number_format((float)$r['return_amount'], 2); ?></span>
                    <span class="rd-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span>
                </span>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
