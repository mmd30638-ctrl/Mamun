(function () {
  'use strict';
  var root = document.documentElement;
  if (!root) {
    return;
  }
  root.classList.add('ame-platform-contract');
  root.dataset.ameQuality = 'intl-100';
  root.dataset.ameContract = '1.0.0';

  var coarse = false;
  try {
    coarse = window.matchMedia('(pointer: coarse)').matches;
  } catch (e) {
    coarse = 'ontouchstart' in window;
  }
  if (coarse) {
    root.classList.add('ame-touch');
    root.dataset.amePointer = 'coarse';
  } else {
    root.dataset.amePointer = 'fine';
  }

  function refreshViewportMode() {
    var w = window.innerWidth || 0;
    if (coarse && w >= 721) {
      root.classList.add('ame-mobile-desktop-mode');
      root.dataset.ameViewportMode = 'mobile-desktop-site';
    } else if (coarse) {
      root.classList.remove('ame-mobile-desktop-mode');
      root.dataset.ameViewportMode = 'mobile';
    } else {
      root.classList.remove('ame-mobile-desktop-mode');
      root.dataset.ameViewportMode = w >= 1024 ? 'desktop' : 'tablet';
    }
  }

  refreshViewportMode();
  window.addEventListener('resize', refreshViewportMode, { passive: true });
  window.addEventListener('orientationchange', refreshViewportMode, { passive: true });
})();