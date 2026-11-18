/**
 * 20 minutes with no click, key, touch or scroll locks the desk.
 * Background pings do not count as work.
 */
(function () {
    'use strict';
    var LIMIT = 20 * 60 * 1000;
    var last = Date.now();
    var locked = false;

    function mark() {
        if (!locked) {
            last = Date.now();
        }
    }

    function lockNow() {
        if (locked) {
            return;
        }
        locked = true;
        window.location.replace('/logout.php?idle=1');
    }

    ['click', 'keydown', 'touchstart', 'scroll'].forEach(function (name) {
        window.addEventListener(name, mark, { passive: true });
    });

    setInterval(function () {
        if (Date.now() - last >= LIMIT) {
            lockNow();
        }
    }, 15000);
})();
