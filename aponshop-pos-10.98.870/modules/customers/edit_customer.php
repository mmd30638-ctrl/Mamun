<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);
if (function_exists('ame_customer_status_boot')) {
    ame_customer_status_boot($db);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) {
    header('Location: index.php');
    exit;
}

$groups = get_customer_groups($db);
$areas = get_customer_areas($db);
$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$bal = (float)($c['receivable_balance'] ?? 0);
if ($bal > 0.0001) {
    $bal_kind = 'due';
    $bal_label = 'বর্তমান বকেয়া';
} elseif ($bal < -0.0001) {
    $bal_kind = 'deposit';
    $bal_label = 'বর্তমান জমা';
} else {
    $bal_kind = 'even';
    $bal_label = 'হিসাব সমান';
}
$limit = (float)($c['credit_limit'] ?? 0);
$photo = trim((string)($c['photo'] ?? ''));
$name_raw = trim((string)($c['name'] ?? ''));
if ($name_raw !== '' && function_exists('mb_substr')) {
    $initial = mb_substr($name_raw, 0, 1, 'UTF-8');
} elseif ($name_raw !== '') {
    $initial = substr($name_raw, 0, 1);
} else {
    $initial = 'ক';
}
$asset_v = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.361';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">
<link rel="stylesheet" href="/assets/css/customer-edit.css?v=<?php echo $h($asset_v); ?>">
<link rel="stylesheet" href="/assets/css/customer-sign.css?v=<?php echo $h($asset_v); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ক্রেতা সম্পাদনা — <?php echo $h($c['name']); ?></title>
</head>
<body class="ced-body">
<div class="ced">
    <header class="ced-hero">
        <div class="ced-who">
            <div class="ced-face" id="cedFace">
                <?php if ($photo !== ''): ?>
                    <img src="/<?php echo $h($photo); ?>" alt="">
                <?php else: ?>
                    <?php echo $h($initial); ?>
                <?php endif; ?>
            </div>
            <div>
                <p class="ced-kicker">ক্রেতা সম্পাদনা</p>
                <h1><?php echo $h($name_raw !== '' ? $name_raw : 'নাম নেই'); ?></h1>
                <span class="ced-code">কোড <?php echo $h($c['customer_code'] ?? '—'); ?></span>
                <?php $enabled_now = function_exists('ame_customer_status_enabled') ? ame_customer_status_enabled($c['status'] ?? 1) : ((int)($c['status'] ?? 1) === 1); ?>
                <span class="ced-mode <?php echo $enabled_now ? 'is-on' : 'is-off'; ?>" id="cedModeNow"><?php echo $enabled_now ? 'এখন চালু (Enable)' : 'এখন বন্ধ (Disable)'; ?></span>
            </div>
        </div>
        <div class="ced-money">
            <div class="ced-tile <?php echo $h($bal_kind); ?>">
                <span><?php echo $h($bal_label); ?></span>
                <b>৳<?php echo number_format(abs($bal), 2); ?></b>
            </div>
            <div class="ced-tile limit">
                <span>ক্রেডিট লিমিট</span>
                <b>৳<?php echo number_format($limit, 2); ?></b>
                <small><?php echo $limit <= 0 ? 'সীমা নেই' : 'সর্বোচ্চ বাকি'; ?></small>
            </div>
        </div>
    </header>

    <form id="cedForm" action="actions/process_customer_update.php" method="POST" enctype="multipart/form-data" data-ame-hold="1">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">

        <section class="ced-block ced-state">
            <h2>এনেবল / ডিজেবল</h2>
            <p class="ced-state-now <?php echo $enabled_now ? 'is-on' : 'is-off'; ?>" id="cedStateNow"><?php echo $enabled_now ? 'এই কাস্টমার এখন চালু' : 'এই কাস্টমার এখন বন্ধ'; ?></p>
            <div class="ced-roles">
                <label class="ced-role ced-state-on">
                    <input type="radio" name="customer_status" value="1" <?php echo $enabled_now ? 'checked' : ''; ?>>
                    <span>Enable — চালু</span>
                </label>
                <label class="ced-role ced-state-off">
                    <input type="radio" name="customer_status" value="0" <?php echo $enabled_now ? '' : 'checked'; ?>>
                    <span>Disable — বন্ধ</span>
                </label>
            </div>
        </section>

        <section class="ced-block ced-id">
            <h2>পরিচয়</h2>
            <div class="ced-pair">
                <div class="ced-field">
                    <label>আইডি / কাস্টমার কোড <span class="ced-star">*</span></label>
                    <input type="text" name="customer_code" required value="<?php echo $h($c['customer_code'] ?? ''); ?>">
                </div>
                <div class="ced-field">
                    <label>গ্রাহকের নাম <span class="ced-star">*</span></label>
                    <input type="text" name="name" required value="<?php echo $h($c['name'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <section class="ced-block ced-contact">
            <h2>যোগাযোগ ও পরিচয়</h2>
            <div class="ced-pair">
                <div class="ced-field">
                    <label>মোবাইল নম্বর</label>
                    <input type="text" name="phone" inputmode="tel" value="<?php echo $h($c['phone'] ?? ''); ?>">
                </div>
                <div class="ced-field">
                    <label>জাতীয় পরিচয়পত্র (NID)</label>
                    <input type="text" name="nid" value="<?php echo $h($c['nid'] ?? ''); ?>">
                </div>
            </div>
            <div class="ced-field">
                <label>পিতার নাম</label>
                <input type="text" name="father_name" value="<?php echo $h($c['father_name'] ?? ''); ?>">
            </div>
            <div class="ced-field">
                <label>ঠিকানা</label>
                <textarea name="address" rows="2"><?php echo $h($c['address'] ?? ''); ?></textarea>
            </div>
        </section>

        <section class="ced-block ced-place">
            <h2>গ্রুপ ও এরিয়া</h2>
            <div class="ced-pair">
                <div class="ced-field">
                    <label>গ্রুপ</label>
                    <select name="group_id">
                        <option value="">গ্রুপ নির্বাচন করুন</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?php echo (int)$g['id']; ?>" <?php echo (int)($c['group_id'] ?? 0) === (int)$g['id'] ? 'selected' : ''; ?>>
                                <?php echo $h($g['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ced-field">
                    <label>এরিয়া</label>
                    <select name="area_id">
                        <option value="">এরিয়া নির্বাচন করুন</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>" <?php echo (int)($c['area_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>>
                                <?php echo $h($a['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="ced-block ced-guard">
            <h2>গ্যারান্টার</h2>
            <div class="ced-pair">
                <div class="ced-field">
                    <label>গ্যারান্টারের নাম</label>
                    <input type="text" name="guarantor_name" value="<?php echo $h($c['guarantor_name'] ?? ''); ?>">
                </div>
                <div class="ced-field">
                    <label>গ্যারান্টারের মোবাইল</label>
                    <input type="text" name="guarantor_phone" inputmode="tel" value="<?php echo $h($c['guarantor_phone'] ?? ''); ?>">
                </div>
            </div>
        </section>

        <section class="ced-block ced-rolesec">
            <h2>রোল ও ক্রেডিট</h2>
            <div class="ced-roles">
                <label class="ced-role ced-role-cust">
                    <input type="checkbox" name="is_customer" value="1" <?php echo !empty($c['is_customer']) ? 'checked' : ''; ?>>
                    <span>সাধারণ ক্রেতা</span>
                </label>
                <label class="ced-role ced-role-sup">
                    <input type="checkbox" name="is_supplier" value="1" <?php echo !empty($c['is_supplier']) ? 'checked' : ''; ?>>
                    <span>সাপ্লায়ার</span>
                </label>
                <label class="ced-role ced-role-ins">
                    <input type="checkbox" name="is_installment" value="1" <?php echo !empty($c['is_installment']) ? 'checked' : ''; ?>>
                    <span>কিস্তি</span>
                </label>
                <label class="ced-role ced-role-rent">
                    <input type="checkbox" name="is_rental" value="1" <?php echo !empty($c['is_rental']) ? 'checked' : ''; ?>>
                    <span>ভাড়া</span>
                </label>
            </div>
            <div class="ced-field">
                <label>ক্রেডিট লিমিট (৳) — ০ মানে সীমা নেই</label>
                <input type="number" step="0.01" min="0" name="credit_limit" value="<?php echo $h($c['credit_limit'] ?? 0); ?>">
            </div>
        </section>

        <?php
        $watch_on = (string)($c['due_watch_on'] ?? '');
        $watch_fire = function_exists('ame_due_watch_fire_date') ? ame_due_watch_fire_date($watch_on) : '';
        $watch_hint = '';
        if (!empty($c['due_watch']) && $watch_fire) {
            $watch_hint = 'ওয়াচ চালু আছে। নোটিফিকেশন তারিখ: ' . $watch_fire . '। টিক খুলে সেভ করলে বন্ধ হয়ে যাবে।';
        }
        ?>
        <section class="ced-block ced-watch">
            <h2>১৫ দিনের বাকি</h2>
            <label class="ced-watchbox">
                <input type="checkbox" name="due_watch" value="1" <?php echo !empty($c['due_watch']) ? 'checked' : ''; ?>>
                <span>
                    <strong>টিক দিন — ১৫ দিন পর বাকি থাকলে উপরের ঘণ্টায় দেখাবে</strong>
                    <em>এই বক্স টিক করে রাখলে ১৫ দিন পরেও যদি এই কাস্টমারের কাছে বাকি টাকা থাকে, উপরের নোটিফিকেশন বারের ঘণ্টায় সংখ্যা উঠবে।</em>
                    <?php if ($watch_hint !== ''): ?>
                    <em><?php echo $h($watch_hint); ?></em>
                    <?php endif; ?>
                </span>
            </label>
        </section>

        <section class="ced-block ced-ledger">
            <h2>লেজার সমন্বয়</h2>
            <p class="ced-hint">শুধু নতুন বকেয়া বা জমা যোগ করতে চাইলে লিখুন। খালি রাখলে ব্যালেন্স অপরিবর্তিত।</p>
            <div class="ced-pair">
                <div class="ced-field">
                    <label>অতিরিক্ত বকেয়া যোগ (৳)</label>
                    <input type="number" step="0.01" min="0" name="add_due" value="0">
                </div>
                <div class="ced-field">
                    <label>অতিরিক্ত জমা যোগ (৳)</label>
                    <input type="number" step="0.01" min="0" name="add_deposit" value="0">
                </div>
            </div>
        </section>

        <?php
        $sign_existing = (string)($c['agreement_signature'] ?? '');
        $sign_raw_when = trim((string)($c['agreement_signed_at'] ?? ''));
        $sign_when = ($sign_raw_when !== '' && strtotime($sign_raw_when)) ? date('d/m/Y H:i', strtotime($sign_raw_when)) : '';
        $sign_mode = 'edit';
        $sign_customer_id = (int)($c['id'] ?? 0);
        include __DIR__ . '/signature_pad.php';
        ?>

        <section class="ced-block ced-photo">
            <h2>ছবি ও নোট</h2>
            <?php if ($photo !== ''): ?>
                <img class="ced-shot" id="cedShot" src="/<?php echo $h($photo); ?>" alt="">
            <?php else: ?>
                <img class="ced-shot" id="cedShot" alt="" hidden>
            <?php endif; ?>
            <label class="ced-file">
                <input type="file" name="photo" accept="image/*" id="cedPhoto">
                <span id="cedPhotoLabel">নতুন প্রোফাইল ছবি</span>
            </label>
            <div class="ced-field">
                <label>কাস্টম শর্ত</label>
                <textarea name="custom_requirement" rows="2"><?php echo $h($c['custom_requirement'] ?? ''); ?></textarea>
            </div>
            <div class="ced-field">
                <label>অতিরিক্ত নোট</label>
                <textarea name="additional_notes" rows="2"><?php echo $h($c['additional_notes'] ?? $c['notes'] ?? ''); ?></textarea>
            </div>
        </section>

        <div class="ced-savebar">
            <button type="submit">পরিবর্তন সংরক্ষণ</button>
            <div class="ced-jumps">
                <a href="profile.php?id=<?php echo (int)$c['id']; ?>">প্রোফাইল</a>
                <a href="index.php">ক্রেতা তালিকা</a>
            </div>
        </div>
    </form>
    <form class="ced-hide" method="post" action="actions/hide_customer.php" onsubmit="return confirm('চিরতরে মুছুন। তালিকা থেকে সরান। আগের বিক্রয়, পেমেন্ট, লেজার ও টাকা মুছবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
        <button type="submit">চিরতরে মুছুন</button>
        <p>সরালে পুরনো বিক্রয়, পেমেন্ট, লেজার ও টাকা থাকবে। এই মোবাইল ও আইডি নতুন কাস্টমারে আর লাগবে না।</p>
    </form>
</div>
<script>
(function () {
    var input = document.getElementById('cedPhoto');
    if (input) input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        var label = document.getElementById('cedPhotoLabel');
        if (label) label.textContent = file ? file.name : 'নতুন প্রোফাইল ছবি';
        if (!file || !/^image\//.test(file.type)) return;
        var shot = document.getElementById('cedShot');
        var face = document.getElementById('cedFace');
        var url = URL.createObjectURL(file);
        if (shot) {
            shot.hidden = false;
            shot.src = url;
        }
        if (face) {
            face.textContent = '';
            var img = face.querySelector('img') || document.createElement('img');
            img.alt = '';
            img.src = url;
            face.appendChild(img);
        }
    });
    function paintCustomerStatus() {
        var picked = document.querySelector('input[name="customer_status"]:checked');
        var on = picked && picked.value === '1';
        document.querySelectorAll('.ced-state-on, .ced-state-off').forEach(function (el) {
            el.classList.toggle('is-picked', !!el.querySelector('input:checked'));
        });
        var mode = document.getElementById('cedModeNow');
        var state = document.getElementById('cedStateNow');
        if (mode) {
            mode.textContent = on ? 'এখন চালু (Enable)' : 'এখন বন্ধ (Disable)';
            mode.className = 'ced-mode ' + (on ? 'is-on' : 'is-off');
        }
        if (state) {
            state.textContent = on ? 'এই কাস্টমার এখন চালু' : 'এই কাস্টমার এখন বন্ধ';
            state.className = 'ced-state-now ' + (on ? 'is-on' : 'is-off');
        }
    }
    document.querySelectorAll('input[name="customer_status"]').forEach(function (radio) {
        radio.addEventListener('change', paintCustomerStatus);
    });
    paintCustomerStatus();
    var form = document.getElementById('cedForm');
    if (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.ready === '1') {
                return;
            }
            ev.preventDefault();
            var code = (form.querySelector('[name="customer_code"]') || {}).value || '';
            var phone = (form.querySelector('[name="phone"]') || {}).value || '';
            var nid = (form.querySelector('[name="nid"]') || {}).value || '';
            var id = (form.querySelector('[name="id"]') || {}).value || '';
            var q = 'actions/check_duplicate.php?exclude_id=' + encodeURIComponent(id)
                + '&code=' + encodeURIComponent(code.trim())
                + '&phone=' + encodeURIComponent(phone.trim())
                + '&nid=' + encodeURIComponent(nid.trim());
            fetch(q).then(function (r) { return r.json(); }).then(function (data) {
                if (data.code_exists) {
                    alert('এই আইডি নম্বরে আগে থেকে একজন কাস্টমার আছে। অন্য আইডি দিন।');
                    return;
                }
                if (data.phone_exists) {
                    alert('এই মোবাইল নম্বরে আগে থেকে একজন কাস্টমার আছে। এক নম্বরে একাধিক কাস্টমার সেভ হবে না।');
                    return;
                }
                if (data.nid_exists) {
                    alert('এই এনআইডি নম্বরে আগে থেকে একজন কাস্টমার আছে। এক এনআইডিতে একাধিক কাস্টমার সেভ হবে না।');
                    return;
                }
                form.dataset.ready = '1';
                if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                    form.dataset.ready = '';
                    return;
                }
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }).catch(function () {
                form.dataset.ready = '1';
                if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                    form.dataset.ready = '';
                    return;
                }
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            });
        });
    }
})();
</script>
<script src="/assets/js/customer-sign-pad.js?v=<?php echo $h($asset_v); ?>"></script>
</body>
</html>
