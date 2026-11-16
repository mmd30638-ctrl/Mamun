<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/supplier_settlement.php';
require_once '../../includes/supplier_due_ui.php';
require_once '../../includes/supplier_module_guard.php';
ame_supplier_module_view_boot();
ame_spay_ensure($db);
$spay = ame_spay_dashboard($db);
$spay_limit = ame_spay_approval_limit($db);
$spay_admin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';

ame_supplier_schema_boot($db);
$live = ame_supplier_live_sql($db);
$active_sql = ame_supplier_status_active_sql();
$kpi = $db->query("SELECT
        COUNT(*) AS total_sup,
        SUM(CASE WHEN {$active_sql} THEN 1 ELSE 0 END) AS active_sup,
        COALESCE(SUM(total_due), 0) AS total_due
    FROM suppliers WHERE {$live}")->fetch_assoc();

$month_purchase = $db->query("SELECT COALESCE(SUM(total_cost), 0) AS amt, COUNT(*) AS cnt
    FROM purchases WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc();

$top_due = $db->query("SELECT id, name, phone, total_due FROM suppliers WHERE {$live} AND total_due > 0 ORDER BY total_due DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

$top_volume = $db->query("SELECT s.id, s.name, COUNT(p.id) AS cnt, COALESCE(SUM(p.total_cost),0) AS amt
    FROM suppliers s JOIN purchases p ON p.supplier_id = s.id
    GROUP BY s.id, s.name ORDER BY amt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

$recent = $db->query("SELECT p.id, p.total_cost, p.created_at, s.name AS supplier_name, s.id AS supplier_id
    FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id
    ORDER BY p.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include '../../partials/header.php';
ame_supplier_due_page_boot(true, true);
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.sup-dash-wrap { padding: 15px; }
.sup-kpi-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 14px; margin-bottom: 18px;
}
.sup-kpi-card {
    background: var(--brand-white); border: 1px solid var(--brand-border); border-radius: var(--radius-lg);
    padding: 16px 18px; box-shadow: var(--shadow-sm);
}
.sup-kpi-card .sup-kpi-label { font-size: 12.5px; color: var(--brand-muted); font-weight: 600; margin-bottom: 6px; }
.sup-kpi-card .sup-kpi-value { font-size: 22px; font-weight: 800; color: var(--brand-navy); }
.sup-kpi-card.danger .sup-kpi-value { color: var(--brand-danger); }
.sup-kpi-card.success .sup-kpi-value { color: var(--brand-success); }
.sup-dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
.sup-mini-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid var(--brand-border);
}
.sup-mini-row:last-child { border-bottom: none; }
.sup-mini-name { font-weight: 700; color: var(--brand-navy); text-decoration: none; }
.sup-mini-sub { font-size: 12px; color: var(--brand-muted); }
.sup-mini-amt { font-weight: 700; }
.sup-mini-amt.danger { color: var(--brand-danger); }
.sup-mini-amt.success { color: var(--brand-success); }
.sup-empty-note { color: var(--brand-muted); text-align: center; padding: 16px 0; font-size: 13px; }

.sup-dash-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important;
    border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="sup-dash-wrap">
    <h3 style="color: var(--dark-blue); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <span>📊 সাপ্লায়ার ড্যাশবোর্ড</span>
        <a href="list.php" style="font-size:13px; color:var(--brand-accent); font-weight:600;">📋 তালিকা দেখুন →</a>
    </h3>

    <div class="sup-kpi-grid">
        <div class="sup-kpi-card">
            <div class="sup-kpi-label">মোট সাপ্লায়ার</div>
            <div class="sup-kpi-value"><?php echo (int)($kpi['total_sup'] ?? 0); ?></div>
        </div>
        <div class="sup-kpi-card success">
            <div class="sup-kpi-label">সক্রিয় সাপ্লায়ার</div>
            <div class="sup-kpi-value"><?php echo (int)($kpi['active_sup'] ?? 0); ?></div>
        </div>
        <?php $dash_net_due = round((float)($kpi['total_due'] ?? 0), 2); ?>
        <div class="sup-kpi-card <?php echo htmlspecialchars(ame_supplier_due_kpi_class($dash_net_due), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="sup-kpi-label">নিট বকেয়া (তালিকা যোগফল)</div>
            <div class="sup-kpi-value"><?php echo htmlspecialchars(ame_supplier_due_format_taka($dash_net_due), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="sup-kpi-card">
            <div class="sup-kpi-label">এই মাসের ক্রয়</div>
            <div class="sup-kpi-value"><?php echo number_format((float)($month_purchase['amt'] ?? 0), 2); ?> ৳</div>
            <div class="sup-mini-sub"><?php echo (int)($month_purchase['cnt'] ?? 0); ?>টি অর্ডার</div>
        </div>
    </div>

    <div class="sup-kpi-grid">
        <?php
        $spay_cards = [
            [ame_l('মোট প্রদেয়', 'Total payable'), $spay['payable']],
            [ame_l('পরিশোধিত', 'Paid'), $spay['paid']],
            [ame_l('বাকি', 'Remaining'), $spay['remaining']],
            [ame_l('মেয়াদোত্তীর্ণ', 'Overdue'), $spay['overdue']],
            [ame_l('আজকের বকেয়া', 'Due today'), $spay['due_today']],
            [ame_l('আসন্ন বকেয়া', 'Upcoming due'), $spay['upcoming']],
            [ame_l('মোট ক্রয়', 'Total purchases'), $spay['purchase']],
            [ame_l('ক্রয় ফেরত', 'Purchase returns'), $spay['returns']],
            [ame_l('অতিরিক্ত খরচ', 'Extra charges'), $spay['charges']],
            [ame_l('নিট প্রদেয়', 'Net payable'), $spay['net']],
        ];
        foreach ($spay_cards as $card):
        ?>
        <div class="sup-kpi-card">
            <div class="sup-kpi-label"><?php echo htmlspecialchars($card[0], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="sup-kpi-value"><?php echo number_format((float)$card[1], 2); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="sup-mini-sub" style="margin:0 0 8px;">
        <?php echo ame_l('শেষ পেমেন্ট', 'Last payment'); ?>:
        <?php if (!empty($spay['last_payment'])): ?>
            <?php echo htmlspecialchars((string)$spay['last_payment']['name'], ENT_QUOTES, 'UTF-8'); ?>
            · <?php echo number_format((float)$spay['last_payment']['amount'], 2); ?>
            · <?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$spay['last_payment']['at'])), ENT_QUOTES, 'UTF-8'); ?>
        <?php else: ?>—<?php endif; ?>
    </p>
    <p style="margin:0 0 14px;">
        <a href="pay.php" style="font-weight:800;"><?php echo ame_l('পেমেন্ট দিন', 'Make a payment'); ?></a>
        <?php if ($spay_admin): ?>
        <form method="post" action="settlement_action.php" style="display:inline-flex; gap:8px; align-items:center; margin-left:12px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="threshold">
            <label><?php echo ame_l('অনুমোদনের সীমা', 'Approval limit'); ?></label>
            <input type="number" name="limit" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$spay_limit, ENT_QUOTES, 'UTF-8'); ?>" style="width:120px; padding:6px;">
            <button type="submit"><?php echo ame_l('সেভ', 'Save'); ?></button>
        </form>
        <?php endif; ?>
    </p>

    <div class="sup-dash-grid">
        <div class="card">
            <h3>🔴 সর্বোচ্চ বকেয়া সাপ্লায়ার</h3>
            <?php if (!$top_due): ?>
                <div class="sup-empty-note">কোনো বকেয়া নেই — সব পরিশোধ করা আছে ✅</div>
            <?php else: foreach ($top_due as $s): ?>
                <div class="sup-mini-row">
                    <div>
                        <a href="profile.php?id=<?php echo (int)$s['id']; ?>" class="sup-mini-name"><?php echo htmlspecialchars($s['name']); ?></a>
                        <div class="sup-mini-sub"><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></div>
                    </div>
                    <div class="sup-mini-amt"><?php echo ame_supplier_due_render_inline((float)$s['total_due']); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h3>🏆 সর্বোচ্চ ক্রয়ের সাপ্লায়ার</h3>
            <?php if (!$top_volume): ?>
                <div class="sup-empty-note">এখনো কোনো ক্রয় রেকর্ড নেই।</div>
            <?php else: foreach ($top_volume as $s): ?>
                <div class="sup-mini-row">
                    <div>
                        <a href="profile.php?id=<?php echo (int)$s['id']; ?>" class="sup-mini-name"><?php echo htmlspecialchars($s['name']); ?></a>
                        <div class="sup-mini-sub"><?php echo (int)$s['cnt']; ?>টি অর্ডার</div>
                    </div>
                    <div class="sup-mini-amt success"><?php echo number_format((float)$s['amt'], 2); ?> ৳</div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card" style="grid-column: 1 / -1;">
            <h3>🕒 সাম্প্রতিক ক্রয়</h3>
            <?php if (!$recent): ?>
                <div class="sup-empty-note">এখনো কোনো ক্রয় রেকর্ড নেই।</div>
            <?php else: foreach ($recent as $r): ?>
                <div class="sup-mini-row">
                    <div>
                        <a href="profile.php?id=<?php echo (int)$r['supplier_id']; ?>" class="sup-mini-name"><?php echo htmlspecialchars($r['supplier_name'] ?? 'অজানা সাপ্লায়ার'); ?></a>
                        <div class="sup-mini-sub"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime((string)$r['created_at']))); ?></div>
                    </div>
                    <div class="sup-mini-amt"><?php echo number_format((float)$r['total_cost'], 2); ?> ৳</div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
