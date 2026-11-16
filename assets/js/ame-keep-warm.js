/**
 * While the shop is open and not yet locked, a light ping keeps the
 * next click fast. It does not refresh the login idle clock.
 * SPEED.txt §৫ — 45s /warm.php. Do not remove.
 */
(function () {
    'use strict';
    if (window.AME_KEEP_WARM) {
        return;
    }
    window.AME_KEEP_WARM = true;

    var lockMs = 20 * 60 * 1000;
    var lastUse = Date.now();

    function mark() {
        lastUse = Date.now();
    }

    ['pointerdown', 'keydown', 'touchstart'].forEach(function (name) {
        document.addEventListener(name, mark, { passive: true });
    });

    function ping() {
        if (document.hidden) {
            return;
        }
        if (Date.now() - lastUse >= lockMs) {
            return;
        }
        fetch('/warm.php', { cache: 'no-store', credentials: 'omit' }).catch(function () {});
    }

    ping();
    setInterval(ping, 45000);
})();
