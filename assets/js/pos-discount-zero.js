/* 10.98.722 */
'use strict';

(function () {
    function num(value) {
        var n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    }

    function node(id) {
        return document.getElementById(id);
    }

    function paintDiscountBoxes() {
        var takaEl = node('deskDiscountTaka');
        var afterEl = node('deskAfter');
        var discEl = node('discount');
        var autoEl = node('deskDiscountAuto');
        var pctEl = node('deskDiscountPct');
        var box = node('deskDiscBox');
        var pctOff = !!(box && box.getAttribute('data-pct-off') === '1');
        if (!pctOff && pctEl) {
            pctEl.readOnly = true;
            if (document.activeElement !== pctEl) {
                pctEl.value = '12.00';
            }
        }
        if (autoEl && pctOff) {
            autoEl.value = '0';
        }

        var gross = num(node('sub-total') && node('sub-total').value);
        var lineOff = num(window.ameLineDiscountTaka);
        var afterLine = Math.max(0, gross - lineOff);
        var on = pctOff ? (!box || box.getAttribute('data-on') !== '0') : true;
        var locked = !pctOff && !!pctEl && (!box || box.getAttribute('data-lock') !== '0');
        var mode = locked || (!pctOff && box && box.getAttribute('data-mode') === 'pct') ? 'pct' : 'taka';
        var extra = 0;
        if (takaEl) {
            takaEl.readOnly = locked;
        }
        if (!box && pctEl && document.activeElement !== pctEl) {
            pctEl.value = '0';
        }
        if (on && mode === 'pct') {
            var pct = Math.min(100, Math.max(0, num(pctEl && pctEl.value)));
            extra = afterLine * pct / 100;
            if (takaEl && document.activeElement !== takaEl) {
                takaEl.value = extra > 0.009 ? extra.toFixed(2) : '0';
            }
        } else if (on) {
            extra = Math.max(0, num(takaEl && takaEl.value));
            if (extra > afterLine) {
                extra = afterLine;
            }
            if (box && pctEl && document.activeElement !== pctEl) {
                var fromTaka = afterLine > 0.0001 ? (extra / afterLine) * 100 : 0;
                pctEl.value = fromTaka.toFixed(2);
            }
            if (takaEl && document.activeElement !== takaEl) {
                takaEl.value = extra > 0.009 ? extra.toFixed(2) : '0';
            }
        }
        var off = extra + lineOff;

        var serviceTaka = Math.max(0, num(node('service_amount') && node('service_amount').value));
        if (afterEl) {
            afterEl.textContent = Math.max(0, gross + serviceTaka - off).toFixed(2);
        }
        if (discEl) {
            discEl.value = off.toFixed(2);
        }
    }

    function wrapCalc() {
        var prev = window.calculateAll;
        if (typeof prev === 'function' && prev.__ameDiscZero) {
            return;
        }
        window.calculateAll = function () {
            var result = typeof prev === 'function' ? prev.apply(this, arguments) : undefined;
            paintDiscountBoxes();
            return result;
        };
        window.calculateAll.__ameDiscZero = 1;
    }

    wrapCalc();
    paintDiscountBoxes();
    document.addEventListener('input', function (event) {
        var target = event.target;
        if (!target) {
            return;
        }
        if (target.id === 'deskDiscountTaka' || target.id === 'discount' || (target.classList && target.classList.contains('ame-pos-line-input'))) {
            paintDiscountBoxes();
        }
    });
    document.addEventListener('DOMContentLoaded', function () {
        wrapCalc();
        paintDiscountBoxes();
    });
    window.setInterval(wrapCalc, 800);
    window.amePaintDiscountBoxes = paintDiscountBoxes;
})();
