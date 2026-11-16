(function () {
    var pay = document.getElementById('amePosPay');
    if (!pay || pay.getAttribute('data-pay-dock') === '1') {
        return;
    }
    pay.setAttribute('data-pay-dock', '1');

    var english = document.documentElement.lang === 'en';
    var dockState = null;
    var tabPress = false;
    var keyboardUntil = 0;
    var tab = document.createElement('button');
    tab.type = 'button';
    tab.id = 'amePayDockTab';
    tab.className = 'ame-pay-dock-tab';
    tab.setAttribute('aria-controls', 'amePosPay');

    function word(open) {
        if (open) {
            return english ? 'Hide' : 'বন্ধ';
        }
        return english ? 'Pay' : 'পেমেন্ট';
    }

    function panelWidth() {
        return Math.min(420, Math.max(260, window.innerWidth - 52));
    }

    function panelTop() {
        var header = document.querySelector('.master-header-fixed');
        var top = 8;
        if (header) {
            var box = header.getBoundingClientRect();
            if (box.bottom > 0) {
                top = Math.round(box.bottom + 4);
            }
        }
        return top;
    }

    function unlock(node) {
        if (!node) {
            return;
        }
        setStyle(node, 'overflow', 'visible');
        setStyle(node, 'overflow-x', 'visible');
        setStyle(node, 'overflow-y', 'visible');
        setStyle(node, 'contain', 'none');
    }

    function setStyle(node, prop, value) {
        node.style.setProperty(prop, value, 'important');
    }

    function paint(open) {
        var changed = dockState !== open;
        dockState = open;
        var width = panelWidth();
        pay.classList.toggle('is-open', open);
        document.body.classList.toggle('ame-pay-open', open);
        setStyle(pay, 'position', 'fixed');
        setStyle(pay, 'top', panelTop() + 'px');
        setStyle(pay, 'right', '0');
        setStyle(pay, 'bottom', '0');
        setStyle(pay, 'left', 'auto');
        setStyle(pay, 'width', width + 'px');
        setStyle(pay, 'max-width', width + 'px');
        setStyle(pay, 'height', 'auto');
        setStyle(pay, 'margin', '0');
        setStyle(pay, 'z-index', '900000');
        setStyle(pay, 'overflow', 'auto');
        setStyle(pay, 'display', 'block');
        setStyle(pay, 'visibility', 'visible');
        setStyle(pay, 'opacity', '1');
        setStyle(pay, 'background', '#ffffff');
        setStyle(pay, 'border-radius', '14px 0 0 14px');
        setStyle(pay, 'box-shadow', open ? '-16px 0 36px rgba(15, 35, 80, 0.18)' : 'none');
        if (changed) {
            setStyle(pay, 'transition', 'transform 0.22s ease');
            setStyle(pay, 'transform', open ? 'translateX(0)' : 'translateX(110%)');
            setStyle(pay, 'pointer-events', open ? 'auto' : 'none');
            if (open) {
                window.setTimeout(function () {
                    if (pay.classList.contains('is-open')) {
                        setStyle(pay, 'transition', 'none');
                        setStyle(pay, 'transform', 'none');
                    }
                }, 240);
            }
        }
        setStyle(tab, 'position', 'fixed');
        setStyle(tab, 'z-index', '900001');
        setStyle(tab, 'top', '42%');
        setStyle(tab, 'right', open ? (width + 'px') : '0');
        setStyle(tab, 'left', 'auto');
        setStyle(tab, 'overflow', 'hidden');
        setStyle(tab, 'transform', 'none');
        if (changed) {
            var extras = document.querySelectorAll('.ame-pay-dock-tab');
            for (var i = 0; i < extras.length; i++) {
                if (extras[i] !== tab) {
                    extras[i].remove();
                }
            }
            tab.setAttribute('aria-expanded', open ? 'true' : 'false');
            tab.setAttribute('aria-label', open
                ? (english ? 'Hide the payment panel' : 'পেমেন্ট প্যানেল লুকান')
                : (english ? 'Show the payment panel' : 'পেমেন্ট প্যানেল খুলুন'));
            var path = open ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6';
            tab.innerHTML = '<svg class="ame-pay-dock-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="'
                + path
                + '" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path></svg><span class="ame-pay-dock-word">'
                + word(open)
                + '</span>';
        }
    }

    function armTab(event) {
        var node = event.target;
        if (node && node.nodeType !== 1) {
            node = node.parentElement;
        }
        tabPress = !!(node && tab.contains(node));
    }

    tab.addEventListener('pointerdown', armTab);
    tab.addEventListener('touchstart', armTab);
    document.addEventListener('pointerup', function (event) {
        var node = event.target;
        if (node && node.nodeType !== 1) {
            node = node.parentElement;
        }
        if (!node || !tab.contains(node)) {
            tabPress = false;
        }
    }, true);
    document.addEventListener('pointercancel', function () {
        tabPress = false;
    }, true);
    tab.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        event.preventDefault();
        paint(!pay.classList.contains('is-open'));
    });
    tab.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!tabPress) {
            return;
        }
        tabPress = false;
        paint(!pay.classList.contains('is-open'));
    });

    var grid = pay.closest('.ame-pos-grid');
    document.body.appendChild(tab);
    unlock(document.getElementById('main-content'));
    unlock(pay.closest('.ame-pos-page'));
    unlock(pay.closest('.ame-pos-shell'));
    unlock(grid);
    if (grid) {
        grid.style.setProperty('grid-template-columns', 'minmax(0, 1fr)', 'important');
    }
    function editableField(node) {
        if (!node) {
            return null;
        }
        if (node.nodeType !== 1) {
            node = node.parentElement;
        }
        if (!node || !node.closest) {
            return null;
        }
        if (node.closest('button, a, select, option')) {
            return null;
        }
        var field = node.closest('input, textarea');
        if (!field) {
            var label = node.closest('label');
            if (label && pay.contains(label)) {
                field = label.querySelector('input, textarea');
            }
        }
        if (!field || !pay.contains(field) || field.disabled || field.readOnly) {
            return null;
        }
        var type = String(field.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio' || type === 'button' || type === 'file' || type === 'hidden' || type === 'submit' || type === 'reset') {
            return null;
        }
        return field;
    }

    function openKeyboard(event) {
        var field = editableField(event.target);
        if (!field || !pay.classList.contains('is-open')) {
            return;
        }
        setStyle(pay, 'transition', 'none');
        setStyle(pay, 'transform', 'none');
        keyboardUntil = Date.now() + 800;
        if (document.activeElement === field) {
            return;
        }
        try {
            field.focus({ preventScroll: true });
        } catch (err) {
            field.focus();
        }
    }

    var amounts = pay.querySelectorAll('input[type="number"]');
    for (var n = 0; n < amounts.length; n++) {
        if (!amounts[n].getAttribute('inputmode')) {
            amounts[n].setAttribute('inputmode', 'decimal');
        }
    }
    pay.addEventListener('touchstart', openKeyboard, true);
    pay.addEventListener('pointerdown', openKeyboard, true);
    pay.addEventListener('pointerup', openKeyboard);
    pay.addEventListener('touchend', openKeyboard);
    pay.addEventListener('click', openKeyboard);

    paint(false);
    window.addEventListener('resize', function () {
        if (Date.now() < keyboardUntil || pay.contains(document.activeElement)) {
            return;
        }
        paint(pay.classList.contains('is-open'));
    });

    document.addEventListener('click', function (event) {
        var jump = event.target.closest && event.target.closest('#amePosPayJump, #ameSaleDockGo, a[href="#amePosPay"]');
        if (!jump) {
            return;
        }
        event.preventDefault();
        paint(true);
    });
})();
