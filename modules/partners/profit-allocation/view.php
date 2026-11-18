<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM profit_allocations WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$allocation = $stmt->get_result()->fetch_assoc();

if (!$allocation) {
    die('❌ বণ্টন খুঁজে পাওয়া যায়নি। <a href="index.php">তালিকায় ফিরুন</a>');
}

$dstmt = $db->prepare(
    "SELECT pad.*, p.name as partner_name
     FROM profit_allocation_details pad
     JOIN partners p ON pad.partner_id = p.id
     WHERE pad.allocation_id = ?
     ORDER BY pad.allocated_amount DESC"
);
$dstmt->bind_param('i', $id);
$dstmt->execute();
$details = $dstmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_meta = [
    'proposed' => ['label' => '⏳ প্রস্তাবিত', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => '✅ অনুমোদিত', 'bg' => '#E1F5FE', 'color' => '#0B8043'],
    'rejected' => ['label' => '❌ বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
$meta = $status_meta[$allocation['status']] ?? $status_meta['proposed'];
?>
<style>
    .av-wrap { padding: 15px; max-width: 640px; margin: 0 auto; }
    .av-card { background:#fff; border-radius:18px; box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:hidden; }
    .av-header { background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; padding: 24px 26px; }
    .av-header h2 { margin:0 0 4px; font-size:19px; }
    .av-badge { display:inline-block; margin-top:10px; padding:5px 12px; border-radius:20px; font-size:11.5px; font-weight:800; }
    .av-body { padding: 24px 26px 28px; }
    .av-figures { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:20px; }
    .av-fig { background:#EAF4FF; border-radius:12px; padding:12px 14px; }
    .av-fig .lbl { font-size:11px; color:#64748B; }
    .av-fig .val { font-size:17px; font-weight:800; color:#0B1E4D; margin-top:3px; }

    .av-table { width:100%; border-collapse:collapse; font-size:13.5px; margin-top:10px; }
    .av-table th { background:#0B1E4D; color:#fff; padding:10px; text-align:left; }
    .av-table td { padding:10px; border-bottom:1px solid #F1F5F9; }

    .av-approve-btn { width:100%; padding:14px; border:none; border-radius:12px; background: linear-gradient(135deg, #0B8043, #2ECC71); color:#fff; font-weight:800; font-size:15px; cursor:pointer; margin-top:20px; box-shadow: 0 6px 18px rgba(11,128,67,0.25); }
    .av-locked-note { background:#E1F5FE; color:#0B1E4D; padding:12px 14px; border-radius:10px; font-size:12.5px; margin-top:18px; }
</style>

<div class="av-wrap">
    <div class="av-card">
        <div class="av-header">
            <h2>📅 <?php echo htmlspecialchars($allocation['period_label']); ?></h2>
            <div style="font-size:12.5px; opacity:.85;"><?php echo date('d M Y', strtotime($allocation['period_start'])); ?> — <?php echo date('d M Y', strtotime($allocation['period_end'])); ?></div>
            <span class="av-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span>
        </div>
        <div class="av-body">
            <div class="av-figures">
                <div class="av-fig"><div class="lbl">গ্রস প্রফিট</div><div class="val">৳<?php echo number_format((float)$allocation['gross_profit'], 2); ?></div></div>
                <div class="av-fig"><div class="lbl">মোট খরচ</div><div class="val">৳<?php echo number_format((float)$allocation['total_expenses'], 2); ?></div></div>
                <div class="av-fig"><div class="lbl">সমন্বয়</div><div class="val">৳<?php echo number_format((float)$allocation['total_adjustments'], 2); ?></div></div>
                <div class="av-fig" style="background:<?php echo $allocation['net_profit'] >= 0 ? '#E1F5FE' : '#FFEBEE'; ?>;">
                    <div class="lbl">নিট লাভ/ক্ষতি</div>
                    <div class="val" style="color:<?php echo $allocation['net_profit'] >= 0 ? '#0B8043' : '#C62828'; ?>;">৳<?php echo number_format((float)$allocation['net_profit'], 2); ?></div>
                </div>
            </div>

            <h3 style="color:#0B1E4D; font-size:14px;">👥 পার্টনার-ভিত্তিক বণ্টন</h3>
            <table class="av-table">
                <thead><tr><th>পার্টনার</th><th>শেয়ার</th><th>প্রাপ্য অংশ</th></tr></thead>
                <tbody>
                <?php foreach ($details as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['partner_name']); ?></td>
                        <td><?php echo number_format((float)$d['share_percentage_used'], 2); ?>%</td>
                        <td style="font-weight:700; color:<?php echo $d['allocated_amount'] >= 0 ? '#0B8043' : '#C62828'; ?>;">৳<?php echo number_format((float)$d['allocated_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($allocation['status'] === 'proposed'): ?>
                <form action="actions/approve.php" method="POST" onsubmit="return confirm('অনুমোদন করার পর এটা আর পরিবর্তন করা যাবে না। নিশ্চিত?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$allocation['id']; ?>">
                    <button type="submit" class="av-approve-btn">✅ এই বণ্টন অনুমোদন করুন</button>
                </form>
            <?php else: ?>
                <div class="av-locked-note">🔒 এই বণ্টন অনুমোদিত হয়ে গেছে — এটা এখন স্থায়ী এবং প্রতিটা পার্টনারের Current Account-এ যুক্ত হয়েছে।</div>
            <?php endif; ?>

            <div style="margin-top:16px; text-align:center;">
                <a href="index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← তালিকায় ফিরে যান</a>
            </div>
        </div>
    </div>
</div>
