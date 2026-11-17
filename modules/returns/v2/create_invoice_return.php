<?php
/* 10.98.726 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accounts = ame_active_accounts($db);

$preview_return_no = generate_return_no($db);

require_once __DIR__ . '/../sales_return_module.php';
include __DIR__ . '/../../../partials/header.php';
ame_sales_return_include_nav('invoice');
?>
<style>
    :root { --sr-primary:#059669; --sr-primary-dark:#047857; --sr-bg:#F0FDF4; --sr-border:#D1FAE5; --sr-danger:#DC2626; }
    .sr-wrap { padding: 14px; max-width: 960px; margin: 0 auto; }

    .sr-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
    .sr-topbar h2 { margin:0; color:#0F172A; font-size:19px; }
    .sr-topbar p { margin:4px 0 0; color:#64748B; font-size:12px; }
    .sr-back-link { color:var(--sr-primary); text-decoration:none; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:5px; }

    .sr-summary-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px; }
    @media (max-width:900px) { .sr-summary-grid { grid-template-columns:repeat(2,1fr); } }
    .sr-summary-card { background:#fff; border-radius:14px; padding:12px 14px; box-shadow:0 4px 14px rgba(15,23,42,0.06); border:1px solid #F1F5F9; }
    .sr-summary-card .lbl { font-size:10.5px; color:#94A3B8; font-weight:700; text-transform:uppercase; }
    .sr-summary-card .val { font-size:14px; font-weight:800; color:#0F172A; margin-top:4px; }
    .sr-summary-card.highlight .val { color:var(--sr-primary); font-size:16px; }

    .sr-card { background:#fff; border-radius:16px; box-shadow:0 4px 16px rgba(15,23,42,0.06); padding:16px; margin-bottom:14px; }
    .sr-card-title { display:flex; align-items:center; gap:8px; font-weight:800; color:#0F172A; font-size:13.5px; margin-bottom:12px; }

    .sr-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
    @media (max-width:700px) { .sr-grid-2 { grid-template-columns: 1fr; } }

    .sr-field { margin-bottom:0; position:relative; }
    .sr-field label { display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:5px; }
    .sr-field input, .sr-field select, .sr-field textarea {
        width:100%; padding:11px 13px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:13px; box-sizing:border-box; font-family:inherit;
    }
    .sr-field input:focus, .sr-field select:focus, .sr-field textarea:focus { outline:none; border-color:var(--sr-primary); box-shadow:0 0 0 3px rgba(5,150,105,0.12); }

    .sr-search-btn { background:linear-gradient(135deg,var(--sr-primary),var(--sr-primary-dark)); color:#fff; border:none; border-radius:10px; padding:0 18px; font-weight:800; font-size:13px; cursor:pointer; height:43px; }

    .sr-card { position:relative; }
    .sr-results { position:absolute; left:0; right:0; top:calc(100% + 4px); background:#fff; border:1.5px solid #111; border-radius:12px; box-shadow:0 14px 30px rgba(15,23,42,0.18); max-height:360px; overflow-y:auto; z-index:80; display:none; }
    .sr-results.open { display:block; }
    .sr-result-item { padding:10px 13px; cursor:pointer; border-bottom:1px solid #E5E7EB; font-size:14px; font-weight:800; color:#111; background:#fff; text-align:left; width:100%; }
    .sr-result-item:hover { background:#F8FAFC; }
    .sr-result-item small { color:#111; display:block; font-size:12px; font-weight:600; margin-top:3px; }

    .sr-cust-chip { display:none; align-items:center; gap:10px; background:var(--sr-bg); border:1.5px solid var(--sr-border); border-radius:12px; padding:10px 12px; margin-top:8px; }
    .sr-cust-chip.show { display:flex; }
    .sr-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--sr-primary),var(--sr-primary-dark)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }

    .sr-table-wrap { overflow-x:auto; }
    .sr-items-table { width:100%; border-collapse:collapse; font-size:11.5px; min-width:900px; }
    .sr-items-table th { background:#0F172A; color:#fff; padding:9px 7px; text-align:left; white-space:nowrap; }
    .sr-items-table td { padding:7px; border-bottom:1px solid #F1F5F9; }
    .sr-items-table input, .sr-items-table select { width:74px; padding:6px; border:1.5px solid #E2E8F0; border-radius:6px; font-size:11.5px; }
    .sr-items-table select.sr-price-type { width:92px; }
    .sr-price-modes { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0 0 10px; }
    .sr-price-modes span { font-size:12px; font-weight:800; color:#111; }
    .sr-price-modes button { min-height:32px; padding:4px 10px; border-radius:8px; border:1.5px solid #111; background:#fff; color:#111; font-weight:800; font-size:12px; cursor:pointer; }
    .sr-price-modes button.is-on { border-width:2.5px; text-decoration:underline; }
    .sr-remove-btn { color:var(--sr-danger); cursor:pointer; font-weight:800; }
    .sr-empty-row td { text-align:center; color:#94A3B8; padding:26px; font-size:12.5px; }

    .sr-radio-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    @media (max-width:600px) { .sr-radio-grid { grid-template-columns:1fr; } }
    .sr-radio-option { border:1.5px solid #E2E8F0; border-radius:10px; padding:12px; display:flex; align-items:center; gap:8px; cursor:pointer; font-size:12.5px; font-weight:600; }
    .sr-radio-option.active { border-color:var(--sr-primary); background:var(--sr-bg); color:var(--sr-primary-dark); font-weight:800; }

    .sr-char-counter { text-align:right; font-size:10.5px; color:#94A3B8; margin-top:3px; }

    .sr-calc-row { display:flex; justify-content:space-between; padding:7px 0; font-size:12.5px; border-bottom:1px solid #F1F5F9; }
    .sr-calc-row.total { font-weight:800; color:var(--sr-primary-dark); font-size:16px; border-bottom:none; padding-top:10px; }

    .sr-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
    .sr-btn { flex:1; min-width:120px; padding:14px; border:none; border-radius:12px; font-weight:800; font-size:14px; cursor:pointer; }
    .sr-btn-cancel { background:#F1F5F9; color:#64748B; }
    .sr-btn-submit { background:linear-gradient(135deg,var(--sr-primary),var(--sr-primary-dark)); color:#fff; }

    .sr-registry-balance { margin-top:10px; padding:12px 14px; border-radius:12px; background:#FFFBEB; border:1px solid #FDE68A; font-size:13px; font-weight:800; color:#92400E; display:none; }
    .sr-registry-balance.show { display:block; }
    .sr-memo-table { width:100%; border-collapse:collapse; font-size:12px; margin-top:10px; }
    .sr-memo-table th { background:#0F172A; color:#fff; padding:8px 6px; text-align:left; white-space:nowrap; }
    .sr-memo-table td { padding:8px 6px; border-bottom:1px solid #F1F5F9; vertical-align:middle; }
    .sr-memo-actions { display:flex; flex-wrap:wrap; gap:6px; }
    .sr-memo-btn { border:none; border-radius:8px; padding:6px 10px; font-size:11px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; }
    .sr-memo-btn.open { background:var(--sr-primary); color:#fff; }
    .sr-memo-btn.memo { background:#E0F2FE; color:#0369A1; }
    .sr-memo-btn.ex { background:#EDE9FE; color:#5B21B6; }
    .sr-memo-muted { color:#94A3B8; font-size:11px; }
</style>

<div class="sr-wrap">
    <div class="sr-topbar">
        <div>
            <h2>🔄 বিক্রয় ফেরত <span style="font-weight:400; color:#94A3B8; font-size:14px;">Sales Return</span></h2>
            <p>গ্রাহকের কাছ থেকে ফেরত আসা পণ্যের হিসাব তৈরি ও সংরক্ষণ করুন</p>
        </div>
        <a href="dashboard.php" class="sr-back-link">← ড্যাশবোর্ডে ফিরে যান</a>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#FEF2F2; color:var(--sr-danger); padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- সারসংক্ষেপ কার্ড -->
    <div class="sr-summary-grid">
        <div class="sr-summary-card"><div class="lbl">ফেরত নং</div><div class="val"><?php echo htmlspecialchars($preview_return_no); ?></div></div>
        <div class="sr-summary-card"><div class="lbl">গ্রাহক</div><div class="val" id="summaryCustomer">রিটেল কাস্টমার</div></div>
        <div class="sr-summary-card"><div class="lbl">তারিখ</div><div class="val"><?php echo date('d/m/Y'); ?></div></div>
        <div class="sr-summary-card"><div class="lbl">মোট পণ্য</div><div class="val" id="summaryItemCount">0 আইটেম</div></div>
        <div class="sr-summary-card highlight"><div class="lbl">মোট ফেরত</div><div class="val" id="summaryTotal">৳0.00</div></div>
    </div>

    <form action="actions/save_invoice_return.php" method="POST" id="returnForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="sale_id" id="saleIdInput">
        <input type="hidden" name="customer_id" id="customerIdInput">
        <input type="hidden" name="customer_name" id="customerNameInput">
        <input type="hidden" name="sales_type" id="salesTypeInput" value="retail">
        <input type="hidden" name="items_json" id="itemsJsonInput">
        <input type="hidden" name="refund_method" id="refundMethodInput" value="customer_balance">
        <input type="hidden" name="is_manual" id="isManualInput" value="0">

        <!-- মোড নির্বাচন -->
        <div style="display:flex; gap:8px; margin-bottom:14px;">
            <div class="sr-mode-tab active" id="invoiceModeTab" onclick="switchReturnMode('invoice')" style="flex:1; text-align:center; padding:11px; border-radius:10px; border:1.5px solid var(--sr-primary); background:var(--sr-bg); color:var(--sr-primary-dark); font-weight:800; font-size:12.5px; cursor:pointer;">🧾 ইনভয়েস থেকে</div>
            <div class="sr-mode-tab" id="manualModeTab" onclick="switchReturnMode('manual')" style="flex:1; text-align:center; padding:11px; border-radius:10px; border:1.5px solid #E2E8F0; background:#fff; color:#64748B; font-weight:800; font-size:12.5px; cursor:pointer;">✍️ ইনভয়েস ছাড়া (ম্যানুয়াল)</div>
        </div>

        <!-- ইনভয়েস মোড -->
        <div id="invoiceModeSection">
        <div class="sr-card" id="srRegistryCard">
            <div class="sr-card-title">📋 রেজিস্টার ক্রেতা — নাম দিয়ে মেমো খুঁজুন</div>
            <p class="sr-memo-muted">নিবন্ধিত ক্রেতার নাম/ফোন লিখুন → তার মেমো তালিকা → ফেরত বা এক্সচেঞ্জ খুলুন। সাবেক বকেয়া/জমা মেমো সহ দেখাবে।</p>
            <div style="position:relative;">
                <input type="text" id="registryCustSearch" autocomplete="off" placeholder="রেজিস্টার ক্রেতার নাম, কোড বা ফোন...">
                <div class="sr-results" id="registryCustResults"></div>
            </div>
            <div class="sr-registry-balance" id="registryBalanceBox">
                <span id="registryBalanceText">সাবেক ব্যালেন্স: ৳0.00</span>
            </div>
            <div id="registryMemoHost"></div>
            <div id="registryProductBlock" style="margin-top:14px; padding-top:14px; border-top:1px dashed #E2E8F0; display:none;">
                <div class="sr-card-title" style="margin-bottom:6px;">📦 ক্রেতার যেকোনো মেমো থেকে পণ্য ফেরত</div>
                <p class="sr-memo-muted">ক্রেতা বেছে নিলে তার সব সম্পন্ন মেমোর ফেরতযোগ্য পণ্য এখানে খুঁজুন — মেমো নম্বর মনে না থাকলেও চলে।</p>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <input type="text" id="registryProdSearch" autocomplete="off" placeholder="পণ্যের নাম, কোড, বারকোড..." style="flex:1; min-width:180px;">
                    <button type="button" class="sr-search-btn" id="registryProdSearchBtn">🔍 পণ্য খুঁজুন</button>
                </div>
                <div class="sr-results" id="registryProdResults" style="position:relative; max-height:280px;"></div>
            </div>
        </div>
        <div class="sr-card">
            <div class="sr-card-title">🧾 ইনভয়েস নির্বাচন করুন (ঐচ্ছিক — মেমো নম্বর জানা থাকলে)</div>
            <div style="display:flex; gap:8px;">
                <input type="text" id="invoiceSearchInput" placeholder="মেমো #, বিক্রয় নম্বর বা INV-20261114-060739" style="flex:1;">
                <button type="button" class="sr-search-btn" onclick="searchInvoice()">🔍 খুঁজুন</button>
            </div>
            <div id="searchNote" style="font-size:11.5px; color:#94A3B8; margin-top:8px;">বিক্রয় ইতিহাসে যে মেমো নম্বর (#60739) বা পূর্ণ ইনভয়েস নম্বর দেখায় — যেকোনো একটি দিলেই গ্রাহক ও পণ্য লোড হবে।</div>
            <div class="sr-cust-chip" id="custChip">
                <div class="sr-avatar" id="custAvatar">?</div>
                <div><strong id="custChipName"></strong><br><small style="color:#64748B;" id="custChipMeta"></small></div>
            </div>
        </div>
        </div>

        <!-- ম্যানুয়াল মোড (ইনভয়েস ছাড়া) -->
        <div id="manualModeSection" style="display:none;">
            <div class="sr-card">
                <div class="sr-card-title">👤 গ্রাহক নির্বাচন করুন (ঐচ্ছিক — Retail হলে খালি রাখুন)</div>
                <div style="position:relative;">
                    <input type="text" id="manualCustSearch" autocomplete="off" placeholder="গ্রাহকের নাম বা ফোন দিয়ে খুঁজুন...">
                    <div class="sr-results" id="manualCustResults"></div>
                </div>
                <div class="sr-cust-chip" id="manualCustChip">
                    <div class="sr-avatar" id="manualCustAvatar">?</div>
                    <div><strong id="manualCustChipName"></strong></div>
                </div>
            </div>

            <div class="sr-card">
                <div class="sr-card-title">📦 পণ্য নির্বাচন করে যোগ করুন</div>
                <div style="position:relative;">
                    <input type="text" id="manualProdSearch" autocomplete="off" placeholder="পণ্যের নাম বা আইডি দিয়ে খুঁজুন...">
                    <div class="sr-results" id="manualProdResults"></div>
                </div>
                <p style="font-size:11px; color:#94A3B8; margin:8px 0 0;">ⓘ ম্যানুয়াল মোডে ফেরত পরিমাণ কোনো পূর্ববর্তী ইনভয়েসের সাথে যাচাই করা হয় না।</p>
            </div>
        </div>

        <div class="sr-card">
            <div class="sr-card-title">📋 ফেরত পণ্যের তালিকা <span style="font-weight:400; color:#94A3B8;">Return Items</span></div>
            <div class="sr-price-modes" id="srPriceModes">
                <span>ফেরতের দাম</span>
                <button type="button" data-sr-price="wholesale">পাইকারি</button>
                <button type="button" data-sr-price="retail" class="is-on">খুচরা</button>
                <button type="button" data-sr-price="dealer">ডিলার</button>
                <button type="button" data-sr-price="price">প্রাইস</button>
            </div>
            <p id="srPriceNote" role="status" style="display:none; margin:6px 0 0; font-size:12px; font-weight:700; color:#B45309;"></p>
            <div class="sr-table-wrap">
                <table class="sr-items-table">
                    <thead>
                        <tr><th>#</th><th>পণ্য</th><th>বিক্রিত</th><th>আগে ফেরত</th><th>ফেরত পরিমাণ</th><th>মূল্যের ধরন</th><th>একক মূল্য</th><th>ছাড় %</th><th>ফেরতের কারণ</th><th>মোট</th><th></th></tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="sr-empty-row"><td colspan="11">উপরে ইনভয়েস খুঁজে লোড করুন</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sr-grid-2">
            <div class="sr-card" id="refundPayCard">
                <div class="sr-card-title">💳 ফেরত পদ্ধতি ও একাউন্ট <span style="font-weight:400; color:#94A3B8;">Refund Method &amp; Account</span></div>
                <input type="hidden" name="posted_account_id" id="postedAccountId" value="">
                <div class="sr-field" id="accountGroup" style="display:block; margin-bottom:12px;">
                    <label>কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস (−) হবে *</label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" id="accountSelect">
                        <?php echo ame_render_account_options($accounts, 0, 'যে অ্যাকাউন্ট থেকে ফেরত যাবে'); ?>
                    </select>
                </div>
                <div style="font-size:11.5px; font-weight:700; color:#334155; margin-bottom:6px;">অথবা দ্রুত একাউন্ট / সমন্বয় পদ্ধতি নির্বাচন করুন:</div>
                <div class="sr-radio-grid" style="margin-bottom:8px;">
                    <?php foreach ($accounts as $acc):
                        $accId = (int)($acc['id'] ?? 0);
                        if ($accId < 1) continue;
                        $accName = trim((string)($acc['account_name'] ?? ''));
                        $accType = ame_account_payment_method($acc);
                        $accMethod = $accType === 'bank' ? 'bank_transfer' : ($accType === 'mobile' ? 'mobile_banking' : 'cash_refund');
                        $accIcon = $accType === 'bank' ? '🏦' : ($accType === 'mobile' ? '📱' : '💵');
                    ?>
                    <div class="sr-radio-option sr-acc-quick-btn" data-acc-id="<?php echo $accId; ?>" data-method="<?php echo $accMethod; ?>" data-acc-name="<?php echo htmlspecialchars($accName, ENT_QUOTES, 'UTF-8'); ?>" onclick="pickReturnAccount(<?php echo $accId; ?>, '<?php echo $accMethod; ?>', this)">
                        <?php echo $accIcon . ' ' . htmlspecialchars($accName, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endforeach; ?>
                    <div class="sr-radio-option" id="custBalanceOption" data-val="customer_balance" onclick="setRefundMethod('customer_balance')">👤 গ্রাহক ব্যালেন্স সমন্বয়</div>
                </div>
                <div class="sr-radio-grid" style="margin-top:6px;">
                    <div class="sr-radio-option" data-val="cash_refund" onclick="setRefundMethod('cash_refund')">💵 নগদ ফেরত</div>
                    <div class="sr-radio-option" data-val="bank_transfer" onclick="setRefundMethod('bank_transfer')">🏦 ব্যাংক ট্রান্সফার</div>
                    <div class="sr-radio-option" data-val="mobile_banking" onclick="setRefundMethod('mobile_banking')">📱 মোবাইল ব্যাংকিং</div>
                </div>
                <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 12px; border-radius:9px; font-size:12px; font-weight:800; margin-top:10px; display:none;" id="accountDeductPreview">
                    🔻 নির্বাচিত একাউন্ট থেকে মাইনাস হবে: <span id="deductAccName">—</span> (<span id="deductAccAmt">−৳0.00</span>)
                </div>
                <div style="background:var(--sr-bg); color:var(--sr-primary-dark); padding:10px 12px; border-radius:9px; font-size:11.5px; margin-top:10px;" id="balanceNote">
                    যে একাউন্ট নির্বাচন করবেন সেই একাউন্ট থেকে ফেরতের টাকা মাইনাস (−) হবে। নিবন্ধিত গ্রাহকের বকেয়া থেকে সমন্বয় করতে চাইলে «গ্রাহক ব্যালেন্স সমন্বয়» নির্বাচন করুন।
                </div>
            </div>

            <div class="sr-card">
                <div class="sr-card-title">📝 মন্তব্য <span style="font-weight:400; color:#94A3B8;">Notes (Optional)</span></div>
                <textarea name="notes" id="notesInput" rows="4" maxlength="500" placeholder="ফেরতের বিষয়ে অতিরিক্ত তথ্য লিখুন..." oninput="updateCharCounter()"></textarea>
                <div class="sr-char-counter"><span id="charCount">0</span>/500</div>
            </div>
        </div>

        <div class="sr-card">
            <div class="sr-card-title">🧮 হিসাবের তথ্য <span style="font-weight:400; color:#94A3B8;">Return Calculation</span></div>
            <div class="sr-calc-row"><span>উপ-মোট (Subtotal)</span><span id="subtotalDisplay">৳0.00</span></div>
            <div class="sr-calc-row"><span>মোট ছাড় (-)</span><span id="discountDisplay">৳0.00</span></div>
            <div class="sr-calc-row total"><span>মোট ফেরত / Net Return</span><span id="netTotalDisplay">৳0.00</span></div>
            <div id="registeredDueBox" style="display:none; margin-top:10px; padding:10px 12px; border:1px solid #FDE68A; border-radius:10px; background:#FFFBEB;">
                <div class="sr-calc-row"><span>সাবেক বকেয়া</span><span id="regPrevDue">৳0.00</span></div>
                <div class="sr-calc-row"><span>ফেরত (−)</span><span id="regCutDue">−৳0.00</span></div>
                <div class="sr-calc-row total"><span>সর্বমোট ব্যালেন্স</span><span id="regAfterDue">৳0.00</span></div>
                <p style="margin:6px 0 0; font-size:12px; font-weight:700; color:#92400E;">রেজিস্টার কাস্টমারের ফেরত টাকা সাবেক বকেয়া থেকে মাইনাস হবে। সর্বমোট ব্যালেন্স মেমোতে দেখাবে এবং মেমো শেয়ার করা যাবে।</p>
            </div>
        </div>

        <div class="sr-actions">
            <a href="dashboard.php" class="sr-btn sr-btn-cancel" style="text-align:center; text-decoration:none;">✕ বাতিল করুন</a>
            <button type="submit" class="sr-btn sr-btn-submit">✓ বিক্রয় ফেরত সংরক্ষণ করুন</button>
        </div>
    </form>
</div>

<script>
var currentItems = [];
var cart = [];
var REASON_OPTIONS = ['পণ্য ক্ষতিগ্রস্ত', 'ভুল পণ্য সরবরাহ', 'ভুল সাইজ/ভ্যারিয়েন্ট', 'গ্রাহকের পছন্দ নয়', 'অতিরিক্ত পণ্য', 'মেয়াদ শেষ', 'Warranty Return', 'অন্যান্য'];

function fmtBal(n) {
    var v = Number(n) || 0;
    if (v > 0.009) return 'বকেয়া ৳' + v.toFixed(2);
    if (v < -0.009) return 'জমা ৳' + Math.abs(v).toFixed(2);
    return 'সমান ৳0.00';
}

var srRegistryCustomerId = 0;
var srFocusProductId = 0;

function bindRegistryCustomer(data) {
    if (!data || !data.customer) return;
    srRegistryCustomerId = Number(data.customer.id) || 0;
    document.getElementById('customerIdInput').value = srRegistryCustomerId;
    document.getElementById('customerNameInput').value = data.customer.name || '';
    document.getElementById('summaryCustomer').textContent = data.customer.name || '';
    applyRegisteredReturnLock(srRegistryCustomerId > 0, Number(data.balance) || 0);
    var block = document.getElementById('registryProductBlock');
    if (block) block.style.display = srRegistryCustomerId > 0 ? 'block' : 'none';
}

function loadInvoiceMemo(ref, productId) {
    if (!ref) return;
    srFocusProductId = productId ? Number(productId) : 0;
    switchReturnMode('invoice');
    document.getElementById('invoiceSearchInput').value = ref;
    searchInvoice();
    document.getElementById('itemsBody').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function paintRegistryMemos(data) {
    var host = document.getElementById('registryMemoHost');
    var balBox = document.getElementById('registryBalanceBox');
    var balText = document.getElementById('registryBalanceText');
    if (!host) return;
    var memos = data.memos || [];
    if (!memos.length) {
        host.innerHTML = '<p class="sr-memo-muted" style="margin-top:10px;">এই ক্রেতার সম্পন্ন মেমো পাওয়া যায়নি।</p>';
        return;
    }
    var rows = '';
    memos.forEach(function (m) {
        var retTag = m.can_return
            ? '<span style="color:#059669;font-weight:800;">ফেরতযোগ্য ' + m.returnable_lines + ' লাইন</span>'
            : '<span class="sr-memo-muted">ফেরত নেই</span>';
        rows += '<tr>'
            + '<td><b>' + srEsc(m.invoice_number) + '</b><br><small>' + srEsc(m.sale_date) + '</small></td>'
            + '<td>৳' + Number(m.net_payable).toFixed(2) + '<br><small>বকেয়া ৳' + Number(m.due_amount).toFixed(2) + '</small></td>'
            + '<td>' + retTag + '</td>'
            + '<td><div class="sr-memo-actions">'
            + (m.can_return ? '<button type="button" class="sr-memo-btn open" onclick="loadInvoiceMemo(' + JSON.stringify(String(m.search_ref)) + ')">মেমো খুলুন</button>' : '')
            + '<a class="sr-memo-btn memo" href="../../sales/invoice.php?id=' + encodeURIComponent(String(m.sale_id)) + '" target="_blank" rel="noopener">মেমো</a>'
            + '<a class="sr-memo-btn ex" href="create_exchange.php?customer_id=' + encodeURIComponent(String(data.customer.id)) + '">এক্সচেঞ্জ</a>'
            + '</div></td></tr>';
    });
    host.innerHTML = '<table class="sr-memo-table"><thead><tr><th>মেমো</th><th>বিক্রয়</th><th>ফেরত</th><th>কর্ম</th></tr></thead><tbody>' + rows + '</tbody></table>';
    if (balBox && balText) {
        balText.textContent = (data.customer.name || '') + ' — সাবেক: ' + fmtBal(data.balance);
        balBox.classList.add('show');
    }
    bindRegistryCustomer(data);
}

function fetchRegistryMemos(customerId) {
    if (!customerId) return;
    document.getElementById('registryMemoHost').innerHTML = '<p class="sr-memo-muted">লোড হচ্ছে...</p>';
    fetch('actions/search_customer_memos.php?customer_id=' + encodeURIComponent(String(customerId)), { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status !== 'success') {
                document.getElementById('registryMemoHost').innerHTML = '<p class="sr-memo-muted">' + srEsc(data.message || 'তথ্য পাওয়া যায়নি') + '</p>';
                return;
            }
            paintRegistryMemos(data);
        })
        .catch(function () {
            document.getElementById('registryMemoHost').innerHTML = '<p class="sr-memo-muted">সার্ভার ত্রুটি।</p>';
        });
}

var registryCustSearch = document.getElementById('registryCustSearch');
var registryCustResults = document.getElementById('registryCustResults');
if (registryCustSearch && registryCustResults) {
    srBindLookup(registryCustSearch, registryCustResults, 'customer', function (c) {
        var meta = [];
        if (c.code) meta.push('কোড ' + c.code);
        if (c.phone) meta.push('ফোন ' + c.phone);
        meta.push(srMoney(c.balance));
        return srEsc(c.name) + '<small>' + srEsc(meta.join(' · ')) + '</small>';
    }, function (c) {
        registryCustSearch.value = c.name;
        bindRegistryCustomer({ customer: { id: c.id, name: c.name }, balance: c.balance });
        fetchRegistryMemos(c.id);
    });
}

(function () {
    var prodInput = document.getElementById('registryProdSearch');
    var prodBtn = document.getElementById('registryProdSearchBtn');
    var prodBox = document.getElementById('registryProdResults');
    if (!prodInput || !prodBox) return;

    function runRegistryProductSearch() {
        if (!srRegistryCustomerId) {
            prodBox.innerHTML = '<div class="sr-result-item" style="color:#B45309;font-weight:700;">আগে উপরে রেজিস্টার ক্রেতা বেছে নিন।</div>';
            prodBox.classList.add('open');
            return;
        }
        var q = prodInput.value.trim();
        prodBox.innerHTML = '<div class="sr-result-item">খোঁজা হচ্ছে...</div>';
        prodBox.classList.add('open');
        fetch('actions/search_customer_products.php?customer_id=' + encodeURIComponent(String(srRegistryCustomerId)) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status !== 'success' || !data.lines || !data.lines.length) {
                    prodBox.innerHTML = '<div class="sr-result-item">' + srEsc(data.message || 'ফেরতযোগ্য পণ্য পাওয়া যায়নি।') + '</div>';
                    return;
                }
                prodBox.innerHTML = data.lines.map(function (ln, idx) {
                    return '<button type="button" class="sr-result-item" data-reg-prod="' + idx + '">'
                        + srEsc(ln.product_name) + ' <small>মেমো ' + srEsc(ln.invoice_number)
                        + ' · বাকি ' + Number(ln.remaining).toFixed(2) + ' · ৳' + Number(ln.unit_price).toFixed(2) + '</small></button>';
                }).join('');
                prodBox._lines = data.lines;
            })
            .catch(function () {
                prodBox.innerHTML = '<div class="sr-result-item">সার্ভার ত্রুটি।</div>';
            });
    }

    prodBtn.addEventListener('click', runRegistryProductSearch);
    prodInput.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            runRegistryProductSearch();
        }
    });
    prodBox.addEventListener('click', function (ev) {
        var hit = ev.target.closest('[data-reg-prod]');
        if (!hit || !prodBox._lines) return;
        var ln = prodBox._lines[Number(hit.getAttribute('data-reg-prod'))];
        if (!ln) return;
        loadInvoiceMemo(ln.search_ref, ln.product_id);
        prodBox.classList.remove('open');
    });
})();

function switchReturnMode(mode) {
    document.getElementById('isManualInput').value = mode === 'manual' ? '1' : '0';
    document.getElementById('invoiceModeSection').style.display = mode === 'invoice' ? 'block' : 'none';
    document.getElementById('manualModeSection').style.display = mode === 'manual' ? 'block' : 'none';

    document.getElementById('invoiceModeTab').style.borderColor = mode === 'invoice' ? 'var(--sr-primary)' : '#E2E8F0';
    document.getElementById('invoiceModeTab').style.background = mode === 'invoice' ? 'var(--sr-bg)' : '#fff';
    document.getElementById('invoiceModeTab').style.color = mode === 'invoice' ? 'var(--sr-primary-dark)' : '#64748B';
    document.getElementById('manualModeTab').style.borderColor = mode === 'manual' ? 'var(--sr-primary)' : '#E2E8F0';
    document.getElementById('manualModeTab').style.background = mode === 'manual' ? 'var(--sr-bg)' : '#fff';
    document.getElementById('manualModeTab').style.color = mode === 'manual' ? 'var(--sr-primary-dark)' : '#64748B';

    cart = [];
    renderCart();
    document.getElementById('saleIdInput').value = '';
    document.getElementById('customerIdInput').value = '';
    document.getElementById('customerNameInput').value = '';
    document.getElementById('custChip').classList.remove('show');
    document.getElementById('manualCustChip').classList.remove('show');
    document.getElementById('summaryCustomer').textContent = 'রিটেল কাস্টমার';
    applyRegisteredReturnLock(false, 0);
}

function srEsc(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function srMoney(n) {
    var v = Number(n) || 0;
    var sign = v < -0.009 ? 'জমা ' : (v > 0.009 ? 'বকেয়া ' : 'সমান ');
    return sign + '৳' + Math.abs(v).toFixed(2);
}
function srBindLookup(input, box, kind, paint, pick) {
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
                    if (!rows.length) {
                        box.innerHTML = '<div class="sr-result-item">কোনো মিল পাওয়া যায়নি</div>';
                    } else {
                        box.innerHTML = rows.map(function (row, idx) {
                            return '<button type="button" class="sr-result-item" data-idx="' + idx + '">' + paint(row) + '</button>';
                        }).join('');
                    }
                    box.classList.add('open');
                })
                .catch(function () {
                    box.innerHTML = '<div class="sr-result-item">সার্চ লোড হয়নি। আবার লিখুন।</div>';
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
var manualCustSearch = document.getElementById('manualCustSearch');
var manualCustResults = document.getElementById('manualCustResults');
var closeCust = srBindLookup(manualCustSearch, manualCustResults, 'customer', function (c) {
    var meta = [];
    if (c.code) meta.push('কোড ' + c.code);
    if (c.phone) meta.push('ফোন ' + c.phone);
    if (c.alt) meta.push('বিকল্প ' + c.alt);
    if (c.address) meta.push(c.address);
    meta.push(srMoney(c.balance));
    return srEsc(c.name) + '<small>' + srEsc(meta.join(' · ')) + '</small>';
}, function (c) {
    document.getElementById('customerIdInput').value = c.id;
    document.getElementById('customerNameInput').value = c.name;
    document.getElementById('manualCustAvatar').textContent = String(c.name || '?').charAt(0);
    document.getElementById('manualCustChipName').textContent = c.name;
    document.getElementById('manualCustChip').classList.add('show');
    document.getElementById('summaryCustomer').textContent = c.name;
});

var manualProdSearch = document.getElementById('manualProdSearch');
var manualProdResults = document.getElementById('manualProdResults');
var closeProd = srBindLookup(manualProdSearch, manualProdResults, 'product', function (p) {
    var meta = ['আইডি ' + p.id];
    if (p.code) meta.push('কোড ' + p.code);
    if (p.barcode && p.barcode !== p.code) meta.push('বারকোড ' + p.barcode);
    meta.push('স্টক ' + p.stock);
    meta.push('খুচরা ৳' + Number(p.sale_price || 0).toFixed(2));
    meta.push('পাইকারি ৳' + Number(p.wholesale_price || 0).toFixed(2));
    meta.push('ডিলার ৳' + Number(p.dealer_price || 0).toFixed(2));
    if (p.unit) meta.push(p.unit);
    return srEsc(p.name) + '<small>' + srEsc(meta.join(' · ')) + '</small>';
}, function (p) { addManualItemToCart(p); });

document.addEventListener('click', function (e) {
    if (!e.target.closest('#manualCustSearch') && !e.target.closest('#manualCustResults')) { closeCust(); }
    if (!e.target.closest('#manualProdSearch') && !e.target.closest('#manualProdResults')) { closeProd(); }
});

var srDefaultPrice = 'retail';
function srUnitFor(item, type) {
    if (type === 'wholesale') return Number(item.wholesale_price) || 0;
    if (type === 'dealer') return Number(item.dealer_price) || 0;
    if (type === 'price') return Number(item.ask_price) || 0;
    return Number(item.retail_price) || 0;
}
function srNote(msg) {
    var el = document.getElementById('srPriceNote');
    if (!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? 'block' : 'none';
}
var srTypeLabels = { wholesale: 'পাইকারি', retail: 'খুচরা', dealer: 'ডিলার', price: 'প্রাইস' };
function srApplyType(item, type) {
    var next = srUnitFor(item, type);
    item.price_type = type;
    if (next <= 0 && type !== 'price') {
        return item.name;
    }
    item.unit_price = next;
    return '';
}
function srPaintRow(idx) {
    var item = cart[idx];
    var row = document.getElementById('itemsBody').children[idx];
    if (!item || !row) return;
    var inputs = row.querySelectorAll('input');
    var sel = row.querySelector('.sr-price-type');
    if (sel) sel.value = item.price_type;
    if (inputs[1] && document.activeElement !== inputs[1]) inputs[1].value = Number(item.unit_price).toFixed(2);
    var cell = row.querySelector('.sr-line-total');
    if (cell) cell.textContent = '৳' + lineTotal(item).toFixed(2);
}
function liveItem(idx, field, el) {
    var item = cart[idx];
    if (!item) return;
    var v = parseFloat(el.value);
    if (!isFinite(v) || v < 0) v = 0;
    if (field === 'returned_qty') {
        if (!item.is_manual && v > item.remaining) { v = item.remaining; }
        item.returned_qty = v;
    } else if (field === 'unit_price') {
        item.unit_price = v;
        item.ask_price = v;
        item.price_type = 'price';
    } else if (field === 'discount_pct') {
        item.discount_pct = Math.min(100, v);
    }
    srPaintRow(idx);
    recalc();
}
function srPriceOptions(item) {
    return '<option value="wholesale"' + (item.price_type === 'wholesale' ? ' selected' : '') + '>পাইকারি</option>' +
        '<option value="retail"' + (item.price_type === 'retail' ? ' selected' : '') + '>খুচরা</option>' +
        '<option value="dealer"' + (item.price_type === 'dealer' ? ' selected' : '') + '>ডিলার</option>' +
        '<option value="price"' + (item.price_type === 'price' ? ' selected' : '') + '>প্রাইস</option>';
}
function srMarkPriceMode(type) {
    srDefaultPrice = type;
    document.querySelectorAll('#srPriceModes button').forEach(function (btn) {
        btn.classList.toggle('is-on', btn.getAttribute('data-sr-price') === type);
    });
}
document.getElementById('srPriceModes').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-sr-price]');
    if (!btn) return;
    var type = btn.getAttribute('data-sr-price');
    srMarkPriceMode(type);
    var missing = [];
    cart.forEach(function (item, idx) {
        var miss = srApplyType(item, type);
        if (miss) missing.push(miss);
        srPaintRow(idx);
    });
    srNote(missing.length ? (srTypeLabels[type] + ' দাম সেট নেই, আগের দামই আছে: ' + missing.join(', ')) : '');
    recalc();
});

function addManualItemToCart(p) {
    if (cart.find(function (c) { return c.product_id === p.id; })) { return; }
    var item = {
        product_id: p.id, name: p.name, sold_qty: null, already_returned: 0, remaining: 999999, returned_qty: 1,
        price_type: srDefaultPrice, retail_price: parseFloat(p.sale_price) || 0, wholesale_price: parseFloat(p.wholesale_price) || 0,
        dealer_price: parseFloat(p.dealer_price) || 0, ask_price: parseFloat(p.sale_price) || 0,
        unit_price: 0, discount_pct: 0, reason: REASON_OPTIONS[0], is_manual: true
    };
    item.unit_price = srUnitFor(item, 'retail');
    srApplyType(item, srDefaultPrice);
    cart.push(item);
    renderCart();
}

var registeredInvoice = false;
var registeredDue = 0;
function applyRegisteredReturnLock(on, due) {
    registeredInvoice = !!on;
    registeredDue = Number(due) || 0;
    var box = document.getElementById('registeredDueBox');
    var pay = document.getElementById('refundPayCard');
    if (registeredInvoice) {
        setRefundMethod('customer_balance');
    }
    if (box) box.style.display = registeredInvoice ? 'block' : 'none';
    if (pay) pay.style.display = registeredInvoice ? 'none' : 'block';
    paintRegisteredDue();
}
function paintRegisteredDue(net) {
    if (!registeredInvoice) return;
    if (typeof net !== 'number') {
        var netText = document.getElementById('netTotalDisplay') ? document.getElementById('netTotalDisplay').textContent.replace(/[^\d.]/g, '') : '0';
        net = parseFloat(netText) || 0;
    }
    var after = registeredDue - net;
    var prevEl = document.getElementById('regPrevDue');
    var cutEl = document.getElementById('regCutDue');
    var afterEl = document.getElementById('regAfterDue');
    if (prevEl) prevEl.textContent = '৳' + registeredDue.toFixed(2);
    if (cutEl) cutEl.textContent = '−৳' + net.toFixed(2);
    if (afterEl) afterEl.textContent = '৳' + after.toFixed(2);
}

function searchInvoice() {
    var invoiceNo = document.getElementById('invoiceSearchInput').value.trim();
    if (!invoiceNo) { alert('ইনভয়েস নম্বর দিন।'); return; }
    document.getElementById('searchNote').textContent = 'খোঁজা হচ্ছে...';

    fetch('actions/search_invoice.php?invoice_no=' + encodeURIComponent(invoiceNo))
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status !== 'success') {
                document.getElementById('searchNote').textContent = data.message || 'ইনভয়েস পাওয়া যায়নি।';
                document.getElementById('custChip').classList.remove('show');
                applyRegisteredReturnLock(false, 0);
                return;
            }
            document.getElementById('searchNote').textContent = '';
            currentItems = data.items;
            cart = [];

            document.getElementById('saleIdInput').value = data.sale.id;
            document.getElementById('customerIdInput').value = data.sale.customer_id || '';
            document.getElementById('customerNameInput').value = data.sale.customer_name || '';
            document.getElementById('salesTypeInput').value = data.sale.sales_type || 'retail';

            document.getElementById('custAvatar').textContent = (data.sale.customer_name || '?').charAt(0);
            document.getElementById('custChipName').textContent = data.sale.customer_name || 'Retail Customer';
            document.getElementById('custChipMeta').textContent = 'ইনভয়েস: ' + data.sale.invoice_number + ' • তারিখ: ' + data.sale.sale_date + (data.sale.customer_phone ? ' • ' + data.sale.customer_phone : '');
            document.getElementById('custChip').classList.add('show');
            document.getElementById('summaryCustomer').textContent = data.sale.customer_name || 'Retail Customer';
            applyRegisteredReturnLock(!!data.sale.customer_id, data.sale.customer_balance);

            // সব পণ্য স্বয়ংক্রিয়ভাবে কার্টে যোগ (রেফারেন্স ডিজাইনের মতো, পরিমাণ ০ দিয়ে শুরু)
            currentItems.forEach(function (it) {
                var remaining = it.sold_qty - it.already_returned;
                if (remaining <= 0) return;
                var line = {
                    product_id: it.product_id, name: it.product_name, sold_qty: it.sold_qty,
                    already_returned: it.already_returned, remaining: remaining, returned_qty: 0,
                    price_type: srDefaultPrice, retail_price: it.retail_price || it.unit_price, wholesale_price: it.wholesale_price || 0,
                    dealer_price: it.dealer_price || 0, ask_price: it.unit_price,
                    unit_price: 0, discount_pct: 0, reason: REASON_OPTIONS[0], is_manual: false
                };
                line.unit_price = it.unit_price || srUnitFor(line, 'retail');
                srApplyType(line, srDefaultPrice);
                cart.push(line);
            });
            renderCart();
            if (srFocusProductId > 0) {
                var focusId = srFocusProductId;
                srFocusProductId = 0;
                cart.forEach(function (line, idx) {
                    if (line.product_id === focusId && (line.returned_qty || 0) < 0.001) {
                        line.returned_qty = Math.min(1, line.remaining || 1);
                        srPaintRow(idx);
                    }
                });
                recalc();
            }
        })
        .catch(function () {
            document.getElementById('searchNote').textContent = 'সার্ভারে সংযোগে সমস্যা হয়েছে।';
        });
}

function renderCart() {
    var body = document.getElementById('itemsBody');
    if (cart.length === 0) {
        var msg = document.getElementById('isManualInput').value === '1' ? 'উপরে পণ্য সার্চ করে যোগ করুন' : 'উপরে ইনভয়েস খুঁজে লোড করুন';
        body.innerHTML = '<tr class="sr-empty-row"><td colspan="11">' + msg + '</td></tr>';
        recalc();
        return;
    }
    body.innerHTML = '';
    cart.forEach(function (item, idx) {
        var reasonOptions = REASON_OPTIONS.map(function (r) {
            return '<option value="' + r + '"' + (item.reason === r ? ' selected' : '') + '>' + r + '</option>';
        }).join('');
        var row = document.createElement('tr');
        var qtyMax = item.is_manual ? '' : ('max="' + item.remaining + '"');
        row.innerHTML =
            '<td>' + (idx + 1) + '</td>' +
            '<td><b>' + item.name + '</b>' + (item.is_manual ? ' <small style="color:#D97706;">(ম্যানুয়াল)</small>' : '') + '</td>' +
            '<td>' + (item.is_manual ? '—' : item.sold_qty) + '</td>' +
            '<td>' + (item.is_manual ? '—' : item.already_returned) + '</td>' +
            '<td><input type="number" min="0.01" ' + qtyMax + ' step="0.01" value="' + item.returned_qty + '" oninput="liveItem(' + idx + ',\'returned_qty\',this)" onchange="updateItem(' + idx + ',\'returned_qty\',this.value)"></td>' +
            '<td><select class="sr-price-type" onchange="updateItem(' + idx + ',\'price_type\',this.value)">' +
                srPriceOptions(item) +
            '</select></td>' +
            '<td><input type="number" min="0" step="0.01" value="' + item.unit_price.toFixed(2) + '" oninput="liveItem(' + idx + ',\'unit_price\',this)" onchange="updateItem(' + idx + ',\'unit_price\',this.value)"></td>' +
            '<td><input type="number" min="0" max="100" step="0.01" value="' + item.discount_pct + '" oninput="liveItem(' + idx + ',\'discount_pct\',this)" onchange="updateItem(' + idx + ',\'discount_pct\',this.value)"></td>' +
            '<td><select onchange="updateItem(' + idx + ',\'reason\',this.value)" style="width:120px;">' + reasonOptions + '</select></td>' +
            '<td class="sr-line-total" style="font-weight:800;">৳' + lineTotal(item).toFixed(2) + '</td>' +
            '<td class="sr-remove-btn" onclick="removeItem(' + idx + ')">✖</td>';
        body.appendChild(row);
    });
    recalc();
}

function lineTotal(item) {
    var gross = item.returned_qty * item.unit_price;
    var discountAmt = gross * (item.discount_pct / 100);
    return gross - discountAmt;
}

function updateItem(idx, field, value) {
    var item = cart[idx];
    if (field === 'returned_qty') {
        var v = parseFloat(value) || 0;
        if (!item.is_manual && v > item.remaining) { v = item.remaining; }
        item.returned_qty = v;
    } else if (field === 'price_type') {
        var miss = srApplyType(item, value);
        srNote(miss ? (srTypeLabels[value] + ' দাম সেট নেই, আগের দামই আছে: ' + miss) : '');
        srPaintRow(idx);
        recalc();
        return;
    } else if (field === 'unit_price') {
        item.unit_price = parseFloat(value) || 0;
        item.ask_price = item.unit_price;
        item.price_type = 'price';
    } else if (field === 'discount_pct') {
        item.discount_pct = Math.min(100, Math.max(0, parseFloat(value) || 0));
    } else if (field === 'reason') {
        item.reason = value;
        recalc();
        return;
    }
    var row = document.getElementById('itemsBody').children[idx];
    if (row) {
        var inputs = row.querySelectorAll('input');
        if (inputs[0]) inputs[0].value = item.returned_qty;
        if (inputs[2]) inputs[2].value = item.discount_pct;
    }
    srPaintRow(idx);
    recalc();
}

function removeItem(idx) { cart.splice(idx, 1); renderCart(); }

function recalc() {
    var activeItems = cart.filter(function (i) { return i.returned_qty > 0; });
    var subtotal = 0, discountTotal = 0;

    activeItems.forEach(function (item) {
        var gross = item.returned_qty * item.unit_price;
        subtotal += gross;
        discountTotal += gross * (item.discount_pct / 100);
    });
    var net = subtotal - discountTotal;

    document.getElementById('subtotalDisplay').textContent = '৳' + subtotal.toFixed(2);
    document.getElementById('discountDisplay').textContent = '৳' + discountTotal.toFixed(2);
    document.getElementById('netTotalDisplay').textContent = '৳' + net.toFixed(2);
    document.getElementById('summaryTotal').textContent = '৳' + net.toFixed(2);
    document.getElementById('summaryItemCount').textContent = activeItems.length + ' আইটেম';
    updateDeductPreview();
    paintRegisteredDue(net);

    var payload = activeItems.map(function (i) {
        return {
            product_id: i.product_id, original_qty: i.sold_qty, returned_qty: i.returned_qty,
            unit_price: i.unit_price, discount_pct: i.discount_pct, reason: i.reason, price_type: i.price_type,
            is_manual: i.is_manual
        };
    });
    document.getElementById('itemsJsonInput').value = JSON.stringify(payload);
}

function inferMethodFromLabel(label) {
    var txt = String(label || '').toLowerCase();
    if (txt.indexOf('bank') !== -1 || txt.indexOf('ব্যাংক') !== -1 || txt.indexOf('ডাচ') !== -1 || txt.indexOf('dutch') !== -1) {
        return 'bank_transfer';
    }
    if (txt.indexOf('mobile') !== -1 || txt.indexOf('bkash') !== -1 || txt.indexOf('nagad') !== -1 || txt.indexOf('rocket') !== -1 || txt.indexOf('বিকাশ') !== -1 || txt.indexOf('নগদ একাউন্ট') !== -1 || txt.indexOf('রকেট') !== -1 || txt.indexOf('মোবাইল') !== -1) {
        return 'mobile_banking';
    }
    return 'cash_refund';
}

function updateDeductPreview() {
    var sel = document.getElementById('accountSelect');
    var method = document.getElementById('refundMethodInput').value;
    var preview = document.getElementById('accountDeductPreview');
    var nameEl = document.getElementById('deductAccName');
    var amtEl = document.getElementById('deductAccAmt');
    if (!preview || !sel) return;
    var netText = document.getElementById('netTotalDisplay') ? document.getElementById('netTotalDisplay').textContent.replace(/[^\d.]/g, '') : '0';
    var net = parseFloat(netText) || 0;
    if (sel.value && method !== 'customer_balance') {
        var opt = sel.options[sel.selectedIndex];
        var label = opt ? opt.textContent : ('Account #' + sel.value);
        nameEl.textContent = label;
        amtEl.textContent = '−৳' + net.toFixed(2);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function pickReturnAccount(accId, method, btnEl) {
    var sel = document.getElementById('accountSelect');
    if (sel) {
        sel.setAttribute('data-ame-user', '1');
        sel.setAttribute('data-ame-locked', String(accId));
        sel.value = String(accId);
    }
    var posted = document.getElementById('postedAccountId');
    if (posted) {
        posted.value = String(accId);
    }
    document.getElementById('refundMethodInput').value = method;
    document.querySelectorAll('.sr-acc-quick-btn').forEach(function (b) {
        b.classList.toggle('active', b === btnEl);
    });
    document.querySelectorAll('.sr-radio-option[data-val]').forEach(function (el) {
        el.classList.toggle('active', el.getAttribute('data-val') === method);
    });
    document.getElementById('accountGroup').style.display = 'block';
    if (sel) sel.required = true;
    document.getElementById('balanceNote').style.display = 'none';
    updateDeductPreview();
}

function setRefundMethod(method) {
    document.getElementById('refundMethodInput').value = method;
    document.querySelectorAll('.sr-radio-option[data-val]').forEach(function (el) {
        el.classList.toggle('active', el.getAttribute('data-val') === method);
    });
    var sel = document.getElementById('accountSelect');
    var posted = document.getElementById('postedAccountId');
    if (method === 'customer_balance') {
        document.querySelectorAll('.sr-acc-quick-btn').forEach(function (b) { b.classList.remove('active'); });
        if (sel) {
            sel.setAttribute('data-ame-user', '1');
            sel.setAttribute('data-ame-locked', '');
            sel.value = '';
            sel.required = false;
        }
        if (posted) posted.value = '';
        document.getElementById('balanceNote').style.display = 'block';
    } else {
        if (sel) sel.required = true;
        document.getElementById('balanceNote').style.display = 'none';
        if (sel && !sel.value) {
            var matchBtn = document.querySelector('.sr-acc-quick-btn[data-method="' + method + '"]') || document.querySelector('.sr-acc-quick-btn');
            if (matchBtn) {
                var aid = matchBtn.getAttribute('data-acc-id');
                sel.setAttribute('data-ame-user', '1');
                sel.setAttribute('data-ame-locked', String(aid));
                sel.value = String(aid);
                if (posted) posted.value = String(aid);
                document.querySelectorAll('.sr-acc-quick-btn').forEach(function (b) {
                    b.classList.toggle('active', b === matchBtn);
                });
            }
        }
    }
    document.getElementById('accountGroup').style.display = 'block';
    updateDeductPreview();
}

document.getElementById('accountSelect').addEventListener('change', function () {
    var sel = this;
    var val = String(sel.value || '').trim();
    var posted = document.getElementById('postedAccountId');
    if (posted) posted.value = val;
    if (val !== '') {
        var btn = document.querySelector('.sr-acc-quick-btn[data-acc-id="' + val + '"]');
        var method = btn ? btn.getAttribute('data-method') : inferMethodFromLabel(sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '');
        document.getElementById('refundMethodInput').value = method;
        document.querySelectorAll('.sr-acc-quick-btn').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-acc-id') === val);
        });
        document.querySelectorAll('.sr-radio-option[data-val]').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-val') === method);
        });
        sel.required = true;
        document.getElementById('balanceNote').style.display = 'none';
    }
    updateDeductPreview();
});

function updateCharCounter() {
    document.getElementById('charCount').textContent = document.getElementById('notesInput').value.length;
}

document.getElementById('returnForm').addEventListener('submit', function (e) {
    var itemsVal = document.getElementById('itemsJsonInput').value;
    if (!itemsVal || itemsVal === '[]') {
        e.preventDefault();
        alert('⚠️ অনুগ্রহ করে অন্তত একটি পণ্যের ফেরত-পরিমাণ দিন।');
        return;
    }
    var sel = document.getElementById('accountSelect');
    var posted = document.getElementById('postedAccountId');
    var cid = document.getElementById('customerIdInput');
    var methodInput = document.getElementById('refundMethodInput');
    var chosenAcc = (sel && sel.value) ? sel.value : (posted && posted.value ? posted.value : '');
    if (registeredInvoice) {
        methodInput.value = 'customer_balance';
        if (sel) sel.value = '';
        if (posted) posted.value = '';
        chosenAcc = '';
    } else if (chosenAcc && methodInput.value === 'customer_balance') {
        var btn = document.querySelector('.sr-acc-quick-btn[data-acc-id="' + chosenAcc + '"]');
        methodInput.value = btn ? btn.getAttribute('data-method') : 'cash_refund';
    }
    if ((!cid || !cid.value) && !chosenAcc) {
        e.preventDefault();
        alert('⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।');
        if (sel) sel.focus();
        return;
    }
    if (methodInput.value !== 'customer_balance' && !chosenAcc) {
        e.preventDefault();
        alert('⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।');
        if (sel) sel.focus();
        return;
    }
    var name = document.getElementById('customerNameInput');
    if (name && (!cid || !cid.value) && name.value.trim() === '') {
        name.value = 'রিটেল কাস্টমার';
    }
    if (!cid || !cid.value) {
        var summary = document.getElementById('summaryCustomer');
        if (summary && (summary.textContent === '—' || summary.textContent.trim() === '')) {
            summary.textContent = 'রিটেল কাস্টমার';
        }
    }
    if (!confirm('আপনি কি এই বিক্রয় ফেরতটি সংরক্ষণ করতে চান?')) {
        e.preventDefault();
    }
});
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
