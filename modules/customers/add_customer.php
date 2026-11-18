<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

$suggested_code = generate_customer_code($db);
$groups = get_customer_groups($db);
$areas = get_customer_areas($db);
$preset_type = trim($_GET['type'] ?? '');
$is_supplier_mode = ($preset_type === 'supplier');

$page_heading = $is_supplier_mode ? '🏭 নতুন সাপ্লায়ার এন্ট্রি' : '👤 নতুন ইউনিভার্সাল কাস্টমার এন্ট্রি';
$submit_label = $is_supplier_mode ? '💾 সাপ্লায়ার প্রোফাইল তৈরি করুন' : '💾 কাস্টমার প্রোফাইল তৈরি করুন';

// খোলা ক্যাশ, ব্যাংক, মোবাইল — status শুধু active না হলেও (yes, সক্রিয়, খালি) তালিকায় থাকে।
$accounts_list = ame_active_accounts($db);

ob_start();
?>
<div class="section-title ac-orange">আর্থিক প্রারম্ভিক তথ্য (ঐচ্ছিক)</div>
<div class="ledger-hint">এখানে যা লিখবেন তা স্বয়ংক্রিয়ভাবে কাস্টমারের লেজার স্টেটমেন্টে যোগ হয়ে যাবে।</div>
<div class="ac-row">
    <div class="form-group ac-rose">
        <label>সাবেক বকেয়া (৳)</label>
        <input type="number" step="0.01" name="opening_due" value="0" min="0">
        <small>আগে থেকে এই কাস্টমারের কাছে যা পাওনা আছে</small>
    </div>
    <div class="form-group ac-teal">
        <label>অগ্রিম জমা (৳)</label>
        <input type="number" step="0.01" name="advance_deposit" id="advanceDepositInput" value="0" min="0" oninput="toggleAdvanceAccount()">
        <small>কাস্টমার আগে থেকে যা জমা রেখেছে</small>
    </div>
</div>
<div class="form-group ac-violet" id="advanceAccountWrap" style="display:none;">
    <label>অগ্রিম জমা কোন অ্যাকাউন্টে জমা হয়েছে? <span class="required-star">*</span></label>
    <select class="ame-account-select" name="advance_account_id" id="advanceAccountSelect" data-ame-account-select="1" data-ame-direction="receive" data-ame-sheet="desk">
        <option value="">অ্যাকাউন্ট নির্বাচন করুন</option>
        <?php foreach ($accounts_list as $acc):
            $accType = trim((string)($acc['account_type'] ?? ''));
            $accLabel = trim((string)($acc['account_name'] ?? ''));
            if ($accType !== '') {
                $accLabel .= ' · ' . $accType;
            }
            $accLabel .= ' (ব্যালেন্স: ' . ame_balance_label((float)($acc['balance'] ?? 0)) . ')';
        ?>
            <option value="<?php echo (int)$acc['id']; ?>"><?php echo htmlspecialchars($accLabel, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!$accounts_list): ?>
    <small>কোনো খোলা অ্যাকাউন্ট নেই। আগে ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট খুলুন।</small>
    <?php else: ?>
    <small>ক্যাশ, ব্যাংক ও মোবাইল — খোলা অ্যাকাউন্ট সব এখানে। এই টাকা সেই অ্যাকাউন্টের ব্যালেন্সে যোগ হবে।</small>
    <?php endif; ?>
</div>
<script>
function toggleAdvanceAccount() {
    var amount = parseFloat(document.getElementById('advanceDepositInput').value) || 0;
    var wrap = document.getElementById('advanceAccountWrap');
    var select = document.getElementById('advanceAccountSelect');
    if (amount > 0) {
        wrap.style.display = '';
        select.required = true;
    } else {
        wrap.style.display = 'none';
        select.required = false;
        select.value = '';
    }
}
</script>
<?php
$financial_section_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_supplier_mode ? 'Add New Supplier' : 'Add New Customer'; ?> - Unified System</title>
    <link rel="stylesheet" href="/assets/css/add-customer.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">
    <link rel="stylesheet" href="/assets/css/customer-sign.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>">

</head>
<body class="ame-ac-page">

