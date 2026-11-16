'use strict';

(function () {
    function drawerEl() {
        return document.getElementById('draftDrawer')
            || document.querySelector('.pos-draft-drawer');
    }

    function syncDrawer() {
        var drawer = drawerEl();
        if (!drawer) {
            return;
        }
        var open = drawer.classList.contains('open');
        document.documentElement.classList.toggle('ame-pos-draft-open', open);
        document.body.classList.toggle('ame-pos-draft-open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open && drawer.parentElement !== document.body) {
            drawer.dataset.ameDraftPortal = '1';
            document.body.appendChild(drawer);
        }
    }

    function bind() {
        var drawer = drawerEl();
        if (!drawer || drawer.dataset.ameDraftLayoutBound === '1') {
            return;
        }
        drawer.dataset.ameDraftLayoutBound = '1';
        new MutationObserver(syncDrawer).observe(drawer, {
            attributes: true,
            attributeFilter: ['class']
        });
        syncDrawer();
    }

    function boot() {
        bind();
        document.addEventListener('click', function (event) {
            var drawer = drawerEl();
            if (!drawer || !drawer.classList.contains('open')) {
                return;
            }
            if (event.target === drawer) {
                drawer.classList.remove('open');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.setInterval(bind, 2500);
})();