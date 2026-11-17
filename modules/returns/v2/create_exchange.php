<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$customers_res = $db->query("SELECT id, name, phone, customer_code FROM customers WHERE is_customer = 1 AND " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1') . " ORDER BY name ASC LIMIT 500");
$customers = $customers_res ? $customers_res->fetch_all(MYSQLI_ASSOC) : [];

$products_res = $db->query('SELECT id, name, sale_price, stock FROM products WHERE ' . ame_product_option_sql('status') . ' ORDER BY name ASC LIMIT 1000');
$products = $products_res ? $products_res->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../../../partials/header.php';
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
        max-height:200px; overflow-y:auto; z-index:30; display:none;
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

    .ex-submit-btn { width:100%; padding:14px; border:none; border-radius:12px; background: linear-gradient(135deg,#00838F,#26C6DA); color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
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

                <div class="ex-section">
                    <div class="ex-section-title returned">↩️ ফেরত দেওয়া পণ্য</div>
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
                    <div class="ex-section-title replacement">🎁 বদলি পণ্য</div>
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
                    <div class="ex-calc-row"><span>ফেরত পণ্যের মূল্য</span><span id="oldValueDisplay">৳0.00</span></div>
                    <div class="ex-calc-row"><span>বদলি পণ্যের মূল্য</span><span id="newValueDisplay">৳0.00</span></div>
                    <div class="ex-calc-final" id="finalResultDisplay">সমান মূল্য — কোনো সমন্বয় লাগবে না</div>
                </div>
                <input type="hidden" name="adjustment" id="adjustmentInput" value="0">

                <div class="ex-row">
                    <div class="ex-group">
                        <label>বিক্রয়ের ধরন</label>
                        <select name="sales_type">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                </div>

                <div class="ex-group">
                    <label>এক্সচেঞ্জের কারণ *</label>
                    <textarea name="reason" rows="2" required maxlength="255" placeholder="যেমন: সাইজ পরিবর্তন, রং পরিবর্তন"></textarea>
                </div>

                <button type="submit" class="ex-submit-btn">✅ অনুমোদনের জন্য জমা দিন</button>
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

        results.innerHTML = matches.length === 0
            ? '<div class="ex-result-item" style="color:#94A3B8;">কোনো ফলাফল নেই</div>'
            : matches.map(function (item) {
                return '<div class="ex-result-item" data-i="' + dataset.indexOf(item) + '">' + formatFn(item) + '</div>';
            }).join('');
        results.classList.add('open');

        results.querySelectorAll('.ex-result-item[data-i]').forEach(function (el) {
            el.addEventListener('click', function () {
                onSelect(dataset[parseInt(el.getAttribute('data-i'), 10)]);
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

setupSearch('oldProdSearch', 'oldProdResults', PRODUCTS, function (p) {
    document.getElementById('oldProductIdInput').value = p.id;
    document.getElementById('oldProdSearch').value = '';
    document.getElementById('oldProdChipText').textContent = p.name;
    document.getElementById('oldProdChip').classList.add('show');
    document.getElementById('oldPriceInput').value = p.sale_price;
    calcExchange();
}, function (p) { return p.name + '<small>ID: ' + p.id + ' • মূল্য: ৳' + p.sale_price + '</small>'; });

setupSearch('newProdSearch', 'newProdResults', PRODUCTS, function (p) {
    document.getElementById('newProductIdInput').value = p.id;
    document.getElementById('newProdSearch').value = '';
    document.getElementById('newProdChipText').textContent = p.name + ' (স্টক: ' + p.stock + ')';
    document.getElementById('newProdChip').classList.add('show');
    document.getElementById('newPriceInput').value = p.sale_price;
    calcExchange();
}, function (p) { return p.name + '<small>ID: ' + p.id + ' • মূল্য: ৳' + p.sale_price + ' • স্টক: ' + p.stock + '</small>'; });

function clearCustomer() {
    document.getElementById('customerIdInput').value = '';
    document.getElementById('customerNameInput').value = '';
    document.getElementById('custChip').classList.remove('show');
}
function clearOldProduct() {
    document.getElementById('oldProductIdInput').value = '';
    document.getElementById('oldProdChip').classList.remove('show');
    calcExchange();
}
function clearNewProduct() {
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
    if (cid < 1) {
        return;
    }
    var found = CUSTOMERS.find(function (c) {
        return Number(c.id) === cid;
    });
    if (!found) {
        return;
    }
    document.getElementById('customerIdInput').value = found.id;
    document.getElementById('customerNameInput').value = found.name;
    document.getElementById('custChipText').textContent = found.name + ' (' + (found.phone || 'N/A') + ')';
    document.getElementById('custChip').classList.add('show');
})();
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
