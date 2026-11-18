(function () {
  function setInk(el, hex) {
    if (!el || !hex) return;
    el.classList.add('sb-label-colored');
    el.style.setProperty('color', hex, 'important');
    el.style.setProperty('-webkit-text-fill-color', hex, 'important');
  }

  function chipHex(chip) {
    if (!chip) return '';
    var inline = chip.style.color || '';
    if (inline) return inline;
    var svg = chip.querySelector('svg');
    if (svg) {
      var c = window.getComputedStyle(svg).color;
      if (c && c !== 'rgb(0, 0, 0)') return c;
    }
    return window.getComputedStyle(chip).color || '';
  }

  function subHex(a) {
    var dot = a.querySelector('.sb-sub-dot');
    if (dot) {
      var bg = dot.style.background || window.getComputedStyle(dot).backgroundColor;
      if (bg) return bg;
    }
    var ico = a.querySelector('.sb-sub-icon');
    if (ico) {
      var ic = ico.style.color || window.getComputedStyle(ico).color;
      if (ic) return ic;
    }
    return '';
  }

  function paint() {
    var root = document.querySelector('.custom-sidebar');
    if (!root) return;

    root.querySelectorAll('.direct-link, .menu-head').forEach(function (row) {
      var chip = row.querySelector('.menu-icon-chip');
      if (chip) setInk(row, chipHex(chip));
    });

    root.querySelectorAll('.menu-list a').forEach(function (a) {
      var hex = subHex(a);
      if (hex) setInk(a, hex);
    });

    root.querySelectorAll('.menu-section').forEach(function (sec) {
      var headChip = sec.querySelector('.menu-head .menu-icon-chip');
      var parentHex = chipHex(headChip);
      if (!parentHex) return;
      sec.querySelectorAll('.menu-list a').forEach(function (a) {
        if (!a.classList.contains('sb-label-colored')) {
          setInk(a, parentHex);
        }
      });
    });

    root.querySelectorAll('.menu-head > span:first-child').forEach(function (wrap) {
      var head = wrap.closest('.menu-head');
      if (head && head.classList.contains('sb-label-colored')) {
        wrap.style.setProperty('color', head.style.color, 'important');
      }
    });
  }

  function boot() {
    paint();
    window.setTimeout(paint, 120);
    window.setTimeout(paint, 600);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('sidebarOpened', paint);
})();