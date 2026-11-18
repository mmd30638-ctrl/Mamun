/**
 * APONSHOP shell 8.25 — phone left menu stays out after swipe.
 * 8.18 called close() on every resize while !desktop(); swipe, address-bar
 * hide, and visualViewport jitter all fire resize and shoved the bar back in.
 */
(function () {
  'use strict';
  if (window.__ameShellIn825) return;
  window.__ameShellIn825 = 1;

  var BP = 1100;
  var holdOpen = false;
  var lastResizeAt = 0;
  var restoring = false;
  var wasDesktop = false;
  var touch = null;

  function desktop() {
    return window.matchMedia('(min-width: ' + BP + 'px)').matches;
  }
  function pos() {
    return !!(document.body && document.body.classList.contains('ame-pos-terminal-page'));
  }
  function sb() {
    return document.querySelector('.custom-sidebar');
  }
  function isOpen() {
    var el = sb();
    return !!(document.body && (
      document.body.classList.contains('ame-sb-open') ||
      (el && (el.classList.contains('open') || el.classList.contains('active') || el.classList.contains('show')))
    ));
  }
  function open() {
    if (!document.body || pos()) return;
    holdOpen = true;
    document.body.classList.add('ame-sb-open');
    var el = sb();
    if (el) el.classList.add('open', 'active');
  }
  function close(reason) {
    if (!document.body) return;
    if (reason === 'resize') return;
    if (reason === 'jitter' && holdOpen && !desktop()) return;
    holdOpen = false;
    document.body.classList.remove('ame-sb-open');
    var el = sb();
    if (el) el.classList.remove('open', 'active', 'show');
  }
  function toggle() {
    if (isOpen()) close('user');
    else open();
  }
  function keepIfStolen() {
    if (restoring || !holdOpen || desktop() || pos()) return;
    if (Date.now() - lastResizeAt > 700) return;
    var el = sb();
    if (!el) return;
    if (el.classList.contains('open') && document.body.classList.contains('ame-sb-open')) return;
    restoring = true;
    el.classList.add('open', 'active');
    document.body.classList.add('ame-sb-open');
    restoring = false;
  }

  document.addEventListener(
    'click',
    function (ev) {
      if (pos()) return;
      var t = ev.target;
      if (!t || !t.closest) return;
      if (t.closest('.hdr-i-menu')) {
        ev.preventDefault();
        ev.stopPropagation();
        toggle();
        return;
      }
      if (t.closest('.sidebar-close-btn, .sidebar-backdrop')) {
        ev.preventDefault();
        ev.stopPropagation();
        close('user');
        return;
      }
      if (isOpen() && t.closest('.custom-sidebar a')) {
        close('user');
      }
    },
    true
  );

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') close('user');
  });

  document.addEventListener('touchstart', function (ev) {
    if (pos() || !ev.touches || !ev.touches[0]) return;
    var x = ev.touches[0].clientX;
    var y = ev.touches[0].clientY;
    if (isOpen()) {
      var el = sb();
      if (el && el.contains(ev.target)) touch = { x: x, y: y, mode: 'close' };
      return;
    }
    if (x <= 80) touch = { x: x, y: y, mode: 'open' };
  }, { passive: true });

  document.addEventListener('touchend', function (ev) {
    if (!touch) return;
    var t = ev.changedTouches && ev.changedTouches[0];
    if (!t) { touch = null; return; }
    var dx = t.clientX - touch.x;
    var dy = t.clientY - touch.y;
    if (Math.abs(dx) > 46 && Math.abs(dx) > Math.abs(dy) * 1.15) {
      if (touch.mode === 'open' && dx > 0) open();
      if (touch.mode === 'close' && dx < 0) close('user');
    }
    touch = null;
  }, { passive: true });

  function onViewport() {
    lastResizeAt = Date.now();
    var now = desktop();
    if (wasDesktop && !now) close('breakpoint');
    wasDesktop = now;
    if (!now && holdOpen) {
      setTimeout(keepIfStolen, 40);
      setTimeout(keepIfStolen, 180);
    }
  }

  function boot() {
    wasDesktop = desktop();
    if (wasDesktop && !pos()) close('boot');
    if (window.MutationObserver) {
      var mo = new window.MutationObserver(function () {
        if (isOpen()) holdOpen = true;
        keepIfStolen();
      });
      mo.observe(document.documentElement, {
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('resize', onViewport);
  window.addEventListener('orientationchange', function () {
    setTimeout(onViewport, 80);
  });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', onViewport);
  }
})();
