<?php
require_once '../../includes/config.php';
admin_only();
require_env_flag('AME_ENABLE_MIGRATION_TOOLS');
require_once '../../includes/ma_engineering_migrate.php';

$legacy_ready = mae_legacy_exists($db);
$legacy_preview = $legacy_ready ? mae_preview($db, false) : null;
$server_dumps = mae_list_server_sql_dumps();

include '../../partials/header.php';
?>
<style>
    .msi-wrap { padding: 15px; }
    .msi-card { background:#fff; border-radius:14px; max-width:540px; margin:0 auto 16px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
    .msi-topbar { display:flex; height:6px; }
    .msi-topbar span { flex:1; }
    .msi-c1{background:var(--brand-navy);} .msi-c2{background:var(--brand-accent);} .msi-c3{background:var(--brand-gold);}
    .msi-header { text-align:center; padding:20px 15px 15px; }
    .msi-header h3 { margin:0; font-size:19px; color:var(--brand-navy); }
    .msi-header small { color:#666; font-size:12px; display:block; margin-top:6px; line-height:1.5; }
    .msi-body { padding:0 20px 20px; }
    .msi-info { background:#f0f7ff; border:1px solid #b8d4f0; color:#1a3a5c; border-radius:10px; padding:14px; font-size:13px; margin-bottom:16px; line-height:1.6; }
    .msi-warning { background: var(--brand-danger-soft); border: 1px solid var(--brand-danger); color: #7a1c1c; border-radius: 10px; padding: 14px; font-size: 13px; margin-bottom: 18px; line-height: 1.6; }
    .msi-ok { background:#e8f5e9; border:1px solid #a5d6a7; color:#1b5e20; border-radius:10px; padding:14px; font-size:13px; margin-bottom:16px; line-height:1.6; }
    .msi-group { margin-bottom: 14px; }
    .msi-group label { display:block; font-weight:700; font-size:13px; color:#000; margin-bottom:8px; }
    .msi-check { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#333; margin-bottom:10px; }
    .msi-check input { width:18px; height:18px; margin-top:2px; flex-shrink:0; }
    .msi-submit { width:100%; padding:14px; border:none; border-radius:8px; background: var(--brand-gradient); color:#fff; font-weight:700; font-size:15px; cursor:pointer; }
    .msi-submit.alt { background:linear-gradient(135deg,#0B1E4D,#1D9BF0); margin-top:8px; }
    .msi-submit:disabled { opacity:0.5; cursor:not-allowed; }
    .msi-back { display:block; text-align:center; margin-top:14px; color:#666; text-decoration:none; font-size:13px; }
    .msi-table { width:100%; font-size:12px; border-collapse:collapse; margin-top:10px; }
    .msi-table th, .msi-table td { padding:6px 8px; border-bottom:1px solid #eee; text-align:left; }
    .msi-table th { color:#555; font-weight:600; }
    .msi-divider { border-top:1px dashed #ddd; margin:18px 0; }
    .msi-file-hidden { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; pointer-events:none; }
    .msi-file-btn { width:100%; padding:16px 14px; border:2px dashed #1D9BF0; border-radius:10px; background:#f0f7ff; color:#0B1E4D; font-weight:700; font-size:15px; cursor:pointer; }
    .msi-file-name { margin-top:10px; font-size:13px; color:#333; word-break:break-all; line-height:1.5; }
    .msi-mobile-tip { background:#fff8e6; border:1px solid #f0c36d; color:#6b4e00; border-radius:10px; padding:12px; font-size:12px; margin-bottom:14px; line-height:1.6; }
    .msi-progress-wrap { display:none; margin:16px 0; padding:14px; background:#f8fbff; border:1px solid #b8d4f0; border-radius:10px; }
    .msi-progress-bar { height:12px; background:#e3edf7; border-radius:999px; overflow:hidden; }
    .msi-progress-fill { height:100%; width:0; background:linear-gradient(90deg,#0B1E4D,#1D9BF0); transition:width .3s ease; }
    .msi-progress-text { margin-top:10px; font-size:13px; color:#1a3a5c; line-height:1.5; font-weight:600; }
    .msi-resume { display:none; background:#fff8e6; border:1px solid #f0c36d; color:#6b4e00; border-radius:10px; padding:14px; font-size:13px; margin-bottom:16px; line-height:1.6; }
    .msi-resume-btns { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
    .msi-resume-btns button { flex:1; min-width:120px; padding:10px 12px; border:none; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; }
    .msi-resume-go { background:linear-gradient(135deg,#0B1E4D,#1D9BF0); color:#fff; }
    .msi-resume-cancel { background:#fff; color:#7a1c1c; border:1px solid #e57373; }
</style>

<div class="msi-wrap">
    <p style="max-width:540px;margin:0 auto 12px;text-align:center;">
        <a href="/import.php" style="display:block;padding:16px;background:#2e7d32;color:#fff;border-radius:10px;font-weight:800;text-decoration:none;font-size:18px;">ডাটাবেজ ইনপুট — এখানে যান</a>
    </p>
    <div class="msi-card">
        <div class="msi-topbar"><span class="msi-c1"></span><span class="msi-c2"></span><span class="msi-c3"></span></div>
        <div class="msi-header">
            <h3>📦 পুরনো ডাটা ইমপোর্ট</h3>
            <small>মা ইঞ্জিনিয়ারিং POS (maengineering) বা মামু স্টোর (business)<br>phpMyAdmin SQL → Aponshop<br>ইমপোর্ট টুল ভার্সন: <?php echo htmlspecialchars(defined('AME_APP_VERSION') ? AME_APP_VERSION : '?', ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <?php if (isset($_GET['error'])): ?>
        <div style="margin:0 20px 15px; background:var(--brand-danger-soft); color:var(--brand-danger); padding:12px 15px; border-radius:8px; font-size:13px; font-weight:600;">
            ❌ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['done']) && !empty($_SESSION['mamun_import_stats'])): ?>
        <div style="margin:0 20px 15px; background:var(--brand-success-soft); color:var(--brand-success); padding:14px 15px; border-radius:8px; font-size:13px;">
            <b>✅ ইমপোর্ট সম্পন্ন!</b>
            <?php $st = $_SESSION['mamun_import_stats']; unset($_SESSION['mamun_import_stats']); ?>
            <table class="msi-table">
                <tr><th>ধরন</th><th>সোর্স</th><th>ইমপোর্ট</th><th>স্কিপ</th></tr>
                <?php
                $is_mae = ($st['source'] ?? '') === 'ma_engineering';
                $rows = $is_mae ? [
                    ['ক্যাটাগরি', 'product_type', 'categories'],
                    ['পণ্য', 'product', 'products'],
                    ['কাস্টমার', 'customer', 'customers'],
                    ['সাপ্লায়ার', 'company', 'suppliers'],
                    ['ইউজার', 'user_info', 'users'],
                    ['ক্রয় লাইন', 'purchase_item', 'purchases'],
                    ['বিক্রয়', 'sale', 'sales'],
                    ['বিক্রয় লাইন', 'sale_item', 'sale_items'],
                ] : [
                    ['ক্যাটাগরি', 'categories', 'categories'],
                    ['পণ্য', 'products', 'products'],
                    ['কাস্টমার', 'customers', 'customers'],
                    ['সাপ্লায়ার', 'suppliers', 'suppliers'],
                    ['ইউজার', 'users', 'users'],
                    ['ক্রয়', 'purchase_items', 'purchases'],
                    ['বিক্রয়', 'sales', 'sales'],
                    ['বিক্রয় লাইন', 'sale_items', 'sale_items'],
                ];
                foreach ($rows as $r):
                    $staging = (int)($st['staging'][$r[1]] ?? 0);
                    $imp = (int)($st[$r[2]]['imported'] ?? 0);
                    $skip = (int)($st[$r[2]]['skipped'] ?? 0);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r[0]); ?></td>
                    <td><?php echo $staging; ?></td>
                    <td><?php echo $imp; ?></td>
                    <td><?php echo $skip; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php if (!empty($st['settings']['shop_name'])): ?>
            <p style="margin:10px 0 0;">দোকানের নাম/সেটিংস আপডেট হয়েছে।</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="msi-body">
            <?php if ($legacy_preview): ?>
            <div class="msi-ok">
                <b>✅ পুরনো POS টেবিল পাওয়া গেছে!</b><br>
                কাস্টমার: <b><?php echo (int)$legacy_preview['customers']; ?></b> |
                পণ্য: <b><?php echo (int)$legacy_preview['products']; ?></b> |
                বিক্রয়: <b><?php echo (int)$legacy_preview['sales']; ?></b> |
                সাপ্লায়ার: <b><?php echo (int)$legacy_preview['suppliers']; ?></b>
            </div>
            <?php endif; ?>

            <div class="msi-warning" style="margin-bottom:14px;">
                <b>⚠️ phpMyAdmin-এ পুরো SQL ইমপোর্ট করবেন না!</b><br>
                পুরো maengineering ফাইলে <code>accounts</code> টেবিল আছে — Aponshop-এর <code>accounts</code>-এর সাথে মিলে
                <b>#1050 Table already exists</b> এরর হবে।<br>
                নিচের <b>“ফাইল আপলোড”</b> ব্যবহার করুন — সিস্টেম শুধু প্রয়োজনীয় পুরনো টেবিল নেবে
                (<?php echo htmlspecialchars(mae_core_table_list_bn()); ?>).
            </div>

            <div class="msi-info">
                <b>মা ইঞ্জিনিয়ারিং (২৭MB) — সঠিক পদ্ধতি:</b><br>
                ১. নিচে .sql ফাইল সিলেক্ট করুন → <b>“ফাইল আপলোড করে ইমপোর্ট”</b> চাপুন<br>
                ২. ফোন থেকে ২৬MB আপলোডে প্রথম কয়েক মিনিট বার ০%-এ থাকতে পারে — <b>পেজ বন্ধ করবেন না</b><br>
                ৩. আটকে গেলে Hostinger File Manager দিয়ে ফাইলটি <code>private_data/dumps/</code>-এ তুলে এই পেজ রিফ্রেশ করুন<br>
                ৪. নেট কেটে গেলে অটো রিজিউম হবে
            </div>

            <div class="msi-resume" id="msiResumeBox">
                <b>⏸️ অসম্পূর্ণ ইমপোর্ট পাওয়া গেছে</b><br>
                <span id="msiResumeText">আগের ইমপোর্ট চালিয়ে যেতে পারবেন।</span>
                <div class="msi-resume-btns">
                    <button type="button" class="msi-resume-go" id="msiResumeBtn">▶️ চালিয়ে যান</button>
                    <button type="button" class="msi-resume-cancel" id="msiCancelBtn">✖️ বাতিল</button>
                </div>
            </div>

            <div class="msi-progress-wrap" id="msiProgressWrap">
                <div class="msi-progress-bar"><div class="msi-progress-fill" id="msiProgressFill"></div></div>
                <div class="msi-progress-text" id="msiProgressText">ইমপোর্ট শুরু হচ্ছে...</div>
                <div class="msi-progress-text" id="msiProgressClock" style="font-weight:600;color:#546e7a;">সময়: ০ সেকেন্ড</div>
            </div>

            <?php if ($legacy_ready): ?>
            <form action="process_import_mamun_store.php" method="POST" id="msiDbForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="use_existing_tables" value="1">
                <label class="msi-check"><input type="checkbox" name="import_settings" value="1" checked><span>দোকানের নাম/সেটিংস</span></label>
                <label class="msi-check"><input type="checkbox" name="import_master" value="1" checked><span>পণ্য, কাস্টমার, সাপ্লায়ার, ইউজার</span></label>
                <label class="msi-check"><input type="checkbox" name="import_transactions" value="1" checked><span>ক্রয় ও বিক্রয় (সময় লাগতে পারে)</span></label>
                <label class="msi-check"><input type="checkbox" name="skip_existing" value="1" checked><span>ডুপ্লিকেট স্কিপ</span></label>
                <label class="msi-check" style="margin-bottom:12px;"><input type="checkbox" id="msiDbConfirm" required><span>phpMyAdmin থেকে SQL ইমপোর্ট করা হয়েছে — মাইগ্রেট শুরু করতে চাই।</span></label>
                <button type="submit" class="msi-submit alt" id="msiDbBtn" disabled>🔄 টেবিল থেকে মাইগ্রেট করুন</button>
            </form>
            <div class="msi-divider"></div>
            <?php endif; ?>

            <div class="msi-warning">
                <b>ছোট .sql ফাইল (৫০MB পর্যন্ত) সরাসরি আপলোড:</b> Custom Export → Data সহ → SQL
            </div>

            <form action="process_import_mamun_store.php" method="POST" enctype="multipart/form-data" id="msiForm">
                <?php echo csrf_field(); ?>
                <div class="msi-group">
                    <label>সার্ভারে রাখা SQL (ফোন আপলোড এড়ান)</label>
                    <select name="server_sql" id="msiServerSql" style="width:100%;padding:12px;border:1px solid #9aa7b5;border-radius:8px;font-size:14px;">
                        <option value="">— ফোন থেকে নতুন ফাইল তুলব —</option>
                        <?php foreach ($server_dumps as $dump): ?>
                        <option value="<?php echo htmlspecialchars($dump['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($dump['name'] . ' (' . $dump['mb'] . ' MB)', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="msi-file-name">আটকে গেলে: File Manager-এ <code>private_data/dumps/</code> ফোল্ডার বানিয়ে <code>u7802670_maengineering.sql</code> তুলুন, পেজ রিফ্রেশ করুন, উপর থেকে ফাইল বেছে ইমপোর্ট চাপুন।</div>
                </div>
                <div class="msi-group">
                    <label>.sql ফাইল (maengineering বা business)</label>
                    <div class="msi-mobile-tip">
                        <b>মোবাইলে ফাইল সিলেক্ট না হলে:</b><br>
                        ১. নিচের নীল বাটন → <b>Download</b> → আপনার পুরোনো database-এর <b>.sql</b> ফাইল ট্যাপ করুন<br>
                        ২. ZIP ফাইল ধূসর দেখাবে — সেটা স্বাভাবিক (এখানে শুধু .sql লাগে)<br>
                        ৩. তবুও না হলে PC/Laptop ব্রাউজার ব্যবহার করুন
                    </div>
                    <input type="file" name="sql_file" id="msiSqlFile" class="msi-file-hidden">
                    <button type="button" class="msi-file-btn" id="msiPickBtn">📁 .sql ফাইল বেছে নিন</button>
                    <div id="msiFileName" class="msi-file-name">কোনো ফাইল সিলেক্ট হয়নি</div>
                </div>
                <label class="msi-check"><input type="checkbox" name="import_settings" value="1" checked><span>সেটিংস</span></label>
                <label class="msi-check"><input type="checkbox" name="import_master" value="1" checked><span>মাস্টার ডাটা</span></label>
                <label class="msi-check"><input type="checkbox" name="import_transactions" value="1" checked><span>লেনদেন</span></label>
                <label class="msi-check"><input type="checkbox" name="skip_existing" value="1" checked><span>ডুপ্লিকেট স্কিপ</span></label>
                <label class="msi-check" style="margin-bottom:18px;"><input type="checkbox" id="msiConfirm" required><span>নিশ্চিত — Aponshop-এ নতুন ডাটা যোগ হবে।</span></label>
                <button type="submit" class="msi-submit" id="msiBtn" disabled>📥 ফাইল আপলোড করে ইমপোর্ট</button>
            </form>
        </div>
        <a href="tools.php" class="msi-back">⬅️ টুলসে ফিরে যান</a>
    </div>
</div>
<script>
function bindConfirm(chkId, btnId) {
    var c = document.getElementById(chkId);
    var b = document.getElementById(btnId);
    if (!c || !b) return;
    c.addEventListener('change', function () { updateMsiSubmit(); });
}
function updateMsiSubmit() {
    var c = document.getElementById('msiConfirm');
    var b = document.getElementById('msiBtn');
    var f = document.getElementById('msiSqlFile');
    if (!c || !b || !f) return;
    var s = document.getElementById('msiServerSql');
    var hasServer = s && s.value;
    b.disabled = !(c.checked && ((f.files && f.files.length > 0) || hasServer));
}
bindConfirm('msiConfirm', 'msiBtn');
bindConfirm('msiDbConfirm', 'msiDbBtn');
var pickBtn = document.getElementById('msiPickBtn');
var fileInput = document.getElementById('msiSqlFile');
var fileNameEl = document.getElementById('msiFileName');
if (pickBtn && fileInput) {
    pickBtn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) {
            fileNameEl.textContent = 'কোনো ফাইল সিলেক্ট হয়নি';
            updateMsiSubmit();
            return;
        }
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'sql') {
            fileNameEl.textContent = '❌ শুধু .sql ফাইল দিন — ' + f.name;
            fileInput.value = '';
            updateMsiSubmit();
            return;
        }
        fileNameEl.textContent = '✅ ' + f.name + ' (' + (f.size / (1024 * 1024)).toFixed(1) + ' MB)';
        updateMsiSubmit();
    });
}
var serverSel = document.getElementById('msiServerSql');
if (serverSel) serverSel.addEventListener('change', updateMsiSubmit);
updateMsiSubmit();

var MSI_JOB_KEY = 'mae_migration_job_id';
var msiClockTimer = 0;
var msiClockStart = 0;
var msiRunning = false;
var msiCurrentJobId = '';
var msiLastProgress = { percent: 0, label: 'ইমপোর্ট' };

function msiCsrfToken() {
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
}

function msiSleep(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
}

function msiSaveJobId(id) {
    msiCurrentJobId = id || '';
    try {
        if (id) localStorage.setItem(MSI_JOB_KEY, id);
        else localStorage.removeItem(MSI_JOB_KEY);
    } catch (e) {}
}

function msiLoadJobId() {
    try { return localStorage.getItem(MSI_JOB_KEY) || ''; } catch (e) { return ''; }
}

function msiSetButtonsDisabled(disabled) {
    ['msiBtn', 'msiDbBtn', 'msiResumeBtn'].forEach(function (id) {
        var b = document.getElementById(id);
        if (b) b.disabled = disabled;
    });
}

function msiStartClock() {
    msiClockStart = Date.now();
    var el = document.getElementById('msiProgressClock');
    if (msiClockTimer) clearInterval(msiClockTimer);
    msiClockTimer = setInterval(function () {
        if (!el) return;
        var s = Math.floor((Date.now() - msiClockStart) / 1000);
        var m = Math.floor(s / 60);
        el.textContent = 'সময়: ' + (m ? (m + ' মিনিট ' + (s % 60) + ' সেকেন্ড') : (s + ' সেকেন্ড')) + ' — পেজ বন্ধ করবেন না';
    }, 1000);
}

function msiPostStart(form, onProgress) {
    return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'process_import_mamun_store.php');
        xhr.withCredentials = true;
        xhr.timeout = 20 * 60 * 1000;
        xhr.onload = function () {
            try { resolve(JSON.parse(xhr.responseText || '{}')); }
            catch (e) { reject(new Error('সার্ভারের উত্তর পড়া যায়নি। পেজ রিফ্রেশ করে “চালিয়ে যান” চাপুন।')); }
        };
        xhr.onerror = function () { reject(new Error('নেট কেটেছে। রিফ্রেশ করলে আবার চালু হবে।')); };
        xhr.ontimeout = function () { reject(new Error('আপলোড অনেকক্ষণ ধরে আটকে আছে। File Manager দিয়ে dumps ফোল্ডারে তুলুন।')); };
        if (xhr.upload) {
            xhr.upload.onprogress = function (ev) {
                if (!ev.lengthComputable) return;
                var pct = Math.max(1, Math.round((ev.loaded / ev.total) * 100));
                onProgress(pct, ev.loaded, ev.total);
            };
        }
        var fd = new FormData(form);
        fd.append('ajax', '1');
        fd.append('action', 'start');
        xhr.send(fd);
    });
}

function msiSetProgress(data) {
    var wrap = document.getElementById('msiProgressWrap');
    var fill = document.getElementById('msiProgressFill');
    var text = document.getElementById('msiProgressText');
    if (!wrap || !fill || !text) return;
    wrap.style.display = 'block';
    var p = data && data.progress ? data.progress : (data && data.job && data.job.progress ? data.job.progress : {});
    if (p.percent) msiLastProgress = p;
    fill.style.width = (p.percent || msiLastProgress.percent || 0) + '%';
    var msg = (data && data.message) ? data.message : '';
    var label = p.label || msiLastProgress.label || 'ইমপোর্ট';
    if (p.phase === 'sales' && p.sales_total > 0) {
        msg = label + ' — ' + Math.min(p.sales_offset, p.sales_total) + ' / ' + p.sales_total;
    } else if (!msg) {
        msg = label + ' চলছে...';
    }
    text.textContent = '⏳ ' + msg + ' (' + (p.percent || msiLastProgress.percent || 0) + '%)';
}

function msiShowResume(jobData) {
    var box = document.getElementById('msiResumeBox');
    var txt = document.getElementById('msiResumeText');
    if (!box || !jobData) return;
    var p = jobData.progress || {};
    var detail = (p.label || 'ইমপোর্ট') + ' — ' + (p.percent || 0) + '%';
    if (p.phase === 'sales' && p.sales_total > 0) {
        detail += ' (' + Math.min(p.sales_offset, p.sales_total) + '/' + p.sales_total + ' বিক্রয়)';
    }
    if (jobData.last_error) {
        detail += '\nশেষ সমস্যা: ' + jobData.last_error;
    }
    txt.textContent = detail;
    box.style.display = 'block';
}

function msiHideResume() {
    var box = document.getElementById('msiResumeBox');
    if (box) box.style.display = 'none';
}

async function msiPostJson(action, extra) {
    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', action);
    fd.append('csrf_token', msiCsrfToken());
    if (extra) {
        Object.keys(extra).forEach(function (k) {
            if (extra[k] !== undefined && extra[k] !== null) fd.append(k, extra[k]);
        });
    }
    var attempt = 0;
    while (true) {
        try {
            var res = await fetch('process_import_mamun_store.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            var json = await res.json();
            if (!json.ok && json.resumable && action === 'step') {
                attempt++;
                if (attempt <= 12) {
                    msiSetProgress({ message: 'সংযোগ বিচ্ছিন্ন — আবার চেষ্টা (' + attempt + ')...', progress: msiLastProgress });
                    await msiSleep(Math.min(30000, 2000 * Math.pow(1.4, attempt)));
                    continue;
                }
            }
            return json;
        } catch (err) {
            attempt++;
            if (attempt > 12) throw err;
            msiSetProgress({ message: 'নেট সমস্যা — আবার চেষ্টা হচ্ছে (' + attempt + ')...', progress: msiLastProgress });
            await msiSleep(Math.min(30000, 2000 * Math.pow(1.4, attempt)));
        }
    }
}

async function msiRunSteps(jobId) {
    msiSaveJobId(jobId);
    var done = false;
    while (!done) {
        var stepJson = await msiPostJson('step', { job_id: jobId });
        if (!stepJson.ok) {
            if (stepJson.resumable) {
                msiSetProgress({ message: 'অপেক্ষা করুন — আবার চেষ্টা হচ্ছে...', progress: msiLastProgress });
                await msiSleep(3000);
                continue;
            }
            throw new Error(stepJson.error || 'ধাপ ব্যর্থ');
        }
        msiSetProgress(stepJson);
        done = !!stepJson.done;
    }
    msiSaveJobId('');
    window.location.href = 'import_mamun_store.php?done=1';
}

async function msiResumeMigration(jobId) {
    if (msiRunning) return;
    msiRunning = true;
    msiHideResume();
    msiSetButtonsDisabled(true);
    msiSetProgress({ progress: { percent: msiLastProgress.percent || 1, label: 'রিজিউম' }, message: 'আগের ইমপোর্ট চালিয়ে যাচ্ছি...' });
    try {
        var resumeJson = await msiPostJson('resume', { job_id: jobId || msiLoadJobId() });
        if (!resumeJson.ok || !resumeJson.job) {
            throw new Error(resumeJson.error || 'রিজিউম করা যায়নি');
        }
        var jid = resumeJson.job.job_id || jobId;
        msiSetProgress(resumeJson.job);
        await msiRunSteps(jid);
    } catch (err) {
        msiRunning = false;
        msiSetButtonsDisabled(false);
        msiSetProgress({ message: 'বিরতি — নেট এলে অটো চালু হবে', progress: msiLastProgress });
        msiCheckOpenJob();
        alert('ইমপোর্ট বিরতি: ' + (err && err.message ? err.message : err) + '\n\nইন্টারনেট এলে অটো চালু হবে, অথবা “চালিয়ে যান” চাপুন।');
    }
}

async function msiRunChunked(form) {
    if (msiRunning) return;
    msiRunning = true;
    var btn = form.querySelector('button[type="submit"]');
    var oldText = btn ? btn.textContent : '';
    msiHideResume();
    msiSetButtonsDisabled(true);
    if (btn) btn.textContent = '⏳ ইমপোর্ট চলছে...';
    msiStartClock();
    msiSetProgress({ progress: { percent: 1, label: 'আপলোড' }, message: 'ফাইল যাচ্ছে — এই ধাপে কয়েক মিনিট লাগতে পারে' });
    try {
        var startJson = await msiPostStart(form, function (pct) {
            msiSetProgress({ progress: { percent: Math.min(20, Math.round(pct * 0.2)), label: 'আপলোড' }, message: 'ফাইল আপলোড ' + pct + '%' });
        });
        if (!startJson.ok) {
            throw new Error(startJson.error || 'শুরু করা যায়নি');
        }
        msiSetProgress(startJson);
        var jobId = startJson.job_id || '';
        if (!startJson.done) {
            await msiRunSteps(jobId);
        } else {
            msiSaveJobId('');
            window.location.href = 'import_mamun_store.php?done=1';
        }
    } catch (err) {
        msiRunning = false;
        msiSetButtonsDisabled(false);
        if (btn) btn.textContent = oldText;
        msiSetProgress({ message: 'বিরতি — নেট এলে অটো চালু হবে', progress: msiLastProgress });
        msiCheckOpenJob();
        alert('ইমপোর্ট বিরতি: ' + (err && err.message ? err.message : err) + '\n\nইন্টারনেট এলে অটো চালু হবে, অথবা “চালিয়ে যান” চাপুন।');
    }
}

async function msiCancelMigration() {
    var jobId = msiCurrentJobId || msiLoadJobId();
    await msiPostJson('cancel', { job_id: jobId });
    msiSaveJobId('');
    msiRunning = false;
    msiHideResume();
    msiSetButtonsDisabled(false);
    var wrap = document.getElementById('msiProgressWrap');
    if (wrap) wrap.style.display = 'none';
}

async function msiCheckOpenJob() {
    try {
        var status = await msiPostJson('status');
        if (status.has_job && status.job) {
            msiShowResume(status.job);
            if (status.job.job_id) msiSaveJobId(status.job.job_id);
            if (status.job.progress) msiLastProgress = status.job.progress;
            if (msiRunning) return;
            var saved = msiLoadJobId();
            if (saved && navigator.onLine !== false) {
                msiResumeMigration(saved);
            }
        }
    } catch (e) {}
}

document.getElementById('msiForm').addEventListener('submit', function (e) {
    e.preventDefault();
    msiRunChunked(this);
});
var dbForm = document.getElementById('msiDbForm');
if (dbForm) {
    dbForm.addEventListener('submit', function (e) {
        e.preventDefault();
        msiRunChunked(this);
    });
}
var resumeBtn = document.getElementById('msiResumeBtn');
if (resumeBtn) {
    resumeBtn.addEventListener('click', function () {
        msiResumeMigration(msiLoadJobId());
    });
}
var cancelBtn = document.getElementById('msiCancelBtn');
if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
        if (confirm('অসম্পূর্ণ ইমপোর্ট বাতিল করবেন?')) msiCancelMigration();
    });
}
window.addEventListener('online', function () {
    if (!msiRunning && msiLoadJobId()) {
        msiResumeMigration(msiLoadJobId());
    }
});
msiCheckOpenJob();
</script>
<?php include '../../partials/footer.php'; ?>
