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
            '.ame-desk-stack',
            '.ame-pos-term-top',
            '.ame-pos-term-cart',
            '.ame-pos-summary',
            '#amePosCartTableWrap',
            '.ame-pos-term-cart .ame-pos-table-wrap',
            '.table-pos',
            '#cart-body'
        ];
        var inlineProps = [
            'width',
            'max-width',
            'min-width',
            'transform',
            'scale',
            'max-height',
            'height',
            'min-height',
            'overflow',
            'clip-path',
            'left',
            'top',
            'right',
            'bottom',
            'margin-left',
            'margin-right',
            'grid-column',
            'grid-row',
            'position'
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (node) {
                if (!node || !node.style) {
                    return;
                }
                inlineProps.forEach(function (prop) {
                    node.style.removeProperty(prop);
                });
            });
        });

        document.querySelectorAll('#main-content .ame-sale-desk [data-ame-fit="1"]').forEach(function (node) {
            if (!node || !node.style) {
                return;
            }
            inlineProps.forEach(function (prop) {
                node.style.removeProperty(prop);
            });
        });

        if (typeof window.amePosCartScrollSync === 'function') {
            window.amePosCartScrollSync();
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

    function ensurePcDeskParity() {
        if (document.documentElement.classList.contains('ame-phone-desktop')) {
            return;
        }
        if (Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0) < 1000) {
            return;
        }
        recoverDeskLayout();
        if (typeof window.amePosReleasePageFitDesk === 'function') {
            window.amePosReleasePageFitDesk();
        }
        if (typeof window.amePosCartScrollSync === 'function') {
            window.amePosCartScrollSync();
        }
    }

    function boot() {
        bind();
        ensurePcDeskParity();
        window.setTimeout(ensurePcDeskParity, 450);
        window.setTimeout(ensurePcDeskParity, 1600);
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