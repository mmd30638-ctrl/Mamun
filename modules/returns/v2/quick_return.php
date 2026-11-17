<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
require_once __DIR__ . '/../sales_return_module.php';

ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../../../partials/header.php';
ame_sales_return_include_nav('quick');
?>
<link rel="stylesheet" href="/assets/css/sales-return-module.css?v=<?php echo htmlspecialchars(AME_SALES_RETURN_MODULE_REV, ENT_QUOTES, 'UTF-8'); ?>">
<style>
    .qr-wrap { max-width: 520px; margin: 0 auto; padding: 14px; }
    .qr-hero { text-align: center; margin-bottom: 18px; }
    .qr-hero h2 { margin: 0; color: #991b1b; font-size: 1.25rem; }
    .qr-hero p { margin: 8px 0 0; color: #64748b; font-size: 0.85rem; line-height: 1.45; }
    .qr-step { background: #fff; border: 1px solid #fecaca; border-radius: 14px; padding: 16px; margin-bottom: 12px; box-shadow: 0 4px 16px rgba(220,38,38,0.06); }
    .qr-step-num { display: inline-block; background: #dc2626; color: #fff; font-weight: 800; font-size: 0.7rem; padding: 3px 8px; border-radius: 999px; margin-bottom: 8px; }
    .qr-step h3 { margin: 0 0 10px; font-size: 0.95rem; color: #0f172a; }
    .qr-input { width: 100%; padding: 12px 13px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; box-sizing: border-box; }
    .qr-input:disabled { background: #f8fafc; color: #94a3b8; }
    .qr-chip { display: none; margin-top: 10px; padding: 12px; background: #fef2f2; border-radius: 10px; border: 1px solid #fecaca; }
    .qr-chip.show { display: block; }
    .qr-results { position: relative; border: 1px solid #e2e8f0; border-radius: 10px; max-height: 220px; overflow: auto; display: none; margin-top: 8px; background: #fff; }
    .qr-results.open { display: block; }
    .qr-result-item { width: 100%; text-align: left; padding: 10px 12px; border: none; border-bottom: 1px solid #f1f5f9; background: #fff; cursor: pointer; font-size: 0.85rem; }
    .qr-result-item:hover { background: #fef2f2; }
    .qr-result-item small { display: block; color: #64748b; margin-top: 3px; }
    .qr-summary { display: none; }
    .qr-summary.show { display: block; }
    .qr-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.88rem; border-bottom: 1px dashed #f1f5f9; }
    .qr-submit { width: 100%; margin-top: 14px; padding: 15px; border: none; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer;
        background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 6px 18px rgba(220,38,38,0.35); }
    .qr-submit:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }
    .qr-muted { font-size: 0.78rem; color: #94a3b8; margin-top: 8px; }
</style>

<div class="qr-wrap">
    <div class="qr-hero">
        <h2>⚡ দ্রুত বিক্রয় ফেরত</h2>
        <p>① রেজিস্টার ক্রেতার নাম → ② পণ্য বেছে নিন → এক ক্লিকে স্টক + বকেয়া আপডেট ও শেয়ারযোগ্য মেমো</p>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#fef2f2;color:#b91c1c;padding:12px;border-radius:10px;margin-bottom:12px;font-size:0.88rem;">
            <?php echo htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="actions/quick_customer_return.php" id="quickReturnForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="customer_id" id="qrCustomerId" value="">
        <input type="hidden" name="sale_id" id="qrSaleId" value="">
        <input type="hidden" name="product_id" id="qrProductId" value="">
        <input type="hidden" name="unit_price" id="qrUnitPrice" value="">
        <input type="hidden" name="sales_type" id="qrSalesType" value="retail">

        <div class="qr-step">
            <span class="qr-step-num">১</span>
            <h3>রেজিস্টার ক্রেতা</h3>
            <input type="text" class="qr-input" id="qrCustSearch" autocomplete="off" placeholder="নাম, ফোন বা কোড লিখুন...">
            <div class="qr-results" id="qrCustResults"></div>
            <div class="qr-chip" id="qrCustChip"><strong id="qrCustName"></strong><br><small id="qrCustBal"></small></div>
        </div>

        <div class="qr-step">
            <span class="qr-step-num">২</span>
            <h3>পণ্য (ক্রেতার যেকোনো মেমো থেকে)</h3>
            <input type="text" class="qr-input" id="qrProdSearch" autocomplete="off" placeholder="পণ্যের নাম বা কোড..." disabled>
            <div class="qr-results" id="qrProdResults"></div>
            <div class="qr-chip" id="qrProdChip"><strong id="qrProdName"></strong><br><small id="qrProdMeta"></small></div>
        </div>

        <div class="qr-step qr-summary" id="qrSummary">
            <span class="qr-step-num">৩</span>
            <h3>ফেরত নিশ্চিত করুন</h3>
            <div class="qr-row"><span>ফেরত পরিমাণ</span>
                <input type="number" name="qty" id="qrQty" min="0.01" step="0.01" value="1" style="width:80px;text-align:right;padding:6px;border-radius:8px;border:1px solid #e2e8f0;">
            </div>
            <div class="qr-row"><span>একক মূল্য</span><span id="qrPriceShow">৳0</span></div>
            <input type="hidden" name="reason" value="গ্রাহক ফেরত">
            <p class="qr-muted">সংরক্ষণ করলে: স্টক বাড়বে, ক্রেতার বকেয়া থেকে টাকা কমবে, ফেরত মেমো তৈরি — শেয়ার/প্রিন্ট করা যাবে।</p>
            <button type="submit" class="qr-submit" id="qrSubmitBtn" disabled>✓ ফেরত নিন ও মেমো দেখুন</button>
        </div>
    </form>
</div>

<script>
(function () {
    var state = { customerId: 0, line: null };
    var custIn = document.getElementById('qrCustSearch');
    var custBox = document.getElementById('qrCustResults');
    var prodIn = document.getElementById('qrProdSearch');
    var prodBox = document.getElementById('qrProdResults');

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function fmtBal(n) {
        var v = Number(n) || 0;
        if (v > 0.009) return 'বকেয়া ৳' + v.toFixed(2);
        if (v < -0.009) return 'জমা ৳' + Math.abs(v).toFixed(2);
        return 'সমান ৳0.00';
    }
    function bindSearch(input, box, kind, onPick) {
        var timer = null;
        var rows = [];
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (!q) { box.classList.remove('open'); box.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch('actions/search_lookup.php?kind=' + kind + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        rows = data.rows || [];
                        box.innerHTML = rows.length
                            ? rows.map(function (row, i) {
                                return '<button type="button" class="qr-result-item" data-i="' + i + '">' + (kind === 'customer'
                                    ? esc(row.name) + '<small>' + esc((row.phone || '') + ' · ' + fmtBal(row.balance)) + '</small>'
                                    : esc(row.name)) + '</button>';
                            }).join('')
                            : '<div class="qr-result-item" style="cursor:default;">মিল নেই</div>';
                        box.classList.add('open');
                    });
            }, 160);
        });
        box.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-i]');
            if (!b) return;
            var row = rows[Number(b.getAttribute('data-i'))];
            if (row) onPick(row);
            box.classList.remove('open');
            input.value = '';
        });
    }

    bindSearch(custIn, custBox, 'customer', function (c) {
        state.customerId = Number(c.id) || 0;
        document.getElementById('qrCustomerId').value = state.customerId;
        document.getElementById('qrCustName').textContent = c.name;
        document.getElementById('qrCustBal').textContent = fmtBal(c.balance);
        document.getElementById('qrCustChip').classList.add('show');
        prodIn.disabled = false;
        prodIn.focus();
        resetProduct();
    });

    prodIn.addEventListener('input', function () {
        if (!state.customerId) return;
        var q = prodIn.value.trim();
        clearTimeout(prodIn._t);
        prodIn._t = setTimeout(function () {
            fetch('actions/search_customer_products.php?customer_id=' + state.customerId + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var lines = data.lines || [];
                    prodBox.innerHTML = lines.length
                        ? lines.map(function (ln, i) {
                            return '<button type="button" class="qr-result-item" data-li="' + i + '">' + esc(ln.product_name)
                                + '<small>মেমো ' + esc(ln.invoice_number) + ' · বাকি ' + Number(ln.remaining).toFixed(2) + '</small></button>';
                        }).join('')
                        : '<div class="qr-result-item" style="cursor:default;">ফেরতযোগ্য পণ্য নেই</div>';
                    prodBox._lines = lines;
                    prodBox.classList.add('open');
                });
        }, 180);
    });

    prodBox.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-li]');
        if (!b || !prodBox._lines) return;
        var ln = prodBox._lines[Number(b.getAttribute('data-li'))];
        if (!ln) return;
        state.line = ln;
        document.getElementById('qrSaleId').value = ln.sale_id;
        document.getElementById('qrProductId').value = ln.product_id;
        document.getElementById('qrUnitPrice').value = ln.unit_price;
        document.getElementById('qrProdName').textContent = ln.product_name;
        document.getElementById('qrProdMeta').textContent = 'মেমো ' + ln.invoice_number + ' · সর্বোচ্চ ' + Number(ln.remaining).toFixed(2);
        document.getElementById('qrProdChip').classList.add('show');
        document.getElementById('qrPriceShow').textContent = '৳' + Number(ln.unit_price).toFixed(2);
        document.getElementById('qrQty').max = ln.remaining;
        document.getElementById('qrQty').value = Math.min(1, Number(ln.remaining));
        document.getElementById('qrSummary').classList.add('show');
        document.getElementById('qrSubmitBtn').disabled = false;
        prodBox.classList.remove('open');
        prodIn.value = ln.product_name;
    });

    function resetProduct() {
        state.line = null;
        ['qrSaleId', 'qrProductId', 'qrUnitPrice'].forEach(function (id) { document.getElementById(id).value = ''; });
        document.getElementById('qrProdChip').classList.remove('show');
        document.getElementById('qrSummary').classList.remove('show');
        document.getElementById('qrSubmitBtn').disabled = true;
        prodIn.value = '';
    }
})();
</script>

<?php include __DIR__ . '/../../../partials/footer.php'; ?>