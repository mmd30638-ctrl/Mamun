<?php
/* 10.98.789 */
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/txn_types.php';
ensure_accounts_schema($db);
ame_txn_types_cleanup_once($db);

$ame_body_class = 'ame-txn-page';

$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$accounts = ame_active_accounts($db);

$categories = ame_txn_types($db);

$members = [];
$memRes = @$db->query("SELECT id, name FROM family_members WHERE is_active = 1 ORDER BY name ASC");
if ($memRes) {
    $members = $memRes->fetch_all(MYSQLI_ASSOC);
}

$merchants = [];
$merRes = @$db->query("SELECT DISTINCT name FROM merchants WHERE is_active = 1 ORDER BY name ASC");
if ($merRes) {
    $merchants = $merRes->fetch_all(MYSQLI_ASSOC);
}

$recent = [];
$recRes = @$db->query(
    "SELECT al.id, al.type, al.amount, al.note, al.created_at, al.ref_type,
            a.account_name, ec.name AS category_name
     FROM account_ledger al
     LEFT JOIN accounts a ON a.id = al.account_id
     LEFT JOIN expense_categories ec ON ec.id = al.category_id
     WHERE al.ref_type IN ('manual_income', 'manual_expense')
     ORDER BY al.id DESC
     LIMIT 12"
);
if ($recRes) {
    $recent = $recRes->fetch_all(MYSQLI_ASSOC);
}

