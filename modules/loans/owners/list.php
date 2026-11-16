<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$q      = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(owner_name LIKE ? OR mobile LIKE ? OR shop_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($status === '1' || $status === '0') {
    $where[] = 'status = ?';
    $params[] = (int)$status;
    $types .= 'i';
}

$sql = "SELECT so.*,
    (SELECT COUNT(*) FROM owner_loans ol WHERE ol.owner_id = so.id AND ol.status IN ('approved','active','overdue')) active_loans,
    (SELECT COALESCE(SUM(outstanding_amount),0) FROM owner_loans ol WHERE ol.owner_id = so.id) outstanding,
    (SELECT COALESCE(SUM(current_balance),0) FROM owner_savings_accounts osa WHERE osa.owner_id = so.id) savings
    FROM shop_owners so" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY so.id DESC LIMIT 500';

if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $owners = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-page-wrap { padding: 15px; }
.oln-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; }
.oln-toolbar input, .oln-toolbar select {
    padding:10px 12px; border:1.5px solid var(--brand-border); border-radius:8px; font-size:13.5px; background: var(--brand-white);
}
.oln-toolbar input[type="text"] { flex:1; min-width:180px; }
.oln-add-btn {
    display:inline-flex; align-items:center; gap:6px; background: var(--brand-gradient); color: var(--brand-white) !important;
    padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; white-space:nowrap;
}
.oln-table-wrap { overflow-x:auto; }
.oln-table { width:100%; border-collapse: collapse; text-align:left; }
.oln-table thead tr { background: var(--brand-navy); color: var(--brand-white); }
.oln-table th { padding:12px; font-size:13px; }
.oln-table td { padding:12px; border-bottom:1px solid var(--brand-border); font-size:13.5px; }
.oln-name { font-weight:700; color: var(--brand-navy); }
.oln-sub { color: var(--brand-muted); font-size:12px; }
.oln-due { color: var(--brand-danger); font-weight:700; text-align:right; }
.oln-save { color: var(--brand-success); font-weight:700; text-align:right; }
.oln-status { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11.5px; font-weight:700; }
.oln-status.on { background: var(--brand-success-soft); color: var(--brand-success); }
.oln-status.off { background: var(--brand-danger-soft); color: var(--brand-danger); }
.oln-actions a {
    display:inline-block; text-decoration:none; border:1px solid var(--brand-border);
    padding:4px 9px; border-radius:6px; font-size:12px; margin:2px; color: var(--brand-accent);
}
.oln-empty { text-align:center; padding:30px 0; color: var(--brand-muted); }

.oln-page-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-page-wrap">
    <div class="card" style="padding:0; overflow:visible;">
        <div style="padding:18px 18px 0;">
            <h3 style="color: var(--brand-navy); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <span>🏪 <?php echo te('nav_oloan_owners'); ?></span>
                <a href="dashboard.php" style="font-size:13px; color:var(--brand-accent); font-weight:600;">📊 <?php echo ame_l('ড্যাশবোর্ড দেখুন', 'View dashboard'); ?> →</a>
            </h3>
            <form method="GET" class="oln-toolbar">
                <input type="text" name="q" value="<?php echo esc_html($q); ?>" placeholder="<?php echo ame_l('নাম, মোবাইল বা দোকানের নাম দিয়ে খুঁজুন...', 'Search by name, mobile or shop name...'); ?>">
                <select name="status">
                    <option value=""><?php echo ame_l('সব অবস্থা', 'All status'); ?></option>
                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>><?php echo ame_l('সক্রিয়', 'Active'); ?></option>
                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>><?php echo ame_l('নিষ্ক্রিয়', 'Inactive'); ?></option>
                </select>
                <button type="submit" class="oln-add-btn" style="background:var(--brand-navy);"><?php echo ame_l('খুঁজুন', 'Search'); ?></button>
                <a href="add.php" class="oln-add-btn">+ <?php echo ame_l('নতুন মালিক', 'New owner'); ?></a>
            </form>
        </div>
        <div class="oln-table-wrap">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('মালিক', 'Owner'); ?></th>
                <th><?php echo ame_l('দোকান', 'Shop'); ?></th>
                <th><?php echo ame_l('মোবাইল', 'Mobile'); ?></th>
                <th><?php echo ame_l('সক্রিয় ঋণ', 'Active loans'); ?></th>
                <th><?php echo ame_l('বকেয়া', 'Outstanding'); ?></th>
                <th><?php echo ame_l('সঞ্চয়', 'Savings'); ?></th>
                <th><?php echo ame_l('অবস্থা', 'Status'); ?></th>
                <th><?php echo ame_l('অ্যাকশন', 'Action'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$owners): ?>
                <tr><td colspan="8" class="oln-empty"><?php echo ame_l('কোনো মালিক পাওয়া যায়নি।', 'No owners found.'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($owners as $o): ?>
                <tr>
                    <td><span class="oln-name"><?php echo esc_html($o['owner_name']); ?></span></td>
                    <td><?php echo esc_html($o['shop_name']); ?><br><span class="oln-sub"><?php echo esc_html($o['shop_type']); ?></span></td>
                    <td><?php echo esc_html($o['mobile']); ?></td>
                    <td><?php echo (int)$o['active_loans']; ?></td>
                    <td class="oln-due">৳<?php echo number_format((float)$o['outstanding'], 2); ?></td>
                    <td class="oln-save">৳<?php echo number_format((float)$o['savings'], 2); ?></td>
                    <td><span class="oln-status <?php echo (int)$o['status'] === 1 ? 'on' : 'off'; ?>"><?php echo (int)$o['status'] === 1 ? ame_l('সক্রিয়', 'Active') : ame_l('নিষ্ক্রিয়', 'Inactive'); ?></span></td>
                    <td class="oln-actions">
                        <a href="profile.php?id=<?php echo (int)$o['id']; ?>"><?php echo ame_l('প্রোফাইল', 'Profile'); ?></a>
                        <a href="add.php?id=<?php echo (int)$o['id']; ?>"><?php echo ame_l('এডিট', 'Edit'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
