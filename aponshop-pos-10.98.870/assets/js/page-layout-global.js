(function () {
    var cfg = window.AME_PAGE_LAYOUT || {};

    function isSalesDesk() {
        var path = String(cfg.path || location.pathname || '').toLowerCase();
        var page = String(cfg.page || (document.body && document.body.getAttribute('data-ame-layout-page')) || '');
        if (document.body && document.body.classList.contains('ame-pos-terminal-page')) {
            return true;
        }
        if (/^sales(\/|$)/.test(page) || /\/modules\/sales\//.test(path) || /\/sales(?:\/|$|\?)/.test(path)) {
            return true;
        }
        if (document.querySelector('.ame-sale-desk, #ameSaleDesk')) {
            return true;
        }
        return false;
    }

    function hideSalesDesigner() {
        document.querySelectorAll(
            '#ameDeskBarDesigner, .ame-desk-tool-designer, a.ame-desk-tool-designer, #ameLayoutFab, .ame-layout-fab'
        ).forEach(function (el) {
            if (isSalesDesk() || el.id === 'ameDeskBarDesigner' || el.classList.contains('ame-desk-tool-designer')) {
                el.hidden = true;
                el.style.setProperty('display', 'none', 'important');
            }
        });
        if (isSalesDesk()) {
            document.querySelectorAll(
                '.ame-sale-desk a[href*="layout_designer"], .ame-sale-desk button[title*="লেআউট"], ' +
                '.ame-pos-terminal-page a[href*="layout_designer.php"], .ame-layout-fab, .ame-pos-layout-studio'
            ).forEach(function (el) {
                el.hidden = true;
                el.style.setProperty('display', 'none', 'important');
            });
            var fab = document.getElementById('ameLayoutFab');
            if (fab && fab.parentNode) {
                fab.parentNode.removeChild(fab);
            }
        }
        document.querySelectorAll('#m-sales a[href*="layout_designer.php"]').forEach(function (el) {
            el.remove();
        });
        document.querySelectorAll('#m-settings a[href="/modules/sales/layout_designer.php"]').forEach(function (a) {
            a.setAttribute('href', '/modules/settings/layout_designer.php');
            a.textContent = '📐 লেআউট ডিজাইন';
        });
        var list = document.getElementById('m-settings');
        if (list && !list.querySelector('a[href*="/modules/settings/layout_designer.php"]')) {
            var a = document.createElement('a');
            a.href = '/modules/settings/layout_designer.php';
            a.style.cssText = 'color:#d97706;font-weight:700;';
            a.textContent = '📐 লেআউট ডিজাইন';
            var after = list.querySelector('a[href*="pos_title.php"]');
            if (after && after.nextSibling) {
                list.insertBefore(a, after.nextSibling);
            } else if (after) {
                after.insertAdjacentElement('afterend', a);
            } else {
                list.insertBefore(a, list.firstChild);
            }
        }
    }

    function cssPath(el) {
        if (!(el instanceof Element)) {
            return '';
        }
        if (el.id && /^[A-Za-z][\w-]*$/.test(el.id)) {
            return '#' + el.id;
        }
        var parts = [];
        var node = el;
        var depth = 0;
        while (node && node.nodeType === 1 && depth < 5 && node !== document.body) {
            var name = node.nodeName.toLowerCase();
            if (node.id && /^[A-Za-z][\w-]*$/.test(node.id)) {
                parts.unshift('#' + node.id);
                break;
            }
            var cls = (node.className && typeof node.className === 'string')
                ? node.className.trim().split(/\s+/).filter(function (c) {
                    return c && !c.startsWith('ame-layout') && /^[A-Za-z][\w-]*$/.test(c);
                })[0]
                : '';
            var bit = cls ? name + '.' + cls : name;
            parts.unshift(bit);
            node = node.parentElement;
            depth += 1;
        }
        return parts.join(' ');
    }

    function payloadFrom(el, kind) {
        var rect = el.getBoundingClientRect();
        var style = window.getComputedStyle(el);
        return {
            kind: kind,
            selector: cssPath(el),
            label: (el.getAttribute('aria-label') || el.title || el.textContent || '').trim().slice(0, 40),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            left: Math.round(el.offsetLeft || 0),
            top: Math.round(el.offsetTop || 0),
            bg: rgbToHex(style.backgroundColor),
            color: rgbToHex(style.color),
            size: Math.round(parseFloat(style.fontSize) || 16)
        };
    }

    function rgbToHex(rgb) {
        var m = String(rgb || '').match(/(\d+),\s*(\d+),\s*(\d+)/);
        if (!m) {
            return '#ffffff';
        }
        return '#' + [m[1], m[2], m[3]].map(function (n) {
            return ('0' + Number(n).toString(16)).slice(-2);
        }).join('');
    }

    function embedMode() {
        if (!cfg.embed) {
            return;
        }
        document.body.classList.add('ame-layout-embed', 'ame-layout-pick');
        var last = null;
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!(t instanceof Element)) {
                return;
            }
            if (t.closest('.ame-layout-fab, .vd-ui, .vd-launch')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var box = t.closest('section, article, .card, .ame-pos-card, .studio-panel, table, form, header, nav, aside, .menu-section') || t;
            var icon = t.closest('a.hdr-icon-btn, .hdr-icon-btn, #mySidebar a, button svg, a svg') ? t.closest('a, button') : null;
            document.querySelectorAll('[data-ame-hit="2"]').forEach(function (n) {
                n.removeAttribute('data-ame-hit');
            });
            var chosen = icon || box;
            chosen.setAttribute('data-ame-hit', '2');
            last = chosen;
            parent.postMessage({
                type: 'ame-layout-pick',
                icon: !!icon,
                data: payloadFrom(chosen, icon ? 'icon' : 'box')
            }, '*');
        }, true);
        document.addEventListener('mouseover', function (e) {
            var t = e.target;
            if (!(t instanceof Element)) {
                return;
            }
            document.querySelectorAll('[data-ame-hit="1"]').forEach(function (n) {
                if (n.getAttribute('data-ame-hit') !== '2') {
                    n.removeAttribute('data-ame-hit');
                }
            });
            if (t.getAttribute('data-ame-hit') !== '2') {
                t.setAttribute('data-ame-hit', '1');
            }
        });
        window.addEventListener('message', function (ev) {
            if (!ev.data || ev.data.type !== 'ame-layout-css') {
                return;
            }
            var tag = document.getElementById('amePageLayoutCss');
            if (!tag) {
                tag = document.createElement('style');
                tag.id = 'amePageLayoutCss';
                document.head.appendChild(tag);
            }
            tag.textContent = ev.data.css || '';
        });
        parent.postMessage({ type: 'ame-layout-ready', page: cfg.page, path: cfg.path }, '*');
    }

    function listenTextCss() {
        window.addEventListener('message', function (ev) {
            if (!ev.data || ev.data.type !== 'ame-text-css') {
                return;
            }
            var tag = document.getElementById('amePageTextCss');
            if (!tag) {
                tag = document.createElement('style');
                tag.id = 'amePageTextCss';
                document.head.appendChild(tag);
            }
            tag.textContent = ev.data.css || '';
        });
    }

    function boot() {
        if (document.body && cfg.page) {
            document.body.setAttribute('data-ame-layout-page', cfg.page);
        }
        listenTextCss();
        hideSalesDesigner();
        embedMode();
        if (isSalesDesk()) {
            setTimeout(hideSalesDesigner, 300);
            setTimeout(hideSalesDesigner, 1200);
            if (window.MutationObserver) {
                var mo = new MutationObserver(function () {
                    hideSalesDesigner();
                });
                mo.observe(document.body, { childList: true, subtree: true });
                setTimeout(function () {
                    mo.disconnect();
                }, 8000);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
