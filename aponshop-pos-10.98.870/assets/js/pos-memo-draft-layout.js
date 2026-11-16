'use strict';

(function () {
    function hasUrlDraft() {
        try {
            var q = new URLSearchParams(window.location.search);
            if (q.get('draft_id') || q.get('draft') || q.get('memo')) {
                return true;
            }
        } catch (e) {
            return /[?&](draft_id|draft|memo)=/i.test(String(window.location.search || ''));
        }
        return false;
    }

    function isMemoEditing() {
        var idEl = document.getElementById('currentDraftId');
        var id = idEl ? parseInt(String(idEl.value || '').trim(), 10) : 0;
        if (id > 0) {
            return true;
        }
        var mode = document.getElementById('amePosSaleMode');
        if (mode && String(mode.value || '').toLowerCase() === 'draft') {
            return true;
        }
        if (document.getElementById('amePosRecentDraft')?.classList.contains('is-active')) {
            return true;
        }
        var completeBtn = document.getElementById('amePosCompleteBtn');
        if (completeBtn && completeBtn.classList.contains('is-draft')) {
            return true;
        }
        return hasUrlDraft();
    }

    function closeDraftListOverlay() {
        var drawer = document.getElementById('draftDrawer') || document.querySelector('.pos-draft-drawer');
        if (drawer && drawer.classList.contains('open')) {
            drawer.classList.remove('open');
        }
        document.documentElement.classList.remove('ame-pos-draft-open');
        document.body.classList.remove('ame-pos-draft-open');
    }

    function releaseDeskFit() {
        if (typeof window.amePosReleasePageFitDesk === 'function') {
            window.amePosReleasePageFitDesk();
        }
        if (typeof window.amePosDraftListRecover === 'function') {
            window.amePosDraftListRecover();
        }
    }

    window.amePosMemoLayoutSync = function () {
        var editing = isMemoEditing();
        document.documentElement.classList.toggle('ame-pos-memo-editing', editing);
        if (editing) {
            closeDraftListOverlay();
            releaseDeskFit();
        }
        if (typeof window.amePosCartScrollSync === 'function') {
            window.amePosCartScrollSync();
        }
    };

    function bindWatchers() {
        ['currentDraftId', 'amePosSaleMode'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el || el.dataset.ameMemoWatch === '1') {
                return;
            }
            el.dataset.ameMemoWatch = '1';
            el.addEventListener('change', window.amePosMemoLayoutSync);
            el.addEventListener('input', window.amePosMemoLayoutSync);
        });
        var render = window.renderCart;
        if (typeof render === 'function' && !window.ameMemoRenderPatched) {
            window.ameMemoRenderPatched = true;
            window.renderCart = function () {
                var out = render.apply(this, arguments);
                window.amePosMemoLayoutSync();
                return out;
            };
        }
    }

    function boot() {
        window.amePosMemoLayoutSync();
        bindWatchers();
        window.setInterval(function () {
            window.amePosMemoLayoutSync();
            bindWatchers();
        }, 1500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('resize', window.amePosMemoLayoutSync);
    window.addEventListener('orientationchange', window.amePosMemoLayoutSync);
})();