<div class="cam-overlay" id="camOverlay">
    <div class="cam-status" id="camStatus">ক্যামেরা খুলছে...</div>
    <video id="camVideo" autoplay playsinline muted webkit-playsinline></video>
    <div class="cam-actions">
        <button type="button" class="cam-shot" id="camShotBtn" onclick="captureCustomerPhoto()">📸 ছবি তুলুন</button>
        <button type="button" class="cam-shot" id="camRetryBtn" style="display:none;" onclick="startCustomerCameraStream()">ক্যামেরা চালু করুন</button>
        <button type="button" class="cam-skip" onclick="fallbackCameraFile()">গ্যালারি / ফাইল থেকে নিন</button>
        <button type="button" class="cam-skip" onclick="closeCustomerCamera()">এখন না — পরে তুলব</button>
    </div>
</div>

<div class="ame-ac-well">
<div class="form-card">
    <nav class="ac-jump" aria-label="পেজ যাওয়া">
      <a class="ac-jump-box ac-jump-bag" href="/modules/sales/pos.php">ব্যাগে যান</a>
      <a class="ac-jump-box ac-jump-home" href="/index.php">হোমে যান</a>
    </nav>
    <h2><?php echo $page_heading; ?></h2>

    <?php if ($is_supplier_mode): ?>
    <div class="preset-banner">🏭 সাপ্লায়ার হিসেবে যোগ করা হচ্ছে — "সাপ্লায়ার" রোল আগে থেকেই টিক দেওয়া আছে, নিচে সাবেক বকেয়ার তথ্য সবার উপরে রাখা হয়েছে। প্রয়োজনে পরিবর্তন করুন।</div>
    <?php endif; ?>

    <form id="customerForm" action="actions/process_customer.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="ac-row">
        <div class="form-group ac-navy">
            <label>আইডি / কাস্টমার কোড <span class="required-star">*</span></label>
            <input type="text" name="customer_code" id="customerCode" required value="<?php echo htmlspecialchars($suggested_code); ?>">
            <div class="error-msg" id="codeError">⚠️ এই আইডি ইতিমধ্যে ব্যবহৃত হয়েছে</div>
        </div>

        <div class="form-group ac-cyan">
            <label><?php echo $is_supplier_mode ? 'সাপ্লায়ারের নাম' : 'গ্রাহকের নাম'; ?> <span class="required-star">*</span></label>
            <input type="text" name="name" required placeholder="উদাহরণ: মোঃ মামুন">
        </div>
        </div>

        <?php if ($is_supplier_mode) { echo $financial_section_html; } ?>

        <div class="section-title ac-green">যোগাযোগ ও পরিচয়</div>
        <div class="ac-row">
            <div class="form-group ac-green">
                <label>মোবাইল নম্বর (ঐচ্ছিক)</label>
                <input type="text" name="phone" id="customerPhone" placeholder="01700000000">
                <div class="error-msg" id="phoneError">⚠️ এই নম্বরে ইতিমধ্যে একজন কাস্টমার আছে</div>
            </div>
            <?php if (!$is_supplier_mode): ?>
            <div class="form-group ac-violet" id="fieldNidWrap">
                <label>জাতীয় পরিচয়পত্র (NID) / পাসপোর্ট</label>
                <input type="text" name="nid" id="customerNid" placeholder="1992269123456789">
                <div class="error-msg" id="nidError">⚠️ এই NID ইতিমধ্যে অন্য একজন কাস্টমারের নামে আছে</div>
            </div>
            <?php else: ?>
            <input type="hidden" name="nid" value="">
            <?php endif; ?>
        </div>

        <div class="ac-row">
        <div class="form-group ac-orange">
            <label>পিতার নাম</label>
            <input type="text" name="father_name">
        </div>

        <div class="form-group ac-rose">
            <label>ঠিকানা</label>
            <textarea name="address" rows="2" placeholder="পূর্ণাঙ্গ ঠিকানা লিখুন"></textarea>
        </div>
        </div>

        <div class="section-title ac-teal">গ্রুপ ও এরিয়া</div>
        <div class="ac-row">
            <?php if (!$is_supplier_mode): ?>
            <div class="form-group ac-teal" id="fieldGroupWrap">
                <label>গ্রুপ</label>
                <select name="group_id">
                    <option value="">গ্রুপ নির্বাচন করুন</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="group_id" value="">
            <?php endif; ?>
            <div class="form-group ac-navy">
                <label>এরিয়া</label>
                <select name="area_id">
                    <option value="">এরিয়া নির্বাচন করুন</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (!$is_supplier_mode): ?>
        <div id="sectionGuarantor">
        <div class="section-title ac-cyan">গ্যারান্টার তথ্য (ঐচ্ছিক)</div>
        <div class="ac-row">
            <div class="form-group ac-cyan">
                <label>গ্যারান্টারের নাম</label>
                <input type="text" name="guarantor_name">
            </div>
            <div class="form-group ac-green">
                <label>গ্যারান্টারের মোবাইল</label>
                <input type="text" name="guarantor_phone">
            </div>
        </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="guarantor_name" value="">
        <input type="hidden" name="guarantor_phone" value="">
        <?php endif; ?>

        <?php if (!$is_supplier_mode) { echo $financial_section_html; } ?>

        <?php if (!$is_supplier_mode): ?>
        <div class="section-title ac-navy">রোল ও ক্রেডিট</div>
        <div class="form-group ac-navy">
            <label>গ্রাহকের একটিভ রোলসমূহ নির্বাচন করুন:</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="is_customer" id="isCustomer" value="1" checked onchange="updateRoleFieldVisibility()"> 🛒 সাধারণ ক্রেতা</label>
                <label><input type="checkbox" name="is_supplier" id="isSupplier" value="1" onchange="updateRoleFieldVisibility()"> 🏭 সাপ্লায়ার</label>
                <label><input type="checkbox" name="is_installment" value="1" onchange="updateRoleFieldVisibility()"> 🏦 কিস্তি গ্রহীতা</label>
                <label><input type="checkbox" name="is_rental" value="1" onchange="updateRoleFieldVisibility()"> 🚜 ভাড়া গ্রহীতা</label>
            </div>
            <div id="roleInfoBanner"></div>
        </div>
        <?php else: ?>
        <input type="hidden" name="is_customer" value="0">
        <input type="hidden" name="is_supplier" value="1">
        <input type="hidden" name="is_installment" value="0">
        <input type="hidden" name="is_rental" value="0">
        <div class="section-title ac-navy">রোল</div>
        <div class="ac-role-note">
            🏭 রোল: শুধু <strong>সাপ্লায়ার</strong> হিসেবে সংরক্ষিত হবে। এই ব্যক্তি ভবিষ্যতে কাস্টমারও হলে "✏️ এডিট" পেজ থেকে অতিরিক্ত রোল যোগ করা যাবে।
        </div>
        <?php endif; ?>

        <?php if (!$is_supplier_mode): ?>
        <div class="form-group ac-teal" id="fieldCreditLimit">
            <label>প্রাথমিক ক্রেডিট লিমিট (৳)</label>
            <input type="number" step="0.01" name="credit_limit" value="0">
        </div>
        <?php else: ?>
        <input type="hidden" name="credit_limit" value="0">
        <?php endif; ?>

        <div class="section-title ac-green">সংযুক্তি</div>
        <div class="ac-row">
            <div class="form-group ac-green">
                <label>প্রোফাইল ছবি</label>
                <input type="hidden" name="photo_jpeg" id="photoJpeg">
                <input type="file" name="photo" id="customerPhoto" accept="image/*">
                <div class="photo-hint">প্রয়োজনে নিচের বোতামে ক্যামেরা খুলুন, অথবা উপরে ফাইল বেছে নিন। ছবি সফটওয়্যারে ছোট JPG করে সেভ হয়।</div>
                <button type="button" class="ac-cam-open" onclick="openCustomerCamera()">📷 ক্যামেরা খুলুন</button>
                <div class="photo-ready" id="photoReady" style="display:none;margin-top:8px;">
                    <img id="photoPreview" alt="">
                    <div>
                        <strong>ছবি রেডি — সেভের সময় জমা হবে</strong><br>
                        <button type="button" class="ac-cam-open" onclick="openCustomerCamera()">আবার ক্যামেরা খুলুন</button>
                    </div>
                </div>
            </div>
            <div class="form-group ac-violet">
                <label>পিডিএফ ফাইল (চুক্তি/ডকুমেন্ট)</label>
                <input type="file" name="pdf_file" accept="application/pdf">
            </div>
        </div>

        <div class="section-title ac-rose">স্থায়ী কাস্টম রিকোয়ারমেন্ট</div>
        <div class="form-group ac-rose">
            <label>কাস্টমারের বিশেষ শর্ত/প্রয়োজনীয়তা (স্থায়ী নোট)</label>
            <textarea name="custom_requirement" rows="2" placeholder="যেমন: শুধু নগদ পেমেন্ট নেবে, বিশেষ ছাড় প্রযোজ্য ইত্যাদি"></textarea>
        </div>

        <div class="form-group ac-cyan">
            <label>অতিরিক্ত বার্তা / নোট</label>
            <textarea name="additional_notes" rows="2"></textarea>
        </div>

        <?php
        $sign_existing = '';
        $sign_when = '';
        $sign_mode = 'add';
        include __DIR__ . '/signature_pad.php';
        ?>

        <button type="submit" id="submitBtn" name="action" value="create" class="btn-submit"><?php echo $submit_label; ?></button>
    </form>
