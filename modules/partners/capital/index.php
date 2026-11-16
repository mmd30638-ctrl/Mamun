<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
include __DIR__ . '/../../../partials/header.php';

$partners = $db->query("SELECT id, name, partner_type FROM partners WHERE status != 'exited' ORDER BY name ASC");
$partner_list = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];

$total_business_capital = get_total_business_capital($db);

$recent = $db->query(
    "SELECT pct.*, p.name as partner_name
     FROM partner_capital_transactions pct
     JOIN partners p ON pct.partner_id = p.id
     ORDER BY pct.transaction_date DESC, pct.id DESC LIMIT 30"
);

$type_meta = [
    'investment' => ['label' => 'মূলধন জমা', 'color' => '#0B8043', 'bg' => '#E1F5FE', 'sign' => '+'],
    'capital_withdrawal' => ['label' => 'মূলধন উত্তোলন', 'color' => '#C62828', 'bg' => '#FFEBEE', 'sign' => '-'],
    'loan_to_business' => ['label' => 'ব্যবসাকে ঋণ', 'color' => '#F57C00', 'bg' => '#FFF3E0', 'sign' => '+'],
];
?>
<style>
    .cap-wrap { padding: 15px; }
    .cap-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
    .cap-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .cap-add-btn {
        background: linear-gradient(135deg, #0B1E4D, #0091EA);
        color:#fff; text-decoration:none; padding:11px 18px; border-radius:10px;
        font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(11,30,77,0.25);
    }
    .cap-total-card {
        background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff;
        border-radius:18px; padding:22px; margin-bottom:18px; text-align:center;
        box-shadow: 0 8px 24px rgba(11,30,77,0.25);
    }
    .cap-total-card .lbl { font-size:12.5px; opacity:.85; }
    .cap-total-card .val { font-size:30px; font-weight:800; margin-top:6px; }

    .cap-partner-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap:12px; margin-bottom: 22px; }
    .cap-partner-card { background:#fff; border-radius:14px; padding:16px; box-shadow:0 4px 14px rgba(11,30,77,0.08); border:1px solid #EAF4FF; }
    .cap-partner-name { font-weight:800; color:#0B1E4D; font-size:14px; margin-bottom:8px; }
    .cap-partner-row { display:flex; justify-content:space-between; font-size:12.5px; padding:4px 0; color:#334155; }
    .cap-partner-row .net { font-weight:800; color:#0091EA; }

    .cap-table-wrap { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .cap-table { width:100%; border-collapse:collapse; font-size:13px; }
    .cap-table th { background:#0B1E4D; color:#fff; padding:12px; text-align:left; }
    .cap-table td { padding:11px 12px; border-bottom:1px solid #F1F5F9; }
    .cap-type-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; }
    .cap-empty { text-align:center; color:#999; padding:40px 20px; }
</style>

<div class="cap-wrap">
    <div class="cap-header">
        <h2>💰 মূলধন ও বিনিয়োগ</h2>
        <a href="add.php" class="cap-add-btn">➕ নতুন এন্ট্রি যোগ করুন</a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E1F5FE; color:#0B1E4D; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="cap-total-card">
        <div class="lbl">ব্যবসায় মোট নিট মূলধন</div>
        <div class="val">৳<?php echo number_format($total_business_capital, 2); ?></div>
    </div>

    <div class="cap-partner-grid">
        <?php foreach ($partner_list as $p):
            $summary = get_partner_capital_summary($db, (int)$p['id']);
        ?>
        <div class="cap-partner-card">
            <div class="cap-partner-name">👤 <?php echo htmlspecialchars($p['name']); ?></div>
            <div class="cap-partner-row"><span>মোট মূলধন জমা</span><span>৳<?php echo number_format((float)($summary['total_invested'] ?? 0), 2); ?></span></div>
            <div class="cap-partner-row"><span>মূলধন উত্তোলন</span><span>৳<?php echo number_format((float)($summary['total_capital_withdrawn'] ?? 0), 2); ?></span></div>
            <?php if ($summary['total_loaned'] > 0): ?>
            <div class="cap-partner-row"><span>ব্যবসাকে ঋণ দিয়েছেন</span><span>৳<?php echo number_format((float)($summary['total_loaned'] ?? 0), 2); ?></span></div>
            <?php endif; ?>
            <div class="cap-partner-row net"><span>নিট মূলধন ব্যালেন্স</span><span>৳<?php echo number_format((float)($summary['net_capital_balance'] ?? 0), 2); ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>

    <h3 style="color:#0B1E4D; font-size:15px; margin-bottom:10px;">📋 সাম্প্রতিক লেনদেন</h3>
    <div class="cap-table-wrap">
        <?php if ($recent && $recent->num_rows > 0): ?>
        <table class="cap-table">
            <thead>
                <tr><th>তারিখ</th><th>পার্টনার</th><th>ধরন</th><th>পরিমাণ</th><th>নোট</th></tr>
            </thead>
            <tbody>
                <?php while ($r = $recent->fetch_assoc()):
                    $meta = $type_meta[$r['type']] ?? $type_meta['investment'];
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($r['transaction_date'])); ?></td>
                    <td><?php echo htmlspecialchars($r['partner_name']); ?></td>
                    <td><span class="cap-type-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span></td>
                    <td style="font-weight:700; color:<?php echo $meta['color']; ?>;"><?php echo $meta['sign']; ?>৳<?php echo number_format((float)$r['amount'], 2); ?></td>
                    <td style="color:#64748B;"><?php echo htmlspecialchars($r['note'] ?? ''); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="cap-empty">এখনো কোনো মূলধন লেনদেন যোগ করা হয়নি।</div>
        <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
        <a href="/modules/partners/index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনার তালিকায় ফিরে যান</a>
    </div>
</div>
