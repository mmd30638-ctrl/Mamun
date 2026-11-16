<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accounts = ame_active_accounts($db);
$customers_res = $db->query("SELECT id, name, phone, customer_code FROM customers WHERE is_customer = 1 AND " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1') . " ORDER BY name ASC LIMIT 500");
$customers = $customers_res ? $customers_res->fetch_all(MYSQLI_ASSOC) : [];

$products_res = $db->query('SELECT id, name, sale_price, stock FROM products WHERE ' . ame_product_option_sql('status') . ' ORDER BY name ASC LIMIT 1000');
$products = $products_res ? $products_res->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../../../partials/header.php';
?>
<style>
    .mr-wrap { padding: 15px; max-width: 620px; margin: 0 auto; }
    .mr-card { background:#fff; border-radius:18px; box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:visible; }
    .mr-header { background: linear-gradient(135deg, #0B1E4D, #0091EA); color:#fff; padding: 20px 24px; border-radius:18px 18px 0 0; }
    .mr-header h2 { margin:0; font-size:18px; }
    .mr-body { padding: 22px 24px 26px; }

    .mr-group { margin-bottom: 16px; position: relative; }
    .mr-group label { display:block; font-weight:700; font-size:12.5px; color:#0B1E4D; margin-bottom:6px; }
    .mr-group input, .mr-group select, .mr-group textarea {
        width:100%; padding:11px 13px; border:1.5px solid #CFE3F7; border-radius:10px; font-size:14px; box-sizing:border-box;
    }
    .mr-group input:focus, .mr-group select:focus, .mr-group textarea:focus {
        outline:none; border-color:#0091EA; box-shadow:0 0 0 4px rgba(0,145,234,0.12);
    }

    .mr-results {
        position:absolute; left:0; right:0; top:calc(100% + 4px); background:#fff;
        border:1.5px solid #CFE3F7; border-radius:12px; box-shadow:0 12px 28px rgba(11,30,77,0.15);
        max-height:220px; overflow-y:auto; z-index:30; display:none;
    }
    .mr-results.open { display:block; }
    .mr-result-item { padding:9px 13px; cursor:pointer; border-bottom:1px solid #F1F5F9; font-size:13px; }
    .mr-result-item:hover { background:#EAF4FF; }
    .mr-result-item small { color:#64748B; display:block; font-size:11px; }

    .mr-selected-chip {
        display:none; align-items:center; justify-content:space-between; gap:8px;
        background:#EAF4FF; border:1.5px solid #CFE3F7; border-radius:10px; padding:9px 12px; margin-top:8px; font-size:13px;
    }
    .mr-selected-chip.show { display:flex; }
    .mr-selected-chip button { border:none; background:none; color:#C62828; font-weight:800; cursor:pointer; }

    .mr-row { display:flex; gap:10px; }
    .mr-row .mr-group { flex:1; }

    .mr-total-box { background:#0A0E27; color:#fff; border-radius:12px; padding:12px; text-align:center; margin-bottom:16px; }
    .mr-total-box .amt { font-size:20px; font-weight:800; color:#22D3EE; }

    .mr-submit-btn { width:100%; padding:14px; border:none; border-radius:12px; background: linear-gradient(135deg,#0B1E4D,#0091EA); color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
</style>

<div class="mr-wrap">
    <div class="mr-card">
        <div class="mr-header"><h2>✍️ Manual Return (ইনভয়েস ছাড়া)</h2></div>
        <div class="mr-body">
            <?php if (!empty($_SESSION['error'])): ?>
                <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="actions/save_manual_return.php" method="POST" id="manualForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="customer_id" id="customerIdInput">
                <input type="hidden" name="customer_name" id="customerNameInput" value="রিটেল কাস্টমার">
                <input type="hidden" name="product_id" id="productIdInput" required>
                <input type="hidden" name="product_name" id="productNameInput">

                <div class="mr-group">
                    <label>👤 কাস্টমার (ঐচ্ছিক — Retail হলে খালি রাখুন)</label>
                    <input type="text" id="custSearch" autocomplete="off" placeholder="নাম বা ফোন দিয়ে খুঁজুন...">
                    <div class="mr-results" id="custResults"></div>
                    <div class="mr-selected-chip" id="custChip">
                        <span id="custChipText"></span>
                        <button type="button" onclick="clearCustomer()">✕</button>
                    </div>
                </div>

                <div class="mr-group">
                    <label>📦 পণ্য *</label>
                    <input type="text" id="prodSearch" autocomplete="off" placeholder="পণ্যের নাম দিয়ে খুঁজুন...">
                    <div class="mr-results" id="prodResults"></div>
                    <div class="mr-selected-chip" id="prodChip">
                        <span id="prodChipText"></span>
                        <button type="button" onclick="clearProduct()">✕</button>
                    </div>
                </div>

                <div class="mr-row">
                    <div class="mr-group">
                        <label>পরিমাণ *</label>
                        <input type="number" name="qty" id="qtyInput" step="0.01" min="0.01" required oninput="calcTotal()">
                    </div>
                    <div class="mr-group">
                        <label>রিটার্ন মূল্য (প্রতি ইউনিট) *</label>
                        <input type="number" name="unit_price" id="priceInput" step="0.01" min="0" required oninput="calcTotal()">
                    </div>
                </div>

                <div class="mr-total-box">
                    <div style="font-size:11px; opacity:.8;">মোট রিটার্ন পরিমাণ</div>
                    <div class="amt" id="totalDisplay">৳0.00</div>
                </div>

                <div class="mr-row">
                    <div class="mr-group">
                        <label>বিক্রয়ের ধরন</label>
                        <select name="sales_type">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                </div>

                <div class="mr-group">
                    <label>💳 কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস (−) হবে *</label>
                    <input type="hidden" name="refund_method" id="mrRefundMethod" value="cash_refund">
                    <input type="hidden" name="posted_account_id" id="mrPostedAccountId" value="">
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" id="mrAccountSelect">
                        <?php echo ame_render_account_options($accounts, 0, 'যে অ্যাকাউন্ট থেকে ফেরত যাবে'); ?>
                    </select>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                        <?php foreach ($accounts as $acc):
                            $accId = (int)($acc['id'] ?? 0);
                            if ($accId < 1) continue;
                            $accName = trim((string)($acc['account_name'] ?? ''));
                            $accType = ame_account_payment_method($acc);
                            $accMethod = $accType === 'bank' ? 'bank_transfer' : ($accType === 'mobile' ? 'mobile_banking' : 'cash_refund');
                            $accIcon = $accType === 'bank' ? '🏦' : ($accType === 'mobile' ? '📱' : '💵');
                        ?>
                        <button type="button" class="mr-acc-btn" data-acc-id="<?php echo $accId; ?>" data-method="<?php echo $accMethod; ?>" onclick="pickMrAccount(<?php echo $accId; ?>, '<?php echo $accMethod; ?>', this)" style="padding:7px 11px; border:1.5px solid #CFE3F7; border-radius:8px; background:#fff; color:#0B1E4D; font-weight:700; font-size:12px; cursor:pointer;">
                            <?php echo $accIcon . ' ' . htmlspecialchars($accName, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <?php endforeach; ?>
                        <button type="button" class="mr-acc-btn" id="mrCustBalanceBtn" data-method="customer_balance" onclick="pickMrCustomerBalance(this)" style="padding:7px 11px; border:1.5px solid #CFE3F7; border-radius:8px; background:#fff; color:#0B1E4D; font-weight:700; font-size:12px; cursor:pointer;">
                            👤 গ্রাহক ব্যালেন্স সমন্বয়
                        </button>
                    </div>
                </div>

                <div class="mr-group">
                    <label>ফেরতের কারণ *</label>
                    <textarea name="reason" rows="2" required maxlength="255" placeholder="যেমন: পণ্য ত্রুটিপূর্ণ"></textarea>
                </div>

                <button type="submit" class="mr-submit-btn">✅ বিক্রয় ফেরত সংরক্ষণ করুন</button>
            </form>

            <div style="margin-top:16px; text-align:center;">
                <a href="dashboard.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← ড্যাশবোর্ডে ফিরে যান</a>
            </div>
        </div>
    </div>
</div>

<script>
var CUSTOMERS = <?php echo json_encode($customers, JSON_UNESCAPED_UNICODE); ?>;
var PRODUCTS = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;

function setupSearch(inputId, resultsId, dataset, onSelect, formatFn) {
    var input = document.getElementById(inputId);
    var results = document.getElementById(resultsId);

    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        if (q === '') { results.classList.remove('open'); return; }

        var matches = dataset.filter(function (item) {
            return (item.name || '').toLowerCase().indexOf(q) !== -1 ||
                (item.phone || '').indexOf(q) !== -1 ||
                String(item.id).indexOf(q) !== -1;
        }).slice(0, 8);

        if (matches.length === 0) {
            results.innerHTML = '<div class="mr-result-item" style="color:#94A3B8;">কোনো ফলাফল নেই</div>';
        } else {
            results.innerHTML = matches.map(function (item, i) {
                return '<div class="mr-result-item" data-i="' + dataset.indexOf(item) + '">' + formatFn(item) + '</div>';
            }).join('');
        }
        results.classList.add('open');

        results.querySelectorAll('.mr-result-item[data-i]').forEach(function (el) {
            el.addEventListener('click', function () {
                var item = dataset[parseInt(el.getAttribute('data-i'), 10)];
                onSelect(item);
                results.classList.remove('open');
            });
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#' + inputId) && !e.target.closest('#' + resultsId)) {
            results.classList.remove('open');
        }
    });
}

setupSearch('custSearch', 'custResults', CUSTOMERS, function (c) {
    document.getElementById('customerIdInput').value = c.id;
    document.getElementById('customerNameInput').value = c.name;
    document.getElementById('custSearch').value = '';
    document.getElementById('custChipText').textContent = c.name + ' (' + (c.phone || 'N/A') + ')';
    document.getElementById('custChip').classList.add('show');
}, function (c) { return c.name + '<small>' + (c.phone || '') + ' • ' + c.customer_code + '</small>'; });

setupSearch('prodSearch', 'prodResults', PRODUCTS, function (p) {
    document.getElementById('productIdInput').value = p.id;
    document.getElementById('productNameInput').value = p.name;
    document.getElementById('prodSearch').value = '';
    document.getElementById('prodChipText').textContent = p.name + ' (স্টক: ' + p.stock + ')';
    document.getElementById('prodChip').classList.add('show');
    document.getElementById('priceInput').value = p.sale_price;
    calcTotal();
}, function (p) { return p.name + '<small>ID: ' + p.id + ' • মূল্য: ৳' + p.sale_price + ' • স্টক: ' + p.stock + '</small>'; });

function clearCustomer() {
    document.getElementById('customerIdInput').value = '';
    document.getElementById('customerNameInput').value = 'রিটেল কাস্টমার';
    document.getElementById('custChip').classList.remove('show');
}
function clearProduct() {
    document.getElementById('productIdInput').value = '';
    document.getElementById('productNameInput').value = '';
    document.getElementById('prodChip').classList.remove('show');
}

function calcTotal() {
    var qty = parseFloat(document.getElementById('qtyInput').value) || 0;
    var price = parseFloat(document.getElementById('priceInput').value) || 0;
    document.getElementById('totalDisplay').textContent = '৳' + (qty * price).toFixed(2);
}

function pickMrAccount(accId, method, btn) {
    var sel = document.getElementById('mrAccountSelect');
    if (sel) {
        sel.setAttribute('data-ame-user', '1');
        sel.setAttribute('data-ame-locked', String(accId));
        sel.value = String(accId);
    }
    document.getElementById('mrPostedAccountId').value = String(accId);
    document.getElementById('mrRefundMethod').value = method;
    document.querySelectorAll('.mr-acc-btn').forEach(function (b) {
        var on = (b === btn);
        b.style.borderColor = on ? '#0091EA' : '#CFE3F7';
        b.style.background = on ? '#EAF4FF' : '#fff';
    });
}

function pickMrCustomerBalance(btn) {
    var sel = document.getElementById('mrAccountSelect');
    if (sel) {
        sel.setAttribute('data-ame-user', '1');
        sel.setAttribute('data-ame-locked', '');
        sel.value = '';
    }
    document.getElementById('mrPostedAccountId').value = '';
    document.getElementById('mrRefundMethod').value = 'customer_balance';
    document.querySelectorAll('.mr-acc-btn').forEach(function (b) {
        var on = (b === btn);
        b.style.borderColor = on ? '#0091EA' : '#CFE3F7';
        b.style.background = on ? '#EAF4FF' : '#fff';
    });
}

document.getElementById('mrAccountSelect').addEventListener('change', function () {
    var val = String(this.value || '').trim();
    document.getElementById('mrPostedAccountId').value = val;
    if (val !== '') {
        var btn = document.querySelector('.mr-acc-btn[data-acc-id="' + val + '"]');
        document.getElementById('mrRefundMethod').value = btn ? btn.getAttribute('data-method') : 'cash_refund';
        document.querySelectorAll('.mr-acc-btn').forEach(function (b) {
            var on = (b === btn);
            b.style.borderColor = on ? '#0091EA' : '#CFE3F7';
            b.style.background = on ? '#EAF4FF' : '#fff';
        });
    }
});

document.getElementById('manualForm').addEventListener('submit', function (e) {
    if (!document.getElementById('productIdInput').value) {
        e.preventDefault();
        alert('একটা পণ্য নির্বাচন করুন।');
        return;
    }
    var sel = document.getElementById('mrAccountSelect');
    var posted = document.getElementById('mrPostedAccountId');
    var cid = document.getElementById('customerIdInput');
    var method = document.getElementById('mrRefundMethod');
    var chosenAcc = (sel && sel.value) ? sel.value : (posted && posted.value ? posted.value : '');
    if ((!cid || !cid.value) && !chosenAcc) {
        e.preventDefault();
        alert('⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।');
        return;
    }
    if (method && method.value !== 'customer_balance' && !chosenAcc) {
        e.preventDefault();
        alert('⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।');
        return;
    }
    var name = document.getElementById('customerNameInput');
    if (name && name.value.trim() === '') {
        name.value = 'রিটেল কাস্টমার';
    }
});
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