</div>
</div>

<script>
let codeTaken = false;
let phoneTaken = false;
let nidTaken = false;
let identityWait = 0;

function updateSubmitState() {
    document.getElementById('submitBtn').disabled = codeTaken || phoneTaken || nidTaken || identityWait > 0;
}

function markDuplicate(input, errBox, taken) {
    if (!input || !errBox) return;
    if (taken) {
        input.classList.add('input-error');
        errBox.style.display = 'block';
    } else {
        input.classList.remove('input-error');
        errBox.style.display = 'none';
    }
}

// ⭐ রোল চেকবক্স অনুযায়ী কাস্টমার-নির্দিষ্ট ফিল্ড (NID/গ্রুপ/গ্যারান্টার/ক্রেডিট লিমিট) দেখানো/লুকানো
function updateRoleFieldVisibility() {
    const isCustomerEl = document.getElementById('isCustomer');
    const isSupplierEl = document.getElementById('isSupplier');
    if (!isCustomerEl) { return; } // সাপ্লায়ার-প্রিসেট মোডে এই চেকবক্সগুলো থাকে না

    const custChecked = isCustomerEl.checked;
    const supChecked = isSupplierEl ? isSupplierEl.checked : false;

    ['fieldNidWrap', 'fieldGroupWrap', 'sectionGuarantor', 'fieldCreditLimit'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) { el.style.display = custChecked ? '' : 'none'; }
    });

    const banner = document.getElementById('roleInfoBanner');
    if (banner) {
        if (custChecked && supChecked) {
            banner.innerText = '👤🏭 কাস্টমার ও সাপ্লায়ার — দুই ধরনের তথ্যই দেখানো হচ্ছে।';
        } else if (custChecked) {
            banner.innerText = '👤 কাস্টমারের বিস্তারিত তথ্য (NID, গ্রুপ, গ্যারান্টার, ক্রেডিট লিমিট) দেখানো হচ্ছে।';
        } else if (supChecked) {
            banner.innerText = '🏭 সাপ্লায়ার নির্বাচিত — কাস্টমার-নির্দিষ্ট বাড়তি তথ্য লুকানো হয়েছে।';
        } else {
            banner.innerText = '';
        }
    }
    if (typeof window.ameAcFit === 'function') { window.ameAcFit(); }
}
window.addEventListener('DOMContentLoaded', updateRoleFieldVisibility);

