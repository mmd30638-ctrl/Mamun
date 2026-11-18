/* 10.98.722 */
'use strict';

(function () {
    if (!document.querySelector('.ame-sale-desk')) {
        return;
    }

    function el(id) {
        return document.getElementById(id);
    }

    function money(value) {
        var number = Number.parseFloat(value);
        return Number.isFinite(number) ? number : 0;
    }

    function paintClock() {
        var now = new Date();
        var clock = el('ameDeskClock');
        var date = el('ameDeskDate');
        if (clock) {
            var hours = now.getHours();
            var suffix = hours >= 12 ? 'PM' : 'AM';
            var hour12 = hours % 12 || 12;
            var pad = function (part) { return String(part).padStart(2, '0'); };
            clock.textContent = pad(hour12) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + suffix;
        }
        if (date) {
            var english = document.documentElement.lang === 'en';
            if (english) {
                date.textContent = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' }).replace(/ /g, '-');
            } else {
                var day = now.toLocaleDateString('bn-BD', { day: 'numeric' });
                var month = now.toLocaleDateString('bn-BD', { month: 'long' });
                var year = now.toLocaleDateString('bn-BD', { year: 'numeric' });
                date.textContent = day + '-' + month + '-' + year;
            }
        }
    }

    var discountSource = 'pct';
    var discountOn = true;

    function pctFeatureOn() {
        var box = el('deskDiscBox');
        return !!(box && box.getAttribute('data-pct-off') !== '1' && el('deskDiscountPct'));
    }

    function pctLocked() {
        var box = el('deskDiscBox');
        if (!pctFeatureOn()) {
            return false;
        }
        return box.getAttribute('data-lock') !== '0';
    }

    function storedLockPct() {
        try {
            var saved = localStorage.getItem('ameDeskLockPct');
            if (saved === null || saved === '') {
                return null;
            }
            var n = money(saved);
            if (!Number.isFinite(n)) {
                return null;
            }
            return Math.min(100, Math.max(0, n));
        } catch (err) {
            return null;
        }
    }

    var lastSavedPct = null;
    var pctSaveTimer = 0;

    function saveLockPct(value) {
        if (!pctFeatureOn() || !pctLocked()) {
            return;
        }
        var next = Math.min(100, Math.max(0, value));
        if (lastSavedPct !== null && Math.abs(lastSavedPct - next) < 0.009) {
            return;
        }
        if (pctSaveTimer) {
            clearTimeout(pctSaveTimer);
        }
        pctSaveTimer = setTimeout(function () {
            lastSavedPct = next;
            var token = window.AME_CSRF_TOKEN || '';
            if (!token || typeof fetch !== 'function') {
                return;
            }
            var body = new URLSearchParams();
            body.set('pos_discount_percent', next.toFixed(2));
            body.set('csrf_token', token);
            fetch('/modules/settings/discount_percent_save.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': token
                },
                body: body.toString()
            }).catch(function () {});
        }, 450);
    }

    function rememberLockPct(value) {
        if (!pctLocked()) {
            return;
        }
        var next = Math.min(100, Math.max(0, value));
        try {
            localStorage.setItem('ameDeskLockPct', String(next));
        } catch (err) {
        }
        saveLockPct(next);
    }

    (function initDiscountSource() {
        var autoEl = el('deskDiscountAuto');
        var pctEl = el('deskDiscountPct');
        var box = el('deskDiscBox');
        if (autoEl) {
            autoEl.value = pctFeatureOn() ? '1' : '0';
        }
        if (pctEl && pctFeatureOn()) {
            pctEl.readOnly = true;
            pctEl.value = '12.00';
            lastSavedPct = 12;
        }
        discountOn = pctFeatureOn() ? true : !(box && box.getAttribute('data-on') === '0');
        discountSource = pctLocked() ? 'pct' : 'taka';
        if (box) {
            box.setAttribute('data-mode', discountSource);
            box.setAttribute('data-on', discountOn ? '1' : '0');
            box.setAttribute('data-lock', pctLocked() ? '1' : '0');
        }
    })();

    function discIsOn() {
        var box = el('deskDiscBox');
        if (!box) {
            return discountOn;
        }
        return box.getAttribute('data-on') !== '0';
    }

    function paintDiscSwitch() {
        var box = el('deskDiscBox');
        var btn = el('deskDiscToggle');
        if (box) {
            box.setAttribute('data-on', discountOn ? '1' : '0');
            box.classList.toggle('is-off', !discountOn);
        }
        if (btn) {
            var onLabel = btn.getAttribute('data-on-label') || 'ছাড় চালু';
            var offLabel = btn.getAttribute('data-off-label') || 'ছাড় বন্ধ';
            btn.setAttribute('aria-pressed', discountOn ? 'true' : 'false');
            btn.classList.toggle('is-on', discountOn);
            btn.textContent = discountOn ? onLabel : offLabel;
        }
        document.querySelectorAll('.ame-desk-disc-field').forEach(function (lab) {
            var live = discountOn && lab.getAttribute('data-disc-field') === discountSource;
            lab.classList.toggle('is-live', live);
        });
        paintLock();
    }

    function paintLock() {
        var box = el('deskDiscBox');
        var btn = el('deskDiscLock');
        var takaEl = el('deskDiscountTaka');
        var locked = pctLocked();
        if (box) {
            box.classList.toggle('is-locked', locked);
            box.setAttribute('data-lock', locked ? '1' : '0');
        }
        if (takaEl) {
            takaEl.readOnly = locked;
        }
        if (btn) {
            var lockLabel = btn.getAttribute('data-lock-label') || 'লক';
            var openLabel = btn.getAttribute('data-open-label') || 'খোলা';
            btn.classList.toggle('is-locked', locked);
            btn.setAttribute('aria-pressed', locked ? 'true' : 'false');
            btn.textContent = locked ? lockLabel : openLabel;
        }
    }

    function applyDiscountField() {
        var gross = money(el('sub-total') && el('sub-total').value);
        var lineOff = money(window.ameLineDiscountTaka);
        var afterLine = Math.max(0, gross - lineOff);
        var pctEl = el('deskDiscountPct');
        var takaEl = el('deskDiscountTaka');
        var extra = 0;
        if (pctLocked()) {
            discountSource = 'pct';
        }
        if (!discIsOn()) {
            extra = 0;
            if (takaEl && document.activeElement !== takaEl) {
                takaEl.value = '0';
            }
        } else if (!pctLocked() && discountSource === 'taka') {
            extra = Math.max(0, money(takaEl && takaEl.value));
            if (extra > afterLine) {
                extra = afterLine;
            }
            if (pctEl && document.activeElement !== pctEl) {
                var fromTaka = afterLine > 0.0001 ? (extra / afterLine) * 100 : 0;
                pctEl.value = fromTaka.toFixed(2);
            }
            if (takaEl && document.activeElement !== takaEl) {
                takaEl.value = extra > 0.009 ? extra.toFixed(2) : '0';
            }
        } else {
            var rawPct = pctLocked() ? 12 : Math.max(0, money(pctEl && pctEl.value));
            var pct = Math.min(100, rawPct);
            if (pctLocked() && pctEl) {
                pctEl.readOnly = true;
                pctEl.value = '12.00';
            }
            extra = afterLine * pct / 100;
            if (takaEl && document.activeElement !== takaEl) {
                takaEl.value = extra > 0.009 ? extra.toFixed(2) : '0';
            }
            if (pctEl && document.activeElement !== pctEl && rawPct !== pct) {
                pctEl.value = pct.toFixed(2);
            }
            if (pctLocked()) {
                rememberLockPct(pct);
            }
        }
        var discount = el('discount');
        if (discount) {
            discount.value = (lineOff + extra).toFixed(2);
        }
    }

    function paintDesk() {
        var gross = money(el('sub-total') && el('sub-total').value);
        var discount = money(el('discount') && el('discount').value);
        var service = Math.max(0, money(el('service_amount') && el('service_amount').value));
        var shownGross = gross + service;
        var after = Math.max(0, shownGross - discount);
        var payableNode = el('net-payable');
        var grandText = (payableNode && payableNode.textContent) || '0.00';
        var grand = money(String(grandText).replace(/[^\d.-]/g, ''));
        if (grand < 0) {
            grand = 0;
        }
        var saleBill = payableNode && payableNode.dataset.saleBill != null && payableNode.dataset.saleBill !== ''
            ? money(payableNode.dataset.saleBill)
            : grand;
        var paid = money(el('deskPaid') && el('deskPaid').value) + money(el('deskPaid2') && el('deskPaid2').value);
        var balanceNode = el('curr-due');
        var dueRaw = balanceNode ? money(balanceNode.value) : Math.max(0, saleBill - paid);
        var due = dueRaw > 0.009 ? dueRaw : 0;
        if (el('deskSubtotal')) {
            el('deskSubtotal').textContent = shownGross.toFixed(2);
        }
        if (el('deskAfter')) {
            el('deskAfter').textContent = after.toFixed(2);
        }
        if (el('deskGrand')) {
            el('deskGrand').textContent = '৳ ' + grand.toFixed(2);
        }
        if (el('deskDue')) {
            el('deskDue').textContent = due.toFixed(2);
            el('deskDue').classList.toggle('is-clear', due <= 0.009);
        }
        paintExtra(saleBill, paid);
        paintRunning(saleBill);
    }

    function round2(value) {
        return Math.round(money(value) * 100) / 100;
    }

    function paintRunning(saleBill) {
        var box = el('ameDeskRunBal');
        if (!box) {
            return;
        }
        var select = el('customer_id');
        var opt = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
        if (!opt || !opt.value) {
            box.hidden = true;
            return;
        }
        var previous = round2(el('prev-due') && el('prev-due').value);
        var bill = round2(saleBill);
        var rounding = round2(el('rounding') && el('rounding').value);
        var exchange = (typeof window.ameExchangeTotal === 'function')
            ? Math.max(0, round2(window.ameExchangeTotal()))
            : 0;
        var paid = round2(money(el('deskPaid') && el('deskPaid').value) + money(el('deskPaid2') && el('deskPaid2').value));
        var netCash = Math.max(0, round2(bill - exchange));
        var remaining = Math.max(0, round2(bill - exchange - paid));
        var extra = Math.max(0, round2(paid - netCash));
        var apply = !!(el('ameExtraApply') && el('ameExtraApply').checked && deskRegistered());
        var adjustment = apply ? extra : 0;
        var projected = round2(previous + remaining - adjustment);
        var english = document.documentElement.lang === 'en';
        var tag = el('deskRunTag');
        var amount = el('deskRunAmount');
        var line = el('deskRunLine');
        var kind = 'even';
        var label = english ? 'Settled' : 'সমান';
        var color = '#64748b';
        if (projected < -0.009) {
            kind = 'deposit';
            label = english ? 'Deposit left' : 'জমা থাকবে';
            color = '#166534';
        } else if (projected > 0.009) {
            kind = 'due';
            label = english ? 'Due will be' : 'বাকি হবে';
            color = '#b91c1c';
        }
        if (tag) {
            tag.textContent = label;
            tag.style.color = color;
        }
        if (amount) {
            amount.textContent = (kind === 'even' ? '0.00' : Math.abs(projected).toFixed(2));
            amount.style.color = color;
        }
        if (line) {
            function side(value) {
                var n = round2(value);
                if (n < -0.009) {
                    return (english ? 'deposit ' : 'জমা ') + Math.abs(n).toFixed(2);
                }
                if (n > 0.009) {
                    return (english ? 'due ' : 'বাকি ') + n.toFixed(2);
                }
                return english ? '0.00' : '০.০০';
            }
            var paidKept = round2(Math.max(0, paid - (apply ? 0 : extra)));
            var opener = previous < -0.009
                ? (english ? 'Was deposit ' : 'সাবেক জমা ') + Math.abs(previous).toFixed(2) + (english ? ' − bill ' : ' − বিল ')
                : (previous > 0.009
                    ? (english ? 'Was due ' : 'সাবেক বাকি ') + previous.toFixed(2) + (english ? ' + bill ' : ' + বিল ')
                    : (english ? 'Bill ' : 'বিল '));
            var text = opener + bill.toFixed(2);
            if (Math.abs(rounding) > 0.009) {
                text += (english ? ' (rounding ' : ' (রাউন্ডিং ')
                    + (rounding > 0 ? '+' : '') + rounding.toFixed(2) + ')';
            }
            if (paidKept > 0.009) {
                var paySign = previous < -0.009 ? ' + ' : ' − ';
                text += paySign + (english ? 'paid ' : 'পরিশোধ ') + paidKept.toFixed(2);
            }
            text += (english ? ' = now ' : ' = এখন ') + side(projected);
            line.textContent = text;
        }
        box.hidden = false;
    }

    function deskRegistered() {
        var nodes = document.querySelectorAll('input[name="cus_type"]:checked, input[name="cus_type_toolbar"]:checked');
        var i;
        for (i = 0; i < nodes.length; i += 1) {
            if (nodes[i].value === 'Registered' || nodes[i].value === 'Supplier') {
                return true;
            }
        }
        return false;
    }

    function paintChange(amount) {
        var node = el('deskChange');
        if (!node) {
            return;
        }
        node.textContent = Math.max(0, money(amount)).toFixed(2);
    }

    function paintExtra(grand, paid) {
        var box = el('ameDeskExtra');
        if (!box) {
            paintChange(Math.max(0, paid - grand));
            return;
        }
        var exchange = (typeof window.ameExchangeTotal === 'function')
            ? Math.max(0, money(window.ameExchangeTotal()))
            : 0;
        var extra = Math.max(0, paid - Math.max(0, grand - exchange));
        var registered = deskRegistered();
        var applyLabel = el('ameExtraApplyLabel');
        var ret = el('ameExtraReturn');
        var apply = el('ameExtraApply');
        var adjust = el('adjust-due');
        var wasRetail = !!(applyLabel && applyLabel.hidden);
        if (applyLabel) {
            applyLabel.hidden = !registered;
        }
        if (!registered) {
            if (ret) {
                ret.checked = true;
            }
            if (apply) {
                apply.checked = false;
            }
        } else if (wasRetail && apply) {
            apply.checked = true;
            if (ret) {
                ret.checked = false;
            }
        } else if (apply && ret && !apply.checked && !ret.checked) {
            apply.checked = true;
        }
        var modeApply = registered && apply && apply.checked;
        if (adjust) {
            adjust.checked = modeApply && extra > 0.009;
            if (extra > 0.009) {
                adjust.dataset.userTouched = '1';
            }
        }
        box.hidden = extra <= 0.009;
        var prev = money(el('prev-due') && el('prev-due').value);
        var cut = modeApply ? Math.min(extra, Math.max(0, prev)) : 0;
        var credit = modeApply ? Math.max(0, extra - Math.max(0, prev)) : 0;
        var change = modeApply ? 0 : extra;
        var english = document.documentElement.lang === 'en';
        function line(id, text, show) {
            var node = el(id);
            if (!node) {
                return;
            }
            node.hidden = !show;
            node.textContent = show ? text : '';
        }
        paintChange(change);
        line('ameDeskReturnLine', (english ? 'Return: ৳ ' : 'ফেরত: ৳ ') + change.toFixed(2), change > 0.009);
        line('ameDeskDueCutLine', (english ? 'Cut from previous due: ৳ ' : 'আগের বাকি থেকে কাটবে: ৳ ') + cut.toFixed(2), cut > 0.009);
        line('ameDeskCreditLine', (english ? 'Added to the account: ৳ ' : 'অ্যাকাউন্টে যোগ: ৳ ') + credit.toFixed(2), credit > 0.009);
    }

    var innerCalc = window.calculateAll;
    window.calculateAll = function () {
        applyDiscountField();
        var result = typeof innerCalc === 'function' ? innerCalc.apply(this, arguments) : undefined;
        paintDesk();
        return result;
    };

    function paymentRow(index) {
        var box = el('posPaymentRows');
        var rows = box ? box.querySelectorAll('.pos-payment-row') : [];
        if (index === 1 && rows.length < 2 && box) {
            var template = el('posPaymentRowTemplate');
            if (template && template.content) {
                box.appendChild(template.content.cloneNode(true));
                rows = box.querySelectorAll('.pos-payment-row');
            }
        }
        return rows[index] || null;
    }

    function syncPayRow(index, methodId, paidId) {
        var row = paymentRow(index);
        if (!row) {
            return;
        }
        var account = row.querySelector('.pos-payment-account');
        var amount = row.querySelector('.pos-payment-amount');
        var method = el(methodId);
        var paid = el(paidId);
        if (account && method) {
            account.value = method.value;
        }
        if (amount && paid) {
            amount.value = paid.value || '0';
        }
    }

    function secondPayLineOpen() {
        var panel = el('deskPaySecond');
        return Boolean(panel && !panel.hasAttribute('hidden'));
    }

    function clearMirroredPayRows() {
        var box = el('posPaymentRows');
        if (!box) {
            return;
        }
        var rows = box.querySelectorAll('.pos-payment-row');
        Array.prototype.forEach.call(rows, function (row, index) {
            if (index > 0) {
                row.remove();
            }
        });
    }

    function syncPaid() {
        syncPayRow(0, 'deskPayMethod', 'deskPaid');
        if (secondPayLineOpen()) {
            syncPayRow(1, 'deskPayMethod2', 'deskPaid2');
        } else {
            clearMirroredPayRows();
        }
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        } else {
            paintDesk();
        }
    }

    function paymentsFromRows() {
        var out = [];
        var box = el('posPaymentRows');
        if (!box) {
            return out;
        }
        box.querySelectorAll('.pos-payment-row').forEach(function (row) {
            var account = row.querySelector('.pos-payment-account');
            var amount = row.querySelector('.pos-payment-amount');
            var reference = row.querySelector('.pos-payment-reference');
            var accountId = Number(account && account.value ? account.value : 0);
            var paymentAmount = money(amount && amount.value);
            if (accountId > 0 && paymentAmount > 0) {
                out.push({
                    account_id: accountId,
                    amount: paymentAmount,
                    reference_number: (reference && reference.value) ? String(reference.value).trim() : ''
                });
            }
        });
        return out;
    }

    function applyDeskPayments(payments) {
        var list = Array.isArray(payments) ? payments.filter(function (row) {
            return Number(row && row.account_id ? row.account_id : 0) > 0
                && money(row && row.amount) > 0;
        }) : [];
        var first = list[0] || null;
        var second = list[1] || null;
        var paid = el('deskPaid');
        var method = el('deskPayMethod');
        var paid2 = el('deskPaid2');
        var method2 = el('deskPayMethod2');
        var paySecond = el('deskPaySecond');
        var payArrow = el('deskPayArrow');

        if (method && first) {
            method.value = String(first.account_id);
        }
        if (paid) {
            paid.value = first ? money(first.amount).toFixed(2) : '0';
        }
        if (second) {
            if (paySecond) {
                paySecond.removeAttribute('hidden');
            }
            if (payArrow) {
                payArrow.textContent = '▾';
                payArrow.setAttribute('aria-expanded', 'true');
            }
            if (method2) {
                method2.value = String(second.account_id);
            }
            if (paid2) {
                paid2.value = money(second.amount).toFixed(2);
            }
        } else {
            if (paid2) {
                paid2.value = '0';
            }
            if (paySecond) {
                paySecond.setAttribute('hidden', '');
            }
            if (payArrow) {
                payArrow.textContent = '▸';
                payArrow.setAttribute('aria-expanded', 'false');
            }
        }
        syncPaid();
    }

    function applyDeskDiscount(totalTaka) {
        if (pctLocked()) {
            discountSource = 'pct';
            var lockedBox = el('deskDiscBox');
            if (lockedBox) {
                lockedBox.setAttribute('data-mode', 'pct');
            }
            applyDiscountField();
            paintDiscSwitch();
            return;
        }
        var lineOff = money(window.ameLineDiscountTaka);
        var extra = Math.max(0, money(totalTaka) - lineOff);
        discountSource = 'taka';
        var modeBox = el('deskDiscBox');
        if (modeBox) {
            modeBox.setAttribute('data-mode', 'taka');
        }
        paintDiscSwitch();
        var takaEl = el('deskDiscountTaka');
        if (takaEl) {
            takaEl.value = extra > 0.009 ? extra.toFixed(2) : '0';
        }
        var pctEl = el('deskDiscountPct');
        var afterLine = Math.max(0, money(el('sub-total') && el('sub-total').value) - lineOff);
        if (pctEl && document.activeElement !== pctEl) {
            pctEl.value = afterLine > 0.0001 ? ((extra / afterLine) * 100).toFixed(2) : '0.00';
        }
        var discount = el('discount');
        if (discount) {
            discount.value = money(totalTaka).toFixed(2);
        }
        if (typeof window.amePaintDiscountBoxes === 'function') {
            window.amePaintDiscountBoxes();
        }
    }

    window.ameSyncDeskPaid = syncPaid;
    window.ameApplyDeskPayments = applyDeskPayments;
    window.ameApplyDeskPaymentsFromRows = function () {
        applyDeskPayments(paymentsFromRows());
    };
    window.ameApplyDeskDiscount = applyDeskDiscount;

    function syncMethod() {
        syncPaid();
    }

    var paid = el('deskPaid');
    if (paid) {
        ['input', 'keyup', 'change'].forEach(function (evt) {
            paid.addEventListener(evt, syncPaid);
        });
    }
    var paid2 = el('deskPaid2');
    if (paid2) {
        ['input', 'keyup', 'change'].forEach(function (evt) {
            paid2.addEventListener(evt, syncPaid);
        });
    }
    var method2 = el('deskPayMethod2');
    if (method2) {
        method2.addEventListener('change', syncPaid);
    }
    var payArrow = el('deskPayArrow');
    var paySecond = el('deskPaySecond');
    if (payArrow && paySecond) {
        payArrow.addEventListener('click', function () {
            var open = paySecond.hasAttribute('hidden');
            paySecond.toggleAttribute('hidden', !open);
            payArrow.textContent = open ? '▾' : '▸';
            payArrow.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                syncDeskAccountLists();
                var secondMethod = el('deskPayMethod2');
                if (secondMethod) {
                    secondMethod.focus();
                }
            } else {
                var secondAmount = el('deskPaid2');
                if (secondAmount) {
                    secondAmount.value = '0';
                }
                syncPaid();
                var firstAmount = el('deskPaid');
                if (firstAmount) {
                    firstAmount.focus();
                }
            }
        });
    }
    syncDeskAccountLists();
    var method = el('deskPayMethod');
    if (method) {
        var options = method.options;
        var preferred = -1;
        for (var i = 0; i < options.length; i += 1) {
            var label = (options[i].textContent || '').toLowerCase();
            if (label.indexOf('নগদ') >= 0 || label.indexOf('ক্যাশ') >= 0 || label.indexOf('cash') >= 0) {
                preferred = i;
                break;
            }
        }
        if (preferred < 0) {
            for (var j = 0; j < options.length; j += 1) {
                var name = (options[j].textContent || '').toLowerCase();
                if (name.indexOf('বিকাশ') >= 0 || name.indexOf('bkash') >= 0) {
                    preferred = j;
                    break;
                }
            }
        }
        if (preferred > 0) {
            method.selectedIndex = preferred;
        }
        method.addEventListener('change', syncMethod);
        if (method2 && method2.options.length > 1) {
            var used = method.value;
            var second = -1;
            for (var k = 0; k < method2.options.length; k += 1) {
                var text = (method2.options[k].textContent || '').toLowerCase();
                if (!method2.options[k].value || method2.options[k].value === used) {
                    continue;
                }
                if (text.indexOf('বিকাশ') >= 0 || text.indexOf('bkash') >= 0 || text.indexOf('নগদ') >= 0 || text.indexOf('nagad') >= 0) {
                    second = k;
                    break;
                }
                if (second < 0) {
                    second = k;
                }
            }
            if (second > 0) {
                method2.selectedIndex = second;
            }
        }
        if (paymentsFromRows().length > 0) {
            applyDeskPayments(paymentsFromRows());
        } else {
            syncMethod();
        }
    }

    function watchDiscount(id, source) {
        var field = el(id);
        if (!field) {
            return;
        }
        field.addEventListener('input', function () {
            discountSource = source;
            var box = el('deskDiscBox');
            if (box) {
                box.setAttribute('data-mode', source);
            }
            paintDiscSwitch();
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
        field.addEventListener('blur', function () {
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
    }
    watchDiscount('deskDiscountPct', 'pct');
    watchDiscount('deskDiscountTaka', 'taka');
    var discToggle = el('deskDiscToggle');
    if (discToggle) {
        discToggle.addEventListener('click', function () {
            if (pctFeatureOn()) {
                discountOn = true;
                paintDiscSwitch();
                return;
            }
            discountOn = !discountOn;
            paintDiscSwitch();
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
    }
    var discLock = el('deskDiscLock');
    if (discLock) {
        discLock.addEventListener('click', function () {
            var box = el('deskDiscBox');
            if (pctFeatureOn()) {
                if (box) {
                    box.setAttribute('data-lock', '1');
                    box.setAttribute('data-mode', 'pct');
                }
                discountSource = 'pct';
                paintDiscSwitch();
                if (typeof window.calculateAll === 'function') {
                    window.calculateAll();
                }
                return;
            }
            var next = pctLocked() ? '0' : '1';
            if (box) {
                box.setAttribute('data-lock', next);
                box.setAttribute('data-mode', next === '1' ? 'pct' : discountSource);
            }
            discountSource = next === '1' ? 'pct' : discountSource;
            paintDiscSwitch();
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
    }
    paintDiscSwitch();

    var menuBtn = el('ameDeskMenuBtn');
    var menu = el('ameDeskMenu');
    if (menuBtn && menu) {
        menuBtn.addEventListener('click', function () {
            menu.hidden = !menu.hidden;
            menuBtn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.ame-desk-menu')) {
                menu.hidden = true;
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    var complete = el('ameDeskComplete');
    if (complete) {
        complete.addEventListener('click', function () {
            var button = el('amePosCompleteBtn');
            if (button) {
                button.click();
            }
        });
    }
    function openDrafts() {
        var recent = el('amePosRecentDraft');
        if (recent) {
            recent.click();
        }
        var list = el('openDraftsButton') || el('amePosTermHist');
        if (list) {
            list.click();
        }
    }
    var drafts = el('ameDeskDrafts');
    if (drafts) {
        drafts.addEventListener('click', openDrafts);
    }
    var draftBack = el('ameDeskDraftBack');
    if (draftBack) {
        draftBack.addEventListener('click', function (event) {
            event.preventDefault();
            var recent = el('amePosRecentDraft');
            if (recent) {
                recent.click();
            }
            if (typeof window.ameSaveMemoDraft === 'function') {
                window.ameSaveMemoDraft();
                return;
            }
            if (typeof window.ameSaveCurrentDraft === 'function') {
                window.ameSaveCurrentDraft();
            }
        });
    }
    var recentBtn = el('ameDeskRecent');
    if (recentBtn) {
        recentBtn.addEventListener('click', function () {
            var done = el('amePosRecentComplete');
            if (done) {
                done.click();
            }
            var hist = el('amePosTermHist');
            if (hist) {
                hist.click();
            }
        });
    }

    var marquee = document.querySelector('.ame-desk-marquee');
    if (marquee && typeof window.ameOpenPosTitleEdit === 'function') {
        marquee.style.cursor = 'pointer';
        marquee.title = document.documentElement.lang === 'en' ? 'Edit announcement' : 'ঘোষণা এডিট';
        marquee.addEventListener('click', function () {
            window.ameOpenPosTitleEdit();
        });
    }

    function realOptionCount(select) {
        var count = 0;
        if (!select || !select.options) {
            return 0;
        }
        Array.prototype.forEach.call(select.options, function (opt) {
            if (String(opt.value || '').trim() !== '') {
                count += 1;
            }
        });
        return count;
    }

    function syncDeskAccountLists() {
        var candidates = [];
        var first = el('deskPayMethod');
        var second = el('deskPayMethod2');
        if (first) {
            candidates.push(first);
        }
        if (second) {
            candidates.push(second);
        }
        Array.prototype.forEach.call(document.querySelectorAll('select.pos-payment-account'), function (sel) {
            candidates.push(sel);
        });
        var template = el('posPaymentRowTemplate');
        if (template && template.content) {
            Array.prototype.forEach.call(template.content.querySelectorAll('select'), function (sel) {
                candidates.push(sel);
            });
        }
        var source = null;
        var sourceCount = -1;
        candidates.forEach(function (sel) {
            var count = realOptionCount(sel);
            if (count > sourceCount) {
                source = sel;
                sourceCount = count;
            }
        });
        if (!source || sourceCount < 1) {
            return;
        }
        candidates.forEach(function (sel) {
            if (sel === source || realOptionCount(sel) >= sourceCount) {
                return;
            }
            var selected = sel.value;
            while (sel.firstChild) {
                sel.removeChild(sel.firstChild);
            }
            Array.prototype.forEach.call(source.options, function (opt) {
                sel.appendChild(opt.cloneNode(true));
            });
            if (selected && Array.prototype.some.call(sel.options, function (opt) {
                return opt.value === selected;
            })) {
                sel.value = selected;
            }
        });
    }

    function closeOtherDeskSheets(except) {
        ['deskPayMethod', 'deskPayMethod2'].forEach(function (id) {
            var sel = el(id);
            if (sel && sel !== except && typeof sel.ameCloseDeskSheet === 'function') {
                sel.ameCloseDeskSheet();
            }
        });
    }

    function bindDeskMethodSheet(methodPick, titleText, ariaLabel) {
        if (!methodPick || methodPick.dataset.menu) {
            return;
        }
        methodPick.dataset.menu = '1';
        var wrap = document.createElement('div');
        wrap.className = 'ame-desk-method-menu';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ame-desk-acct-icon';
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-label', ariaLabel);
        trigger.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="16.5" cy="14.5" r="1.2" fill="currentColor"/></svg>';
        var list = document.createElement('div');
        list.className = 'ame-desk-method-list';
        list.hidden = true;
        list.setAttribute('role', 'listbox');
        function isCashName(text) {
            var label = (text || '').toLowerCase();
            return label.indexOf('নগদ') >= 0 || label.indexOf('ক্যাশ') >= 0 || label.indexOf('cash') >= 0;
        }
        function methodTone(text) {
            var label = (text || '').toLowerCase();
            if (label.indexOf('বিকাশ') >= 0 || label.indexOf('bkash') >= 0) return 'is-bkash';
            if (label.indexOf('রকেট') >= 0 || label.indexOf('rocket') >= 0) return 'is-rocket';
            if (label.indexOf('ব্যাংক') >= 0 || label.indexOf('bank') >= 0) return 'is-bank';
            if (label.indexOf('নগদ-মোবাইল') >= 0 || label.indexOf('nagad') >= 0 || label.indexOf('মোবাইল') >= 0) return 'is-nagad';
            if (label.indexOf('বাসা') >= 0 || label.indexOf('বাকি') >= 0 || label.indexOf('due') >= 0 || label.indexOf('credit') >= 0) return 'is-due';
            if (isCashName(text)) return 'is-cash';
            return 'is-other';
        }
        var title = document.createElement('p');
        title.className = 'ame-desk-method-title';
        title.textContent = titleText;
        list.appendChild(title);
        function paintPicked() {
            var value = methodPick.value;
            Array.prototype.forEach.call(list.querySelectorAll('.ame-desk-method-item'), function (btn) {
                var on = btn.dataset.id === value;
                btn.classList.toggle('is-picked', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        function rebuild() {
            syncDeskAccountLists();
            Array.prototype.forEach.call(list.querySelectorAll('.ame-desk-method-item'), function (btn) {
                btn.remove();
            });
            Array.prototype.forEach.call(methodPick.options, function (opt) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'ame-desk-method-item ' + methodTone(opt.textContent);
                item.dataset.id = opt.value;
                item.setAttribute('role', 'option');
                item.appendChild(document.createTextNode((opt.textContent || '').trim()));
                if (isCashName(opt.textContent)) {
                    item.classList.add('is-cash-fixed');
                    var tick = document.createElement('b');
                    tick.className = 'ame-cash-tick';
                    tick.textContent = '✓';
                    item.appendChild(tick);
                }
                item.addEventListener('click', function () {
                    methodPick.value = opt.value;
                    methodPick.dispatchEvent(new Event('change', { bubbles: true }));
                    paintPicked();
                    setSheet(false);
                });
                list.appendChild(item);
            });
            paintPicked();
        }
        function setSheet(open) {
            if (open) {
                closeOtherDeskSheets(methodPick);
                rebuild();
                document.body.appendChild(wrap);
            } else if (methodPick.parentNode) {
                methodPick.insertAdjacentElement('afterend', wrap);
            }
            list.hidden = !open;
            wrap.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        methodPick.ameCloseDeskSheet = function () {
            setSheet(false);
        };
        var openingGesture = false;
        var openingTimer = 0;
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setSheet(list.hidden);
        });
        methodPick.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openingGesture = true;
            setSheet(true);
            window.clearTimeout(openingTimer);
            openingTimer = window.setTimeout(function () {
                openingGesture = false;
            }, 450);
        });
        wrap.addEventListener('click', function (event) {
            if (openingGesture) {
                return;
            }
            if (event.target === wrap) {
                setSheet(false);
            }
        });
        document.addEventListener('click', function (event) {
            if (openingGesture) {
                var picked = event.target && event.target.closest ? event.target.closest('.ame-desk-method-item') : null;
                if (picked && list.contains(picked)) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            if (event.target === wrap) {
                setSheet(false);
                return;
            }
            var label = methodPick.closest('label');
            if (wrap.contains(event.target) || event.target === methodPick || (label && label.contains(event.target))) {
                return;
            }
            setSheet(false);
        }, true);
        wrap.appendChild(trigger);
        wrap.appendChild(list);
        methodPick.insertAdjacentElement('afterend', wrap);
        methodPick.addEventListener('change', paintPicked);
        rebuild();
    }

    var englishSheet = document.documentElement.lang === 'en';
    bindDeskMethodSheet(
        el('deskPayMethod'),
        englishSheet ? 'Receive into this account' : 'টাকা জমবে যে অ্যাকাউন্টে',
        englishSheet ? 'Choose the account that receives payment' : 'যে অ্যাকাউন্টে টাকা জমবে সেটি বাছুন'
    );
    bindDeskMethodSheet(
        el('deskPayMethod2'),
        englishSheet ? 'Second account' : 'দ্বিতীয় অ্যাকাউন্ট',
        englishSheet ? 'Choose the second account' : 'দ্বিতীয় অ্যাকাউন্ট বাছুন'
    );

    function cellValue(cell) {
        if (!cell) {
            return '';
        }
        var field = cell.querySelector('input, select, textarea');
        if (field) {
            return (field.value || '').trim();
        }
        return (cell.textContent || '').trim();
    }

    function saleShareText() {
        var english = document.documentElement.lang === 'en';
        var lines = [];
        var shop = document.querySelector('.ame-sale-brand strong');
        if (shop && shop.textContent.trim()) {
            lines.push(shop.textContent.trim());
        }
        var vehicle = el('posVehicleNo');
        if (vehicle && vehicle.value.trim()) {
            lines.push((english ? 'Vehicle no: ' : 'গাড়ির নম্বর: ') + vehicle.value.trim());
        }
        var customer = el('posCustomerInfoField');
        if (customer && customer.value.trim()) {
            lines.push((english ? 'Customer: ' : 'ক্রেতা: ') + customer.value.trim());
        }
        document.querySelectorAll('#cart-body tr').forEach(function (row) {
            if (row.classList.contains('ame-pos-empty-row') || row.dataset.blank === '1') {
                return;
            }
            var cells = row.querySelectorAll('td');
            var name = cellValue(cells[3]);
            var qty = cellValue(cells[4]);
            var total = cellValue(cells[7]);
            if (!name) {
                return;
            }
            lines.push(name + ' × ' + qty + ' = ' + total);
        });
        var grand = el('deskGrand');
        if (grand && grand.textContent.trim()) {
            lines.push((english ? 'Total: ' : 'মোট: ') + grand.textContent.trim());
        }
        return lines.join('\n');
    }

    function escapeMemo(value) {
        return String(value || '').replace(/[&<>"]/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch];
        });
    }

    function buildLiveMemo() {
        var box = el('ameLiveMemo');
        if (!box) {
            return;
        }
        var english = document.documentElement.lang === 'en';
        var rows = '';
        document.querySelectorAll('#cart-body tr').forEach(function (row) {
            if (row.dataset.blank === '1' || row.classList.contains('ame-pos-blank-row') || row.classList.contains('ame-pos-empty-row')) {
                return;
            }
            var cells = row.querySelectorAll('td');
            var name = cellValue(cells[3]);
            var qty = cellValue(cells[4]);
            var total = cellValue(cells[7]);
            if (!name) {
                return;
            }
            rows += '<tr><td>' + escapeMemo(name) + '</td><td class="c">' + escapeMemo(qty) + '</td><td class="r">' + escapeMemo(total) + '</td></tr>';
        });
        var shop = window.AME_SHOP_NAME || '';
        var customer = (el('posCustomerInfoField') && el('posCustomerInfoField').value.trim()) || (english ? 'Walk-in' : 'সাধারণ ক্রেতা');
        var when = (el('ameSaleDate') && el('ameSaleDate').value) || '';
        var grand = (el('deskGrand') && el('deskGrand').textContent.trim()) || '';
        var paid = (el('deskPaid') && el('deskPaid').value) || '0';
        box.innerHTML = '<h1>' + escapeMemo(shop) + '</h1>'
            + '<p class="ame-live-kicker">' + (english ? 'Sales invoice' : 'বিক্রয় ইনভয়েস') + '</p>'
            + '<p>' + escapeMemo(when) + '</p>'
            + '<p>' + (english ? 'Customer: ' : 'ক্রেতা: ') + escapeMemo(customer) + '</p>'
            + '<table><thead><tr><th>' + (english ? 'Item' : 'পণ্য') + '</th><th>' + (english ? 'Qty' : 'পরিমাণ') + '</th><th>' + (english ? 'Amount' : 'টাকা') + '</th></tr></thead><tbody>'
            + rows + '</tbody></table>'
            + '<p class="ame-live-grand">' + (english ? 'Total ' : 'মোট ') + escapeMemo(grand) + '</p>'
            + '<p>' + (english ? 'Paid ' : 'পরিশোধ ') + escapeMemo(paid) + '</p>';
    }

    var printBtn = el('ameDeskPrint');
    if (printBtn) {
        printBtn.checked = false;
        var printBusy = false;
        function clearPrintMark() {
            printBusy = false;
            printBtn.checked = false;
            document.body.classList.remove('ame-printing');
        }
        function runDeskPrint() {
            if (printBusy) {
                return;
            }
            printBusy = true;
            buildLiveMemo();
            document.body.classList.add('ame-printing');
            window.addEventListener('afterprint', clearPrintMark, { once: true });
            window.print();
            window.setTimeout(clearPrintMark, 60000);
        }
        printBtn.addEventListener('change', function () {
            if (!printBtn.checked) {
                return;
            }
            runDeskPrint();
        });
        window.ameDeskRunPrint = runDeskPrint;
    }
    var shareBtn = el('ameDeskShare');
    function runDeskShare() {
        buildLiveMemo();
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture('share', 'aponshop-sale', 'ameLiveMemo');
        }
    }
    if (shareBtn) {
        shareBtn.addEventListener('click', runDeskShare);
    }
    var barPrint = el('ameDeskBarPrint');
    if (barPrint) {
        barPrint.addEventListener('click', function () {
            if (typeof window.ameDeskRunPrint === 'function') {
                window.ameDeskRunPrint();
                return;
            }
            buildLiveMemo();
            window.print();
        });
    }
    var barShare = el('ameDeskBarShare');
    if (barShare) {
        barShare.addEventListener('click', runDeskShare);
    }

    var dateInput = el('ameNextPayDate');
    if (dateInput) {
        var syncDate = function () {
            dateInput.classList.toggle('is-filled', dateInput.value !== '');
        };
        dateInput.addEventListener('change', syncDate);
        dateInput.addEventListener('input', syncDate);
        syncDate();
    }
    ['ameExtraReturn', 'ameExtraApply'].forEach(function (id) {
        var choice = el(id);
        if (!choice) {
            return;
        }
        choice.addEventListener('change', function () {
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
    });
    document.querySelectorAll('input[name="cus_type"], input[name="cus_type_toolbar"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (typeof window.calculateAll === 'function') {
                window.calculateAll();
            }
        });
    });

    var saleChromeHeader = -1;
    var saleChromePad = -1;

    function pinSaleChrome() {
        var header = document.querySelector('.master-header-fixed');
        var dock = document.getElementById('ameRunDock');
        var main = document.getElementById('main-content');
        var headerH = 0;
        if (header) {
            var headerStyle = window.getComputedStyle(header);
            if (headerStyle.display !== 'none' && headerStyle.visibility !== 'hidden') {
                headerH = Math.ceil(header.getBoundingClientRect().height) || header.offsetHeight || 0;
            }
        }
        var ticker = document.getElementById('ameTickerBar');
        if (ticker && header && ticker.parentNode !== header) {
            header.insertBefore(ticker, header.firstChild);
        }
        if (header && (ticker || dock)) {
            header.style.setProperty('flex-wrap', 'wrap', 'important');
            header.style.setProperty('height', 'auto', 'important');
        }
        if (ticker) {
            ticker.style.setProperty('position', 'relative', 'important');
            ticker.style.setProperty('top', 'auto', 'important');
            ticker.style.setProperty('order', '-1', 'important');
            ticker.style.setProperty('width', '100%', 'important');
            ticker.style.setProperty('flex', '1 0 100%', 'important');
        }
        var salePage = document.body && document.body.classList.contains('ame-pos-terminal-page');
        if (dock && (ticker || salePage)) {
            dock.style.setProperty('display', 'none', 'important');
        } else if (dock && header && !salePage) {
            if (dock.parentNode !== header) {
                header.insertBefore(dock, header.firstChild);
            }
            dock.style.setProperty('display', 'block', 'important');
            dock.style.setProperty('position', 'relative', 'important');
            dock.style.setProperty('top', 'auto', 'important');
            dock.style.setProperty('order', '-1', 'important');
            dock.style.setProperty('width', '100%', 'important');
        }
        headerH = header ? Math.ceil(header.getBoundingClientRect().bottom) : 0;
        var currentPad = main ? parseFloat(main.style.paddingTop) : NaN;
        if (
            headerH === saleChromeHeader
            && Number.isFinite(currentPad)
            && Math.abs(currentPad - saleChromePad) < 1
        ) {
            return;
        }
        document.documentElement.style.setProperty('--ame-header-h', headerH + 'px');
        var extra = 0;
        document.querySelectorAll('.ame-tbar-cd').forEach(function (bar) {
            var barStyle = window.getComputedStyle(bar);
            if (barStyle.display === 'none' || barStyle.visibility === 'hidden') {
                return;
            }
            var barH = Math.ceil(bar.getBoundingClientRect().height) || 0;
            if (!barH) {
                return;
            }
            bar.style.setProperty('top', (headerH + extra) + 'px', 'important');
            extra += barH;
        });
        var target = headerH + extra;
        document.documentElement.style.setProperty('--ame-chrome-h', target + 'px');
        if (main && (!Number.isFinite(currentPad) || Math.abs(currentPad - target) > 1)) {
            main.style.setProperty('padding-top', target + 'px', 'important');
        }
        closeSaleGap(target);
        if (main) {
            var settled = parseFloat(main.style.paddingTop);
            saleChromePad = Number.isFinite(settled) ? settled : target;
            saleChromeHeader = headerH;
        }
    }

    function closeSaleGap(fallback) {
        var main = document.getElementById('main-content');
        var bar = document.querySelector('.ame-desk-bar');
        if (!main || !bar) {
            return;
        }
        var barStyle = window.getComputedStyle(bar);
        if (barStyle.display === 'none' || barStyle.visibility === 'hidden') {
            return;
        }
        var edge = 0;
        var header = document.querySelector('.master-header-fixed');
        if (header) {
            edge = Math.max(edge, header.getBoundingClientRect().bottom);
        }
        var dock = document.getElementById('ameRunDock');
        if (dock) {
            var dockStyle = window.getComputedStyle(dock);
            if (dockStyle.display !== 'none' && dockStyle.visibility !== 'hidden') {
                edge = Math.max(edge, dock.getBoundingClientRect().bottom);
            }
        }
        document.querySelectorAll('.ame-tbar-cd').forEach(function (cd) {
            var cdStyle = window.getComputedStyle(cd);
            if (cdStyle.display === 'none' || cdStyle.visibility === 'hidden') {
                return;
            }
            edge = Math.max(edge, cd.getBoundingClientRect().bottom);
        });
        var gap = bar.getBoundingClientRect().top - edge;
        if (Math.abs(gap) < 1) {
            return;
        }
        var current = parseFloat(main.style.paddingTop);
        if (!Number.isFinite(current)) {
            current = fallback || 0;
        }
        main.style.setProperty('padding-top', Math.max(0, Math.round(current - gap)) + 'px', 'important');
    }

    pinSaleChrome();
    window.addEventListener('resize', pinSaleChrome);
    [40, 200, 700, 1500].forEach(function (wait) {
        window.setTimeout(pinSaleChrome, wait);
    });

    paintClock();
    setInterval(paintClock, 1000);
    if (typeof window.calculateAll === 'function') {
        window.calculateAll();
    }
})();
