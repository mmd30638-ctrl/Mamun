(function (g) {
  'use strict';

  function presentation(amount) {
    var n = Math.round((Number(amount) || 0) * 100) / 100;
    if (n < -0.004) {
      return {
        kind: 'credit',
        className: 'sup-due sup-due--credit',
        pickClass: 'sup-due-pick sup-due-pick--credit',
        text: '−৳' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      };
    }
    if (n > 0.004) {
      return {
        kind: 'payable',
        className: 'sup-due sup-due--payable',
        pickClass: 'sup-due-pick sup-due-pick--payable',
        text: '৳' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      };
    }
    return {
      kind: 'zero',
      className: 'sup-due sup-due--zero',
      pickClass: 'sup-due-pick sup-due-pick--zero',
      text: '0.00 ৳',
    };
  }

  function paint(el, amount) {
    if (!el) {
      return;
    }
    var p = presentation(amount);
    if (el.classList.contains('sup-due-pick')) {
      el.className = p.pickClass;
    } else if (el.classList.contains('sup-due-hero-amt') || el.getAttribute('data-sup-due-amt') === '1') {
      el.className = 'sup-due-hero-amt ' + p.className;
      if (el.id) {
        /* keep id */
      }
    } else {
      el.className = p.className + ' sup-due-amt-only';
    }
    el.textContent = p.text;
  }

  g.ameSupDuePresentation = presentation;
  g.ameSupDuePaint = paint;
})(window);