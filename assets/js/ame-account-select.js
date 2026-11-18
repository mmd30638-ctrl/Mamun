(function () {
    if (window.ameBindAccountSelect) {
        return;
    }

    function tone(text, type) {
        var label = ((text || '') + ' ' + (type || '')).toLowerCase();
        if (label.indexOf('বিকাশ') >= 0 || label.indexOf('bkash') >= 0) return 'is-bkash';
        if (label.indexOf('রকেট') >= 0 || label.indexOf('rocket') >= 0) return 'is-rocket';
        if (label.indexOf('ব্যাংক') >= 0 || label.indexOf('bank') >= 0) return 'is-bank';
        if (label.indexOf('নগদ-মোবাইল') >= 0 || label.indexOf('nagad') >= 0 || label.indexOf('মোবাইল') >= 0 || label.indexOf('mobile') >= 0) return 'is-nagad';
        if (label.indexOf('বাসা') >= 0 || label.indexOf('বাকি') >= 0 || label.indexOf('due') >= 0 || label.indexOf('credit') >= 0) return 'is-due';
        if (label.indexOf('ক্যাশ') >= 0 || label.indexOf('cash') >= 0 || label.indexOf('নগদ') >= 0) return 'is-cash';
        return 'is-other';
    }

    function sheetFor(select) {
        var existing = select.ameAccountSheet;
        if (existing) {
            return existing;
        }
        var english = document.documentElement.lang === 'en';
        var wrap = document.createElement('div');
        wrap.className = 'ame-account-sheet';
        wrap.hidden = true;
        var card = document.createElement('div');
        card.className = 'ame-account-sheet-card';
        card.setAttribute('role', 'listbox');
        var title = document.createElement('p');
        title.className = 'ame-account-sheet-title';
        title.textContent = select.getAttribute('data-ame-direction') === 'pay'
            ? (english ? 'Pay from this account' : 'যে অ্যাকাউন্ট থেকে টাকা যাবে')
            : (english ? 'Receive into this account' : 'টাকা জমবে যে অ্যাকাউন্টে');
        card.appendChild(title);
        wrap.appendChild(card);
        wrap.addEventListener('click', function (event) {
            if (event.target === wrap) {
                closeSheet(select);
            }
        });
        select.ameAccountSheet = wrap;
        return wrap;
    }

    function fillAccountSheet(select, wrap) {
        var card = wrap.querySelector('.ame-account-sheet-card');
        if (!card) {
            return;
        }
        Array.prototype.forEach.call(card.querySelectorAll('.ame-account-sheet-item'), function (btn) {
            btn.remove();
        });
        Array.prototype.forEach.call(select.options, function (opt) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'ame-account-sheet-item ' + tone(opt.textContent, opt.getAttribute('data-type'));
            item.dataset.id = opt.value;
            item.setAttribute('role', 'option');
            item.textContent = (opt.textContent || '').trim();
            item.addEventListener('pointerdown', function (event) {
                item.dataset.ameDown = '1';
                event.stopPropagation();
            });
            item.addEventListener('pointerup', function (event) {
                if (item.dataset.ameDown !== '1') {
                    return;
                }
                item.dataset.ameDown = '0';
                item.dataset.ameChose = '1';
                event.preventDefault();
                event.stopPropagation();
                commitAccount(select, opt.value);
            });
            item.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (item.dataset.ameChose === '1') {
                    item.dataset.ameChose = '0';
                    return;
                }
                if (event.detail === 0) {
                    commitAccount(select, opt.value);
                }
            });
            card.appendChild(item);
        });
    }

    function paint(select) {
        var wrap = select.ameAccountSheet;
        if (!wrap) {
            return;
        }
        Array.prototype.forEach.call(wrap.querySelectorAll('.ame-account-sheet-item'), function (btn) {
            var on = btn.dataset.id === select.value;
            btn.classList.toggle('is-picked', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function openSheet(select) {
        if (!select || select.getAttribute('data-ame-sheet') === 'desk') {
            return;
        }
        var wrap = sheetFor(select);
        fillAccountSheet(select, wrap);
        var title = wrap.querySelector('.ame-account-sheet-title');
        if (title) {
            var english = document.documentElement.lang === 'en';
            title.textContent = select.getAttribute('data-ame-direction') === 'pay'
                ? (english ? 'Pay from this account' : 'যে অ্যাকাউন্ট থেকে টাকা যাবে')
                : (english ? 'Receive into this account' : 'টাকা জমবে যে অ্যাকাউন্টে');
        }
        paint(select);
        document.body.appendChild(wrap);
        wrap.hidden = false;
        select.dataset.ameOpen = '1';
    }

    function fieldVisible(select) {
        var node = select;
        while (node && node.nodeType === 1) {
            if (node.hidden) {
                return false;
            }
            var style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            node = node.parentElement;
        }
        return true;
    }

    function commitAccount(select, value) {
        select.setAttribute('data-ame-user', '1');
        select.setAttribute('data-ame-locked', String(value));
        select.dataset.ameRestoring = '1';
        select.value = String(value);
        select.dataset.ameRestoring = '0';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        closeSheet(select);
    }

    function closeSheet(select) {
        var wrap = select && select.ameAccountSheet;
        if (!wrap) {
            return;
        }
        wrap.hidden = true;
        if (select) {
            select.dataset.ameOpen = '0';
        }
    }

    function isSharedSelect(node) {
        return !!(node && node.matches && node.matches('select.ame-account-select[data-ame-account-select="1"]') && node.getAttribute('data-ame-sheet') !== 'desk');
    }

    function eventNode(event) {
        var target = event && event.target;
        if (!target) {
            return null;
        }
        if (target.nodeType === 1) {
            return target;
        }
        return target.parentElement || null;
    }

    function pointOf(event) {
        var x = event.clientX;
        var y = event.clientY;
        if (typeof x !== 'number' || typeof y !== 'number' || !isFinite(x) || !isFinite(y)) {
            return null;
        }
        return { x: x, y: y };
    }

    function pointIn(point, rect, pad) {
        pad = pad || 0;
        return point.x >= rect.left - pad && point.x <= rect.right + pad && point.y >= rect.top - pad && point.y <= rect.bottom + pad;
    }

    function accountSelectFromEvent(event) {
        var target = eventNode(event);
        if (!target || !target.closest || target.closest('.ame-account-sheet')) {
            return null;
        }
        var select = isSharedSelect(target)
            ? target
            : (target.closest ? target.closest('select.ame-account-select[data-ame-account-select="1"]') : null);
        if (!isSharedSelect(select)) {
            return null;
        }
        var point = pointOf(event);
        if (!point) {
            return select;
        }
        if (pointIn(point, select.getBoundingClientRect(), 0)) {
            return select;
        }
        return null;
    }

    var openingSelect = null;
    var openingTimer = 0;
    var openingUntil = 0;
    var openingValue = '';

    function optionExists(select, value) {
        var found = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value === value) {
                found = true;
            }
        });
        return found;
    }

    function chosenValue(select) {
        if (select.getAttribute('data-ame-user') === '1') {
            var locked = select.getAttribute('data-ame-locked');
            return locked == null ? null : String(locked);
        }
        if (select.hasAttribute('data-ame-initial')) {
            return String(select.getAttribute('data-ame-initial'));
        }
        if (select === openingSelect && Date.now() < openingUntil) {
            return openingValue;
        }
        var prior = select.getAttribute('data-ame-locked');
        if (prior != null && prior !== '') {
            return String(prior);
        }
        return null;
    }

    function enforce(select) {
        if (!select || select.dataset.ameRestoring === '1') {
            return;
        }
        var want = chosenValue(select);
        if (want == null || select.value === want || !optionExists(select, want)) {
            return;
        }
        select.dataset.ameRestoring = '1';
        select.value = want;
        select.dataset.ameRestoring = '0';
    }

    function arm(select) {
        if (!isSharedSelect(select) || select.dataset.ameArmed === '1') {
            return;
        }
        select.dataset.ameArmed = '1';
        if (!select.getAttribute('autocomplete')) {
            select.setAttribute('autocomplete', 'off');
        }
        enforce(select);
        if (window.MutationObserver) {
            new MutationObserver(function () {
                enforce(select);
            }).observe(select, { subtree: true, attributes: true, attributeFilter: ['selected'] });
        }
    }

    function scan(root) {
        if (!root) {
            return;
        }
        if (root.nodeType === 9) {
            scan(root.documentElement);
            return;
        }
        if (root.nodeType !== 1) {
            return;
        }
        if (isSharedSelect(root)) {
            arm(root);
        }
        if (!root.querySelectorAll) {
            return;
        }
        Array.prototype.forEach.call(root.querySelectorAll('select.ame-account-select[data-ame-account-select="1"]'), function (select) {
            if (isSharedSelect(select)) {
                arm(select);
            }
        });
    }

    document.addEventListener('pointerdown', function (event) {
        var select = accountSelectFromEvent(event);
        if (!select) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openingSelect = select;
        openingValue = select.value;
        openingUntil = Date.now() + 450;
        openSheet(select);
        window.clearTimeout(openingTimer);
        openingTimer = window.setTimeout(function () {
            if (openingSelect === select) {
                openingSelect = null;
            }
        }, 450);
    }, true);

    document.addEventListener('click', function (event) {
        if (!openingSelect) {
            return;
        }
        var node = eventNode(event);
        var item = node && node.closest ? node.closest('.ame-account-sheet-item') : null;
        if (item && openingSelect.ameAccountSheet && openingSelect.ameAccountSheet.contains(item)) {
            return;
        }
        var sheet = openingSelect.ameAccountSheet;
        var target = eventNode(event);
        var onSheet = !!(sheet && target && sheet.contains(target));
        var onSelect = target === openingSelect || !!(target && target.closest && target.closest('label') && target.closest('label').contains(openingSelect) && accountSelectFromEvent(event));
        if (onSheet || onSelect) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    function keepChosenAccount(event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        if (target.dataset && target.dataset.ameRestoring === '1') {
            return;
        }
        var form = target.closest('form');
        if (form) {
            Array.prototype.forEach.call(form.querySelectorAll('select.ame-account-select[data-ame-account-select="1"]'), function (select) {
                if (isSharedSelect(select)) {
                    enforce(select);
                }
            });
            return;
        }
        if (isSharedSelect(target)) {
            enforce(target);
        }
    }
    document.addEventListener('change', keepChosenAccount, true);
    document.addEventListener('input', keepChosenAccount, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.querySelectorAll) {
            return;
        }
        var hidden = [];
        Array.prototype.forEach.call(form.querySelectorAll('select.ame-account-select'), function (select) {
            if (!fieldVisible(select)) {
                select.disabled = true;
                hidden.push(select);
            }
        });
        var missing = null;
        Array.prototype.forEach.call(form.querySelectorAll('select.ame-account-select[required]'), function (select) {
            if (select.disabled || !fieldVisible(select)) {
                return;
            }
            if (!missing && !String(select.value || '').trim()) {
                missing = select;
            }
        });
        if (!missing) {
            Array.prototype.forEach.call(form.querySelectorAll('select[data-ame-post-name]'), function (select) {
                if (select.disabled || !fieldVisible(select)) {
                    return;
                }
                var postName = select.getAttribute('data-ame-post-name') || '';
                if (!postName || form.querySelector('input[name="' + postName + '"]')) {
                    return;
                }
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = postName;
                hidden.value = select.value;
                form.appendChild(hidden);
                select.removeAttribute('name');
            });
            return;
        }
        hidden.forEach(function (select) {
            select.disabled = false;
        });
        event.preventDefault();
        missing.focus();
    }, true);

    scan(document);
    if (window.MutationObserver && document.documentElement) {
        new MutationObserver(function (records) {
            records.forEach(function (rec) {
                Array.prototype.forEach.call(rec.addedNodes || [], function (node) {
                    scan(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    window.ameBindAccountSelect = openSheet;
})();
