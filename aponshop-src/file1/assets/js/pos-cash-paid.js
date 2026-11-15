(function () {
    var button = document.getElementById('amePosCashPaid');
    if (!button || button.getAttribute('data-cash-paid') === '1') {
        return;
    }
    button.setAttribute('data-cash-paid', '1');

    var english = document.documentElement.lang === 'en';

    function money(value) {
        var parsed = parseFloat(String(value == null ? '' : value).replace(/[^\d.-]/g, ''));
        return isFinite(parsed) ? parsed : 0;
    }

    function cashOption(select) {
        var options = select.options;
        var fallback = -1;
        var i;
        for (i = 0; i < options.length; i++) {
            if (!options[i].value) {
                continue;
            }
            var label = (options[i].textContent || '').toLowerCase();
            if (label.indexOf('ক্যাশ') >= 0 || label.indexOf('cash') >= 0) {
                return options[i];
            }
            if (fallback < 0 && label.indexOf('নগদ') >= 0) {
                fallback = i;
            }
        }
        return fallback >= 0 ? options[fallback] : null;
    }

    function payableNow() {
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }
        var node = document.getElementById('net-payable');
        var grand = document.getElementById('deskGrand');
        var saleBill = node && node.dataset.saleBill != null && node.dataset.saleBill !== ''
            ? money(node.dataset.saleBill)
            : money(node ? node.textContent : (grand ? grand.textContent : ''));
        var exchange = typeof window.ameExchangeTotal === 'function'
            ? Math.max(0, money(window.ameExchangeTotal()))
            : 0;
        return Math.max(0, Math.round((saleBill - exchange) * 100) / 100);
    }

    function poke(field) {
        if (!field) {
            return;
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function mirrorCash(method, paid) {
        var box = document.getElementById('posPaymentRows');
        if (!box) {
            return;
        }
        var rows = box.querySelectorAll('.pos-payment-row');
        Array.prototype.forEach.call(rows, function (row, index) {
            var account = row.querySelector('.pos-payment-account');
            var amount = row.querySelector('.pos-payment-amount');
            if (index === 0) {
                if (account && method) {
                    account.value = method.value;
                }
                if (amount && paid) {
                    amount.value = paid.value || '0';
                }
                return;
            }
            if (amount) {
                amount.value = '0';
            }
        });
    }

    function forceComplete() {
        var mode = document.getElementById('amePosSaleMode');
        if (mode) {
            mode.value = 'complete';
        }
        var draft = document.getElementById('amePosRecentDraft');
        var complete = document.getElementById('amePosRecentComplete');
        if (draft) {
            draft.classList.remove('is-active');
        }
        if (complete) {
            complete.classList.add('is-active');
        }
        var current = document.getElementById('amePosRecentCurrent');
        if (current && complete) {
            current.textContent = (complete.textContent || '').replace(/\s+/g, ' ').trim();
        }
        var memo = document.getElementById('amePosCompleteBtn');
        if (memo) {
            memo.classList.remove('is-draft');
        }
        if (typeof window.amePosSyncMemoButton === 'function') {
            window.amePosSyncMemoButton(false);
        }
    }

    function clearShare() {
        window.amePosCashShare = false;
        try {
            sessionStorage.removeItem('amePosCashShare');
        } catch (err) {}
    }

    function armShare() {
        window.amePosCashShare = true;
        try {
            sessionStorage.setItem('amePosCashShare', '1');
        } catch (err) {}
        if (window.amePosShareReplace) {
            return;
        }
        var native = window.location.replace;
        if (typeof native !== 'function') {
            return;
        }
        try {
            window.location.replace = function (url) {
                var next = String(url || '');
                if (window.amePosCashShare && next.indexOf('invoice.php') !== -1 && next.indexOf('share=1') === -1) {
                    next += (next.indexOf('?') === -1 ? '?' : '&') + 'share=1';
                    clearShare();
                }
                return native.call(window.location, next);
            };
            window.amePosShareReplace = true;
        } catch (err) {}
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        if (target.closest('#amePosCashPaid')) {
            return;
        }
        if (target.closest('#amePosDraftComplete, #amePosCompleteBtn')) {
            clearShare();
        }
    }, true);

    function isRegisteredCustomer() {
        var nodes = document.querySelectorAll('input[name="cus_type"]:checked, input[name="cus_type_toolbar"]:checked');
        var i;
        for (i = 0; i < nodes.length; i++) {
            if (nodes[i].value === 'Registered') {
                return true;
            }
        }
        return false;
    }

    function customerName() {
        var field = document.getElementById('posCustomerInfoField');
        return field && field.value ? field.value.replace(/\s+/g, ' ').trim() : '';
    }

    function closeCashAsk() {
        var open = document.getElementById('ameCashAsk');
        if (open) {
            open.remove();
        }
    }

    function openCashAsk(onYes) {
        closeCashAsk();
        var root = document.createElement('div');
        root.id = 'ameCashAsk';
        root.className = 'ame-cash-ask';
        var card = document.createElement('div');
        card.className = 'ame-cash-ask-card';
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');
        var head = document.createElement('div');
        head.className = 'ame-cash-ask-head';
        var mark = document.createElement('span');
        mark.className = 'ame-cash-ask-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = '৳';
        var kicker = document.createElement('p');
        kicker.className = 'ame-cash-ask-kicker';
        kicker.textContent = english ? 'Message' : 'বার্তা';
        head.appendChild(mark);
        head.appendChild(kicker);
        var body = document.createElement('div');
        body.className = 'ame-cash-ask-body';
        var bubble = document.createElement('p');
        bubble.className = 'ame-cash-ask-bubble';
        bubble.textContent = english
            ? 'Has this customer\'s full amount been paid?'
            : 'এই কাস্টমারের সম্পূর্ণ টাকা কি পরিশোধ হয়েছে?';
        body.appendChild(bubble);
        var name = customerName();
        if (name) {
            var who = document.createElement('p');
            who.className = 'ame-cash-ask-who';
            who.textContent = name;
            body.appendChild(who);
        }
        var actions = document.createElement('div');
        actions.className = 'ame-cash-ask-actions';
        var yes = document.createElement('button');
        yes.type = 'button';
        yes.className = 'ame-cash-ask-yes';
        yes.textContent = english ? 'Yes, it is paid' : 'হ্যাঁ, পরিশোধ হয়েছে';
        var no = document.createElement('button');
        no.type = 'button';
        no.className = 'ame-cash-ask-no';
        no.textContent = english ? 'No' : 'না';
        yes.addEventListener('click', function () {
            closeCashAsk();
            onYes();
        });
        no.addEventListener('click', closeCashAsk);
        actions.appendChild(yes);
        actions.appendChild(no);
        body.appendChild(actions);
        card.appendChild(head);
        card.appendChild(body);
        root.appendChild(card);
        root.addEventListener('click', function (event) {
            if (event.target === root) {
                closeCashAsk();
            }
        });
        document.addEventListener('keydown', function onKey(event) {
            if (!document.getElementById('ameCashAsk')) {
                document.removeEventListener('keydown', onKey);
                return;
            }
            if (event.key === 'Escape') {
                closeCashAsk();
                document.removeEventListener('keydown', onKey);
            }
        });
        document.body.appendChild(root);
        yes.focus();
    }

    function finishCash() {
        var amount = payableNow();
        var method = document.getElementById('deskPayMethod');
        var paid = document.getElementById('deskPaid');
        var paid2 = document.getElementById('deskPaid2');
        if (amount > 0.009) {
            var cash = method ? cashOption(method) : null;
            if (!cash) {
                window.alert(english
                    ? 'No cash account is open. Add a cash account, then try again.'
                    : 'ক্যাশ অ্যাকাউন্ট পাওয়া যায়নি। ক্যাশ অ্যাকাউন্ট খুলে আবার চাপুন।');
                return;
            }
            method.value = cash.value;
            if (paid) {
                paid.value = amount.toFixed(2);
            }
            if (paid2) {
                paid2.value = '0';
            }
            mirrorCash(method, paid);
            poke(method);
            poke(paid);
            poke(paid2);
        }
        forceComplete();
        clearShare();
        if (typeof window.submitFinalSale === 'function') {
            window.submitFinalSale();
        }
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (isRegisteredCustomer()) {
            openCashAsk(finishCash);
            return;
        }
        finishCash();
    });
})();
