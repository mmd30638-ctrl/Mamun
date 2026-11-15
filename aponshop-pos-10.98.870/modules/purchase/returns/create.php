<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/purchase_return_functions.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
admin_only();
ensure_purchase_return_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accounts = ame_active_accounts($db);

$preview_return_no = generate_purchase_return_no($db);

include __DIR__ . '/../../../partials/header.php';
?>
<style>
    :root { --pc-navy:#0B1E4D; --pc-accent:#0091EA; --pc-bg:#EAF4FF; --pc-border:#CFE3F7; }
    .pc-wrap { padding: 14px; max-width: 920px; margin: 0 auto; }

    .pc-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
    .pc-topbar h2 { margin:0; color:var(--pc-navy); font-size:19px; }
    .pc-topbar p { margin:4px 0 0; color:#64748B; font-size:12px; }
    .pc-topbar-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .pc-chip-btn { border:1.5px solid var(--pc-border); background:#fff; color:var(--pc-navy); padding:8px 12px; border-radius:9px; font-size:11.5px; font-weight:700; cursor:pointer; }
    .pc-chip-btn.primary { background:linear-gradient(135deg,var(--pc-navy),var(--pc-accent)); color:#fff; border:none; }

    .pc-card { background:#fff; border-radius:16px; box-shadow:0 4px 16px rgba(11,30,77,0.07); padding:16px; margin-bottom:14px; }
    .pc-card-title { display:flex; align-items:center; gap:8px; font-weight:800; color:var(--pc-navy); font-size:13.5px; margin-bottom:12px; }

    .pc-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
    @media (max-width:700px) { .pc-grid-2 { grid-template-columns: 1fr; } }

    .pc-field { margin-bottom:11px; }
    .pc-field label { display:block; font-size:11px; font-weight:700; color:var(--pc-navy); margin-bottom:5px; }
    .pc-field input, .pc-field select, .pc-field textarea {
        width:100%; padding:10px 12px; border:1.5px solid var(--pc-border); border-radius:9px; font-size:13px; box-sizing:border-box; font-family:inherit;
    }
    .pc-field input:focus, .pc-field select:focus, .pc-field textarea:focus { outline:none; border-color:var(--pc-accent); box-shadow:0 0 0 3px rgba(0,145,234,0.12); }
    .pc-field-readonly { background:#F8FAFC; color:#64748B; }

    .pc-quick-add { display:grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap:8px; align-items:end; position:relative; }
    @media (max-width:700px) { .pc-quick-add { grid-template-columns: 1fr 1fr; } }
    .pc-quick-add .pc-field { margin-bottom:0; }
    .pc-add-btn { background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; border:none; border-radius:9px; padding:10px 16px; font-weight:800; font-size:12.5px; cursor:pointer; height:41px; }

    .pc-results { position:absolute; left:0; right:0; top:calc(100% + 4px); background:#fff; border:1.5px solid var(--pc-border); border-radius:12px; box-shadow:0 14px 30px rgba(11,30,77,0.15); max-height:240px; overflow-y:auto; z-index:40; display:none; }
    .pc-results.open { display:block; }
    .pc-result-item { padding:9px 13px; cursor:pointer; border-bottom:1px solid #F1F5F9; font-size:12.5px; }
    .pc-result-item:hover { background:var(--pc-bg); }
    .pc-result-item small { color:#64748B; display:block; font-size:10.5px; }

    .pc-supplier-chip { display:none; align-items:center; gap:10px; background:var(--pc-bg); border:1.5px solid var(--pc-border); border-radius:12px; padding:10px 12px; margin-top:8px; }
    .pc-supplier-chip.show { display:flex; }
    .pc-supplier-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--pc-navy),var(--pc-accent)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }

    .pc-table-wrap { overflow-x:auto; }
    .pc-items-table { width:100%; border-collapse:collapse; font-size:12px; min-width:680px; }
    .pc-items-table th { background:var(--pc-navy); color:#fff; padding:9px 8px; text-align:left; white-space:nowrap; }
    .pc-items-table td { padding:8px; border-bottom:1px solid #F1F5F9; }
    .pc-items-table input, .pc-items-table select { width:74px; padding:6px; border:1.5px solid var(--pc-border); border-radius:6px; font-size:12px; }
    .pc-items-table select.pc-price-type { width:92px; }
    .pc-price-modes { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0 0 10px; }
    .pc-price-modes span { font-size:12px; font-weight:800; color:#111; }
    .pc-price-modes button { min-height:32px; padding:4px 10px; border-radius:8px; border:1.5px solid #111; background:#fff; color:#111; font-weight:800; font-size:12px; cursor:pointer; }
    .pc-remove-btn { color:#C62828; cursor:pointer; font-weight:800; }
    .pc-empty-row td { text-align:center; color:#94A3B8; padding:26px; font-size:12.5px; }

    .pc-summary-item { display:flex; justify-content:space-between; padding:6px 0; font-size:12.5px; border-bottom:1px solid #F1F5F9; }
    .pc-summary-item.total { font-weight:800; color:var(--pc-navy); font-size:14px; border-bottom:none; padding-top:8px; }

    .pc-char-counter { text-align:right; font-size:10.5px; color:#94A3B8; margin-top:3px; }

    .pc-attach-box { border:2px dashed var(--pc-border); border-radius:12px; padding:22px 14px; text-align:center; color:#94A3B8; font-size:12px; background:#FBFDFF; }

    .pc-settle-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
    @media (max-width:500px) { .pc-settle-grid { grid-template-columns:1fr; } }
    .pc-settle-option { border:1.5px solid var(--pc-border); border-radius:10px; padding:11px; display:flex; align-items:center; gap:8px; cursor:pointer; font-size:12.5px; font-weight:600; }
    .pc-settle-option.active { border-color:var(--pc-accent); background:var(--pc-bg); color:var(--pc-navy); font-weight:800; }

    .pc-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
    .pc-btn { flex:1; min-width:120px; padding:13px; border:none; border-radius:11px; font-weight:800; font-size:13.5px; cursor:pointer; }
    .pc-btn-cancel { background:#F1F5F9; color:#64748B; }
    .pc-btn-submit { background:linear-gradient(135deg,var(--pc-navy),var(--pc-accent)); color:#fff; }
</style>

<div class="pc-wrap">
    <div class="pc-topbar">
        <div>
            <h2>📦 ক্রয় পণ্য ফেরত</h2>
            <p>ক্রয়কৃত পণ্য দ্রুত, সঠিক ও নিরাপদভাবে ফেরত দিন</p>
        </div>
        <div class="pc-topbar-actions">
            <button type="button" class="pc-chip-btn primary" onclick="document.getElementById('memoSearchInput').focus()">📥 মেমো থেকে লোড করুন</button>
            <a href="dashboard.php" class="pc-chip-btn" style="text-decoration:none; display:inline-block;">← ড্যাশবোর্ড</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="actions/save_purchase_return.php" method="POST" id="returnForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="memo_no" id="memoNoInput">
        <input type="hidden" name="supplier_id" id="supplierIdInput">
        <input type="hidden" name="supplier_name" id="supplierNameInput">
        <input type="hidden" name="items_json" id="itemsJsonInput">
        <input type="hidden" name="refund_type" id="refundTypeInput" value="supplier_credit">

        <!-- ফেরত মোড নির্বাচন -->
        <div style="display:flex; gap:8px; margin-bottom:14px;">
            <div class="pc-mode-tab active" id="memoModeTab" onclick="switchMode('memo')" style="flex:1; text-align:center; padding:11px; border-radius:10px; border:1.5px solid var(--pc-accent); background:var(--pc-bg); color:var(--pc-navy); font-weight:800; font-size:12.5px; cursor:pointer;">📥 মেমো থেকে লোড</div>
            <div class="pc-mode-tab" id="manualModeTab" onclick="switchMode('manual')" style="flex:1; text-align:center; padding:11px; border-radius:10px; border:1.5px solid var(--pc-border); background:#fff; color:#64748B; font-weight:800; font-size:12.5px; cursor:pointer;">✍️ ম্যানুয়াল ফেরত (মেমো ছাড়া)</div>
        </div>

        <input type="hidden" name="is_manual" id="isManualInput" value="0">

        <!-- মেমো লোড (মোড ১) -->
        <div id="memoModeSection">
        <div class="pc-card">
            <div class="pc-card-title">📥 ক্রয় মেমো থেকে পণ্য লোড করুন</div>
            <div style="display:flex; gap:8px;">
                <input type="text" id="memoSearchInput" placeholder="মেমো/চালান নম্বর লিখুন" style="flex:1; padding:11px 13px; border:1.5px solid var(--pc-border); border-radius:9px;">
                <button type="button" class="pc-add-btn" onclick="searchMemo()">🔍 লোড করুন</button>
            </div>
            <div id="searchNote" style="font-size:11.5px; color:#94A3B8; margin-top:8px;">মেমো নম্বর দিলে সাপ্লায়ার ও পণ্যের তালিকা স্বয়ংক্রিয়ভাবে নিচে বসে যাবে।</div>
            <div class="pc-supplier-chip" id="supplierChip">
                <div class="pc-supplier-avatar" id="supplierAvatar">?</div>
                <div><strong id="supplierChipName"></strong><br><small style="color:#64748B;">মেমো: <span id="supplierChipMemo"></span></small></div>
            </div>
        </div>
        </div>

        <!-- ম্যানুয়াল ফেরত (মোড ২ — মেমো ছাড়া) -->
        <div id="manualModeSection" style="display:none;">
            <div class="pc-card">
                <div class="pc-card-title">🚚 সাপ্লায়ার নির্বাচন করুন</div>
                <div style="position:relative;">
                    <input type="text" id="manualSupplierSearch" autocomplete="off" placeholder="সাপ্লায়ারের নাম বা ফোন দিয়ে খুঁজুন..." style="width:100%; padding:11px 13px; border:1.5px solid var(--pc-border); border-radius:9px; box-sizing:border-box;">
                    <div class="pc-results" id="manualSupplierResults"></div>
                </div>
                <div class="pc-supplier-chip" id="manualSupplierChip">
                    <div class="pc-supplier-avatar" id="manualSupplierAvatar">?</div>
                    <div><strong id="manualSupplierChipName"></strong></div>
                </div>
            </div>

            <div class="pc-card">
                <div class="pc-card-title">📦 পণ্য নির্বাচন করে যোগ করুন</div>
                <div style="position:relative;">
                    <input type="text" id="manualFreeProductSearch" autocomplete="off" placeholder="পণ্যের নাম বা আইডি দিয়ে খুঁজুন..." style="width:100%; padding:11px 13px; border:1.5px solid var(--pc-border); border-radius:9px; box-sizing:border-box;">
                    <div class="pc-results" id="manualFreeProductResults"></div>
                </div>
                <p style="font-size:11px; color:#94A3B8; margin:8px 0 0;">ⓘ ম্যানুয়াল মোডে ফেরত পরিমাণ কোনো পূর্ববর্তী ক্রয়ের সাথে যাচাই করা হয় না — সরাসরি স্টক ও সাপ্লায়ার হিসাব সমন্বয় করা হবে।</p>
            </div>
        </div>

        <!-- রিটার্ন হেডার তথ্য -->
        <div class="pc-grid-2">
            <div class="pc-card">
                <div class="pc-card-title">🧾 রিটার্নের তথ্য</div>
                <div class="pc-field"><label>রিটার্ন নং</label><input type="text" value="<?php echo htmlspecialchars($preview_return_no); ?>" class="pc-field-readonly" readonly></div>
                <div class="pc-field"><label>রিটার্নের তারিখ</label><input type="date" value="<?php echo date('Y-m-d'); ?>" class="pc-field-readonly" readonly></div>
            </div>
            <div class="pc-card">
                <div class="pc-card-title">🏷️ ফেরতের কারণ</div>
                <div class="pc-field">
                    <label>কারণ নির্বাচন করুন *</label>
                    <select id="reasonCategorySelect" onchange="toggleOtherReason()">
                        <option value="">-- নির্বাচন করুন --</option>
                        <option value="damaged">ক্ষতিগ্রস্ত পণ্য</option>
                        <option value="wrong_product">ভুল পণ্য</option>
                        <option value="excess_qty">অতিরিক্ত পরিমাণ</option>
                        <option value="expired">মেয়াদোত্তীর্ণ</option>
                        <option value="quality_issue">মান সমস্যা</option>
                        <option value="wrong_price">ভুল মূল্য</option>
                        <option value="supplier_agreement">সাপ্লায়ার চুক্তি</option>
                        <option value="other">অন্যান্য</option>
                    </select>
                </div>
                <div class="pc-field" id="otherReasonGroup" style="display:none;">
                    <label>বিস্তারিত কারণ লিখুন *</label>
                    <input type="text" id="reasonInput" name="reason" maxlength="255" placeholder="যেমন: পণ্য ভাঙা অবস্থায় এসেছে">
                </div>
            </div>
        </div>

        <!-- পণ্য যোগ করার সার্চ (মেমো-ভিত্তিক মোডের জন্য) -->
        <div id="memoProductSearchSection">
        <div class="pc-card">
            <div class="pc-card-title">🔍 পণ্য খুঁজুন ও যোগ করুন</div>
            <div style="background:#FFF3E0; color:#8D6E00; padding:8px 12px; border-radius:8px; font-size:11.5px; margin-bottom:10px;">
                ⚠️ এখানে সার্চ করার আগে <strong>উপরে প্রথমে মেমো নম্বর দিয়ে "লোড করুন" চাপুন</strong> — তারপর সেই মেমোর পণ্যের মধ্যে সার্চ করা যাবে।
            </div>
            <div style="position:relative;">
                <input type="text" id="manualProductSearch" placeholder="মেমো লোড করার পর এই তালিকা থেকে পণ্য বেছে অথবা এখানে সার্চ করুন..." style="width:100%; padding:11px 13px; border:1.5px solid var(--pc-border); border-radius:9px; box-sizing:border-box;">
                <div class="pc-results" id="manualResults"></div>
            </div>
        </div>
        </div>

        <!-- রিটার্ন পণ্য টেবিল -->
        <div class="pc-card">
            <div class="pc-card-title">📋 ফেরত পণ্যের তালিকা</div>
            <div class="pc-price-modes" id="pcPriceModes">
                <span>ফেরতের দাম</span>
                <button type="button" data-pc-price="wholesale">পাইকারি</button>
                <button type="button" data-pc-price="retail">খুচরা</button>
                <button type="button" data-pc-price="dealer">ডিলার</button>
                <button type="button" data-pc-price="price" class="is-on">প্রাইস</button>
            </div>
            <div class="pc-table-wrap">
                <table class="pc-items-table">
                    <thead>
                        <tr><th>#</th><th>পণ্যের নাম</th><th>ক্রয়কৃত</th><th>আগে ফেরত</th><th>ফেরত পরিমাণ</th><th>ফ্রি পরিমাণ</th><th>দামের ধরন</th><th>মূল্য</th><th>মোট</th><th></th></tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="pc-empty-row"><td colspan="10">কোনো পণ্য যোগ করা হয়নি — উপরে মেমো লোড করুন অথবা সার্চ করুন</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- সারাংশ / নোট -->
        <div class="pc-grid-2">
            <div class="pc-card">
                <div class="pc-card-title">🧮 সারাংশ</div>
                <div class="pc-summary-item"><span>সাব-টোটাল</span><span id="subtotalDisplay">৳0.00</span></div>
                <div class="pc-summary-item total"><span>নিট রিটার্ন</span><span id="netTotalDisplay">৳0.00</span></div>
            </div>
            <div class="pc-card">
                <div class="pc-card-title">📝 নোট</div>
                <textarea id="notesInput" name="notes" rows="4" maxlength="500" placeholder="যেমন: সাপ্লায়ারের সাথে আলোচনা করে পণ্য ফেরত দেওয়া হলো।" oninput="updateCharCounter()"></textarea>
                <div class="pc-char-counter"><span id="charCount">0</span>/500</div>
            </div>
        </div>

        <!-- ফেরত পরিশোধ পদ্ধতি -->
        <div class="pc-card">
            <div class="pc-card-title">💳 ফেরত পরিশোধ পদ্ধতি</div>
            <div class="pc-settle-grid">
                <div class="pc-settle-option active" data-val="supplier_credit" onclick="setRefundType('supplier_credit')">📝 সাপ্লায়ারের বকেয়া থেকে বাদ যাবে</div>
                <div class="pc-settle-option" data-val="cash_refund" onclick="setRefundType('cash_refund')">💵 নগদ প্রদান</div>
                <div class="pc-settle-option" data-val="bank_transfer" onclick="setRefundType('bank_transfer')">🏦 ব্যাংক ট্রান্সফার</div>
                <div class="pc-settle-option" data-val="mobile_banking" onclick="setRefundType('mobile_banking')">📱 মোবাইল ব্যাংকিং</div>
            </div>
            <div class="pc-field" id="accountGroup" style="display:none; margin-top:12px;">
                <label>কোন অ্যাকাউন্টে জমা/থেকে পরিশোধ হবে *</label>
                <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" id="accountSelect">
                    <?php echo ame_render_account_options($accounts); ?>
                </select>
            </div>
        </div>

        <div class="pc-actions">
            <a href="dashboard.php" class="pc-btn pc-btn-cancel" style="text-align:center; text-decoration:none;">❌ বাতিল</a>
            <button type="submit" class="pc-btn pc-btn-submit">✅ রিটার্ন সেভ করুন</button>
        </div>
    </form>
</div>

<script>
var currentItems = [];
var pcDefaultPrice = 'price';

function switchMode(mode) {
    document.getElementById('isManualInput').value = mode === 'manual' ? '1' : '0';
    document.getElementById('memoModeSection').style.display = mode === 'memo' ? 'block' : 'none';
    document.getElementById('memoProductSearchSection').style.display = mode === 'memo' ? 'block' : 'none';
    document.getElementById('manualModeSection').style.display = mode === 'manual' ? 'block' : 'none';

    document.getElementById('memoModeTab').style.borderColor = mode === 'memo' ? 'var(--pc-accent)' : 'var(--pc-border)';
    document.getElementById('memoModeTab').style.background = mode === 'memo' ? 'var(--pc-bg)' : '#fff';
    document.getElementById('memoModeTab').style.color = mode === 'memo' ? 'var(--pc-navy)' : '#64748B';
    document.getElementById('manualModeTab').style.borderColor = mode === 'manual' ? 'var(--pc-accent)' : 'var(--pc-border)';
    document.getElementById('manualModeTab').style.background = mode === 'manual' ? 'var(--pc-bg)' : '#fff';
    document.getElementById('manualModeTab').style.color = mode === 'manual' ? 'var(--pc-navy)' : '#64748B';

    // মোড বদলালে কার্ট পরিষ্কার (দুই মোডের ডাটা মিশিয়ে ফেলা এড়াতে)
    cart = [];
    renderCart();
    document.getElementById('memoNoInput').value = '';
    document.getElementById('supplierIdInput').value = '';
    document.getElementById('supplierNameInput').value = '';
    document.getElementById('supplierChip').classList.remove('show');
    document.getElementById('manualSupplierChip').classList.remove('show');
}

function pcEsc(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function pcMoney(n) {
    var v = Number(n) || 0;
    var sign = v < -0.009 ? 'জমা ' : (v > 0.009 ? 'বকেয়া ' : 'সমান ');
    return sign + '৳' + Math.abs(v).toFixed(2);
}
function pcBindLookup(input, box, kind, paint, pick) {
    var timer = null;
    var rows = [];
    function close() { box.classList.remove('open'); box.innerHTML = ''; }
    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (!q) { close(); return; }
        timer = setTimeout(function () {
            fetch('actions/search_lookup.php?kind=' + kind + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    rows = (data && data.rows) ? data.rows : [];
                    box.innerHTML = rows.length === 0
                        ? '<div class="pc-result-item">কোনো মিল পাওয়া যায়নি</div>'
                        : rows.map(function (row, idx) {
                            return '<button type="button" class="pc-result-item" data-idx="' + idx + '">' + paint(row) + '</button>';
                        }).join('');
                    box.classList.add('open');
                })
                .catch(function () {
                    box.innerHTML = '<div class="pc-result-item">সার্চ লোড হয়নি। আবার লিখুন।</div>';
                    box.classList.add('open');
                });
        }, 160);
    });
    box.addEventListener('click', function (ev) {
        var hit = ev.target.closest('[data-idx]');
        if (!hit) return;
        var row = rows[Number(hit.getAttribute('data-idx'))];
        if (!row) return;
        pick(row);
        input.value = '';
        close();
    });
    return close;
}
var closeSupplier = pcBindLookup(document.getElementById('manualSupplierSearch'), document.getElementById('manualSupplierResults'), 'supplier', function (s) {
    var meta = [];
    if (s.code) meta.push('কোড ' + s.code);
    if (s.phone) meta.push('ফোন ' + s.phone);
    if (s.alt) meta.push('বিকল্প ' + s.alt);
    if (s.address) meta.push(s.address);
    meta.push(pcMoney(s.balance));
    return pcEsc(s.name) + '<small>' + pcEsc(meta.join(' · ')) + '</small>';
}, function (s) {
    document.getElementById('supplierIdInput').value = s.id;
    document.getElementById('supplierNameInput').value = s.name;
    document.getElementById('manualSupplierAvatar').textContent = String(s.name || '?').charAt(0);
    document.getElementById('manualSupplierChipName').textContent = s.name + ' · ' + pcMoney(s.balance);
    document.getElementById('manualSupplierChip').classList.add('show');
});
var closeProduct = pcBindLookup(document.getElementById('manualFreeProductSearch'), document.getElementById('manualFreeProductResults'), 'product', function (p) {
    var meta = ['আইডি ' + p.id];
    if (p.code) meta.push('কোড ' + p.code);
    meta.push('স্টক ' + p.stock);
    meta.push('প্রাইস ৳' + Number(p.purchase_price || 0).toFixed(2));
    meta.push('খুচরা ৳' + Number(p.sale_price || 0).toFixed(2));
    meta.push('পাইকারি ৳' + Number(p.wholesale_price || 0).toFixed(2));
    meta.push('ডিলার ৳' + Number(p.dealer_price || 0).toFixed(2));
    return pcEsc(p.name) + '<small>' + pcEsc(meta.join(' · ')) + '</small>';
}, function (p) { addManualItemToCart(p); });

document.addEventListener('click', function (e) {
    if (!e.target.closest('#manualSupplierSearch') && !e.target.closest('#manualSupplierResults')) { closeSupplier(); }
    if (!e.target.closest('#manualFreeProductSearch') && !e.target.closest('#manualFreeProductResults')) { closeProduct(); }
});

var cart = [];

function searchMemo() {
    var memo = document.getElementById('memoSearchInput').value.trim();
    if (!memo) { alert('মেমো নম্বর দিন।'); return; }
    document.getElementById('searchNote').textContent = 'খোঁজা হচ্ছে...';

    fetch('actions/search_purchase.php?memo_no=' + encodeURIComponent(memo))
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status !== 'success') {
                document.getElementById('searchNote').textContent = data.message || 'এই মেমো নম্বরে কোনো ক্রয় পাওয়া যায়নি।';
                document.getElementById('supplierChip').classList.remove('show');
                return;
            }
            document.getElementById('searchNote').textContent = '';
            currentItems = data.items;
            document.getElementById('memoNoInput').value = data.memo_no;
            document.getElementById('supplierIdInput').value = data.supplier_id || '';
            document.getElementById('supplierNameInput').value = data.supplier_name || '';

            document.getElementById('supplierAvatar').textContent = (data.supplier_name || '?').charAt(0);
            document.getElementById('supplierChipName').textContent = (data.supplier_name || 'N/A') + (data.supplier_phone ? ' · ' + data.supplier_phone : '') + ' · ' + pcMoney(data.supplier_due);
            document.getElementById('supplierChipMemo').textContent = data.memo_no;
            document.getElementById('supplierChip').classList.add('show');
        })
        .catch(function () {
            document.getElementById('searchNote').textContent = 'সার্ভারে সংযোগে সমস্যা হয়েছে।';
        });
}

// ================= লোড হওয়া মেমোর পণ্যের মধ্যে সার্চ করে কার্টে যোগ =================
var manualInput = document.getElementById('manualProductSearch');
var manualResults = document.getElementById('manualResults');

manualInput.addEventListener('input', function () {
    var q = manualInput.value.trim().toLowerCase();
    if (q === '') { manualResults.classList.remove('open'); return; }

    if (currentItems.length === 0) {
        manualResults.innerHTML = '<div class="pc-result-item" style="color:#E65100; font-weight:700;">⚠️ প্রথমে উপরে মেমো/চালান নম্বর দিয়ে "লোড করুন" চাপুন, তারপর এখানে পণ্য সার্চ করুন।</div>';
        manualResults.classList.add('open');
        return;
    }

    var matches = currentItems.filter(function (it) {
        return it.product_name.toLowerCase().indexOf(q) !== -1 || String(it.product_id).indexOf(q) !== -1;
    }).slice(0, 8);

    manualResults.innerHTML = matches.length === 0
        ? '<div class="pc-result-item" style="color:#94A3B8;">এই মেমোতে এই নামে কোনো পণ্য নেই</div>'
        : matches.map(function (it, i) {
            return '<div class="pc-result-item" data-idx="' + currentItems.indexOf(it) + '">' + it.product_name +
                '<small>ক্রয়কৃত: ' + it.purchased_qty + ' • ফেরতযোগ্য: ' + (it.purchased_qty - it.already_returned) + '</small></div>';
        }).join('');
    manualResults.classList.add('open');

    manualResults.querySelectorAll('.pc-result-item[data-idx]').forEach(function (el) {
        el.addEventListener('click', function () {
            addItemToCart(currentItems[parseInt(el.getAttribute('data-idx'), 10)]);
            manualInput.value = '';
            manualResults.classList.remove('open');
        });
    });
});
document.addEventListener('click', function (e) {
    if (!e.target.closest('#manualProductSearch') && !e.target.closest('#manualResults')) { manualResults.classList.remove('open'); }
});

function pcUnitFor(item, type) {
    if (type === 'wholesale') return Number(item.wholesale_price) || 0;
    if (type === 'retail') return Number(item.sale_price) || 0;
    if (type === 'dealer') return Number(item.dealer_price) || 0;
    return Number(item.ask_price) || 0;
}
function pcPriceOptions(item) {
    return '<option value="wholesale"' + (item.price_type === 'wholesale' ? ' selected' : '') + '>পাইকারি</option>' +
        '<option value="retail"' + (item.price_type === 'retail' ? ' selected' : '') + '>খুচরা</option>' +
        '<option value="dealer"' + (item.price_type === 'dealer' ? ' selected' : '') + '>ডিলার</option>' +
        '<option value="price"' + (item.price_type === 'price' ? ' selected' : '') + '>প্রাইস</option>';
}
function pcMarkPrice(type) {
    pcDefaultPrice = type;
    document.querySelectorAll('#pcPriceModes button').forEach(function (btn) {
        btn.classList.toggle('is-on', btn.getAttribute('data-pc-price') === type);
    });
}
document.getElementById('pcPriceModes').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-pc-price]');
    if (!btn) return;
    var type = btn.getAttribute('data-pc-price');
    pcMarkPrice(type);
    cart.forEach(function (item) {
        item.price_type = type;
        item.unit_price = pcUnitFor(item, type);
    });
    renderCart();
});
function pcLine(base) {
    base.price_type = pcDefaultPrice;
    base.unit_price = pcUnitFor(base, pcDefaultPrice);
    return base;
}
function addItemToCart(it) {
    if (cart.find(function (c) { return c.product_id === it.product_id; })) { return; }
    var remaining = it.purchased_qty - it.already_returned;
    if (remaining <= 0) { alert('এই পণ্যের ফেরতযোগ্য পরিমাণ শূন্য।'); return; }
    cart.push(pcLine({
        product_id: it.product_id, name: it.product_name, purchased_qty: it.purchased_qty,
        already_returned: it.already_returned, remaining: remaining, returned_qty: remaining, free_qty: 0,
        purchase_price: Number(it.purchase_price || it.unit_price) || 0,
        sale_price: Number(it.sale_price) || 0, wholesale_price: Number(it.wholesale_price) || 0,
        dealer_price: Number(it.dealer_price) || 0, ask_price: Number(it.unit_price || it.purchase_price) || 0,
        is_manual: false
    }));
    renderCart();
}

function addManualItemToCart(p) {
    if (cart.find(function (c) { return c.product_id === p.id; })) { return; }
    cart.push(pcLine({
        product_id: p.id, name: p.name, purchased_qty: null, already_returned: 0, remaining: 999999,
        returned_qty: 1, free_qty: 0, purchase_price: Number(p.purchase_price) || 0,
        sale_price: Number(p.sale_price) || 0, wholesale_price: Number(p.wholesale_price) || 0,
        dealer_price: Number(p.dealer_price) || 0, ask_price: Number(p.purchase_price) || 0, is_manual: true
    }));
    renderCart();
}

function renderCart() {
    var body = document.getElementById('itemsBody');
    if (cart.length === 0) {
        body.innerHTML = '<tr class="pc-empty-row"><td colspan="10">কোনো পণ্য যোগ করা হয়নি — উপরে মেমো লোড করুন অথবা সার্চ করুন</td></tr>';
        recalc();
        return;
    }
    body.innerHTML = '';
    cart.forEach(function (item, idx) {
        var row = document.createElement('tr');
        var maxAttr = item.is_manual ? '' : ('max="' + item.remaining + '"');
        row.innerHTML =
            '<td>' + (idx + 1) + '</td>' +
            '<td><b>' + pcEsc(item.name) + '</b>' + (item.is_manual ? ' <small style="color:#E65100;">(ম্যানুয়াল)</small>' : '') + '</td>' +
            '<td>' + (item.purchased_qty === null ? '—' : item.purchased_qty) + '</td>' +
            '<td>' + (item.purchased_qty === null ? '—' : item.already_returned) + '</td>' +
            '<td><input type="number" min="0.01" ' + maxAttr + ' step="0.01" value="' + item.returned_qty + '" onchange="updateCartField(' + idx + ',\'returned_qty\',this.value)"></td>' +
            '<td><input type="number" min="0" step="0.01" value="' + item.free_qty + '" onchange="updateCartField(' + idx + ',\'free_qty\',this.value)"></td>' +
            '<td><select class="pc-price-type" onchange="updateCartField(' + idx + ',\'price_type\',this.value)">' + pcPriceOptions(item) + '</select></td>' +
            '<td><input type="number" min="0" step="0.01" value="' + Number(item.unit_price).toFixed(2) + '" onchange="updateCartField(' + idx + ',\'unit_price\',this.value)"></td>' +
            '<td style="font-weight:800;">৳' + (item.returned_qty * item.unit_price).toFixed(2) + '</td>' +
            '<td class="pc-remove-btn" onclick="removeCartItem(' + idx + ')">✖</td>';
        body.appendChild(row);
    });
    recalc();
}

function updateCartField(idx, field, value) {
    var item = cart[idx];
    if (field === 'returned_qty' || field === 'free_qty') {
        var v = parseFloat(value) || 0;
        if (field === 'returned_qty' && !item.is_manual && v > item.remaining) { v = item.remaining; }
        item[field] = v;
    } else if (field === 'price_type') {
        item.price_type = value;
        item.unit_price = pcUnitFor(item, value);
    } else if (field === 'unit_price') {
        item.unit_price = parseFloat(value) || 0;
        item.ask_price = item.unit_price;
        item.price_type = 'price';
    }
    renderCart();
}
function removeCartItem(idx) { cart.splice(idx, 1); renderCart(); }

function recalc() {
    var subtotal = cart.reduce(function (s, i) { return s + (i.returned_qty * i.unit_price); }, 0);
    document.getElementById('subtotalDisplay').textContent = '৳' + subtotal.toFixed(2);
    document.getElementById('netTotalDisplay').textContent = '৳' + subtotal.toFixed(2);

    var items = cart.filter(function (i) { return i.returned_qty > 0; }).map(function (i) {
        return { product_id: i.product_id, returned_qty: i.returned_qty, free_qty: i.free_qty, unit_price: i.unit_price, is_manual: i.is_manual };
    });
    document.getElementById('itemsJsonInput').value = JSON.stringify(items);
}

function toggleOtherReason() {
    var val = document.getElementById('reasonCategorySelect').value;
    document.getElementById('otherReasonGroup').style.display = (val === 'other' || val === '') ? 'block' : 'none';
    if (val !== 'other' && val !== '') {
        var labels = {
            damaged: 'ক্ষতিগ্রস্ত পণ্য', wrong_product: 'ভুল পণ্য', excess_qty: 'অতিরিক্ত পরিমাণ',
            expired: 'মেয়াদোত্তীর্ণ', quality_issue: 'মান সমস্যা', wrong_price: 'ভুল মূল্য', supplier_agreement: 'সাপ্লায়ার চুক্তি'
        };
        document.getElementById('reasonInput').value = labels[val] || '';
    } else {
        document.getElementById('reasonInput').value = '';
    }
}

function updateCharCounter() {
    document.getElementById('charCount').textContent = document.getElementById('notesInput').value.length;
}

function setRefundType(type) {
    document.getElementById('refundTypeInput').value = type;
    document.querySelectorAll('.pc-settle-option').forEach(function (el) {
        el.classList.toggle('active', el.getAttribute('data-val') === type);
    });
    var needsAccount = type !== 'supplier_credit';
    document.getElementById('accountGroup').style.display = needsAccount ? 'block' : 'none';
    document.getElementById('accountSelect').required = needsAccount;
}

document.getElementById('returnForm').addEventListener('submit', function (e) {
    if (cart.filter(function (i) { return i.returned_qty > 0; }).length === 0) {
        e.preventDefault();
        alert('কমপক্ষে একটা পণ্যের ফেরত-পরিমাণ দিন।');
        return;
    }
    if (document.getElementById('isManualInput').value === '1' && !document.getElementById('supplierIdInput').value) {
        e.preventDefault();
        alert('ম্যানুয়াল ফেরতের জন্য একজন সাপ্লায়ার নির্বাচন করুন।');
        return;
    }
    if (!document.getElementById('reasonInput').value.trim()) {
        e.preventDefault();
        alert('ফেরতের কারণ নির্বাচন/লিখুন।');
        return;
    }
    var hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = 'reason_category';
    hidden.value = document.getElementById('reasonCategorySelect').value;
    this.appendChild(hidden);
});
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
