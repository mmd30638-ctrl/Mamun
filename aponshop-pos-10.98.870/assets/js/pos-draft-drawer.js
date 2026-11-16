'use strict';

(function () {
    var lastOpen = false;

    function drawerEl() {
        return document.getElementById('draftDrawer')
            || document.querySelector('.pos-draft-drawer');
    }

    function portalDrawer(drawer) {
        if (!drawer || drawer.dataset.ameDraftPortaled === '1') {
            return;
        }
        drawer.dataset.ameDraftPortaled = '1';
        document.body.appendChild(drawer);
    }

    function clearScrollLock() {
        document.documentElement.classList.remove('ame-pos-draft-open');
        document.body.classList.remove('ame-pos-draft-open');
        document.documentElement.style.removeProperty('overflow');
        document.body.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('height');
        document.body.style.removeProperty('height');
    }

    function recoverDeskLayout() {
        clearScrollLock();
        document.documentElement.classList.add('ame-pos-draft-recover');
        window.setTimeout(function () {
            document.documentElement.classList.remove('ame-pos-draft-recover');
        }, 50);

        var selectors = [
            '#main-content',
            '.ame-sale-desk',
            '.ame-pos-shell',
            '.ame-pos-grid',
            '.ame-desk-main',
            '.ame-pos-term-cart',
            '#amePosCartTableWrap',
            '.ame-pos-term-cart .ame-pos-table-wrap',
            '.table-pos',
            '#cart-body'
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (node) {
                if (!node || !node.style) {
                    return;
                }
                node.style.removeProperty('width');
                node.style.removeProperty('max-width');
                node.style.removeProperty('transform');
                node.style.removeProperty('scale');
                node.style.removeProperty('max-height');
                node.style.removeProperty('height');
                node.style.removeProperty('overflow');
                node.style.removeProperty('clip-path');
            });
        });

        if (typeof window.amePosCartScrollSync === 'function') {
            window.amePosCartScrollSync();
        }
        if (typeof window.amePosMemoLayoutSync === 'function') {
            window.amePosMemoLayoutSync();
        }
        window.dispatchEvent(new CustomEvent('ame-pos-desk-recovered'));
    }

    function syncDrawer() {
        var drawer = drawerEl();
        if (!drawer) {
            return;
        }
        portalDrawer(drawer);

        var open = drawer.classList.contains('open');
        document.documentElement.classList.toggle('ame-pos-draft-open', open);
        document.body.classList.toggle('ame-pos-draft-open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');

        if (open && !lastOpen) {
            clearScrollLock();
            document.documentElement.classList.add('ame-pos-draft-open');
            document.body.classList.add('ame-pos-draft-open');
        }

        if (!open && lastOpen) {
            recoverDeskLayout();
        }
        lastOpen = open;

        if (!open && typeof window.amePosMemoLayoutSync === 'function') {
            window.amePosMemoLayoutSync();
        }
    }

    function bind() {
        var drawer = drawerEl();
        if (!drawer) {
            return;
        }
        portalDrawer(drawer);
        if (drawer.dataset.ameDraftLayoutBound === '1') {
            return;
        }
        drawer.dataset.ameDraftLayoutBound = '1';
        new MutationObserver(syncDrawer).observe(drawer, {
            attributes: true,
            attributeFilter: ['class']
        });
        lastOpen = drawer.classList.contains('open');
        syncDrawer();
    }

    function boot() {
        bind();
        document.addEventListener('click', function (event) {
            var t = event.target;
            if (!t || !t.closest) {
                return;
            }
            if (t.closest('#amePosTermHist, [aria-controls="draftDrawer"]')) {
                recoverDeskLayout();
            }
            var drawer = drawerEl();
            if (drawer && drawer.classList.contains('open') && event.target === drawer) {
                drawer.classList.remove('open');
            }
        }, true);
        window.addEventListener('ame-pos-desk-recovered', function () {
            var drawer = drawerEl();
            if (drawer && !drawer.classList.contains('open')) {
                portalDrawer(drawer);
            }
        });
    }

    window.amePosDraftListRecover = recoverDeskLayout;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.setInterval(bind, 4000);
})();