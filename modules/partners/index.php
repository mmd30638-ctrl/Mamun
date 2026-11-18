<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
include __DIR__ . '/../../partials/header.php';

$partners = $db->query("SELECT * FROM partners ORDER BY status = 'active' DESC, id ASC");

$status_labels = [
    'active'   => ['label' => 'সক্রিয়', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'inactive' => ['label' => 'নিষ্ক্রিয়', 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'exited'   => ['label' => 'প্রস্থান করেছেন', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];

$type_labels = [
    'equity'       => 'ইক্যুইটি পার্টনার (মালিকানা আছে)',
    'profit_share' => 'শুধু লাভের ভাগ',
    'silent'       => 'সাইলেন্ট পার্টনার',
];
?>
<style>
    .pnr-wrap { padding: 15px; }
    .pnr-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
    .pnr-header h2 { margin:0; color:#0B1E4D; font-size:19px; }
    .pnr-add-btn {
        background: linear-gradient(135deg, #0B1E4D, #0091EA);
        color:#fff; text-decoration:none; padding:11px 18px; border-radius:10px;
        font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(11,30,77,0.25);
    }
    .pnr-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
    .pnr-card {
        background:#fff; border-radius:16px; padding:18px;
        box-shadow: 0 4px 18px rgba(11,30,77,0.08); border:1px solid #EAF4FF;
    }
    .pnr-top { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
    .pnr-avatar {
        width:48px; height:48px; border-radius:50%;
        background: linear-gradient(135deg, #0B1E4D, #0091EA);
        color:#fff; display:flex; align-items:center; justify-content:center;
        font-weight:800; font-size:18px; flex-shrink:0;
    }
    .pnr-name { font-weight:800; color:#0B1E4D; font-size:15px; }
    .pnr-type { font-size:11.5px; color:#64748B; margin-top:2px; }
    .pnr-badge {
        display:inline-block; font-size:11px; font-weight:800; padding:4px 10px;
        border-radius:20px; margin-top:6px;
    }
    .pnr-row { display:flex; justify-content:space-between; font-size:12.5px; color:#334155; padding:5px 0; border-top:1px solid #F1F5F9; }
    .pnr-share { font-size:22px; font-weight:800; color:#0091EA; text-align:center; margin:10px 0; }
    .pnr-actions { display:flex; gap:8px; margin-top:12px; }
    .pnr-actions a { flex:1; text-align:center; padding:9px; border-radius:8px; font-size:12.5px; font-weight:700; text-decoration:none; }
    .pnr-edit-btn { background:#EAF4FF; color:#0B1E4D; }
    .pnr-empty { text-align:center; color:#999; padding:60px 20px; }
</style>

<div class="pnr-wrap">
    <div class="pnr-header">
        <h2>👥 পার্টনার তালিকা</h2>
        <a href="add.php" class="pnr-add-btn">➕ নতুন পার্টনার যোগ করুন</a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div style="background:#E1F5FE; color:#0B1E4D; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13.5px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($partners && $partners->num_rows > 0): ?>
        <div class="pnr-grid">
            <?php while ($p = $partners->fetch_assoc()):
                $status = $status_labels[$p['status']] ?? $status_labels['active'];
                $type_label = $type_labels[$p['partner_type']] ?? $p['partner_type'];
                $initial = mb_substr($p['name'], 0, 1, 'UTF-8');
            ?>
            <div class="pnr-card">
                <div class="pnr-top">
                    <div class="pnr-avatar"><?php echo htmlspecialchars($initial); ?></div>
                    <div>
                        <div class="pnr-name"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div class="pnr-type"><?php echo htmlspecialchars($type_label); ?></div>
                        <span class="pnr-badge" style="background:<?php echo $status['bg']; ?>; color:<?php echo $status['color']; ?>;">
                            <?php echo $status['label']; ?>
                        </span>
                    </div>
                </div>

                <div class="pnr-share"><?php echo number_format((float)$p['share_percentage'], 2); ?>%</div>

                <?php if (!empty($p['phone'])): ?>
                <div class="pnr-row"><span>📞 ফোন</span><span><?php echo htmlspecialchars($p['phone']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($p['email'])): ?>
                <div class="pnr-row"><span>✉️ ইমেইল</span><span><?php echo htmlspecialchars($p['email']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($p['join_date'])): ?>
                <div class="pnr-row"><span>📅 যোগদান</span><span><?php echo date('d M Y', strtotime($p['join_date'])); ?></span></div>
                <?php endif; ?>

                <div class="pnr-actions">
                    <a href="edit.php?id=<?php echo (int)$p['id']; ?>" class="pnr-edit-btn">✏️ এডিট করুন</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="pnr-empty">এখনো কোনো পার্টনার যোগ করা হয়নি।</div>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="/modules/accounts/partnership.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← লাভ-বণ্টন হিসাবে ফিরে যান</a>
    </div>
</div>
