'use strict';

(function () {
    var live = document.querySelector('.ame-pos-title-live');
    if (!live || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    var line = live.closest('.ame-pos-title-line') || live;
    var shown = line.offsetParent !== null;
    if (!shown) {
        try {
            shown = window.getComputedStyle(line).display !== 'none';
        } catch (e) {
            shown = false;
        }
    }
    if (!shown) {
        return;
    }

    var modes = ['gold', 'navy', 'emerald', 'ruby', 'teal'];
    var index = 0;

    function nextMode() {
        live.setAttribute('data-ame-title-mode', modes[index % modes.length]);
        index += 1;
    }

    nextMode();
    setInterval(nextMode, 4200);
})();
