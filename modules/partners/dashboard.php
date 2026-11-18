<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../partials/header.php';

$total_capital = get_total_business_capital($db);
$net_profit_alltime = get_partnership_net_profit($db);

$active_partners = $db->query("SELECT COUNT(*) c FROM partners WHERE status = 'active'")->fetch_assoc()['c'] ?? 0;

$pending = $db->query("SELECT COUNT(*) c FROM profit_allocations WHERE status = 'proposed'")->fetch_assoc()['c'] ?? 0;

// শতাংশের যোগফল ১০০ কিনা যাচাই (সতর্কতা দেখাতে)
$sum_share = $db->query("SELECT COALESCE(SUM(share_percentage),0) s FROM partners WHERE status = 'active'")->fetch_assoc()['s'] ?? 0;

$recent_activity = $db->query(
    "(SELECT 'মূলধন লেনদেন' as kind, transaction_date as d, amount, note FROM partner_capital_transactions ORDER BY id DESC LIMIT 5)
     UNION ALL
     (SELECT 'লাভ বণ্টন প্রস্তাব' as kind, created_at as d, net_profit as amount, period_label as note FROM profit_allocations ORDER BY id DESC LIMIT 5)
     ORDER BY d DESC LIMIT 8"
);
$recent_rows = $recent_activity ? $recent_activity->fetch_all(MYSQLI_ASSOC) : [];

