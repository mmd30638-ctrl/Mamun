<link rel="stylesheet" href="/assets/css/customer-card.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.31', ENT_QUOTES, 'UTF-8'); ?>">

<div class="ccard-overlay" id="ccardOverlay" onclick="if(event.target===this) closeCustomerCard()">
    <div class="ccard-box" role="dialog" aria-modal="true" aria-labelledby="ccardName">
        <button type="button" class="ccard-close" onclick="closeCustomerCard()" aria-label="বন্ধ">&times;</button>
        <div id="ccardLoading" class="ccard-loading">তথ্য আসছে...</div>
        <div id="ccardError" class="ccard-error" style="display:none;"></div>
        <div id="ccardMain" style="display:none;">
            <div class="ccard-hero">
                <img id="ccardPhoto" class="ccard-photo" alt="" style="display:none;">
                <div id="ccardLetter" class="ccard-letter" style="display:none;"></div>
                <h3 id="ccardName"></h3>
                <div class="code" id="ccardCode"></div>
                <div class="ccard-roles" id="ccardRoles"></div>
            </div>
            <div class="ccard-body">
                <div id="ccardRows"></div>
                <div class="ccard-actions">
                    <a class="pri" id="ccardProfile" href="#">৩৬০° প্রোফাইল</a>
                    <a class="sec" id="ccardPay" href="#">পেমেন্ট</a>
                    <a class="sec" id="ccardCall" href="#" style="display:none;">কল</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function closeCustomerCard() {
    var el = document.getElementById('ccardOverlay');
    if (el) el.classList.remove('open');
}
function ameCardTaka(n) {
    var x = Number(n || 0);
    return '৳' + x.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function ameCardRow(label, value) {
    if (value === null || value === undefined || value === '') return '';
    var wrap = document.createElement('div');
    wrap.className = 'ccard-row';
    var a = document.createElement('span');
    a.textContent = label;
    var b = document.createElement('span');
    b.textContent = value;
    wrap.appendChild(a);
    wrap.appendChild(b);
    return wrap;
}
function ameCardPhoneRow(label, phone) {
    if (phone === null || phone === undefined || String(phone).trim() === '') return '';
    var wrap = document.createElement('div');
    wrap.className = 'ccard-row';
    var a = document.createElement('span');
    a.textContent = label;
    var b = document.createElement('a');
    b.href = 'tel:' + String(phone).replace(/\s+/g, '');
    b.textContent = phone;
    wrap.appendChild(a);
    wrap.appendChild(b);
    return wrap;
}
function openCustomerCard(id) {
    var overlay = document.getElementById('ccardOverlay');
    var loading = document.getElementById('ccardLoading');
    var err = document.getElementById('ccardError');
    var main = document.getElementById('ccardMain');
    if (!overlay) return;
    overlay.classList.add('open');
    loading.style.display = 'block';
    err.style.display = 'none';
    main.style.display = 'none';

    fetch('actions/customer_card.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (pack) {
            loading.style.display = 'none';
            if (!pack.data || !pack.data.ok || !pack.data.customer) {
                err.style.display = 'block';
                err.textContent = (pack.data && pack.data.message) ? pack.data.message : 'তথ্য আসেনি।';
                return;
            }
            var c = pack.data.customer;
            var nameEl = document.getElementById('ccardName');
            nameEl.textContent = '';
            nameEl.appendChild(document.createTextNode(c.name || 'ক্রেতা'));
            if (c.is_star) {
                var star = document.createElement('span');
                star.className = 'star-cus';
                star.title = 'স্টার কাস্টমার';
                star.textContent = '★';
                nameEl.appendChild(star);
            }
            document.getElementById('ccardCode').textContent = c.code ? ('আইডি: ' + c.code) : '';

            var img = document.getElementById('ccardPhoto');
            var letter = document.getElementById('ccardLetter');
            if (c.photo) {
                img.src = c.photo;
                img.style.display = 'block';
                letter.style.display = 'none';
            } else {
                img.style.display = 'none';
                letter.textContent = c.letter || '?';
                letter.style.display = 'flex';
            }

            var roles = document.getElementById('ccardRoles');
            roles.textContent = '';
            var roleMap = [
                [c.is_customer, 'ক্রেতা'],
                [c.is_supplier, 'সাপ্লায়ার'],
                [c.is_installment, 'কিস্তি'],
                [c.is_rental, 'ভাড়া']
            ];
            roleMap.forEach(function (r) {
                if (!r[0]) return;
                var b = document.createElement('b');
                b.textContent = r[1];
                roles.appendChild(b);
            });

            var rows = document.getElementById('ccardRows');
            rows.textContent = '';
            var parts = [
                ameCardRow('মোবাইল', c.phone || ''),
                ameCardRow('অন্য মোবাইল', c.alt_phone || ''),
                ameCardRow('জামিনদারের নাম', c.guarantor_name || ''),
                ameCardPhoneRow('জামিনদারের নাম্বার', c.guarantor_phone || ''),
                ameCardRow('NID', c.nid || ''),
                ameCardRow('পিতার নাম', c.father_name || ''),
                ameCardRow('ঠিকানা', c.address || ''),
                ameCardRow('গ্রুপ', c.group_name || ''),
                ameCardRow('এলাকা', c.area_name || ''),
                ameCardRow('রক্তের গ্রুপ', c.blood_group || ''),
                ameCardRow(c.receivable < -0.009 ? (c.is_supplier ? 'দোকানের দেনা / পাওনা' : 'জমা / পাওনা') : 'মোট বাকি', c.receivable < -0.009 ? ('−' + ameCardTaka(Math.abs(c.receivable))) : ameCardTaka(c.receivable)),
                ameCardRow('ক্রেডিট লিমিট', c.credit_limit > 0 ? ameCardTaka(c.credit_limit) : ''),
                ameCardRow('পরিশোধের তারিখ', c.next_due_date || ''),
                ameCardRow('প্রত্যাশিত টাকা', c.next_due_amount !== null ? ameCardTaka(c.next_due_amount) : ''),
                ameCardRow('বাকি নোট', c.due_note || ''),
                ameCardRow('কেনা পণ্য', c.qty ? (c.qty + ' পিস · ' + c.invoices + ' বার') : ''),
                ameCardRow('নোট', c.notes || '')
            ];
            parts.forEach(function (el) { if (el) rows.appendChild(el); });

            document.getElementById('ccardProfile').href = 'profile.php?id=' + c.id;
            document.getElementById('ccardPay').href = 'payment-receive.php?id=' + c.id;
            var call = document.getElementById('ccardCall');
            if (c.phone) {
                call.href = 'tel:' + c.phone;
                call.style.display = '';
            } else {
                call.style.display = 'none';
            }
            main.style.display = 'block';
        })
        .catch(function () {
            loading.style.display = 'none';
            err.style.display = 'block';
            err.textContent = 'সার্ভার থেকে তথ্য আসেনি। আবার চেষ্টা করুন।';
        });
}
</script>
