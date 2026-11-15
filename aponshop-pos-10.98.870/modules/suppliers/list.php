<?php
/**
 * সাপ্লায়ার তালিকা — শুধু পড়া। total_due ক্রয় / পেমেন্ট / অ্যাডমিন সংশোধন ছাড়া বদলায় না (Party Shield)।
 */
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/party_purge.php';
require_once '../../includes/supplier_due_ui.php';
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
$can_party_purge = function_exists('ame_party_purge_can') && ame_party_purge_can();

ame_supplier_schema_boot($db);

$q      = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$btype  = (string)($_GET['btype'] ?? '');
$show_purged = $status === 'purged';

$where  = [];
$params = [];
$types  = '';
$where[] = $show_purged ? 'purged_at IS NOT NULL' : ame_supplier_live_sql($db);

if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR business_name LIKE ? OR party_code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}
if ($status === '1') {
    $where[] = ame_supplier_status_active_sql();
} elseif ($status === '0') {
    $where[] = ame_supplier_status_inactive_sql();
}
if ($btype !== '') {
    $where[] = 'business_type = ?';
    $params[] = $btype;
    $types .= 's';
}

$sql = 'SELECT * FROM suppliers' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 500';
if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $suppliers = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$sum_payable = 0.0;
$sum_credit = 0.0;
foreach ($suppliers as $s) {
    $d = round((float)($s['total_due'] ?? 0), 2);
    if ($d > 0.004) {
        $sum_payable += $d;
    } elseif ($d < -0.004) {
        $sum_credit += abs($d);
    }
}

$ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.928';
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/supplier-list-pro.css?v=<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
<style>
.sup-list-pro .ame-wait-spin i {
    width: 28px !important; height: 28px !important;
    border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="sup-list-pro">
    <div class="card">
        <header class="sup-list-hero">
            <h1>সাপ্লায়ার তালিকা</h1>
            <div class="sup-links">
                <a href="statement.php">স্টেটমেন্ট</a>
                <a href="dashboard.php">ড্যাশবোর্ড</a>
            </div>
            <p class="sup-list-note">মোট বকেয়া = সাবেক + ক্রয় − পরিশোধ। সংখ্যা শুধু <strong>ক্রয়</strong>, <strong>সাপ্লায়ার পেমেন্ট</strong> বা <strong>অ্যাডমিন সংশোধন</strong> এ বদলায় — তালিকা খুললে নয়।</p>
            <p class="sup-list-shield" role="note">লক: Party Amount Shield — পেজ/আপডেট ZIP দিয়ে `total_due` স্বয়ংক্রিয় বদল হয় না।</p>
            <div class="sup-due-legend" role="list" aria-label="বকেয়ার রঙ ব্যাখ্যা">
                <span class="sup-due-legend-item" role="listitem">
                    <span class="sup-due-swatch sup-due-swatch--payable" aria-hidden="true"></span>
                    <span>কমলা · বকেয়া (দোকান দেবে)</span>
                </span>
                <span class="sup-due-legend-item" role="listitem">
                    <span class="sup-due-swatch sup-due-swatch--credit" aria-hidden="true"></span>
                    <span>সবুজ · অগ্রিম (দোকান পাবে)</span>
                </span>
            </div>
        </header>

        <div class="sup-kpi-row" aria-label="তালিকার সারাংশ">
            <div class="sup-kpi">
                <small>মোট বকেয়া (এই তালিকা)</small>
                <strong style="color:#ea580c;"><?php echo number_format($sum_payable, 2); ?> ৳</strong>
            </div>
            <div class="sup-kpi">
                <small>মোট অগ্রিম (এই তালিকা)</small>
                <strong style="color:#16a34a;"><?php echo number_format($sum_credit, 2); ?> ৳</strong>
            </div>
            <div class="sup-kpi">
                <small>সাপ্লায়ার</small>
                <strong><?php echo count($suppliers); ?></strong>
            </div>
        </div>

        <?php if ((string)($_GET['purged'] ?? '') === '1'): ?>
        <p style="margin:12px 20px 0;padding:10px 14px;border-radius:10px;background:#dcfce7;color:#166534;font-weight:700;">সাপ্লায়ার তালিকা থেকে সরানো হয়েছে। পুরনো ক্রয় ও লেজার আছে।</p>
        <?php endif; ?>

        <form method="GET" class="sup-toolbar" id="supSearchForm">
            <input type="text" name="q" id="supQ" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="নাম বা মোবাইল নম্বর..." autocomplete="off" aria-label="খুঁজুন">
            <select name="status" aria-label="স্ট্যাটাস">
                <option value="">— চালু তালিকা —</option>
                <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>সক্রিয়</option>
                <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                <option value="purged" <?php echo $show_purged ? 'selected' : ''; ?>>মুছে যাওয়া</option>
            </select>
            <select name="btype" aria-label="ব্যবসার ধরন">
                <option value="">— সব ব্যবসার ধরন —</option>
                <?php foreach (ame_supplier_business_types() as $k => $label): ?>
                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $btype === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="sup-add-btn sup-btn-ghost">খুঁজুন</button>
            <a href="profile.php?id=0" class="sup-add-btn">+ নতুন সাপ্লায়ার</a>
        </form>

        <div class="sup-table-wrap">
            <table class="sup-table">
                <thead>
                    <tr>
                        <th>নাম্বার</th>
                        <th>সাপ্লায়ার</th>
                        <th>ধরন</th>
                        <th>ফোন</th>
                        <th style="text-align:right;">মোট বকেয়া</th>
                        <th style="text-align:center;">স্ট্যাটাস</th>
                        <th style="text-align:center;">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$suppliers): ?>
                    <tr><td colspan="7" class="sup-empty"><?php echo $show_purged ? 'মুছে যাওয়া সাপ্লায়ার নেই।' : 'কোনো সাপ্লায়ার পাওয়া যায়নি।'; ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($suppliers as $s):
                        $purged = ame_supplier_is_purged($s);
                        $code = trim((string)($s['party_code'] ?? ''));
                        if ($code === '') {
                            $code = '#' . (int)$s['id'];
                        }
                        $due_amt = round((float)($s['total_due'] ?? 0), 2);
                        $due_cell = ame_supplier_due_render_cell($due_amt);
                        ?>
                    <tr
                        data-id="<?php echo (int)$s['id']; ?>"
                        data-name="<?php echo htmlspecialchars((string)($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-phone="<?php echo htmlspecialchars((string)($s['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-alt="<?php echo htmlspecialchars((string)($s['alt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <td data-label="নাম্বার"><span class="sup-code"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td data-label="সাপ্লায়ার">
                            <a href="profile.php?id=<?php echo (int)$s['id']; ?>" class="sup-name"><?php echo htmlspecialchars($s['name'] ?? ''); ?></a>
                            <?php if (!empty($s['business_name'])): ?><div class="sup-sub"><?php echo htmlspecialchars($s['business_name']); ?></div><?php endif; ?>
                        </td>
                        <td data-label="ধরন"><?php echo htmlspecialchars(ame_supplier_business_type_label($s['business_type'] ?? null)); ?></td>
                        <td data-label="ফোন"><?php echo htmlspecialchars($s['phone'] ?? ''); ?></td>
                        <td data-label="মোট বকেয়া"><?php echo $due_cell; ?></td>
                        <td data-label="স্ট্যাটাস" style="text-align:center;">
                            <?php if ($purged): ?>
                            <span class="sup-status off">মুছে যাওয়া</span>
                            <?php else: ?>
                            <?php $row_active = ame_supplier_status_enabled($s['status'] ?? null); ?>
                            <span class="sup-status <?php echo $row_active ? 'on' : 'off'; ?>">
                                <?php echo $row_active ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="অ্যাকশন" class="sup-actions">
                            <a href="profile.php?id=<?php echo (int)$s['id']; ?>" class="sup-act-view">প্রোফাইল</a>
                            <a href="pay.php?id=<?php echo (int)$s['id']; ?>" class="sup-act-pay">পেমেন্ট</a>
                            <a href="ledger.php?id=<?php echo (int)$s['id']; ?>">লেজার</a>
                            <a href="statement.php?id=<?php echo (int)$s['id']; ?>">স্টেটমেন্ট</a>
                            <?php if (!$purged && $can_party_purge): ?>
                            <button type="button" class="sup-act-del" data-ame-party-purge="supplier" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo htmlspecialchars((string)($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-action="process_purge.php">চিরতরে মুছুন</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('supQ');
    var tbody = document.querySelector('.sup-table tbody');
    if (!input || !tbody) return;

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var needleDigits = digits(q);
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-name]'));
        rows.forEach(function (row) {
            var name = (row.getAttribute('data-name') || '').toLowerCase();
            var phone = row.getAttribute('data-phone') || '';
            var alt = row.getAttribute('data-alt') || '';
            var ok = !q
                || name.indexOf(q) !== -1
                || phone.toLowerCase().indexOf(q) !== -1
                || alt.toLowerCase().indexOf(q) !== -1
                || (needleDigits && (digits(phone).indexOf(needleDigits) !== -1 || digits(alt).indexOf(needleDigits) !== -1));
            row.hidden = !ok;
        });
    });
})();
</script>
<?php
if (function_exists('ame_party_purge_assets')) {
    ame_party_purge_assets();
}
include '../../partials/footer.php';
?>