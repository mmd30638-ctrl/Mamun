<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);

/* ব্যালেন্স দৃশ্যতা: অ্যাডমিন/মালিক আসল টাকা দেখবে, অন্যরা মাস্ক। */
$ame_xf_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$ame_xf_role_norm = str_replace([' ', '_', '-'], '', $ame_xf_role);
$ame_xf_is_admin = in_array($ame_xf_role_norm, ['admin', 'owner', 'superadmin', 'manager', 'proprietor', 'administrator', 'এডমিন', 'মালিক'], true)
    || (int)($_SESSION['user_id'] ?? 0) === 1;
$ame_xf_bal_text = static function ($amount) use ($ame_xf_is_admin): string {
    if ($ame_xf_is_admin) {
        return '৳' . number_format((float)$amount, 2);
    }
    return ame_balance_label((float)$amount);
};
$ame_xf_bal_num = static function ($amount) use ($ame_xf_is_admin) {
    if ($ame_xf_is_admin) {
        return (float)$amount;
    }
    return ame_balance_number_masked($amount);
};

$flash_ok = (string)($_GET['ok'] ?? '') === '1';
$flash_err = (string)($_GET['err'] ?? '');
$flash_amt = (string)($_GET['amt'] ?? '');
$flash_from = (string)($_GET['from'] ?? '');
$flash_to = (string)($_GET['to'] ?? '');
$flash_from_now = (string)($_GET['from_now'] ?? '');
$flash_to_now = (string)($_GET['to_now'] ?? '');

$accounts = ame_active_accounts($db);
$xf_from_id = (int)($accounts[0]['id'] ?? 0);
$xf_to_id = 0;
foreach ($accounts as $xf_row) {
    $xf_id = (int)($xf_row['id'] ?? 0);
    if ($xf_id > 0 && $xf_id !== $xf_from_id) {
        $xf_to_id = $xf_id;
        break;
    }
}

$recent = [];
$rq = $db->query(
    "SELECT al.amount, al.note, al.created_at, al.balance_after,
            a.account_name AS from_name, b.account_name AS to_name
     FROM account_ledger al
     LEFT JOIN accounts a ON a.id = al.account_id
     LEFT JOIN accounts b ON b.id = al.ref_id
     WHERE al.ref_type = 'transfer' AND al.type = 'debit'
     ORDER BY al.id DESC
     LIMIT 12"
);
if ($rq) {
    while ($row = $rq->fetch_assoc()) {
        $recent[] = $row;
    }
}

$threshold_raw = (string)get_app_setting($db, 'transfer_approval_threshold', '');
$pin_hash = (string)get_app_setting($db, 'transfer_approval_pin_hash', '');
$pin_needed = ($threshold_raw !== '' && $pin_hash !== '');
$pin_threshold = $pin_needed ? (float)$threshold_raw : 0.0;

