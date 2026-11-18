<?php
require_once '../../includes/config.php';
require_once '../../includes/schema_sync.php';
admin_only();

$report = $_SESSION['schema_sync_report'] ?? null;
unset($_SESSION['schema_sync_report']);
$status = ame_schema_status($db);
$mod_labels = ame_schema_modules();

include '../../partials/header.php';
?>
<style>
.sch-wrap{max-width:920px;margin:0 auto;padding:16px 14px 40px;}
.sch-hero{background:linear-gradient(135deg,#0B1E4D,#1a3a7a);color:#fff;border-radius:18px;padding:22px 20px;margin-bottom:16px;}
.sch-hero h2{margin:0 0 6px;font-size:1.35rem;}
.sch-hero p{margin:0;opacity:.9;font-size:14px;line-height:1.55;}
.sch-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;margin-bottom:14px;}
.sch-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
.sch-stat{background:#f8fafc;border-radius:12px;padding:14px;text-align:center;}
.sch-stat b{display:block;font-size:1.4rem;color:#0B1E4D;}
.sch-stat span{font-size:12px;color:#64748b;}
.sch-ok{color:#15803d;}
.sch-warn{color:#b45309;}
.sch-list{font-size:13px;line-height:1.6;max-height:220px;overflow:auto;background:#f8fafc;border-radius:10px;padding:10px 12px;}
.sch-btn{display:block;width:100%;border:0;cursor:pointer;background:linear-gradient(135deg,#0B1E4D,#1565c0);color:#fff;font-weight:700;padding:14px;border-radius:12px;font-size:15px;}
.sch-note{font-size:13px;color:#475569;margin:10px 0 0;}
.sch-pill{display:inline-block;background:#e8f5e9;color:#2e7d32;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;margin:2px;}
.sch-pill.miss{background:#fff7ed;color:#c2410c;}
@media(max-width:640px){.sch-stats{grid-template-columns:1fr;}}
</style>
<div class="sch-wrap">
    <div class="sch-hero">
        <h2>ডাটাবেজ স্কিমা সিঙ্ক</h2>
        <p>যা আছে সেটা থাকবে। যা নেই সেটা যোগ হবে। কোনো টেবিল বা সারি মুছে যায় না। সব PHP মডিউলের টেবিল একবারে মিলিয়ে নেয়।</p>
    </div>

    <?php if ($report): ?>
    <div class="sch-card" style="border-color:#86efac;background:#f0fdf4;">
        <h3 style="margin:0 0 8px;">সিঙ্ক সম্পন্ন</h3>
        <p style="margin:0 0 8px;font-size:14px;">নতুন টেবিল: <b><?php echo count($report['created_tables']); ?></b> · নতুন কলাম: <b><?php echo count($report['added_columns']); ?></b> · আগে থেকেই ছিল: <b><?php echo count($report['kept_tables']); ?></b></p>
        <?php if ($report['created_tables']): ?>
            <p style="font-size:13px;margin:8px 0 4px;">যোগ হওয়া টেবিল</p>
            <?php foreach ($report['created_tables'] as $t): ?><span class="sch-pill"><?php echo esc_html($t); ?></span><?php endforeach; ?>
        <?php endif; ?>
        <?php if ($report['added_columns']): ?>
            <p style="font-size:13px;margin:12px 0 4px;">যোগ হওয়া কলাম</p>
            <div class="sch-list"><?php echo esc_html(implode(', ', $report['added_columns'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($report['errors'])): ?>
            <p style="font-size:13px;margin:12px 0 4px;color:#b91c1c;">সতর্কতা</p>
            <div class="sch-list"><?php echo esc_html(implode("\n", $report['errors'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($report['ensure_fail'])): ?>
            <p style="font-size:13px;margin:12px 0 4px;color:#b45309;">মডিউল প্যাচ</p>
            <div class="sch-list"><?php echo esc_html(implode("\n", $report['ensure_fail'])); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="sch-stats">
        <div class="sch-stat"><b><?php echo (int)$status['expected']; ?></b><span>প্রয়োজনীয় টেবিল</span></div>
        <div class="sch-stat"><b class="sch-ok"><?php echo count($status['present']); ?></b><span>এখন আছে</span></div>
        <div class="sch-stat"><b class="<?php echo $status['missing'] ? 'sch-warn' : 'sch-ok'; ?>"><?php echo count($status['missing']); ?></b><span>এখনো নেই</span></div>
    </div>

    <div class="sch-card">
        <form action="process_schema_sync.php" method="POST" onsubmit="return confirm('বিদ্যমান ডাটা মুছবে না। শুধু অনুপস্থিত টেবিল ও কলাম যোগ হবে। চালাবেন?');">
            <?php echo csrf_field(); ?>
            <button type="submit" class="sch-btn">স্কিমা মিলিয়ে নিন</button>
        </form>
        <p class="sch-note">ZIP আপডেটের পর একবার চাপলেই হয়। আবার চাপলে ক্ষতি নেই — যা আছে সেটাই থাকবে।</p>
        <p style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 0;">
            <a href="download_schema_sql.php" class="sch-btn" style="width:auto;padding:12px 16px;text-decoration:none;background:linear-gradient(135deg,#0D47A1,#1565c0);">SQL ফাইল ডাউনলোড</a>
            <a href="view_schema_sql.php" class="sch-btn" style="width:auto;padding:12px 16px;text-decoration:none;background:#fff;color:#0B1E4D;border:1px solid #cbd5e1;">সরাসরি টেবিল কোড দেখুন</a>
        </p>
        <p class="sch-note">phpMyAdmin-এ <b>Import</b> দিয়ে ফাইল তুলুন — SQL ট্যাবে পেস্ট করবেন না (মোবাইলে লাইন ভেঙে `--` কমেন্ট পুরো CREATE খেয়ে ফেলে)। CREATE IF NOT EXISTS + অনুপস্থিত কলাম ADD। ড্রপ নেই। যে টেবিল আগে তৈরি হয়েছে সেগুলো থাকবে; আবার Import চালালে বাকিগুলো যোগ হয়।</p>
    </div>

    <?php if ($status['missing']): ?>
    <div class="sch-card">
        <h4 style="margin:0 0 8px;">এখনো নেই</h4>
        <?php foreach ($status['missing'] as $t): ?><span class="sch-pill miss"><?php echo esc_html($t); ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sch-card">
        <h4 style="margin:0 0 8px;">মডিউল অনুযায়ী টেবিল</h4>
        <?php
        $groups = ame_schema_overlay_tables();
        foreach (ame_schema_base_tables() as $name => $def) {
            $m = $def['module'];
            $groups[$m] = $groups[$m] ?? [];
            if (!in_array($name, $groups[$m], true)) {
                $groups[$m][] = $name;
            }
        }
        foreach ($groups as $mod => $tables):
            sort($tables);
        ?>
            <p style="margin:10px 0 4px;font-size:13px;font-weight:700;color:#0B1E4D;"><?php echo esc_html($mod_labels[$mod] ?? $mod); ?></p>
            <div class="sch-list"><?php echo esc_html(implode(', ', $tables)); ?></div>
        <?php endforeach; ?>
    </div>

    <a href="tools.php" style="display:block;text-align:center;margin-top:8px;color:#0B1E4D;font-weight:700;text-decoration:none;">← সিস্টেম টুলস</a>
</div>
<?php include '../../partials/footer.php'; ?>
