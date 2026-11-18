<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

$allocations = $db->query("SELECT * FROM profit_allocations ORDER BY period_start DESC, id DESC");
$rows = $allocations ? $allocations->fetch_all(MYSQLI_ASSOC) : [];

$status_meta = [
    'proposed' => ['label' => '⏳ প্রস্তাবিত (অনুমোদনের অপেক্ষায়)', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => '✅ অনুমোদিত', 'bg' => '#E1F5FE', 'color' => '#0B8043'],
    'rejected' => ['label' => '❌ বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
?>
<style>
    .al-wrap { padding: 15px; }
    .al-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
    .al-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .al-add-btn {
        background: linear-gradient(135deg, #0B1E4D, #0091EA);
        color:#fff; text-decoration:none; padding:11px 18px; border-radius:10px;
        font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(11,30,77,0.25);
    }
    .al-card { background:#fff; border-radius:16px; padding:18px; margin-bottom:14px; box-shadow:0 4px 18px rgba(11,30,77,0.08); border:1px solid #EAF4FF; }
    .al-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .al-period { font-weight:800; color:#0B1E4D; font-size:15px; }
    .al-dates { font-size:12px; color:#64748B; margin-top:3px; }
    .al-badge { padding:5px 12px; border-radius:20px; font-size:11.5px; font-weight:800; white-space:nowrap; }
    .al-figures { display:flex; gap:18px; margin-top:14px; flex-wrap:wrap; }
    .al-figure .lbl { font-size:11px; color:#94A3B8; }
    .al-figure .val { font-size:16px; font-weight:800; color:#0B1E4D; }
    .al-view-btn { display:inline-block; margin-top:14px; color:#0091EA; text-decoration:none; font-size:13px; font-weight:700; }
    .al-empty { text-align:center; color:#999; padding:50px 20px; }
</style>

<div class="al-wrap">
    <div class="al-header">
        <h2>📈 লাভ বণ্টন (Profit Allocation)</h2>
        <a href="create.php" class="al-add-btn">➕ নতুন সময়কাল বণ্টন করুন</a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E1F5FE; color:#0B1E4D; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="al-empty">এখনো কোনো লাভ বণ্টন তৈরি করা হয়নি। "নতুন সময়কাল বণ্টন করুন" চাপুন।</div>
    <?php else: ?>
        <?php foreach ($rows as $a):
            $meta = $status_meta[$a['status']] ?? $status_meta['proposed'];
        ?>
        <div class="al-card">
            <div class="al-card-top">
                <div>
                    <div class="al-period">📅 <?php echo htmlspecialchars($a['period_label']); ?></div>
                    <div class="al-dates"><?php echo date('d M Y', strtotime($a['period_start'])); ?> — <?php echo date('d M Y', strtotime($a['period_end'])); ?></div>
                </div>
                <span class="al-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span>
            </div>
            <div class="al-figures">
                <div class="al-figure"><div class="lbl">গ্রস প্রফিট</div><div class="val">৳<?php echo number_format((float)$a['gross_profit'], 0); ?></div></div>
                <div class="al-figure"><div class="lbl">খরচ</div><div class="val">৳<?php echo number_format((float)$a['total_expenses'], 0); ?></div></div>
                <div class="al-figure"><div class="lbl">নিট লাভ/ক্ষতি</div><div class="val" style="color:<?php echo $a['net_profit'] >= 0 ? '#0B8043' : '#C62828'; ?>;">৳<?php echo number_format((float)$a['net_profit'], 0); ?></div></div>
            </div>
            <a href="view.php?id=<?php echo (int)$a['id']; ?>" class="al-view-btn">বিস্তারিত দেখুন / অনুমোদন করুন →</a>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:10px;">
        <a href="/modules/partners/accounts/index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← পার্টনার অ্যাকাউন্টে ফিরে যান</a>
    </div>
</div>
