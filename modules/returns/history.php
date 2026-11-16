<?php 
require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
include '../../partials/header.php'; 

$returns = $db->query("SELECT * FROM sale_returns ORDER BY id DESC LIMIT 50");
?>
<style>
    .ret-wrap { padding: 15px; }
    .ret-card { background:#fff; border-radius:14px; max-width:640px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .ret-topbar { display:flex; height:6px; }
    .ret-topbar span { flex:1; }
    .ret-c1{background:#2E7D32;} .ret-c2{background:#000;} .ret-c3{background:#B71C1C;}
    .ret-header { text-align:center; padding:20px 15px 15px; }
    .ret-header h3 { margin:0; font-size:19px; color:#1B5E20; }
    .ret-list { padding:0 20px 20px; }
    .ret-item { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:#fafafa; border-radius:10px; margin-bottom:8px; border:1px solid #eee; }
    .ret-left .ret-id { font-weight:700; color:#000; font-size:13px; }
    .ret-left .ret-inv { font-size:12px; color:#777; margin-top:2px; }
    .ret-right { text-align:right; }
    .ret-amount { font-weight:800; color:#B71C1C; font-size:14px; }
    .ret-date { font-size:11px; color:#999; margin-top:2px; }
    .ret-empty { text-align:center; color:#999; font-size:13px; padding:20px; }
    .ret-back { display:block; text-decoration:none; margin:0 20px 20px; padding:13px; border-radius:8px; background:#000; color:#fff; font-weight:700; font-size:14px; text-align:center; }
    .ret-back:active { background:#2E7D32; }
</style>

<div class="ret-wrap">
    <div class="ret-card">
        <div class="ret-topbar"><span class="ret-c1"></span><span class="ret-c2"></span><span class="ret-c3"></span></div>
        <div class="ret-header">
            <h3>📜 <?php echo t('return_history'); ?></h3>
            <a href="add.php" style="display:inline-block; margin-top:10px; padding:9px 20px; background:#2E7D32; color:#fff; border-radius:20px; text-decoration:none; font-size:13px; font-weight:700;">➕ নতুন রিটার্ন</a>
        </div>
        <div class="ret-list">
            <?php if ($returns && $returns->num_rows > 0): while($r = $returns->fetch_assoc()): ?>
            <div class="ret-item">
                <div class="ret-left">
                    <div class="ret-id">#<?php echo (int)$r['id']; ?></div>
                    <div class="ret-inv"><?php echo t('invoice'); ?> #<?php echo (int)$r['sale_id']; ?></div>
                </div>
                <div class="ret-right">
                    <div class="ret-amount">৳<?php echo number_format((float)($r['total_return_amount'] ?? 0), 2); ?></div>
                    <div class="ret-date"><?php echo date('d-m-y', strtotime($r['return_date'])); ?></div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="ret-empty"><?php echo t('no_returns_found'); ?></div>
            <?php endif; ?>
        </div>
        <a href="../../index.php" class="ret-back"><?php echo t('back'); ?></a>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>