var camStream = null;
function storeConvertedPhoto(blob, dataUrl) {
    document.getElementById('photoJpeg').value = dataUrl;
    document.getElementById('photoPreview').src = dataUrl;
    document.getElementById('photoReady').style.display = 'flex';
    var input = document.getElementById('customerPhoto');
    try {
        var file = new File([blob], 'customer.jpg', { type: 'image/jpeg' });
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
    } catch (e) {}
}
function showCamNeedTap(msg) {
    document.getElementById('camStatus').textContent = msg;
    document.getElementById('camRetryBtn').style.display = '';
    document.getElementById('camShotBtn').style.display = 'none';
}
function startCustomerCameraStream() {
    var video = document.getElementById('camVideo');
    var status = document.getElementById('camStatus');
    document.getElementById('camRetryBtn').style.display = 'none';
    document.getElementById('camShotBtn').style.display = '';
    status.textContent = 'ক্যামেরা খুলছে...';
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showCamNeedTap('এই ফোনে লাইভ ক্যামেরা চলে না — গ্যালারি থেকে নিন');
        return;
    }
    var tries = [
        { video: { facingMode: { ideal: 'user' }, width: { ideal: 720 }, height: { ideal: 720 } }, audio: false },
        { video: { facingMode: 'user' }, audio: false },
        { video: true, audio: false }
    ];
    function nextTry(i) {
        if (i >= tries.length) {
            showCamNeedTap('ক্যামেরার অনুমতি দিন, অথবা গ্যালারি থেকে নিন');
            return;
        }
        navigator.mediaDevices.getUserMedia(tries[i])
            .then(function (stream) {
                camStream = stream;
                video.srcObject = stream;
                var playP = video.play();
                if (playP && playP.catch) { playP.catch(function () {}); }
                status.textContent = 'ক্রেতার মুখ ফ্রেমে এনে ছবি তুলুন';
            })
            .catch(function () { nextTry(i + 1); });
    }
    nextTry(0);
}
function openCustomerCamera() {
    var overlay = document.getElementById('camOverlay');
    overlay.classList.add('open');
    startCustomerCameraStream();
}
function fallbackCameraFile() {
    closeCustomerCamera();
    var input = document.getElementById('customerPhoto');
    if (input) { input.click(); }
}
function closeCustomerCamera() {
    var overlay = document.getElementById('camOverlay');
    overlay.classList.remove('open');
    if (camStream) {
        camStream.getTracks().forEach(function (t) { t.stop(); });
        camStream = null;
    }
}
function captureCustomerPhoto() {
    var video = document.getElementById('camVideo');
    if (!video.videoWidth) {
        document.getElementById('camStatus').textContent = 'ক্যামেরা এখনো তৈরি হয়নি, একটু অপেক্ষা করুন';
        return;
    }
    var max = 900;
    var scale = Math.min(1, max / Math.max(video.videoWidth, video.videoHeight));
    var canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    canvas.toBlob(function (blob) {
        if (!blob) { return; }
        var reader = new FileReader();
        reader.onload = function () {
            storeConvertedPhoto(blob, reader.result);
            closeCustomerCamera();
        };
        reader.readAsDataURL(blob);
    }, 'image/jpeg', 0.82);
}
document.getElementById('customerPhoto').addEventListener('change', function () {
    if (!this.files || !this.files[0]) { return; }
    var file = this.files[0];
    var img = new Image();
    var url = URL.createObjectURL(file);
    img.onload = function () {
        var max = 900;
        var scale = Math.min(1, max / Math.max(img.width, img.height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(img.width * scale));
        canvas.height = Math.max(1, Math.round(img.height * scale));
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) { return; }
            var reader = new FileReader();
            reader.onload = function () {
                storeConvertedPhoto(blob, reader.result);
            };
            reader.readAsDataURL(blob);
        }, 'image/jpeg', 0.82);
        URL.revokeObjectURL(url);
    };
    img.src = url;
});