$month_cats = [];
$mcRes = @$db->query(
    "SELECT COALESCE(ec.name, '') AS category_name, ec.id AS category_id,
            SUM(CASE WHEN al.ref_type = 'manual_income' THEN al.amount ELSE 0 END) AS income,
            SUM(CASE WHEN al.ref_type = 'manual_expense' THEN al.amount ELSE 0 END) AS expense
     FROM account_ledger al
     LEFT JOIN expense_categories ec ON ec.id = al.category_id
     WHERE al.ref_type IN ('manual_income', 'manual_expense')
       AND al.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
     GROUP BY ec.id, ec.name
     ORDER BY SUM(al.amount) DESC
     LIMIT 6"
);
if ($mcRes) {
    $month_cats = $mcRes->fetch_all(MYSQLI_ASSOC);
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="txn-wrap">
    <header class="txn-hero">
        <h1>সাধারণ লেনদেন</h1>
        <p>জমা অথবা খরচ হিসাবে টাকা বসান। সেভ হলে নিচে তালিকায় দেখাবে।</p>
    </header>

    <?php if ($flash !== ''): ?>
        <div class="txn-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <nav class="txn-toolbar">
        <a class="txn-btn ghost" href="receive.php">বাহির থেকে রিসিভ</a>
        <a class="txn-btn ghost" href="categories.php">ধরন</a>
        <a class="txn-btn ghost" href="category_report.php">আয় ব্যয় রিপোর্ট</a>
        <a class="txn-btn ghost" href="family_members.php">পরিবার</a>
        <a class="txn-btn ghost" href="cash_book.php">ক্যাশ বই</a>
        <a class="txn-btn ghost" href="list.php">হিসাব তালিকা</a>
    </nav>

    <?php if (count($accounts) === 0): ?>
        <section class="txn-card">
            <p class="txn-empty">সক্রিয় অ্যাকাউন্ট নেই। আগে একটি ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট যোগ করুন।</p>
            <a class="txn-btn" href="add.php">নতুন অ্যাকাউন্ট</a>
        </section>
    <?php else: ?>
        <section class="txn-card">
            <h2>নতুন জমা / খরচ</h2>
            <form class="txn-form" action="actions/process_transaction.php" method="POST" data-ame-once="1">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="posted_account_id" value="">
                <input type="hidden" name="posted_txn_type" value="credit">
                <label class="txn-field txn-full">
                    অ্যাকাউন্ট
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required>
                        <option value="">নির্বাচন করুন</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars(ame_account_option_label($a), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="txn-field txn-full">
                    জমা / খরচ
                    <div class="txn-types">
                        <label class="txn-type is-in">
                            <input type="radio" name="txn_type" value="credit" checked>
                            জমা
                        </label>
                        <label class="txn-type is-out">
                            <input type="radio" name="txn_type" value="debit">
                            খরচ
                        </label>
                    </div>
                </div>

                <label class="txn-field txn-money">
                    পরিমাণ (৳)
                    <input type="number" name="amount" step="0.01" min="0.01" required inputmode="decimal" placeholder="টাকা লিখুন">
                </label>

                <div class="txn-field txn-full" id="txnCategoryField">
                    <span>কোন ধরনের খরচ বা আয় (ঐচ্ছিক) — তালিকায় <?php echo count($categories); ?>টি ধরন, সবাই ব্যবহার করা যায়</span>
                    <input type="hidden" name="category_id" id="txnCategory" value="">
                    <?php if (count($categories) > 6): ?>
                    <input type="search" id="txnTypeQ" class="txn-type-q" placeholder="ধরন খুঁজুন" autocomplete="off">
                    <?php endif; ?>
                    <div class="txn-type-picks" id="txnTypePicks" data-ame-no-page="1" role="listbox" aria-label="ধরন তালিকা">
                        <button type="button" class="txn-pick is-on" data-id="" data-name="ধরন নেই">ধরন নেই</button>
                        <?php foreach ($categories as $c): ?>
                            <button type="button" class="txn-pick" data-id="<?php echo (int) $c['id']; ?>" data-name="<?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <span class="txn-note" id="txnTypeCount">সব <?php echo count($categories); ?>টি ধরন এখানে। চাপলেই বেছে নেবে। নাম বদলাতে <a href="categories.php">ধরন</a> পেজে যান।</span>
                    <button type="button" class="txn-btn ghost" id="txnCatToggle">➕ নতুন ধরন যোগ করুন</button>
                    <div id="txnCatAdd" hidden style="display:none;gap:8px">
                        <input type="text" id="txnCatName" maxlength="100" placeholder="যেমন: ভাড়া, বেতন, চা-নাস্তা" autocomplete="off">
                        <div style="display:flex;gap:8px">
                            <button type="button" class="txn-submit" id="txnCatSave" style="flex:1">যোগ করুন</button>
                            <button type="button" class="txn-btn ghost" id="txnCatCancel">বাতিল</button>
                        </div>
                        <span class="txn-note" id="txnCatMsg" role="status"></span>
                    </div>
                </div>

                <label class="txn-field">
                    দোকান / মার্চেন্ট (ঐচ্ছিক)
                    <input type="text" name="merchant_name" list="merchantList" maxlength="150" placeholder="যেমন: Rahman Store">
                    <datalist id="merchantList">
                        <?php foreach ($merchants as $m): ?>
                            <option value="<?php echo htmlspecialchars((string)$m['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <?php if (!empty($members)): ?>
                <label class="txn-field">
                    পরিবারের সদস্য (ঐচ্ছিক)
                    <select name="member_id">
                        <option value="">নির্বাচন নেই</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars((string)$m['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>

                <label class="txn-field txn-full">
                    বিবরণ
                    <input type="text" name="note" maxlength="255" placeholder="ঐচ্ছিক — যেমন: অক্টোবর মাসের দোকান ভাড়া">
                </label>

                <div class="txn-full">
                    <button type="submit" class="txn-submit">সংরক্ষণ করুন</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="txn-card">
        <h2>এই মাসের ধরন অনুযায়ী</h2>
        <?php if (empty($month_cats)): ?>
            <p class="txn-empty">এই মাসে এখনো কোনো সাধারণ লেনদেন নেই।</p>
        <?php else: ?>
            <div class="txn-table-wrap">
                <table class="txn-table">
                    <thead>
                        <tr><th>ধরন</th><th>আয়</th><th>ব্যয়</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($month_cats as $mc):
                        $mc_name = trim((string)$mc['category_name']) !== '' ? (string)$mc['category_name'] : 'ধরন নেই';
                        $mc_link = (int)$mc['category_id'] > 0 ? (string)(int)$mc['category_id'] : 'none';
                        ?>
                        <tr>
                            <td><a href="category_report.php?range=month&amp;cat=<?php echo htmlspecialchars($mc_link, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mc_name, ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td class="in"><?php echo (float)$mc['income'] > 0 ? number_format((float)$mc['income'], 2) : '—'; ?></td>
                            <td class="out"><?php echo (float)$mc['expense'] > 0 ? number_format((float)$mc['expense'], 2) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p><a class="txn-btn ghost" href="category_report.php?range=month">সম্পূর্ণ আয় ব্যয় রিপোর্ট</a></p>
    </section>

    <section class="txn-card">
        <h2>সাম্প্রতিক সাধারণ লেনদেন</h2>
        <?php if (empty($recent)): ?>
            <p class="txn-empty">এখনো কোনো সাধারণ লেনদেন নেই।</p>
        <?php else: ?>
            <div class="txn-table-wrap">
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th>সময়</th>
                            <th>হিসাব</th>
                            <th>জমা</th>
                            <th>খরচ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $row):
                        $kind = strtolower(trim((string)($row['type'] ?? '')));
                        $ref = strtolower(trim((string)($row['ref_type'] ?? '')));
                        $is_in = ame_manual_row_is_deposit($kind, $ref);
                        $when = (string)($row['created_at'] ?? '');
                        $when_show = $when !== '' ? date('d/m/y h:i A', strtotime($when)) : '—';
                        $shown = number_format((float)($row['amount'] ?? 0), 2);
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($when_show, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($row['category_name'])): ?>
                                    <span class="txn-note">🏷️ <?php echo htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="txn-note"><?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars((string)($row['account_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="in"><?php echo $is_in ? $shown : '—'; ?></td>
                            <td class="out"><?php echo $is_in ? '—' : $shown; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var select = document.getElementById('txnCategory');
    var picks = document.getElementById('txnTypePicks');
    var countEl = document.getElementById('txnTypeCount');
    var search = document.getElementById('txnTypeQ');
    var toggle = document.getElementById('txnCatToggle');
    var box = document.getElementById('txnCatAdd');
    var input = document.getElementById('txnCatName');
    var save = document.getElementById('txnCatSave');
    var cancel = document.getElementById('txnCatCancel');
    var msg = document.getElementById('txnCatMsg');
    var form = select && select.form;
    if (!select || !picks || !toggle || !box || !input || !save || !form) return;

    function typeCount() {
        return picks.querySelectorAll('.txn-pick[data-id]:not([data-id=""])').length;
    }
    function updateCount() {
        if (!countEl) return;
        countEl.innerHTML = 'সব ' + typeCount() + 'টি ধরন এখানে। চাপলেই বেছে নেবে। নাম বদলাতে <a href="categories.php">ধরন</a> পেজে যান।';
    }
    function findChip(id) {
        var want = id ? String(id) : '';
        var found = null;
        Array.prototype.forEach.call(picks.querySelectorAll('.txn-pick'), function (btn) {
            if (String(btn.getAttribute('data-id') || '') === want) {
                found = btn;
            }
        });
        return found;
    }
    function pick(id, name) {
        var want = id ? String(id) : '';
        if (want !== '' && !findChip(want) && name) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'txn-pick';
            btn.setAttribute('data-id', want);
            btn.setAttribute('data-name', name);
            btn.textContent = name;
            picks.appendChild(btn);
        }
        select.value = want;
        Array.prototype.forEach.call(picks.querySelectorAll('.txn-pick'), function (btn) {
            var match = String(btn.getAttribute('data-id') || '') === want;
            btn.classList.toggle('is-on', match);
            if (match) {
                btn.setAttribute('aria-selected', 'true');
            } else {
                btn.removeAttribute('aria-selected');
            }
        });
        updateCount();
    }

    picks.addEventListener('click', function (e) {
        var btn = e.target.closest('.txn-pick');
        if (!btn || !picks.contains(btn)) return;
        e.preventDefault();
        pick(btn.getAttribute('data-id') || '', btn.getAttribute('data-name') || btn.textContent);
    });

    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            Array.prototype.forEach.call(picks.querySelectorAll('.txn-pick'), function (btn) {
                var name = (btn.getAttribute('data-name') || btn.textContent || '').toLowerCase();
                var empty = String(btn.getAttribute('data-id') || '') === '';
                btn.hidden = q !== '' && !empty && name.indexOf(q) === -1;
            });
        });
    }

    function show(open) {
        box.hidden = !open;
        box.style.display = open ? 'grid' : 'none';
        toggle.style.display = open ? 'none' : '';
        msg.textContent = '';
        if (open) input.focus();
    }
    function say(text, ok) {
        msg.textContent = text;
        msg.style.color = ok ? '#0B7A3B' : '#B3261E';
    }

    toggle.addEventListener('click', function () { show(true); });
    cancel.addEventListener('click', function () { input.value = ''; show(false); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); save.click(); }
    });
    save.addEventListener('click', function () {
        var name = input.value.trim();
        if (name === '') { say('ধরনের নাম লিখুন।', false); return; }
        var token = form.querySelector('input[name="csrf_token"]');
        var body = new FormData();
        body.append('ajax', '1');
        body.append('name', name);
        body.append('csrf_token', token ? token.value : '');
        save.disabled = true;
        fetch('actions/process_category.php', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                save.disabled = false;
                if (data && data.ok) {
                    pick(data.id, data.name);
                    input.value = '';
                    show(false);
                } else {
                    say((data && data.message) || 'ধরন যোগ করা যায়নি।', false);
                }
            })
            .catch(function () {
                save.disabled = false;
                say('সংযোগ সমস্যা। আবার চেষ্টা করুন।', false);
            });
    });
})();
</script>
<script>
(function () {
    var form = document.querySelector('.txn-form');
    var select = form && form.querySelector('select[name="account_id"]');
    if (!form || !select) return;
    function sync() {
        var debit = form.querySelector('input[name="txn_type"][value="debit"]');
        var credit = form.querySelector('input[name="txn_type"][value="credit"]');
        var kind = form.querySelector('input[name="posted_txn_type"]');
        var posted = form.querySelector('input[name="posted_account_id"]');
        var opt = select.options[select.selectedIndex];
        if (kind) {
            kind.value = debit && debit.checked ? 'debit' : (credit && credit.checked ? 'credit' : '');
        }
        if (posted) {
            posted.value = opt && String(opt.value || '') !== '' ? String(opt.value) : '';
        }
        select.setAttribute('data-ame-direction', debit && debit.checked ? 'pay' : 'receive');
        if (select.ameAccountSheet) {
            select.ameAccountSheet.remove();
            select.ameAccountSheet = null;
        }
    }
    form.addEventListener('submit', sync, true);
    select.addEventListener('change', sync);
    form.querySelectorAll('input[name="txn_type"]').forEach(function (radio) {
        radio.addEventListener('change', sync);
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
