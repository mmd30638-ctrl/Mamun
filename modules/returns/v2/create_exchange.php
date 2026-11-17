<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../sales_return_module.php';
include __DIR__ . '/../../../partials/header.php';
ame_sales_return_include_nav('exchange');
?>
<style>
    .ex-wrap { padding: 15px; max-width: 640px; margin: 0 auto; }
    .ex-card { background:#fff; border-radius:18px; box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:visible; }
    .ex-header { background: linear-gradient(135deg, #00838F, #26C6DA); color:#fff; padding: 20px 24px; border-radius:18px 18px 0 0; }
    .ex-header h2 { margin:0; font-size:18px; }
    .ex-body { padding: 22px 24px 26px; }

    .ex-section { border:1.5px solid #EAF4FF; border-radius:14px; padding:14px; margin-bottom:16px; }
    .ex-section-title { font-weight:800; font-size:13.5px; margin-bottom:12px; }
    .ex-section-title.returned { color:#C62828; }
    .ex-section-title.replacement { color:#0B8043; }

    .ex-group { margin-bottom: 12px; position: relative; }
    .ex-group label { display:block; font-weight:700; font-size:11.5px; color:#0B1E4D; margin-bottom:5px; }
    .ex-group input, .ex-group select, .ex-group textarea {
        width:100%; padding:10px 12px; border:1.5px solid #CFE3F7; border-radius:9px; font-size:13.5px; box-sizing:border-box;
    }
    .ex-row { display:flex; gap:10px; }
    .ex-row .ex-group { flex:1; }

    .ex-results {
        position:absolute; left:0; right:0; top:calc(100% + 4px); background:#fff;
        border:1.5px solid #CFE3F7; border-radius:12px; box-shadow:0 12px 28px rgba(11,30,77,0.15);
        max-height:min(52vh, 320px); overflow-y:auto; z-index:1200; display:none;
        -webkit-overflow-scrolling: touch;
    }
    .ex-results.open { display:block; }
    .ex-result-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .ex-result-item:hover { background:#EAF4FF; }
    .ex-result-item small { color:#64748B; display:block; font-size:10.5px; }

    .ex-selected-chip {
        display:none; align-items:center; justify-content:space-between; gap:8px;
        background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:9px; padding:8px 11px; margin-top:6px; font-size:12.5px;
    }
    .ex-selected-chip.show { display:flex; }
    .ex-selected-chip button { border:none; background:none; color:#C62828; font-weight:800; cursor:pointer; }

    .ex-calc-box { background:#0A0E27; color:#fff; border-radius:14px; padding:16px; margin-bottom:16px; }
    .ex-calc-row { display:flex; justify-content:space-between; padding:5px 0; font-size:12.5px; border-bottom:1px solid rgba(255,255,255,0.1); }
    .ex-calc-final { font-size:17px; font-weight:800; margin-top:8px; text-align:center; }
    .ex-calc-final.pay { color:#FF8A65; }
    .ex-calc-final.credit { color:#69F0AE; }
    .ex-balance-box { margin-top:10px; padding-top:10px; border-top:1px dashed rgba(255,255,255,0.25); font-size:12px; display:none; }
    .ex-balance-box.show { display:block; }

    .ex-submit-btn { width:100%; padding:14px; border:none; border-radius:12px; background: linear-gradient(135deg,#00838F,#26C6DA); color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
    .ex-price-modes { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0 0 12px; }
    .ex-price-modes > span { font-size:12px; font-weight:800; width:100%; color:#0B1E4D; }
    .ex-price-modes button { flex:1; min-width:0; min-height:38px; padding:6px 8px; border-radius:10px; border:1.5px solid #CFE3F7; background:#fff; font-weight:800; font-size:12px; cursor:pointer; }
    .ex-price-modes button.is-on { border-color:#00838F; background:#E0F7FA; color:#006064; }
    .ex-price-note { display:none; margin:0 0 10px; font-size:11px; font-weight:700; color:#B45309; }
    .ex-price-note.show { display:block; }
</style>

<div class="ex-wrap">
    <div class="ex-card">
        <div class="ex-header"><h2>🔄 Product Exchange</h2></div>
        <div class="ex-body">
            <?php if (!empty($_SESSION['error'])): ?>
                <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="actions/save_exchange.php" method="POST" id="exchangeForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="customer_id" id="customerIdInput">
                <input type="hidden" name="customer_name" id="customerNameInput" required>
                <input type="hidden" name="old_product_id" id="oldProductIdInput" required>
                <input type="hidden" name="new_product_id" id="newProductIdInput" required>

                <div class="ex-group">
                    <label>👤 কাস্টমার (ঐচ্ছিক)</label>
                    <input type="text" id="custSearch" autocomplete="off" placeholder="নাম বা ফোন দিয়ে খুঁজুন...">
                    <div class="ex-results" id="custResults"></div>
                    <div class="ex-selected-chip" id="custChip">
                        <span id="custChipText"></span>
                        <button type="button" onclick="clearCustomer()">✕</button>
                    </div>
                </div>

                <div class="ex-price-modes" id="exPriceModes" role="group" aria-label="ফেরত/বদলির দামের ধরন">
                    <span>ফেরত ও বদলির দাম (উভয় পণ্য)</span>
                    <button type="button" data-ex-price="wholesale">পাইকারি</button>
                    <button type="button" data-ex-price="retail" class="is-on">খুচরা</button>
                    <button type="button" data-ex-price="dealer">ডিলার</button>
                    <button type="button" data-ex-price="price">প্রাইস</button>
                </div>
                <p class="ex-price-note" id="exPriceNote" role="status"></p>

                <div class="ex-section">
                    <div class="ex-section-title returned">↩️ কাস্টমার ফেরত দিচ্ছে</div>
                    <div class="ex-group">
                        <label>পণ্য *</label>
                        <input type="text" id="oldProdSearch" autocomplete="off" placeholder="পণ্যের নাম দিয়ে খুঁজুন...">
                        <div class="ex-results" id="oldProdResults"></div>
                        <div class="ex-selected-chip" id="oldProdChip">
                            <span id="oldProdChipText"></span>
                            <button type="button" onclick="clearOldProduct()">✕</button>
                        </div>
                    </div>
                    <div class="ex-row">
                        <div class="ex-group"><label>পরিমাণ *</label><input type="number" name="old_qty" id="oldQtyInput" step="0.01" min="0.01" required oninput="calcExchange()"></div>
                        <div class="ex-group"><label>মূল্য/ইউনিট *</label><input type="number" name="old_price" id="oldPriceInput" step="0.01" min="0" required oninput="calcExchange()"></div>
                    </div>
                </div>

                <div class="ex-section">
                    <div class="ex-section-title replacement">🎁 কাস্টমারকে দেওয়া হবে</div>
                    <div class="ex-group">
                        <label>পণ্য *</label>
                        <input type="text" id="newProdSearch" autocomplete="off" placeholder="পণ্যের নাম দিয়ে খুঁজুন...">
                        <div class="ex-results" id="newProdResults"></div>
                        <div class="ex-selected-chip" id="newProdChip">
                            <span id="newProdChipText"></span>
                            <button type="button" onclick="clearNewProduct()">✕</button>
                        </div>
                    </div>
                    <div class="ex-row">
                        <div class="ex-group"><label>পরিমাণ *</label><input type="number" name="new_qty" id="newQtyInput" step="0.01" min="0.01" required oninput="calcExchange()"></div>
                        <div class="ex-group"><label>মূল্য/ইউনিট *</label><input type="number" name="new_price" id="newPriceInput" step="0.01" min="0" required oninput="calcExchange()"></div>
                    </div>
                </div>

                <div class="ex-calc-box">
                    <div class="ex-calc-row"><span>কাস্টমার ফেরত দিচ্ছে (মূল্য)</span><span id="oldValueDisplay">৳0.00</span></div>
                    <div class="ex-calc-row"><span>কাস্টমারকে দেওয়া হবে (মূল্য)</span><span id="newValueDisplay">৳0.00</span></div>
                    <div class="ex-calc-final" id="finalResultDisplay">সমান মূল্য — কোনো সমন্বয় লাগবে না</div>
                    <div class="ex-balance-box" id="exBalanceBox">
                        <div class="ex-calc-row"><span>সাবেক বকেয়া / ব্যালেন্স</span><span id="exPrevDue">—</span></div>
                        <div class="ex-calc-row"><span>এক্সচেঞ্জ সমন্বয় (+/−)</span><span id="exAdjLine">৳0.00</span></div>
                        <div class="ex-calc-row" style="font-weight:800;"><span>মেমো শেষে সর্বমোট</span><span id="exAfterDue">—</span></div>
                    </div>
                </div>
                <input type="hidden" name="adjustment" id="adjustmentInput" value="0">

                <input type="hidden" name="sales_type" id="salesTypeInput" value="retail">

                <div class="ex-group">
                    <label>এক্সচেঞ্জের কারণ *</label>
                    <textarea name="reason" rows="2" required maxlength="255" placeholder="যেমন: সাইজ পরিবর্তন, রং পরিবর্তন"></textarea>
                </div>

                <button type="submit" class="ex-submit-btn">✅ এক্সচেঞ্জ সম্পন্ন করুন (স্টক + ব্যালেন্স)</button>
            </form>

            <div style="margin-top:16px; text-align:center;">
                <a href="dashboard.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← ড্যাশবোর্ডে ফিরে যান</a>
            </div>
        </div>
    </div>
</div>

<script>
var exState = { priceType: 'retail', oldP: null, newP: null, custBalance: null };

function escEx(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function exFmtBal(n) {
    var v = Number(n) || 0;
    if (v > 0.009) return 'বকেয়া ৳' + v.toFixed(2);
    if (v < -0.009) return 'জমা ৳' + Math.abs(v).toFixed(2);
    return '৳0';
}
function exUnitPrice(p, type) {
    if (!p) return 0;
    if (type === 'wholesale') return Number(p.wholesale_price) || 0;
    if (type === 'dealer') return Number(p.dealer_price) || 0;
    if (type === 'price') return Number(p.ask_price) || Number(p.sale_price) || 0;
    return Number(p.sale_price) || Number(p.retail_price) || 0;
}
function exNormProduct(row) {
    return {
        id: row.id,
        name: row.name,
        sale_price: Number(row.sale_price) || 0,
        retail_price: Number(row.sale_price) || 0,
        wholesale_price: Number(row.wholesale_price) || 0,
        dealer_price: Number(row.dealer_price) || 0,
        stock: Number(row.stock) || 0,
        ask_price: Number(row.sale_price) || 0
    };
}
function exApplyPricesToInputs() {
    var t = exState.priceType;
    var ro = t !== 'price';
    ['old', 'new'].forEach(function (side) {
        var p = side === 'old' ? exState.oldP : exState.newP;
        var inp = document.getElementById(side + 'PriceInput');
        if (!inp || !p) return;
        var u = exUnitPrice(p, t);
        if (t !== 'price' && u > 0) p.ask_price = u;
        inp.readOnly = ro;
        if (document.activeElement !== inp || t !== 'price') {
            inp.value = (u > 0 ? u : p.ask_price || 0).toFixed(2);
        }
    });
    var note = document.getElementById('exPriceNote');
    if (note && t !== 'price') {
        var miss = (exState.oldP && exUnitPrice(exState.oldP, t) <= 0) || (exState.newP && exUnitPrice(exState.newP, t) <= 0);
        if (miss) { note.textContent = 'কিছু পণ্যে ওই দাম সেট নেই — «প্রাইস» বা অন্য ধরন বেছে নিন।'; note.classList.add('show'); }
        else { note.textContent = ''; note.classList.remove('show'); }
    }
    document.getElementById('salesTypeInput').value = (t === 'retail') ? 'retail' : 'wholesale';
    calcExchange();
}
function exSetPriceType(type) {
    exState.priceType = type;
    document.querySelectorAll('#exPriceModes [data-ex-price]').forEach(function (btn) {
        btn.classList.toggle('is-on', btn.getAttribute('data-ex-price') === type);
    });
    exApplyPricesToInputs();
}
document.getElementById('exPriceModes').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-ex-price]');
    if (btn) exSetPriceType(btn.getAttribute('data-ex-price'));
});
document.getElementById('oldPriceInput').addEventListener('input', function () {
    if (exState.oldP && exState.priceType === 'price') exState.oldP.ask_price = parseFloat(this.value) || 0;
    calcExchange();
});
document.getElementById('newPriceInput').addEventListener('input', function () {
    if (exState.newP && exState.priceType === 'price') exState.newP.ask_price = parseFloat(this.value) || 0;
    calcExchange();
});

function setupRemoteSearch(inputId, resultsId, kind, onSelect, formatRow) {
    var input = document.getElementById(inputId);
    var results = document.getElementById(resultsId);
    var timer = null;
    var rows = [];
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 1) { results.classList.remove('open'); results.innerHTML = ''; return; }
        timer = setTimeout(function () {
            fetch('actions/search_lookup.php?kind=' + encodeURIComponent(kind) + '&q=' + encodeURIComponent(q) + '&limit=60', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    rows = data.rows || [];
                    results.innerHTML = rows.length
                        ? rows.map(function (row, i) {
                            return '<div class="ex-result-item" data-ri="' + i + '">' + formatRow(row) + '</div>';
                        }).join('')
                        : '<div class="ex-result-item" style="color:#94A3B8;cursor:default;">মিল নেই — আরেকটু লিখুন</div>';
                    results.classList.add('open');
                });
        }, 160);
    });
    results.addEventListener('click', function (ev) {
        var el = ev.target.closest('[data-ri]');
        if (!el) return;
        var row = rows[Number(el.getAttribute('data-ri'))];
        if (row) onSelect(row);
        results.classList.remove('open');
        input.value = '';
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#' + inputId) && !e.target.closest('#' + resultsId)) {
            results.classList.remove('open');
        }
    });
}

setupRemoteSearch('custSearch', 'custResults', 'customer', function (c) {
    document.getElementById('customerIdInput').value = c.id;
    document.getElementById('customerNameInput').value = c.name;
    exState.custBalance = Number(c.balance);
    document.getElementById('custChipText').textContent = c.name + ' (' + (c.phone || 'N/A') + ') · ' + exFmtBal(c.balance);
    document.getElementById('custChip').classList.add('show');
    calcExchange();
}, function (c) {
    return escEx(c.name) + '<small>' + escEx(c.phone || '') + (c.code ? ' • ' + escEx(c.code) : '') + ' · ' + exFmtBal(c.balance) + '</small>';
});

setupRemoteSearch('oldProdSearch', 'oldProdResults', 'product', function (row) {
    exState.oldP = exNormProduct(row);
    document.getElementById('oldProductIdInput').value = exState.oldP.id;
    document.getElementById('oldProdChipText').textContent = exState.oldP.name;
    document.getElementById('oldProdChip').classList.add('show');
    exApplyPricesToInputs();
}, function (p) {
    return escEx(p.name) + '<small>খু ৳' + Number(p.sale_price || 0).toFixed(0)
        + ' · পা ৳' + Number(p.wholesale_price || 0).toFixed(0)
        + ' · ডি ৳' + Number(p.dealer_price || 0).toFixed(0) + '</small>';
});

setupRemoteSearch('newProdSearch', 'newProdResults', 'product', function (row) {
    exState.newP = exNormProduct(row);
    document.getElementById('newProductIdInput').value = exState.newP.id;
    document.getElementById('newProdChipText').textContent = exState.newP.name + ' (স্টক: ' + exState.newP.stock + ')';
    document.getElementById('newProdChip').classList.add('show');
    exApplyPricesToInputs();
}, function (p) {
    return escEx(p.name) + '<small>স্টক ' + Number(p.stock || 0).toFixed(2)
        + ' · খু ৳' + Number(p.sale_price || 0).toFixed(0)
        + ' · পা ৳' + Number(p.wholesale_price || 0).toFixed(0)
        + ' · ডি ৳' + Number(p.dealer_price || 0).toFixed(0) + '</small>';
});

function clearCustomer() {
    exState.custBalance = null;
    document.getElementById('customerIdInput').value = '';
    document.getElementById('customerNameInput').value = '';
    document.getElementById('custChip').classList.remove('show');
    calcExchange();
}
function clearOldProduct() {
    exState.oldP = null;
    document.getElementById('oldProductIdInput').value = '';
    document.getElementById('oldProdChip').classList.remove('show');
    calcExchange();
}
function clearNewProduct() {
    exState.newP = null;
    document.getElementById('newProductIdInput').value = '';
    document.getElementById('newProdChip').classList.remove('show');
    calcExchange();
}

function calcExchange() {
    var oldQty = parseFloat(document.getElementById('oldQtyInput').value) || 0;
    var oldPrice = parseFloat(document.getElementById('oldPriceInput').value) || 0;
    var newQty = parseFloat(document.getElementById('newQtyInput').value) || 0;
    var newPrice = parseFloat(document.getElementById('newPriceInput').value) || 0;

    var oldValue = oldQty * oldPrice;
    var newValue = newQty * newPrice;
    var diff = newValue - oldValue;

    document.getElementById('oldValueDisplay').textContent = '৳' + oldValue.toFixed(2);
    document.getElementById('newValueDisplay').textContent = '৳' + newValue.toFixed(2);
    document.getElementById('adjustmentInput').value = diff.toFixed(2);

    var resultEl = document.getElementById('finalResultDisplay');
    if (diff > 0.005) {
        resultEl.className = 'ex-calc-final pay';
        resultEl.textContent = 'কাস্টমার বাড়তি প্রদান করবেন: ৳' + diff.toFixed(2);
    } else if (diff < -0.005) {
        resultEl.className = 'ex-calc-final credit';
        resultEl.textContent = 'কাস্টমার ক্রেডিট পাবেন: ৳' + Math.abs(diff).toFixed(2);
    } else {
        resultEl.className = 'ex-calc-final';
        resultEl.textContent = 'সমান মূল্য — কোনো সমন্বয় লাগবে না';
    }

    var balBox = document.getElementById('exBalanceBox');
    var cid = parseInt(document.getElementById('customerIdInput').value || '0', 10);
    if (balBox && cid > 0 && exState.custBalance !== null) {
        var prev = Number(exState.custBalance) || 0;
        var after = prev + diff;
        balBox.classList.add('show');
        document.getElementById('exPrevDue').textContent = exFmtBal(prev);
        document.getElementById('exAdjLine').textContent = (diff >= 0 ? '+' : '') + '৳' + diff.toFixed(2);
        document.getElementById('exAfterDue').textContent = exFmtBal(after);
    } else if (balBox) {
        balBox.classList.remove('show');
    }
}

document.getElementById('exchangeForm').addEventListener('submit', function (e) {
    if (!document.getElementById('oldProductIdInput').value || !document.getElementById('newProductIdInput').value) {
        e.preventDefault();
        alert('ফেরত ও বদলি — দুটো পণ্যই নির্বাচন করুন।');
    }
});

(function () {
    var params = new URLSearchParams(window.location.search);
    var cid = parseInt(params.get('customer_id') || '0', 10);
    if (cid < 1) return;
    fetch('actions/search_lookup.php?kind=customer&q=' + cid + '&limit=5', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var found = (data.rows || []).find(function (c) { return Number(c.id) === cid; }) || (data.rows || [])[0];
            if (!found) return;
            document.getElementById('customerIdInput').value = found.id;
            document.getElementById('customerNameInput').value = found.name;
            exState.custBalance = Number(found.balance);
            document.getElementById('custChipText').textContent = found.name + ' (' + (found.phone || 'N/A') + ') · ' + exFmtBal(found.balance);
            document.getElementById('custChip').classList.add('show');
            calcExchange();
        });
})();
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
