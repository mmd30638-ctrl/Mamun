<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/party_purge.php';
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

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.sup-page-wrap { padding: 15px; }
.sup-toolbar {
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
    margin-bottom: 16px;
}
.sup-toolbar input, .sup-toolbar select {
    padding: 10px 12px; border: 1.5px solid var(--brand-border); border-radius: 8px;
    font-size: 13.5px; background: var(--brand-white);
}
.sup-toolbar input[type="text"] { flex: 1; min-width: 180px; }
.sup-add-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--brand-gradient); color: var(--brand-white) !important;
    padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13.5px;
    white-space: nowrap;
}
.sup-table-wrap { overflow-x: auto; }
.sup-table { width: 100%; border-collapse: collapse; text-align: left; }
.sup-table thead tr { background: var(--brand-navy); color: var(--brand-white); }
.sup-table th { padding: 12px; font-size: 13px; }
.sup-table td { padding: 12px; border-bottom: 1px solid var(--brand-border); font-size: 13.5px; }
.sup-name { font-weight: 700; color: var(--brand-navy); }
.sup-sub { color: var(--brand-muted); font-size: 12px; }
.sup-due { font-weight: 700; text-align: right; }
.sup-due--owe { color: #b91c1c !important; -webkit-text-fill-color: #b91c1c !important; }
.sup-due--recv { color: #1d4ed8 !important; -webkit-text-fill-color: #1d4ed8 !important; }
.sup-due--zero { color: var(--brand-muted) !important; -webkit-text-fill-color: var(--brand-muted) !important; font-weight: 600; }
.sup-due-legend { margin: 0 0 12px; font-size: 12.5px; color: var(--brand-muted); display: flex; flex-wrap: wrap; gap: 12px 18px; }
.sup-due-legend span { font-weight: 700; }
.sup-due-legend .lg-owe { color: #b91c1c; }
.sup-due-legend .lg-recv { color: #1d4ed8; }
.sup-status { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
.sup-status.on { background: var(--brand-success-soft); color: var(--brand-success); }
.sup-status.off { background: var(--brand-danger-soft); color: var(--brand-danger); }
.sup-actions a {
    display: inline-block; text-decoration: none; border: 1px solid var(--brand-border);
    padding: 4px 9px; border-radius: 6px; font-size: 12px; margin: 2px; color: var(--brand-accent);
}
.sup-actions a.sup-act-pay { color: var(--brand-success); }
.sup-actions a.sup-act-view { color: var(--brand-gold); }
.sup-actions button.sup-act-del {
    display: inline-block; border: 1px solid var(--brand-danger); background: var(--brand-white);
    padding: 4px 9px; border-radius: 6px; font-size: 12px; margin: 2px; color: var(--brand-danger);
    cursor: pointer; font-weight: 700;
}
.sup-empty { text-align: center; padding: 30px 0; color: var(--brand-muted); }

/* স্মরণিকা নীতি ২: এই পেজের লোডিং/স্পিনার এখন সাধারণ ঘোরানো রিং নয়,
   দোকানের লোগো ঘুরিয়ে দেখানো হয় — সাইটজুড়ে ফর্ম সাবমিটে অটো-লাগা
   .ame-wait-spin রিংকে এই পেজের ভেতরেই লোগোতে বদলে দেওয়া হলো। */
.sup-page-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important;
    border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="sup-page-wrap">
    <div class="card" style="padding:0; overflow:visible;">
        <div style="padding:18px 18px 0;">
            <h3 style="color: var(--dark-blue); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <span>📋 সাপ্লায়ার তালিকা</span>
                <span style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <a href="statement.php" style="font-size:13px; color:var(--brand-accent); font-weight:600;">🏦 স্টেটমেন্ট →</a>
                    <a href="dashboard.php" style="font-size:13px; color:var(--brand-accent); font-weight:600;">📊 ড্যাশবোর্ড দেখুন →</a>
                </span>
            </h3>
            <p style="margin:0 0 8px;font-size:13px;color:var(--brand-muted);">মোট বকেয়া = সাবেক + ক্রয় − পরিশোধ। চিরতরে মুছলে ক্রয়-লেজার থাকে; পরে নতুন নাম্বার দিয়ে আবার যোগ করা যায়।</p>
            <p class="sup-due-legend" aria-hidden="false">
                <span><span class="lg-owe">লাল</span> — দোকান সাপ্লায়ারকে দেবে (পাওনা)</span>
                <span><span class="lg-recv">নীল</span> — সাপ্লায়ারের কাছে দোকানের অগ্রিম/পাওনা</span>
            </p>
            <?php if ((string)($_GET['purged'] ?? '') === '1'): ?>
            <p style="padding:10px 14px;border-radius:8px;background:var(--brand-success-soft);color:var(--brand-success);font-weight:700;">সাপ্লায়ার তালিকা থেকে সরানো হয়েছে। পুরনো ক্রয় ও লেজার আছে।</p>
            <?php endif; ?>
            <form method="GET" class="sup-toolbar" id="supSearchForm">
                <input type="text" name="q" id="supQ" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="নাম বা মোবাইল নম্বর লিখুন..." autocomplete="off">
                <div id="supFront" class="sup-front" hidden></div>
                <select name="status">
                    <option value="">— চালু তালিকা —</option>
                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>সক্রিয়</option>
                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>নিষ্ক্রিয়</option>
                    <option value="purged" <?php echo $show_purged ? 'selected' : ''; ?>>মুছে যাওয়া</option>
                </select>
                <select name="btype">
                    <option value="">— সব ব্যবসার ধরন —</option>
                    <?php foreach (ame_supplier_business_types() as $k => $label): ?>
                    <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $btype === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="sup-add-btn" style="background:var(--brand-bg-soft); color:var(--brand-navy) !important; border:1.5px solid var(--brand-border);">🔍 খুঁজুন</button>
                <a href="profile.php?id=0" class="sup-add-btn">➕ নতুন সাপ্লায়ার</a>
            </form>
        </div>
        <div class="sup-table-wrap">
            <table class="sup-table">
                <thead>
                    <tr>
                        <th>নাম্বার</th>
                        <th>সাপ্লায়ারের নাম</th>
                        <th>ব্যবসার ধরন</th>
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
                    ?>
                    <tr
                        data-id="<?php echo (int)$s['id']; ?>"
                        data-name="<?php echo htmlspecialchars((string)($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-phone="<?php echo htmlspecialchars((string)($s['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-alt="<?php echo htmlspecialchars((string)($s['alt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <td><b><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></b></td>
                        <td>
                            <a href="profile.php?id=<?php echo (int)$s['id']; ?>" class="sup-name" style="text-decoration:none;"><?php echo htmlspecialchars($s['name'] ?? ''); ?></a>
                            <?php if (!empty($s['business_name'])): ?><div class="sup-sub"><?php echo htmlspecialchars($s['business_name']); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(ame_supplier_business_type_label($s['business_type'] ?? null)); ?></td>
                        <td><?php echo htmlspecialchars($s['phone'] ?? ''); ?></td>
                        <?php
                        $due_amt = round((float)($s['total_due'] ?? 0), 2);
                        if ($due_amt < -0.004) {
                            $due_cls = 'sup-due sup-due--recv';
                        } elseif ($due_amt > 0.004) {
                            $due_cls = 'sup-due sup-due--owe';
                        } else {
                            $due_cls = 'sup-due sup-due--zero';
                        }
                        ?>
                        <td class="<?php echo htmlspecialchars($due_cls, ENT_QUOTES, 'UTF-8'); ?>"><?php echo number_format($due_amt, 2); ?> ৳</td>
                        <td style="text-align:center;">
                            <?php if ($purged): ?>
                            <span class="sup-status off">মুছে যাওয়া</span>
                            <?php else: ?>
                            <?php $row_active = ame_supplier_status_enabled($s['status'] ?? null); ?>
                            <span class="sup-status <?php echo $row_active ? 'on' : 'off'; ?>">
                                <?php echo $row_active ? 'সক্রিয়' : 'নিষ্ক্রিয়'; ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="sup-actions" style="text-align:center; white-space:nowrap;">
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
    var front = document.getElementById('supFront');
    var tbody = document.querySelector('.sup-table tbody');
    if (!input || !front || !tbody) return;
    var timer = 0;

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function paint(list) {
        front.innerHTML = '';
        if (!list.length) {
            front.hidden = true;
            return;
        }
        front.hidden = false;
        list.forEach(function (person) {
            var link = document.createElement('a');
            link.href = 'profile.php?id=' + person.id;
            var name = document.createElement('span');
            name.className = 'sup-front-name';
            name.textContent = person.name || '';
            var phone = document.createElement('span');
            phone.className = 'sup-front-phone';
            phone.textContent = person.phone || person.alt || '';
            link.appendChild(name);
            link.appendChild(phone);
            front.appendChild(link);
        });
    }

    function filterRows(q) {
        var needle = q.toLowerCase();
        var needleDigits = digits(q);
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-name]'));
        var hits = [];
        rows.forEach(function (row) {
            var name = (row.getAttribute('data-name') || '').toLowerCase();
            var phone = row.getAttribute('data-phone') || '';
            var alt = row.getAttribute('data-alt') || '';
            var ok = !needle
                || name.indexOf(needle) !== -1
                || phone.toLowerCase().indexOf(needle) !== -1
                || alt.toLowerCase().indexOf(needle) !== -1
                || (needleDigits && (digits(phone).indexOf(needleDigits) !== -1 || digits(alt).indexOf(needleDigits) !== -1));
            row.hidden = !ok;
            if (ok && needle) hits.push(row);
        });
        hits.sort(function (a, b) {
            function rank(row) {
                var name = (row.getAttribute('data-name') || '').toLowerCase();
                var phone = digits(row.getAttribute('data-phone') || '');
                if (name.indexOf(needle) === 0 || (needleDigits && phone.indexOf(needleDigits) === 0)) return 0;
                return 1;
            }
            return rank(a) - rank(b);
        });
        hits.forEach(function (row) { tbody.appendChild(row); });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        filterRows(q);
        clearTimeout(timer);
        if (q.length < 1) {
            paint([]);
            return;
        }
        timer = setTimeout(function () {
            fetch('search_json.php?q=' + encodeURIComponent(q), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (res) { return res.json(); })
                .then(function (list) { paint(Array.isArray(list) ? list : []); })
                .catch(function () { paint([]); });
        }, 160);
    });
})();
</script>
<?php
if (function_exists('ame_party_purge_assets')) {
    ame_party_purge_assets();
}
include '../../partials/footer.php';
?>
