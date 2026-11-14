(function () {
    var cfg = window.AME_PAGE_LAYOUT || {};
    if (!cfg.canEdit || cfg.page !== 'sales/pos' || cfg.embed) {
        return;
    }

  var EDIT_SEL = [
        '.ame-desk-quick-btn',
        'a.ame-desk-tool',
        'button.ame-desk-tool',
        '#amePosCompleteBtn',
        '.ame-desk-actions',
        '.ame-desk-box',
        '.ame-desk-bar .ame-desk-home',
        '#ameDeskClock'
    ].join(',');

    var editing = false;
    var drag = null;

    function cssPath(el) {
        if (!(el instanceof Element)) {
            return '';
        }
        if (el.id && /^[A-Za-z][\w-]*$/.test(el.id)) {
            return '#' + el.id;
        }
        var parts = [];
        var node = el;
        for (var depth = 0; node && node.nodeType === 1 && depth < 5 && node !== document.body; depth++) {
            if (node.id && /^[A-Za-z][\w-]*$/.test(node.id)) {
                parts.unshift('#' + node.id);
                break;
            }
            var cls = (node.className && typeof node.className === 'string')
                ? node.className.trim().split(/\s+/)[0]
                : '';
            parts.unshift(cls ? node.nodeName.toLowerCase() + '.' + cls : node.nodeName.toLowerCase());
            node = node.parentElement;
        }
        return parts.join(' ');
    }

    function markEditable() {
        document.querySelectorAll(EDIT_SEL).forEach(function (el) {
            if (!el.getAttribute('data-ame-pos-layout-item')) {
                el.setAttribute('data-ame-pos-layout-item', '1');
            }
            if (getComputedStyle(el).position === 'static') {
                el.style.position = 'relative';
            }
        });
    }

    function setEditing(on) {
        editing = !!on;
        document.body.classList.toggle('ame-pos-layout-edit', editing);
        if (editing) {
            markEditable();
        }
    }

    function onPointerDown(e) {
        if (!editing) {
            return;
        }
        var el = e.target instanceof Element ? e.target.closest('[data-ame-pos-layout-item]') : null;
        if (!el || el.closest('.ame-pos-layout-studio')) {
            return;
        }
        if (el.id === 'amePosCompleteBtn' && !e.shiftKey) {
            return;
        }
        if (e.button !== 0) {
            return;
        }
        e.preventDefault();
        el.classList.add('is-dragging');
        drag = {
            el: el,
            startX: e.clientX,
            startY: e.clientY,
            baseLeft: parseFloat(el.style.left) || 0,
            baseTop: parseFloat(el.style.top) || 0
        };
    }

    function onPointerMove(e) {
        if (!drag) {
            return;
        }
        var dx = e.clientX - drag.startX;
        var dy = e.clientY - drag.startY;
        drag.el.style.left = (drag.baseLeft + dx) + 'px';
        drag.el.style.top = (drag.baseTop + dy) + 'px';
    }

    function onPointerUp() {
        if (!drag) {
            return;
        }
        drag.el.classList.remove('is-dragging');
        drag = null;
    }

    function onWheel(e) {
        if (!editing || !e.altKey) {
            return;
        }
        var el = e.target instanceof Element ? e.target.closest('[data-ame-pos-layout-item]') : null;
        if (!el) {
            return;
        }
        e.preventDefault();
        var rect = el.getBoundingClientRect();
        var scale = e.deltaY < 0 ? 1.06 : 0.94;
        var w = Math.max(28, Math.round(rect.width * scale));
        var h = Math.max(24, Math.round(rect.height * scale));
        el.style.width = w + 'px';
        el.style.height = h + 'px';
        el.style.minWidth = w + 'px';
        el.style.minHeight = h + 'px';
        if (el.querySelector('svg')) {
            var fs = Math.max(12, Math.round(w * 0.45));
            el.style.fontSize = fs + 'px';
        }
    }

    function collectPageState() {
        var boxes = [];
        var icons = [];
        document.querySelectorAll('[data-ame-pos-layout-item]').forEach(function (el) {
            var sel = cssPath(el);
            if (!sel) {
                return;
            }
            var rect = el.getBoundingClientRect();
            var isIcon = el.matches('.ame-desk-quick-btn, .ame-desk-tool, #amePosCompleteBtn, .ame-desk-tool-designer');
            var entry = {
                selector: sel,
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                left: Math.round(parseFloat(el.style.left) || 0),
                top: Math.round(parseFloat(el.style.top) || 0),
                moved: true
            };
            if (isIcon) {
                entry.size = Math.round(parseFloat(getComputedStyle(el).fontSize) || rect.width);
                entry.color = '#003F8F';
                icons.push(entry);
            } else {
                boxes.push(entry);
            }
        });
        return { boxes: boxes, icons: icons };
    }

    function applySavedCss(css) {
        var tag = document.getElementById('amePageLayoutCssAfterLock')
            || document.getElementById('amePageLayoutCss');
        if (!tag) {
            tag = document.createElement('style');
            tag.id = 'amePageLayoutCssAfterLock';
            document.head.appendChild(tag);
        }
        tag.textContent = css || '';
    }

    function saveLayout() {
        var page = collectPageState();
        fetch('/modules/settings/layout_designer_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save',
                pageKey: 'sales/pos',
                page: page
            })
        }).then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.css) {
                    applySavedCss(res.css);
                }
                alert(res.ok ? (res.message || 'লেআউট সংরক্ষিত হয়েছে') : (res.message || 'সংরক্ষণ ব্যর্থ'));
                if (res.ok) {
                    setEditing(false);
                }
            })
            .catch(function () {
                alert('সংরক্ষণ ব্যর্থ — নেটওয়ার্ক চেক করুন।');
            });
    }

    function buildStudio() {
        var bar = document.createElement('div');
        bar.className = 'ame-pos-layout-studio';
        bar.setAttribute('role', 'toolbar');
        bar.innerHTML = '<button type="button" class="is-on" data-act="toggle">📐 লেআউট সম্পাদনা</button>'
            + '<button type="button" class="is-save" data-act="save">💾 সংরক্ষণ</button>'
            + '<button type="button" class="is-off" data-act="full">সেটিংস এডিটর</button>';
        bar.addEventListener('click', function (e) {
            var btn = e.target instanceof Element ? e.target.closest('button[data-act]') : null;
            if (!btn) {
                return;
            }
            var act = btn.getAttribute('data-act');
            if (act === 'toggle') {
                setEditing(!editing);
                btn.textContent = editing ? '✓ সম্পাদনা চালু' : '📐 লেআউট সম্পাদনা';
            } else if (act === 'save') {
                saveLayout();
            } else if (act === 'full') {
                window.location.href = '/modules/settings/layout_designer.php?page=sales/pos';
            }
        });
        document.body.appendChild(bar);
    }

    function boot() {
        document.body.classList.add('ame-pos-layout-can-edit');
        var designer = document.getElementById('ameDeskBarDesigner');
        if (designer) {
            designer.href = '/modules/settings/layout_designer.php?page=sales/pos';
        }
        document.querySelectorAll('a.ame-desk-tool-designer').forEach(function (a) {
            a.href = '/modules/settings/layout_designer.php?page=sales/pos';
        });
        markEditable();
        buildStudio();
        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('pointermove', onPointerMove, true);
        document.addEventListener('pointerup', onPointerUp, true);
        document.addEventListener('pointercancel', onPointerUp, true);
        document.addEventListener('wheel', onWheel, { passive: false, capture: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();