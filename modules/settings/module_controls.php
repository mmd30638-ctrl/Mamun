<?php
require_once '../../includes/config.php';
admin_only();
ame_module_control_ensure($db);

$catalog = ame_module_control_catalog();
$states = [];
$result = $db->query('SELECT module_key, enabled, reason, updated_at FROM module_controls');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $states[(string)$row['module_key']] = $row;
    }
}

include '../../partials/header.php';
?>
<style>
html,body,#main-content{background:#fff!important}
.mc-page{max-width:980px;margin:0 auto;padding:14px;color:#10233f}
.mc-hero{background:#fff;border:1px solid #bfdbfe;border-top:7px solid #1565c0;border-radius:18px;padding:18px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.08)}
.mc-hero h1{margin:0;color:#0b1e4d;font-size:clamp(21px,3vw,30px)}
.mc-hero p{margin:7px 0 0;color:#475569;line-height:1.6}
.mc-note{background:#fff7ed;border:1px solid #fdba74;color:#9a3412;border-radius:12px;padding:11px 13px;margin-top:12px}
.mc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}
.mc-card{background:#fff;border:1px solid #dbeafe;border-left:6px solid #0e8f52;border-radius:15px;padding:15px;box-shadow:0 5px 16px rgba(15,23,42,.06)}
.mc-card.off{border-left-color:#dc2626}
.mc-title{display:flex;justify-content:space-between;gap:10px;align-items:center}
.mc-title h2{font-size:17px;margin:0;color:#0b1e4d}
.mc-badge{border-radius:999px;padding:4px 9px;font-size:12px;font-weight:900;background:#dcfce7;color:#166534}
.mc-card.off .mc-badge{background:#fee2e2;color:#991b1b}
.mc-form{display:grid;gap:9px;margin-top:12px}
.mc-form textarea{width:100%;min-height:66px;resize:vertical;box-sizing:border-box;border:1.5px solid #bfdbfe;border-radius:10px;padding:10px;background:#fff;color:#10233f;font:inherit}
.mc-btn{border:0;border-radius:10px;padding:11px 13px;background:#dc2626;color:#fff;font-weight:900;cursor:pointer}
.mc-card.off .mc-btn{background:#0e8f52}
.mc-meta{font-size:12px;color:#64748b;margin-top:8px}
@media(max-width:520px){.mc-page{padding:10px}.mc-grid{grid-template-columns:1fr}}
</style>
<main class="mc-page">
    <section class="mc-hero">
        <h1>মডিউল সুরক্ষা ও অন/অফ</h1>
        <p>কোনো স্বাধীন মডিউলে সমস্যা হলে সেটি সাময়িক বন্ধ রাখুন। বিক্রয় বন্ধ করলে কাস্টমার বা স্টক মডিউল নিজে থেকে বন্ধ হবে না। অ্যাডমিন সমস্যা পরীক্ষা করার জন্য সব মডিউল খুলতে পারবেন।</p>
        <div class="mc-note">Authentication, database connection ও shared core এখানে বন্ধ করা যায় না—এসবের জন্য সিস্টেম হেলথ, error ID এবং recovery ব্যবস্থা ব্যবহার করুন।</div>
    </section>

    <?php if (isset($_GET['saved'])): ?>
        <div class="mc-note" style="background:#ecfdf5;border-color:#86efac;color:#166534">পরিবর্তন সংরক্ষিত হয়েছে।</div>
    <?php endif; ?>

    <section class="mc-grid">
        <?php foreach ($catalog as $key => $label):
            $state = $states[$key] ?? ['enabled' => 1, 'reason' => '', 'updated_at' => ''];
            $enabled = (int)($state['enabled'] ?? 1) === 1;
        ?>
        <article class="mc-card <?php echo $enabled ? '' : 'off'; ?>">
            <div class="mc-title">
                <h2><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="mc-badge"><?php echo $enabled ? 'চালু' : 'সাময়িক বন্ধ'; ?></span>
            </div>
            <form class="mc-form" method="post" action="process_module_control.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="module_key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                <textarea name="reason" maxlength="255" placeholder="বন্ধ করার কারণ বা রক্ষণাবেক্ষণ নোট"><?php echo htmlspecialchars((string)($state['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <button class="mc-btn" type="submit"><?php echo $enabled ? 'এই মডিউল সাময়িক বন্ধ করুন' : 'মডিউল আবার চালু করুন'; ?></button>
            </form>
            <?php if (!empty($state['updated_at'])): ?>
                <div class="mc-meta">শেষ পরিবর্তন: <?php echo htmlspecialchars((string)$state['updated_at'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
</main>
<?php include '../../partials/footer.php'; ?>