$modules = [
    ['icon' => '👥', 'title' => 'Partners', 'sub' => 'পার্টনার প্রোফাইল ব্যবস্থাপনা', 'href' => '/modules/partners/index.php', 'color' => '#0B1E4D'],
    ['icon' => '💰', 'title' => 'Capital & Investments', 'sub' => 'মূলধন ও বিনিয়োগ', 'href' => '/modules/partners/capital/index.php', 'color' => '#0091EA'],
    ['icon' => '📈', 'title' => 'Profit Allocation', 'sub' => 'সময়কাল-ভিত্তিক লাভ বণ্টন', 'href' => '/modules/partners/profit-allocation/index.php', 'color' => '#0B8043'],
    ['icon' => '🏦', 'title' => 'Partner Accounts', 'sub' => 'Capital ও Current লেজার', 'href' => '/modules/partners/accounts/index.php', 'color' => '#7B1FA2'],
    ['icon' => '📄', 'title' => 'Statements', 'sub' => 'প্রিন্টযোগ্য স্টেটমেন্ট', 'href' => '/modules/partners/statements/index.php', 'color' => '#F57C00'],
    ['icon' => '📋', 'title' => 'Reports', 'sub' => 'ট্রেন্ড ও তুলনামূলক বিশ্লেষণ', 'href' => '/modules/partners/reports/index.php', 'color' => '#00838F'],
    ['icon' => '⚙️', 'title' => 'Settings', 'sub' => 'পণ্য/খরচ শেয়ারিং নিয়ন্ত্রণ', 'href' => '/modules/partners/settings/index.php', 'color' => '#455A64'],
];
?>
<style>
    .pd-wrap { padding: 15px; }
    .pd-wrap h2 { margin:0 0 16px; color:#0B1E4D; font-size:19px; }

    .pd-alert { background:#FFF3E0; border:1px solid #FFE0B2; color:#8D6E00; border-radius:12px; padding:12px 16px; font-size:12.5px; margin-bottom:16px; }
    .pd-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px; }
    .pd-summary-box { border-radius:14px; padding:16px; color:#fff; box-shadow:0 6px 16px rgba(11,30,77,0.18); }
    .pd-summary-box .lbl { font-size:11.5px; opacity:.85; }
    .pd-summary-box .val { font-size:20px; font-weight:800; margin-top:5px; }

    .pd-module-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; margin-bottom:24px; }
    .pd-module-card { background:#fff; border-radius:16px; padding:18px; text-decoration:none; box-shadow:0 4px 16px rgba(11,30,77,0.08); border:1px solid #EAF4FF; transition:transform .15s; display:block; }
    .pd-module-card:hover { transform:translateY(-2px); }
    .pd-module-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:10px; }
    .pd-module-title { font-weight:800; color:#0B1E4D; font-size:14px; }
    .pd-module-sub { font-size:11.5px; color:#64748B; margin-top:3px; }

    .pd-activity { background:#fff; border-radius:16px; padding:18px; box-shadow:0 4px 16px rgba(11,30,77,0.08); }
    .pd-activity h3 { margin:0 0 12px; color:#0B1E4D; font-size:14px; }
    .pd-activity-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .pd-activity-tag { background:#EAF4FF; color:#0B1E4D; padding:2px 8px; border-radius:10px; font-size:10.5px; font-weight:700; }
</style>

<div class="pd-wrap">
    <h2>🤝 পার্টনারশিপ ড্যাশবোর্ড</h2>

    <?php if ((float)$sum_share !== 100.0 && $active_partners > 0): ?>
        <div class="pd-alert">⚠️ সতর্কতা: সক্রিয় পার্টনারদের শেয়ার শতাংশের যোগফল <strong><?php echo number_format((float)$sum_share, 2); ?>%</strong> — ১০০% হওয়ার কথা। <a href="/modules/partners/index.php" style="color:#8D6E00; text-decoration:underline;">পার্টনার তালিকা থেকে ঠিক করুন</a>।</div>
    <?php endif; ?>

    <?php if ($pending > 0): ?>
        <div class="pd-alert" style="background:#E1F5FE; border-color:#B3E5FC; color:#0B1E4D;">🔔 <strong><?php echo (int)$pending; ?> টা</strong> লাভ বণ্টন প্রস্তাব অনুমোদনের অপেক্ষায় আছে। <a href="/modules/partners/profit-allocation/index.php" style="color:#0091EA; text-decoration:underline;">এখনই দেখুন</a>।</div>
    <?php endif; ?>

    <div class="pd-summary-grid">
        <div class="pd-summary-box" style="background:linear-gradient(135deg,#0B1E4D,#0091EA);"><div class="lbl">💰 মোট ব্যবসায়িক মূলধন</div><div class="val">৳<?php echo number_format($total_capital, 0); ?></div></div>
        <div class="pd-summary-box" style="background:linear-gradient(135deg,#0B8043,#2ECC71);"><div class="lbl">📊 সর্বমোট নিট লাভ</div><div class="val">৳<?php echo number_format($net_profit_alltime, 0); ?></div></div>
        <div class="pd-summary-box" style="background:linear-gradient(135deg,#7B1FA2,#AB47BC);"><div class="lbl">👥 সক্রিয় পার্টনার</div><div class="val"><?php echo (int)$active_partners; ?> জন</div></div>
        <div class="pd-summary-box" style="background:linear-gradient(135deg,#F57C00,#FFB74D);"><div class="lbl">⏳ অপেক্ষমাণ অনুমোদন</div><div class="val"><?php echo (int)$pending; ?> টা</div></div>
    </div>

    <div class="pd-module-grid">
        <?php foreach ($modules as $m): ?>
        <a href="<?php echo htmlspecialchars($m['href']); ?>" class="pd-module-card">
            <div class="pd-module-icon" style="background:<?php echo $m['color']; ?>1A; color:<?php echo $m['color']; ?>;"><?php echo $m['icon']; ?></div>
            <div class="pd-module-title"><?php echo htmlspecialchars($m['title']); ?></div>
            <div class="pd-module-sub"><?php echo htmlspecialchars($m['sub']); ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="pd-activity">
        <h3>🕒 সাম্প্রতিক কার্যক্রম</h3>
        <?php if (empty($recent_rows)): ?>
            <p style="text-align:center; color:#999; font-size:13px; padding:20px 0;">এখনো কোনো কার্যক্রম নেই।</p>
        <?php else: foreach ($recent_rows as $r): ?>
            <div class="pd-activity-row">
                <span><span class="pd-activity-tag"><?php echo htmlspecialchars($r['kind']); ?></span> <?php echo htmlspecialchars($r['note'] ?? ''); ?></span>
                <span style="font-weight:700; color:#0B1E4D;">৳<?php echo number_format((float)$r['amount'], 2); ?></span>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