$type_label = static function (string $type): string {
    return match (strtolower(trim($type))) {
        'bank' => 'ব্যাংক',
        'mobile', 'mfs', 'bkash', 'nagad' => 'মোবাইল ব্যাংকিং',
        default => 'ক্যাশ',
    };
};
$xf_option = static function (array $rows, int $selected, int $skip) use ($ame_xf_bal_num, $ame_xf_bal_text, $type_label): void {
    foreach ($rows as $a) {
        $id = (int)($a['id'] ?? 0);
        if ($id < 1 || $id === $skip) {
            continue;
        }
        $type = $type_label((string)($a['account_type'] ?? 'cash'));
        echo '<option value="' . $id . '"'
            . ' data-bal="' . htmlspecialchars((string)$ame_xf_bal_num($a['balance']), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
            . ($id === $selected ? ' selected' : '')
            . '>'
            . htmlspecialchars((string)$a['account_name'], ENT_QUOTES, 'UTF-8')
            . ' · ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8')
            . ' · ' . $ame_xf_bal_text($a['balance'])
            . '</option>';
    }
};

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/transfer-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="xf-wrap">
    <header class="xf-hero">
        <p class="xf-kicker">ক্যাশ ও ব্যাংক</p>
        <h1>অ্যাকাউন্ট ট্রান্সফার</h1>
        <p>এক খাত থেকে অন্য খাতে টাকা সরান। উৎস থেকে কাটা হবে, গন্তব্যে জমা হবে — ইউনিট ৳।</p>
        <div class="xf-links">
            <a href="list.php">হিসাব তালিকা</a>
            <a href="cash_book.php">ক্যাশ বুক</a>
        </div>
    </header>

    <?php if ($flash_ok): ?>
        <div class="xf-flash ok" role="status">ট্রান্সফার হয়েছে। ৳<?php echo htmlspecialchars($flash_amt, ENT_QUOTES, 'UTF-8'); ?>
            — <?php echo htmlspecialchars($flash_from, ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars($flash_to, ENT_QUOTES, 'UTF-8'); ?>।
            এখন উৎস ৳<?php echo htmlspecialchars($flash_from_now, ENT_QUOTES, 'UTF-8'); ?>, গন্তব্য ৳<?php echo htmlspecialchars($flash_to_now, ENT_QUOTES, 'UTF-8'); ?>।
        </div>
    <?php elseif ($flash_err !== ''): ?>
        <div class="xf-flash err" role="alert">❌ <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (count($accounts) < 2): ?>
        <section class="xf-empty">
            <strong>ট্রান্সফারের জন্য দুটি সক্রিয় অ্যাকাউন্ট লাগে</strong>
            <p>ক্যাশ, ব্যাংক, মোবাইল বা অন্য সক্রিয় খাত থেকে দুটি অ্যাকাউন্ট যোগ করুন। তারপর এখানে টাকা সরানো যাবে।</p>
            <a href="add.php">+ নতুন অ্যাকাউন্ট</a>
        </section>
    <?php else: ?>
        <form class="xf-form" action="process_transfer.php" method="POST" data-ame-once="1" id="xfForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="xf-pair">
                <div class="xf-box xf-from">
                    <span class="xf-box-kicker">উৎস</span>
                    <span class="xf-box-title">যেখান থেকে কাটা হবে</span>
                    <label class="xf-field" for="from_account">
                        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="from_account" id="from_account" required<?php echo $ame_xf_is_admin ? '' : ' data-masked="1"'; ?>>
                            <?php $xf_option($accounts, $xf_from_id, 0); ?>
                        </select>
                    </label>
                    <span class="xf-bal">সাবেক <b id="fromBal">৳0.00</b></span>
                    <span class="xf-after">ট্রান্সফারের পর <b id="fromAfter">৳0.00</b></span>
                </div>

                <button type="button" class="xf-swap" id="xfSwap" aria-label="অ্যাকাউন্ট অদলবদল">⇄</button>

                <div class="xf-box xf-to">
                    <span class="xf-box-kicker">গন্তব্য</span>
                    <span class="xf-box-title">যেখানে জমা হবে</span>
                    <label class="xf-field" for="to_account">
                        <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="to_account" id="to_account" required<?php echo $ame_xf_is_admin ? '' : ' data-masked="1"'; ?>>
                            <?php $xf_option($accounts, $xf_to_id, $xf_from_id); ?>
                        </select>
                    </label>
                    <span class="xf-bal">সাবেক <b id="toBal">৳0.00</b></span>
                    <span class="xf-after">ট্রান্সফারের পর <b id="toAfter">৳0.00</b></span>
                </div>
            </div>

            <label class="xf-amt-lab" for="xfAmount">পরিমাণ</label>
            <div class="xf-amt">
                <span class="xf-unit">৳</span>
                <input id="xfAmount" name="amount" type="number" inputmode="decimal" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="xf-chips" role="group" aria-label="দ্রুত পরিমাণ">
                <button type="button" data-amt="500">৳500</button>
                <button type="button" data-amt="1000">৳1,000</button>
                <button type="button" data-amt="5000">৳5,000</button>
                <button type="button" data-amt="10000">৳10,000</button>
            </div>

            <label for="xfNote">নোট (ঐচ্ছিক)</label>
            <input id="xfNote" name="note" type="text" maxlength="120" placeholder="যেমন: ক্যাশ থেকে ব্যাংকে জমা">

            <?php if ($pin_needed): ?>
                <label for="xfPin">অনুমোদন PIN — ৳<?php echo number_format($pin_threshold, 2); ?>-এর বেশি হলে লাগবে</label>
                <input id="xfPin" name="approval_pin" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" placeholder="PIN">
            <?php endif; ?>

            <p class="xf-live" id="xfLive">দুটি ভিন্ন অ্যাকাউন্ট ও পরিমাণ দিন।</p>

            <button type="submit" class="btn-success xf-go">✅ ট্রান্সফার নিশ্চিত করুন</button>
        </form>
    <?php endif; ?>

    <section class="xf-recent">
        <h2>সাম্প্রতিক ট্রান্সফার</h2>
        <?php if (!$recent): ?>
            <p class="xf-muted">এখনো কোনো ট্রান্সফার হয়নি। প্রথমবার উৎস ও গন্তব্য বেছে পরিমাণ দিন।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>সময়</th>
                        <th>উৎস → গন্তব্য</th>
                        <th>পরিমাণ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/y h:i A', strtotime((string)$r['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string)$r['from_name'], ENT_QUOTES, 'UTF-8'); ?>
                            →
                            <?php echo htmlspecialchars((string)$r['to_name'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (trim((string)$r['note']) !== ''): ?>
                                <small><?php echo htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="xf-num">৳<?php echo number_format((float)$r['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var fromEl = document.getElementById('from_account');
    var toEl = document.getElementById('to_account');
    var amtEl = document.getElementById('xfAmount');
    var live = document.getElementById('xfLive');
    var form = document.getElementById('xfForm');
    if (!fromEl || !toEl || !amtEl) return;

    function taka(n) {
        var x = Number(n || 0);
        return '৳' + x.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function opt(sel) {
        return sel.options[sel.selectedIndex] || null;
    }
    function bal(sel) {
        var o = opt(sel);
        return o ? Number(o.getAttribute('data-bal') || 0) : 0;
    }
    function isMasked() {
        return (fromEl.getAttribute('data-masked') === '1') || (toEl.getAttribute('data-masked') === '1');
    }
    function paint() {
        var fb = bal(fromEl);
        var tb = bal(toEl);
        var amt = Number(amtEl.value || 0);
        var fromBal = document.getElementById('fromBal');
        var toBal = document.getElementById('toBal');
        var fromAfter = document.getElementById('fromAfter');
        var toAfter = document.getElementById('toAfter');
        if (fromBal) fromBal.textContent = taka(fb);
        if (toBal) toBal.textContent = taka(tb);
        if (fromAfter) fromAfter.textContent = taka(fb - (amt > 0 ? amt : 0));
        if (toAfter) toAfter.textContent = taka(tb + (amt > 0 ? amt : 0));
        if (!live) return;
        if (fromEl.value === toEl.value) {
            live.textContent = 'উৎস ও গন্তব্য আলাদা হতে হবে।';
            live.className = 'xf-live warn';
            return;
        }
        if (!(amt > 0)) {
            live.textContent = 'পরিমাণ লিখুন — ইউনিট ৳।';
            live.className = 'xf-live';
            return;
        }
        if (!isMasked() && amt > fb + 0.0001) {
            live.textContent = 'উৎসে যথেষ্ট ব্যালেন্স নেই। সাবেক ' + taka(fb) + '।';
            live.className = 'xf-live warn';
            return;
        }
        live.textContent = taka(amt) + ' সরবে। উৎস ' + taka(fb - amt) + ', গন্তব্য ' + taka(tb + amt) + '।';
        live.className = 'xf-live ok';
    }
    var master = Array.prototype.map.call(fromEl.options, function (o) {
        return {
            value: o.value,
            text: o.textContent,
            bal: o.getAttribute('data-bal') || '0',
            type: o.getAttribute('data-type') || ''
        };
    });
    function renderTo(prefer) {
        var block = String(fromEl.value || '');
        var want = String(prefer || '');
        toEl.innerHTML = '';
        master.forEach(function (row) {
            if (String(row.value) === block) return;
            var o = document.createElement('option');
            o.value = row.value;
            o.textContent = row.text;
            o.setAttribute('data-bal', row.bal);
            o.setAttribute('data-type', row.type);
            toEl.appendChild(o);
        });
        if (want && want !== block) toEl.value = want;
        if (!toEl.value || toEl.value === block) toEl.selectedIndex = 0;
    }
    fromEl.addEventListener('change', function () {
        var keep = toEl.value;
        renderTo(keep);
        paint();
    });
    toEl.addEventListener('change', paint);
    amtEl.addEventListener('input', paint);
    document.querySelectorAll('.xf-chips button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            amtEl.value = btn.getAttribute('data-amt');
            paint();
            amtEl.focus();
        });
    });
    var swap = document.getElementById('xfSwap');
    if (swap) {
        swap.addEventListener('click', function () {
            var nextFrom = toEl.value;
            var nextTo = fromEl.value;
            if (master.some(function (row) { return String(row.value) === String(nextFrom); })) {
                fromEl.value = nextFrom;
            }
            renderTo(nextTo);
            paint();
        });
    }
    if (form) {
        form.addEventListener('submit', function (ev) {
            var fb = bal(fromEl);
            var amt = Number(amtEl.value || 0);
            if (fromEl.value === toEl.value || !(amt > 0) || (!isMasked() && amt > fb + 0.0001)) {
                ev.preventDefault();
                paint();
                if (window.AmeSubmitWait) window.AmeSubmitWait.hideAll();
            }
        });
    }
    paint();
})();
</script>
<?php include '../../partials/footer.php'; ?>