function applyIdentity(data) {
    const codeInput = document.getElementById('customerCode');
    const phoneInput = document.getElementById('customerPhone');
    const nidInput = document.getElementById('customerNid');
    codeTaken = !!(data && data.code_exists);
    phoneTaken = !!(data && data.phone_exists);
    nidTaken = !!(data && data.nid_exists);
    markDuplicate(codeInput, document.getElementById('codeError'), codeTaken);
    markDuplicate(phoneInput, document.getElementById('phoneError'), phoneTaken);
    if (nidInput) markDuplicate(nidInput, document.getElementById('nidError'), nidTaken);
    updateSubmitState();
}

function checkIdentityNow() {
    const code = (document.getElementById('customerCode').value || '').trim();
    const phone = (document.getElementById('customerPhone').value || '').trim();
    const nidEl = document.getElementById('customerNid');
    const nid = nidEl ? (nidEl.value || '').trim() : '';
    identityWait++;
    updateSubmitState();
    return fetch('actions/check_duplicate.php?code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone) + '&nid=' + encodeURIComponent(nid))
        .then(r => r.json())
        .then(data => {
            applyIdentity(data);
            return data;
        })
        .finally(() => {
            identityWait = Math.max(0, identityWait - 1);
            updateSubmitState();
        });
}

['customerCode', 'customerPhone', 'customerNid'].forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('blur', function () { checkIdentityNow(); });
});

document.getElementById('customerForm').addEventListener('submit', function (ev) {
    const btn = document.getElementById('submitBtn');
    if (btn && btn.dataset.ready === '1') return;
    if (codeTaken || phoneTaken || nidTaken) {
        ev.preventDefault();
        return;
    }
    ev.preventDefault();
    checkIdentityNow().then(function (data) {
        if (data && (data.code_exists || data.phone_exists || data.nid_exists)) return;
        if (btn) btn.dataset.ready = '1';
        var form = document.getElementById('customerForm');
        if (typeof form.requestSubmit === 'function') form.requestSubmit(btn);
        else form.submit();
    }).catch(function () {
        if (btn) btn.dataset.ready = '1';
        var form = document.getElementById('customerForm');
        if (typeof form.requestSubmit === 'function') form.requestSubmit(btn);
        else form.submit();
    });
});
</script>
<script src="/assets/js/customer-sign-pad.js?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>"></script>
<script src="/assets/js/add-customer-fit.js?v=<?php echo htmlspecialchars(AME_ASSET_VERSION); ?>"></script>

</body>
</html>