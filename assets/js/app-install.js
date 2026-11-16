(function (global) {
    'use strict';
    var deferred = null;
    var FLAG = 'ameAppInstallHide';

    function standalone() {
        return !!(global.matchMedia && global.matchMedia('(display-mode: standalone)').matches)
            || !!global.navigator.standalone;
    }
    function ios() {
        var ua = global.navigator.userAgent || '';
        return /iPhone|iPad|iPod/i.test(ua) && !/CriOS|FxiOS/i.test(ua);
    }
    function mark() {
        var html = document.documentElement;
        if (standalone()) html.classList.add('ame-is-app');
        if (ios()) html.classList.add('ame-ios');
    }
    function hidden() {
        try { return localStorage.getItem(FLAG) === '1'; } catch (e) { return false; }
    }
    function hideBar() {
        try { localStorage.setItem(FLAG, '1'); } catch (e) {}
        var bar = document.getElementById('ameAppInstall');
        if (bar) bar.classList.remove('show');
    }
    function showBar() {
        if (standalone() || hidden()) return;
        var bar = document.getElementById('ameAppInstall');
        if (bar) bar.classList.add('show');
    }
    function install() {
        if (deferred) {
            deferred.prompt();
            deferred.userChoice.finally(function () { deferred = null; hideBar(); });
            return;
        }
        if (ios()) {
            alert('শেয়ার বাটন (□↑) চাপুন, তারপর Add to Home Screen / হোম স্ক্রিনে যোগ করুন।');
            return;
        }
        alert('Chrome মেনু ⋮ → Add to Home screen / অ্যাপ ইনস্টল করুন। তারপর হোম থেকে অ্যাপের মতো খুলবে।');
    }

    mark();
    global.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        showBar();
    });
    global.addEventListener('appinstalled', hideBar);

    function bind() {
        mark();
        var go = document.getElementById('ameAppInstallGo');
        var lock = document.getElementById('ameLockInstall');
        var x = document.getElementById('ameAppInstallX');
        if (go) go.addEventListener('click', install);
        if (lock) lock.addEventListener('click', install);
        if (x) x.addEventListener('click', hideBar);
        if (!standalone() && !hidden() && (deferred || ios())) showBar();
        if (!standalone() && !hidden()) {
            setTimeout(showBar, 1200);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
    global.AmeAppInstall = { install: install, standalone: standalone };
})(window);
