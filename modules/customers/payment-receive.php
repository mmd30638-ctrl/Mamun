<?php
/* 10.98.723 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
require_once __DIR__ . '/../../includes/lang_helper.php';
ensure_customer_tables($db);
ame_ensure_due_watch_columns($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$preselect_id = (int)($_GET['id'] ?? 0);
$customers = [];
$cpay_group_sql = "'' AS group_name";
$cpay_gcol = @$db->query("SHOW COLUMNS FROM customers LIKE 'group_id'");
if ($cpay_gcol && $cpay_gcol->num_rows > 0) {
    $cpay_group_sql = "(SELECT cg.name FROM customer_groups cg WHERE cg.id = customers.group_id LIMIT 1) AS group_name";
}
$photo_fn = function_exists('ame_customer_photo_url') ? 'ame_customer_photo_url' : null;

if ($preselect_id > 0) {
    $one = $db->prepare(
        "SELECT id, customer_code, name, phone, address, photo, receivable_balance, next_due_date, due_watch, due_watch_on, $cpay_group_sql
         FROM customers WHERE id = ? AND is_customer = 1 AND " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1') . " LIMIT 1"
    );
    if ($one) {
        $one->bind_param('i', $preselect_id);
        $one->execute();
        $row = $one->get_result()->fetch_assoc();
        $one->close();
        if ($row) {
            $paid_total = 0.0;
            $paid_one = $db->prepare(
                'SELECT COALESCE(SUM(amount), 0) AS paid FROM customer_payments WHERE customer_id = ?'
            );
            if ($paid_one) {
                $paid_one->bind_param('i', $preselect_id);
                $paid_one->execute();
                $paid_row = $paid_one->get_result()->fetch_assoc();
                $paid_one->close();
                $paid_total = (float)($paid_row['paid'] ?? 0);
            }
            $row['paid_total'] = $paid_total;
            $row['photo_url'] = $photo_fn ? $photo_fn($row['photo'] ?? '') : '';
            $customers[] = $row;
        }
    }
}

if (!function_exists('ame_active_accounts')) {
    require_once __DIR__ . '/../../includes/account_functions.php';
}
$accounts = ame_active_accounts($db);

$ame_body_class = 'ame-customer-payment-page';
$cpay_ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.425';

include __DIR__ . '/../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/customer-payment.css?v=<?php echo htmlspecialchars($cpay_ver, ENT_QUOTES, 'UTF-8'); ?>">

<div class="cpay-page cpay-page-wrap cpd" id="cpayPage">
    <form action="actions/process_payment.php" method="POST" id="paymentReceiveForm" data-ame-once="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16))); ?>">
        <input type="hidden" name="posted_account_id" id="postedAccountId" value="">
        <input type="hidden" name="customer_id" id="customerSelect">

        <div class="cpd-desk">
            <section class="cpd-panel cpd-form">
                <h1>ক্রেতার পেমেন্ট গ্রহণ <span class="cpay-net" id="amePayNet"></span></h1>
                <div class="cpd-top">
                    <div class="cpd-search customer-search-wrap">
                        <input type="text" id="customerSearchInput" autocomplete="off" placeholder="আইডি/নাম/মোবাইল">
                        <button type="button" id="searchCustomerBtn">খুঁজুন</button>
                        <div class="cpay-results customer-results" id="customerResults"></div>
                    </div>

                    <div class="cpd-who">
                        <div class="cpay-avatar" id="cpayAvatar">?</div>
                        <div>
                            <p class="cpd-name">
                                <span id="cpayName">কাস্টমার বাছাই করুন</span>
                                <span class="cpay-badge is-empty" id="cpayBadge">অপেক্ষমাণ</span>
                            </p>
                            <p class="cpd-meta cpd-c-blue" id="cpayCode">আইডি: —</p>
                            <p class="cpd-meta cpd-c-violet" id="cpayPhone">ফোন: —</p>
                            <p class="cpd-meta cpd-c-amber" id="cpayAddr">ঠিকানা: —</p>
                            <p class="cpd-meta cpd-c-teal" id="cpayGroup" hidden></p>
                        </div>
                    </div>

                    <div class="cpd-balbox">
                        <label class="cpd-label cpd-c-rose">ব্যালেন্স</label>
                        <div class="cpd-balance" id="cpdBalance">0.00</div>
                        <p class="cpd-quiet cpd-c-navy" id="cpayAccountTitle">বকেয়া / জমা হিসাব</p>
                        <p class="cpd-quiet" id="cpayAccountSub">কাস্টমার বাছাই করলে খাতার ব্যালেন্স দেখাবে</p>
                        <p class="cpd-quiet"><span class="cpay-status" id="cpayAccountStatus">—</span></p>
                    </div>
                </div>

                <div class="cpd-fields">
                <div class="cpd-field">
                <label class="cpd-label cpd-c-green" for="amountInput">জমার টাকা</label>
                <input class="cpd-input" type="number" step="0.01" name="amount" min="0" placeholder="0" id="amountInput">
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-orange" for="discountInput">ডিসকাউন্ট</label>
                <input class="cpd-input" type="number" step="0.01" name="discount" min="0" placeholder="0" id="discountInput" value="0">
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-blue">মাধ্যম</label>
                <div class="method-dropdown" id="methodDropdown">
                    <input type="hidden" name="method" id="methodValue" value="CASH">
                    <div class="method-trigger" id="methodTrigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
                        <span class="method-trigger-icon" id="methodTriggerIcon">💵</span>
                        <span class="method-trigger-label" id="methodTriggerLabel">নগদ</span>
                        <span class="chevron">▾</span>
                    </div>
                    <div class="method-panel" id="methodPanel" role="listbox">
                        <div class="method-option selected" data-value="CASH" data-icon="💵" data-label="নগদ"><span class="method-option-icon">💵</span><span class="method-option-label">নগদ</span><span class="check">✓</span></div>
                        <div class="method-option" data-value="BKASH" data-icon="📱" data-label="বিকাশ"><span class="method-option-icon">📱</span><span class="method-option-label">বিকাশ</span><span class="check">✓</span></div>
                        <div class="method-option" data-value="NAGAD" data-icon="📲" data-label="নগদ (মোবাইল)"><span class="method-option-icon">📲</span><span class="method-option-label">নগদ (মোবাইল)</span><span class="check">✓</span></div>
                        <div class="method-option" data-value="BANK" data-icon="🏦" data-label="ব্যাংক"><span class="method-option-icon">🏦</span><span class="method-option-label">ব্যাংক</span><span class="check">✓</span></div>
                        <div class="method-option" data-value="CHEQUE" data-icon="🧾" data-label="চেক"><span class="method-option-icon">🧾</span><span class="method-option-label">চেক</span><span class="check">✓</span></div>
                    </div>
                </div>
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-teal" for="accountSelect">জমা অ্যাকাউন্ট</label>
                <select class="cpd-input ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" id="accountSelect">
                    <?php if (count($accounts) === 1): ?>
                    <option value="<?php echo (int)$accounts[0]['id']; ?>" selected><?php echo htmlspecialchars($accounts[0]['account_name']); ?> (<?php echo ame_balance_label((float)$accounts[0]['balance']); ?>)</option>
                    <?php else: ?>
                    <option value=""></option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['account_name']); ?> (<?php echo ame_balance_label((float)$a['balance']); ?>)</option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (!$accounts): ?>
                <p class="cpd-warn">কোনো সক্রিয় অ্যাকাউন্ট নেই — আগে একটি ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট খুলুন।</p>
                <?php endif; ?>
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-navy">অ্যাকাউন্ট ব্যালেন্স</label>
                <div class="cpd-readonly" id="cpdTill">—</div>
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-violet" for="cpdPayDate">তারিখ</label>
                <input class="cpd-input" type="date" name="payment_date" id="cpdPayDate" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="cpd-field">
                <label class="cpd-label cpd-c-amber" for="cpdRef">রেফারেন্স</label>
                <input class="cpd-input" type="text" name="reference_no" id="cpdRef" maxlength="100" placeholder="ট্রানজেকশন নম্বর">
                </div>

                <div class="cpd-field cpd-span-2">
                <label class="cpd-label cpd-c-rose" for="cpdNote">নোট</label>
                <textarea class="cpd-input" name="note" id="cpdNote" rows="2" maxlength="255" placeholder="নোট"></textarea>
                </div>
                </div>

                <div class="cpd-due" id="dueBody">
                    <div class="cpd-field">
                    <label class="cpd-label cpd-c-violet" for="nextDueInput">পরবর্তী পেমেন্টের তারিখ</label>
                    <input class="cpd-input" type="date" name="next_due_date" id="nextDueInput">
                    </div>
                    <div class="cpd-field">
                    <label class="cpd-label cpd-c-green" for="nextDueAmountInput">পরবর্তী পেমেন্টের পরিমাণ</label>
                    <input class="cpd-input" type="number" step="0.01" min="0" name="next_due_amount" id="nextDueAmountInput" placeholder="0">
                    </div>
                    <div class="cpd-field">
                    <label class="cpd-label cpd-c-amber" for="cpdDueNote">পরবর্তী পেমেন্টের নোট</label>
                    <input class="cpd-input" type="text" name="due_note" id="cpdDueNote" maxlength="255" placeholder="যেমন: মাসিক কিস্তি">
                    </div>
                </div>
                <button type="button" class="cpay-due-toggle" id="dueToggle" hidden>পরবর্তী তারিখ</button>

                <label class="cpd-check">
                    <input type="checkbox" name="notify_off" id="notifyOffInput" value="1">
                    নোটিফিকেশন অফ করুন — ঘণ্টায় ও বাকি-তারিখ তালিকায় আর উঠবে না
                </label>
                <p class="cpd-quiet" id="cpayNotifyState">কাস্টমার বাছাই করলে নোটিফিকেশন অবস্থা দেখাবে</p>

                <div class="cpd-preview" id="cpayMainCalc">
                    <span id="calcBeforeLabel">আগে ছিল</span>
                    <b id="calcBefore">৳ 0</b>
                    <span id="calcOp">−</span>
                    <span>এখন দিলো</span>
                    <b id="calcNow">৳ 0</b>
                    <span>=</span>
                    <span id="calcAfterLabel">এখন থাকবে</span>
                    <b id="calcAfter">৳ 0</b>
                </div>
                <div class="cpd-sums" hidden>
                    <span id="kpiTotal">৳ 0</span>
                    <span id="kpiPaid">৳ 0</span>
                    <span id="kpiDue">৳ 0</span>
                    <span id="kpiDepositBox" hidden><span id="kpiDeposit">৳ 0</span></span>
                    <span id="sumTotal">৳ 0</span>
                    <span id="sumDue">৳ 0</span>
                    <span id="sumPaid">৳ 0</span>
                    <span id="sumNow">৳ 0</span>
                    <span id="sumDiscountRow" hidden><span id="sumDiscount">৳ 0</span></span>
                    <span id="sumDepositRow" hidden><span id="sumDeposit">৳ 0</span></span>
                    <span id="sumAccount">—</span>
                    <span id="sumLeftLabel">পেমেন্টের পর বাকি</span>
                    <span id="sumLeft">৳ 0</span>
                </div>
                <div class="cpay-clear" id="cpayClear">এই পেমেন্টে বকেয়া শেষ হয়ে যাবে।</div>
                <?php if (function_exists('ame_pay_sec_box')) echo ame_pay_sec_box('customer_pay', 0, 'এই কোড না দিলে রসিদ হবে না। একই কাস্টমারের একই টাকা দুবার কাটবে না।', 'This code is required. The same customer will not be charged the same amount twice.'); ?>
                <div class="cpd-actions">
                    <a class="cpd-link" id="qaLedger" href="ledger.php">লেজার</a>
                    <a class="cpd-link" id="qaProfile" href="index.php">প্রোফাইল</a>
                    <span id="qaWhatsapp" hidden>WhatsApp</span>
                    <a class="cpd-cancel" href="index.php">বাতিল</a>
                    <button type="submit" class="cpd-submit" id="cpayConfirmBtn">জমা দিন</button>
                </div>
            </section>

            <section class="cpd-panel cpd-books">
                <h2 class="cpd-h-pay">গ্রহণকৃত পেমেন্ট সমূহ</h2>
                <div class="cpd-table-wrap">
                    <table class="cpd-table cpd-pay-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>তারিখ</th>
                                <th>ইনভয়েস</th>
                                <th>নোট</th>
                                <th>টাকা</th>
                                <th>বকেয়া</th>
                                <th>স্ট্যাটাস</th>
                                <th>কাজ</th>
                            </tr>
                        </thead>
                        <tbody id="cpdPayBody">
                            <tr><td colspan="8">কাস্টমার বাছাই করলে এই খাতার পেমেন্ট দেখাবে।</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="cpd-panel cpd-expect">
                <h2 class="cpd-h-due">প্রত্যাশিত বকেয়া পেমেন্ট এর তারিখ</h2>
                <div class="cpd-table-wrap">
                    <table class="cpd-table cpd-due-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>পেমেন্ট তারিখ</th>
                                <th>নোট</th>
                                <th>টাকা</th>
                                <th>স্ট্যাটাস</th>
                            </tr>
                        </thead>
                        <tbody id="cpdDueBody">
                            <tr><td colspan="5">নির্ধারিত পরবর্তী তারিখ নেই।</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </form>
</div>
<div id="cpayNotice" class="cpay-notice" hidden>
    <button type="button" id="cpayNoticeBtn" class="cpay-notice-btn"></button>
</div>

<script src="/assets/js/cpay-notice.js?v=<?php echo htmlspecialchars($cpay_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/assets/js/offline-payment-queue.js?v=<?php echo htmlspecialchars($cpay_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
const AME_CUSTOMERS = <?php echo json_encode(array_map(function ($c) {
    return [
        'id' => (int)$c['id'],
        'name' => $c['name'],
        'code' => $c['customer_code'],
        'phone' => $c['phone'] ?? '',
        'address' => $c['address'] ?? '',
        'group' => (string)($c['group_name'] ?? ''),
        'photo' => $c['photo_url'] ?? '',
        'balance' => (float)$c['receivable_balance'],
        'paid' => (float)($c['paid_total'] ?? 0),
        'due' => $c['next_due_date'] ?? '',
        'due_watch' => !empty($c['due_watch']),
        'due_watch_on' => (string)($c['due_watch_on'] ?? ''),
        'notify_on' => function_exists('ame_customer_due_notify_on') ? ame_customer_due_notify_on($c) : (!empty($c['due_watch']) || trim((string)($c['next_due_date'] ?? '')) !== ''),
    ];
}, $customers), JSON_UNESCAPED_UNICODE); ?>;

const AME_ACCOUNTS = <?php echo json_encode(array_map(function ($a) {
    return [
        'id' => (int)$a['id'],
        'name' => $a['account_name'],
        'balance' => ame_balance_number_masked($a['balance']),
    ];
}, $accounts), JSON_UNESCAPED_UNICODE); ?>;

const searchInput = document.getElementById('customerSearchInput');
const resultsBox = document.getElementById('customerResults');
const hiddenId = document.getElementById('customerSelect');
const amountInput = document.getElementById('amountInput');
const discountInput = document.getElementById('discountInput');
let picked = null;
let searchTimer = 0;
let searchAbort = null;
const foundCustomers = {};
AME_CUSTOMERS.forEach(function (c) { foundCustomers[c.id] = c; });

function money(n) {
    const x = Number(n) || 0;
    return '৳ ' + x.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function bookNumber(n) {
    const x = Number(n);
    if (!Number.isFinite(x)) return '—';
    return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function paintBalanceTone(node, value) {
    if (!node) return;
    node.classList.remove('is-due', 'is-deposit');
    const n = Number(value);
    if (!Number.isFinite(n) || n === 0) return;
    node.classList.add(n > 0 ? 'is-due' : 'is-deposit');
}

function cpdEsc(value) {
    return String(value == null ? '' : value).replace(/[&<>"]/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch];
    });
}

function paintPaymentDesk(data) {
    const payBody = document.getElementById('cpdPayBody');
    const dueBody = document.getElementById('cpdDueBody');
    if (!payBody || !dueBody) return;
    const rows = (data && data.payments) ? data.payments : [];
    if (!rows.length) {
        payBody.innerHTML = '<tr><td colspan="8">এই কাস্টমারের গ্রহণকৃত পেমেন্ট নেই।</td></tr>';
    } else {
        payBody.innerHTML = rows.map(function (row, index) {
            const ops = '<a class="cpd-a-view" href="receipt.php?payment_id=' + row.id + '">দেখুন</a>'
                + ' <a class="cpd-a-print" href="receipt.php?payment_id=' + row.id + '&amp;print=1">প্রিন্ট</a>'
                + (row.reversed ? '' : ' <a class="cpd-a-edit" href="payment-edit.php?id=' + row.id + '">এডিট</a>'
                    + ' <a class="cpd-a-void" href="receipt.php?payment_id=' + row.id + '#cpayReverse">বাতিল</a>');
            return '<tr>'
                + '<td data-label="#">' + (index + 1) + '</td>'
                + '<td data-label="তারিখ">' + cpdEsc(row.date) + '</td>'
                + '<td data-label="ইনভয়েস">' + cpdEsc(row.invoice) + '</td>'
                + '<td data-label="নোট">' + cpdEsc(row.note || '') + '</td>'
                + '<td class="cpd-num" data-label="টাকা">' + cpdEsc(row.amount) + '</td>'
                + '<td class="cpd-num" data-label="বকেয়া">' + cpdEsc(row.due) + '</td>'
                + '<td data-label="স্ট্যাটাস">' + (row.reversed ? 'বাতিল' : '✓') + '</td>'
                + '<td class="cpd-ops" data-label="কাজ">' + ops + '</td>'
                + '</tr>';
        }).join('');
    }
    const dues = (data && data.expected) ? data.expected : [];
    if (!dues.length) {
        dueBody.innerHTML = '<tr><td colspan="5">নির্ধারিত পরবর্তী তারিখ নেই।</td></tr>';
    } else {
        dueBody.innerHTML = dues.map(function (row, index) {
            return '<tr><td data-label="#">' + (index + 1) + '</td><td data-label="পেমেন্ট তারিখ">' + cpdEsc(row.date) + '</td><td data-label="নোট">' + cpdEsc(row.note || '') + '</td><td class="cpd-num" data-label="টাকা">' + cpdEsc(row.amount) + '</td><td data-label="স্ট্যাটাস">নির্ধারিত</td></tr>';
        }).join('');
    }
}

function loadPaymentDesk(id) {
    const payBody = document.getElementById('cpdPayBody');
    if (!payBody) return;
    if (!id) {
        paintPaymentDesk({ payments: [], expected: [] });
        return;
    }
    payBody.innerHTML = '<tr><td colspan="8">লোড হচ্ছে…</td></tr>';
    fetch('actions/payment_desk.php?id=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) { paintPaymentDesk(data && data.ok ? data : { payments: [], expected: [] }); })
        .catch(function () {
            payBody.innerHTML = '<tr><td colspan="8">পেমেন্ট তালিকা লোড হয়নি।</td></tr>';
        });
}

function initials(name) {
    const t = (name || '').trim();
    return t ? t.charAt(0) : '?';
}

function setSteps() {
    const hasC = !!hiddenId.value;
    const cash = parseFloat(amountInput.value || '0') || 0;
    const disc = parseFloat((discountInput && discountInput.value) || '0') || 0;
    const hasAmt = (cash + disc) > 0;
    document.querySelectorAll('#cpaySteps .cpay-step').forEach(function (el) {
        const s = Number(el.getAttribute('data-step'));
        el.classList.remove('is-active', 'is-done');
        if (s === 1 && hasC) el.classList.add('is-done');
        else if (s === 1) el.classList.add('is-active');
        else if (s === 2 && hasC && hasAmt) el.classList.add('is-done');
        else if (s === 2 && hasC) el.classList.add('is-active');
        else if (s === 3 && hasC && hasAmt) el.classList.add('is-active');
    });
}

function updateSummary() {
    // picked.balance: হিসাবের নেট ব্যালেন্স — ধনাত্মক মানে কাস্টমার বাকি (due) দেবে,
    // ঋণাত্মক মানে কাস্টমারের দোকানে জমা (deposit) আছে। এই পেমেন্ট প্রথমে বাকি কমায়,
    // বাকি শেষ হয়ে গেলে অতিরিক্ত টাকা জমায় যোগ হয় (প্লাস/মাইনাস এখান থেকেই)।
    const balance = picked ? Number(picked.balance) || 0 : 0;
    const due = Math.max(0, balance);
    const deposit = Math.max(0, -balance);
    const paid = picked ? Number(picked.paid) || 0 : 0;
    const total = paid + due;
    const now = parseFloat(amountInput.value || '0') || 0;
    const disc = Math.max(0, parseFloat((discountInput && discountInput.value) || '0') || 0);
    const relief = now + disc;

    const newBalance = balance - relief;
    const newDue = Math.max(0, newBalance);
    const newDeposit = Math.max(0, -newBalance);

    // ⭐ "মূল হিসাব" — আগে কত ছিল (বাকি/জমা), এখন কত দিলো, তাতে এখন কত হবে —
    // একটাই স্পষ্ট সমীকরণ আকারে। এটা শুধু দেখানোর জন্য; নিচের বাকি হিসাব
    // (kpi/sum রো-গুলো) আগের মতোই একই ভ্যারিয়েবল থেকে হিসাব হয়, তাই দুই জায়গায়
    // কখনো ভিন্ন সংখ্যা দেখাবে না।
    const calcBeforeLabel = document.getElementById('calcBeforeLabel');
    const calcAfterLabel = document.getElementById('calcAfterLabel');
    const calcOp = document.getElementById('calcOp');
    const calcBefore = document.getElementById('calcBefore');
    const calcNow = document.getElementById('calcNow');
    const calcAfter = document.getElementById('calcAfter');
    if (calcBefore && calcNow && calcAfter) {
        if (deposit > 0) {
            // আগে থেকেই জমা আছে → এই পেমেন্ট জমার সাথে যোগ হবে
            if (calcBeforeLabel) calcBeforeLabel.textContent = 'আগে জমা ছিল';
            if (calcOp) calcOp.textContent = '+';
            calcBefore.textContent = money(deposit);
        } else {
            // বাকি আছে (বা শূন্য) → এই পেমেন্ট বাকি থেকে বাদ যাবে
            if (calcBeforeLabel) calcBeforeLabel.textContent = 'আগে বাকি ছিল';
            if (calcOp) calcOp.textContent = '−';
            calcBefore.textContent = money(due);
        }
        calcNow.textContent = money(relief);
        if (newDeposit > 0) {
            if (calcAfterLabel) calcAfterLabel.textContent = 'এখন জমা থাকবে';
            calcAfter.textContent = money(newDeposit);
        } else {
            if (calcAfterLabel) calcAfterLabel.textContent = 'এখন বাকি থাকবে';
            calcAfter.textContent = money(newDue);
        }
    }

    document.getElementById('kpiTotal').textContent = money(total);
    document.getElementById('kpiPaid').textContent = money(paid);
    document.getElementById('kpiDue').textContent = money(due);
    const depositBox = document.getElementById('kpiDepositBox');
    if (depositBox) {
        depositBox.hidden = deposit <= 0;
        document.getElementById('kpiDeposit').textContent = money(deposit);
    }

    document.getElementById('sumTotal').textContent = money(total);
    document.getElementById('sumDue').textContent = money(due);
    document.getElementById('sumPaid').textContent = money(paid);
    document.getElementById('sumNow').textContent = money(now);
    const sumDiscountRow = document.getElementById('sumDiscountRow');
    const sumDiscount = document.getElementById('sumDiscount');
    if (sumDiscountRow && sumDiscount) {
        sumDiscountRow.hidden = disc <= 0;
        sumDiscount.textContent = money(disc);
    }
    const sumDepositRow = document.getElementById('sumDepositRow');
    if (sumDepositRow) {
        sumDepositRow.hidden = deposit <= 0;
        document.getElementById('sumDeposit').textContent = money(deposit);
    }

    const accSel = document.getElementById('accountSelect');
    const accLabel = accSel && accSel.value ? (accSel.options[accSel.selectedIndex] ? accSel.options[accSel.selectedIndex].text : '—') : '—';
    const sumAcc = document.getElementById('sumAccount');
    if (sumAcc) sumAcc.textContent = accLabel;
    const till = document.getElementById('cpdTill');
    if (till) {
        const account = AME_ACCOUNTS.find(function (row) { return String(row.id) === String(accSel && accSel.value); });
        till.textContent = account ? bookNumber(account.balance) : '—';
    }
    const balanceBox = document.getElementById('cpdBalance');
    if (balanceBox) {
        balanceBox.textContent = picked ? bookNumber(picked.balance) : '0.00';
        paintBalanceTone(balanceBox, picked ? picked.balance : 0);
    }

    const sumLeftLabel = document.getElementById('sumLeftLabel');
    if (newDeposit > 0) {
        if (sumLeftLabel) sumLeftLabel.textContent = 'পেমেন্টের পর জমা হবে';
        document.getElementById('sumLeft').textContent = money(newDeposit);
    } else {
        if (sumLeftLabel) sumLeftLabel.textContent = 'পেমেন্টের পর বাকি';
        document.getElementById('sumLeft').textContent = money(newDue);
    }
    document.getElementById('cpayClear').classList.toggle('show', picked && due > 0 && relief >= due);
    setSteps();
}

function paintGroup(name) {
    const el = document.getElementById('cpayGroup');
    if (!el) return;
    const g = String(name || '').trim();
    el.textContent = g ? ('👥 ' + g) : '';
    el.hidden = !g;
}

function fillCustomer(c) {
    picked = c;
    foundCustomers[c.id] = c;
    hiddenId.value = c.id;
    searchInput.value = c.name;
    document.getElementById('cpayName').textContent = c.name;
    const badge = document.getElementById('cpayBadge');
    badge.textContent = 'Active';
    badge.classList.remove('is-empty');
    document.getElementById('cpayCode').textContent = 'ID: ' + (c.code || ('CUS-' + c.id));
    paintWhatsapp(c.phone || '');
    document.getElementById('cpayAddr').textContent = '📍 ' + (c.address || '—');
    paintGroup(c.group || '');
    const av = document.getElementById('cpayAvatar');
    if (c.photo) {
        av.innerHTML = '<img src="' + String(c.photo).replace(/"/g, '') + '" alt="">';
    } else {
        av.innerHTML = '';
        av.textContent = initials(c.name);
    }
    const bal = Number(c.balance) || 0;
    document.getElementById('cpayAccountTitle').textContent = c.name + ' — হিসাব';
    document.getElementById('cpayAccountSub').textContent = (c.due ? ('পরবর্তী তারিখ: ' + c.due + ' · ') : '') + 'এখন পর্যন্ত মোট জমা দিয়েছে ' + money(c.paid || 0);
    const st = document.getElementById('cpayAccountStatus');
    if (bal > 0) { st.textContent = 'বাকি আছে'; st.className = 'cpay-status is-due'; }
    else if (bal < 0) { st.textContent = 'জমা আছে'; st.className = 'cpay-status is-deposit'; }
    else { st.textContent = 'পরিশোধিত'; st.className = 'cpay-status'; }
    const notifyState = document.getElementById('cpayNotifyState');
    const notifyOff = document.getElementById('notifyOffInput');
    if (notifyState) {
        if (c.notify_on || c.due || c.due_watch) {
            const bits = [];
            if (c.due) bits.push('পরবর্তী তারিখ: ' + c.due);
            if (c.due_watch) bits.push('১৫ দিনের ওয়াচ চালু');
            notifyState.textContent = 'চালু আছে' + (bits.length ? ' · ' + bits.join(' · ') : '');
        } else {
            notifyState.textContent = 'এখন বন্ধ আছে — ঘণ্টায় উঠবে না';
        }
    }
    if (notifyOff) notifyOff.checked = false;
    document.getElementById('qaLedger').href = 'ledger.php?id=' + c.id;
    document.getElementById('qaProfile').href = 'profile.php?id=' + c.id;
    const balanceBox = document.getElementById('cpdBalance');
    if (balanceBox) {
        balanceBox.textContent = bookNumber(c.balance);
        paintBalanceTone(balanceBox, c.balance);
    }
    updateSummary();
    loadPaymentDesk(c.id);
}

function renderResults(list) {
    if (list.length === 0) {
        resultsBox.innerHTML = '<div class="cpay-results-empty">কোনো কাস্টমার পাওয়া যায়নি</div>';
        resultsBox.classList.add('open');
        return;
    }
    resultsBox.innerHTML = list.slice(0, 30).map(function (c) {
        return '<div class="cpay-result" data-id="' + c.id + '"><span class="cpay-avatar" style="width:36px;height:36px;font-size:14px;">' + initials(c.name) + '</span><span><b>' + c.name.replace(/</g, '') + '</b><small>' + (c.code || '') + (c.phone ? ' · ' + c.phone : '') + (c.area ? ' · ' + String(c.area).replace(/</g, '') : '') + (c.group ? ' · ' + String(c.group).replace(/</g, '') : '') + '</small></span></div>';
    }).join('');
    resultsBox.classList.add('open');
}

function rememberResults(list) {
    list.forEach(function (c) { foundCustomers[c.id] = c; });
}

searchInput.addEventListener('input', function () {
    hiddenId.value = '';
    picked = null;
    const q = this.value.trim();
    window.clearTimeout(searchTimer);
    if (searchAbort && searchAbort.abort) {
        try { searchAbort.abort(); } catch (e) {}
    }
    if (q === '') {
        resultsBox.classList.remove('open');
        updateSummary();
        return;
    }
    searchTimer = window.setTimeout(function () {
        searchAbort = (typeof AbortController === 'function') ? new AbortController() : null;
        const opts = { credentials: 'same-origin' };
        if (searchAbort) opts.signal = searchAbort.signal;
        fetch('actions/customer_search.php?q=' + encodeURIComponent(q), opts)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const list = (data && data.customers) ? data.customers : [];
                rememberResults(list);
                renderResults(list);
            })
            .catch(function () {
                resultsBox.innerHTML = '<div class="cpay-results-empty">খোঁজা যায়নি — আবার চেষ্টা করুন</div>';
                resultsBox.classList.add('open');
            });
    }, 180);
});

searchInput.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    const first = resultsBox.querySelector('.cpay-result');
    if (first && !picked) {
        e.preventDefault();
        first.click();
    }
});

searchInput.addEventListener('focus', function () {
    if (this.value.trim() !== '') this.dispatchEvent(new Event('input'));
});

document.getElementById('searchCustomerBtn').addEventListener('click', function () {
    searchInput.focus();
    searchInput.dispatchEvent(new Event('input'));
});

resultsBox.addEventListener('click', function (e) {
    const item = e.target.closest('.cpay-result');
    if (!item) return;
    const id = Number(item.getAttribute('data-id'));
    const c = foundCustomers[id] || AME_CUSTOMERS.find(function (x) { return x.id === id; });
    if (c) {
        fillCustomer(c);
        resultsBox.classList.remove('open');
    }
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('.customer-search-wrap') && !e.target.closest('#searchCustomerBtn')) {
        resultsBox.classList.remove('open');
    }
});

amountInput.addEventListener('input', updateSummary);
if (discountInput) discountInput.addEventListener('input', updateSummary);
const accountSelect = document.getElementById('accountSelect');
if (accountSelect) accountSelect.addEventListener('change', updateSummary);

const methodTrigger = document.getElementById('methodTrigger');
const methodPanel = document.getElementById('methodPanel');
const methodValue = document.getElementById('methodValue');
const methodTriggerIcon = document.getElementById('methodTriggerIcon');
const methodTriggerLabel = document.getElementById('methodTriggerLabel');

function openMethodPanel() {
    methodPanel.classList.add('open');
    methodTrigger.classList.add('open');
    methodTrigger.setAttribute('aria-expanded', 'true');
}
function closeMethodPanel() {
    methodPanel.classList.remove('open');
    methodTrigger.classList.remove('open');
    methodTrigger.setAttribute('aria-expanded', 'false');
}
function selectPaymentMethod(opt) {
    methodValue.value = opt.getAttribute('data-value');
    methodTriggerIcon.textContent = opt.getAttribute('data-icon');
    methodTriggerLabel.textContent = opt.getAttribute('data-label');
    methodPanel.querySelectorAll('.method-option').forEach(function (o) { o.classList.remove('selected'); });
    opt.classList.add('selected');
    closeMethodPanel();
}
methodTrigger.addEventListener('click', function () {
    methodPanel.classList.contains('open') ? closeMethodPanel() : openMethodPanel();
});
methodTrigger.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); methodTrigger.click(); }
});
methodPanel.querySelectorAll('.method-option').forEach(function (opt) {
    opt.addEventListener('click', function () { selectPaymentMethod(this); });
});
document.addEventListener('click', function (e) {
    if (!e.target.closest('#methodDropdown')) { closeMethodPanel(); }
});

document.getElementById('dueToggle').addEventListener('click', function () {
    const isOpen = document.getElementById('dueBody').classList.toggle('open');
    this.classList.toggle('open', isOpen);
});

function paintWhatsapp(phone) {
    var href = window.ameWhatsappHref ? window.ameWhatsappHref(phone) : '';
    var node = document.getElementById('qaWhatsapp');
    if (node) {
        if (!href) {
            node.outerHTML = '<span id="qaWhatsapp" hidden><span class="qico">💬</span> WhatsApp</span>';
        } else {
            node.outerHTML = '<a id="qaWhatsapp" href="' + href + '" target="_blank" rel="noopener"><span class="qico">💬</span> WhatsApp</a>';
        }
    }
    var phoneNode = document.getElementById('cpayPhone');
    if (!phoneNode) return;
    var shown = String(phone || '').trim();
    if (!href || shown === '') {
        phoneNode.textContent = '📞 —';
        return;
    }
    phoneNode.textContent = '';
    phoneNode.appendChild(document.createTextNode('📞 '));
    var link = document.createElement('a');
    link.href = href;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = shown;
    phoneNode.appendChild(link);
}

function syncPostedAccount() {
    var sel = document.getElementById('accountSelect');
    var posted = document.getElementById('postedAccountId');
    if (!sel || !posted) return;
    var opt = sel.options[sel.selectedIndex];
    posted.value = opt && String(opt.value || '') !== '' ? String(opt.value) : '';
}

var accountSelectEl = document.getElementById('accountSelect');
if (accountSelectEl) {
    accountSelectEl.addEventListener('change', syncPostedAccount);
}
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.id !== 'paymentReceiveForm') return;
    syncPostedAccount();
}, true);
syncPostedAccount();

function validateCustomerPicked() {
    syncPostedAccount();
    if (!hiddenId.value) {
        cpaySay('অনুগ্রহ করে তালিকা থেকে একজন কাস্টমার বাছাই করুন।');
        searchInput.focus();
        return false;
    }
    const cash = parseFloat(amountInput.value || '0') || 0;
    const disc = Math.max(0, parseFloat((discountInput && discountInput.value) || '0') || 0);
    if (cash < 0 || disc < 0 || (cash + disc) <= 0) {
        cpaySay('পেমেন্ট বা ডিসকাউন্টের সঠিক পরিমাণ লিখুন।');
        amountInput.focus();
        return false;
    }
    const acc = document.getElementById('accountSelect');
    if (cash > 0 && (!acc || !String(acc.value || '').trim())) {
        cpaySay('টাকা যে অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।', { tone: 'warn' });
        if (acc) acc.focus();
        return false;
    }
    return true;
}

<?php
$preselected = null;
if ($preselect_id > 0) {
    foreach ($customers as $c) {
        if ((int)$c['id'] === $preselect_id) { $preselected = $c; break; }
    }
}
if ($preselected): ?>
fillCustomer(AME_CUSTOMERS.find(function (x) { return x.id === <?php echo (int)$preselected['id']; ?>; }));
<?php endif; ?>

function clearPickedCustomer() {
    picked = null;
    hiddenId.value = '';
    searchInput.value = '';
    document.getElementById('cpayName').textContent = 'কাস্টমার বাছাই করুন';
    const badge = document.getElementById('cpayBadge');
    badge.textContent = 'অপেক্ষমাণ';
    badge.classList.add('is-empty');
    document.getElementById('cpayCode').textContent = 'ID: —';
    document.getElementById('cpayPhone').textContent = '📞 —';
    paintWhatsapp('');
    document.getElementById('cpayAddr').textContent = '📍 —';
    paintGroup('');
    document.getElementById('cpayAvatar').textContent = '?';
    document.getElementById('cpayAccountTitle').textContent = 'বকেয়া / জমা হিসাব';
    document.getElementById('cpayAccountSub').textContent = 'কাস্টমার বাছাই করলে বকেয়া, জমা ও পরবর্তী তারিখ দেখাবে';
    document.getElementById('cpayAccountStatus').textContent = '—';
    document.getElementById('cpayAccountStatus').className = 'cpay-status';
    const notifyState = document.getElementById('cpayNotifyState');
    const notifyOff = document.getElementById('notifyOffInput');
    if (notifyState) notifyState.textContent = 'কাস্টমার বাছাই করলে নোটিফিকেশন অবস্থা দেখাবে';
    if (notifyOff) notifyOff.checked = false;
    const balanceBox = document.getElementById('cpdBalance');
    if (balanceBox) {
        balanceBox.textContent = '0.00';
        paintBalanceTone(balanceBox, 0);
    }
    loadPaymentDesk(0);
}

function afterQueuedPayment(form, ref) {
    form.reset();
    clearPickedCustomer();
    var cashOpt = document.querySelector('.method-option[data-value="CASH"]');
    if (cashOpt) selectPaymentMethod(cashOpt);
    if (form.querySelector('input[name="idempotency_token"]') && window.AmeOfflinePay) {
        form.querySelector('input[name="idempotency_token"]').value = AmeOfflinePay.makeRef();
    }
    updateSummary();
    setPayNetLabel();
    cpaySay('📡 নেট নেই — পেমেন্ট ফোনে সংরক্ষিত হয়েছে। নেট ফিরলে নিজে থেকে জমা হবে।\nরেফ: ' + ref);
}

if (window.AmeOfflinePay) {
    if (AME_CUSTOMERS.length > 0) AmeOfflinePay.saveCustomers(AME_CUSTOMERS);
    if (AmeOfflinePay.saveAccounts) AmeOfflinePay.saveAccounts(AME_ACCOUNTS);
}

function setPayNetLabel() {
    var el = document.getElementById('amePayNet');
    if (!el) return;
    var n = window.AmeOfflinePay ? AmeOfflinePay.pendingCount() : 0;
    if (navigator.onLine) {
        el.textContent = n ? (' · অনলাইন · ' + n + 'টা পেমেন্ট পাঠানো হচ্ছে') : ' · অনলাইন';
    } else {
        el.textContent = n ? (' · অফলাইন · ফোনে ' + n + 'টা অপেক্ষা করছে') : ' · অফলাইন — পেমেন্ট ফোনে জমা হবে';
    }
}
setPayNetLabel();
window.addEventListener('online', setPayNetLabel);
window.addEventListener('offline', setPayNetLabel);
window.addEventListener('ame-offline-pay-change', setPayNetLabel);

var cpaySending = false;
var CPAY_DUP_MS = 120000;

function cpayDupKey(cid, amt, discount) {
    return 'ame_pay_guard_' + String(cid)
        + '_' + (Number(amt) || 0).toFixed(2)
        + '_' + (Number(discount) || 0).toFixed(2);
}
function cpayRecentDup(cid, amt, discount) {
    try {
        var t = parseInt(sessionStorage.getItem(cpayDupKey(cid, amt, discount)) || '0', 10);
        return t > 0 && (Date.now() - t) < CPAY_DUP_MS;
    } catch (e) {
        return false;
    }
}
function cpayMarkPaid(cid, amt, discount) {
    try { sessionStorage.setItem(cpayDupKey(cid, amt, discount), String(Date.now())); } catch (e) {}
}

function depositConfirmText() {
    var amt = String((amountInput && amountInput.value) || '').trim();
    var sel = document.getElementById('accountSelect');
    var opt = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
    var raw = opt ? String(opt.textContent || '').trim() : '';
    var name = raw.replace(/\s*[\(\[·].*$/, '').trim();
    var lines = ['টাকা জমা হয়েছে'];
    if (amt !== '' && amt !== '0' && amt !== '0.00') {
        lines.push('৳' + amt);
    }
    if (name !== '') {
        lines.push(name);
    }
    return lines.join('\n');
}

document.getElementById('paymentReceiveForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    function stopWait() {
        if (window.AmeSubmitWait) window.AmeSubmitWait.hideIn(form);
    }
    if (!validateCustomerPicked()) {
        stopWait();
        return;
    }
    var amtNow = parseFloat(amountInput.value || '0') || 0;
    var discNow = parseFloat((discountInput && discountInput.value) || '0') || 0;
    if (cpayRecentDup(hiddenId.value, amtNow, discNow)) {
        stopWait();
        cpaySay('একই কাস্টমারের এই পেমেন্ট গত ২ মিনিটেই নেওয়া হয়েছে। আবার কাটেনি। দুই মিনিট পর নিন।');
        return;
    }
    if (cpaySending) {
        return;
    }
    if (!window.AmeOfflinePay || !AmeOfflinePay.sendForm) {
        if (!navigator.onLine) {
            stopWait();
            cpaySay('অফলাইন কিউ লোড হয়নি। হোম পেজ খুলে আবার চেষ্টা করুন।');
            return;
        }
        cpayMarkPaid(hiddenId.value, amtNow, discNow);
        form.submit();
        return;
    }
    cpaySending = true;
    if (window.AmeSubmitWait) window.AmeSubmitWait.show(document.getElementById('cpayConfirmBtn'));
    var payReq = AmeOfflinePay.sendForm(form);
    if (window.AmeSubmitWait && window.AmeSubmitWait.follow) {
        payReq = AmeSubmitWait.follow(payReq);
    }
    payReq.then(function (res) {
        cpaySending = false;
        if (res && res.ok && res.payment_id) {
            cpayMarkPaid(hiddenId.value, amtNow, discNow);
            stopWait();
            var receiptId = res.payment_id;
            cpaySay(depositConfirmText(), {
                tone: 'ok',
                after: function () {
                    window.location.href = 'receipt.php?payment_id=' + receiptId;
                }
            });
            return;
        }
        if (res && res.duplicate) {
            cpayMarkPaid(hiddenId.value, amtNow, discNow);
            cpaySay(res.error || 'এক মিনিটের মধ্যে ডাবল পেমেন্ট আটকানো হয়েছে।');
            if (res.payment_id) {
                window.location.href = 'receipt.php?payment_id=' + res.payment_id;
            }
            stopWait();
            return;
        }
        if (res && res.queued) {
            stopWait();
            afterQueuedPayment(form, res.ref);
            return;
        }
        stopWait();
        cpaySay((res && res.error) ? res.error : 'পেমেন্ট যায়নি। আবার চেষ্টা করুন।');
    }).catch(function () {
        cpaySending = false;
        stopWait();
        cpaySay('পেমেন্ট যায়নি। আবার চেষ্টা করুন।');
    });
});

if (navigator.onLine) {
    fetch('/offline-payment.html', { credentials: 'same-origin' }).catch(function () {});
    fetch('/assets/css/customer-payment.css?v=<?php echo htmlspecialchars($cpay_ver, ENT_QUOTES, 'UTF-8'); ?>', { credentials: 'same-origin' }).catch(function () {});
    fetch('/assets/js/offline-payment-queue.js?v=<?php echo htmlspecialchars($cpay_ver, ENT_QUOTES, 'UTF-8'); ?>', { credentials: 'same-origin' }).catch(function () {});
}

updateSummary();
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
<style id="cpay-white-535">
html body.ame-premium-ui.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page),
html body.ame-saas-os.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page),
html body.ame-if-dash.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page),
html body.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page),
html body.ame-premium-ui.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page) #main-content {
    background: #ffffff !important;
    background-color: #ffffff !important;
    background-image: none !important;
}
html body.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page) #main-content #cpayNoticeBtn {
    background: #C2410C !important;
    background-color: #C2410C !important;
    background-image: none !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    border: 0 !important;
    border-radius: 28px !important;
    font-weight: 800 !important;
}
html body.ame-customer-payment-page:not(.ame-sales-pro):not(.ame-pos-terminal-page) #main-content #cpayNoticeBtn.is-ok {
    background: #04933A !important;
    background-color: #04933A !important;
}
</style>
