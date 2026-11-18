(function () {
  var root = document.getElementById('famApp');
  if (root) {
    root.setAttribute('data-theme', 'light');
    try { localStorage.removeItem('ame-fam-theme'); } catch (e) {}
  }
  var themeBtn = document.getElementById('famThemeBtn');
  if (themeBtn) {
    themeBtn.hidden = true;
  }

  var side = document.getElementById('krSide');
  var sideBtn = document.getElementById('krSideBtn');
  if (!side || !sideBtn) {
    return;
  }
  document.addEventListener('click', function (event) {
    if (!side.classList.contains('is-open')) {
      return;
    }
    if (side.contains(event.target) || sideBtn.contains(event.target)) {
      return;
    }
    side.classList.remove('is-open');
  });
})();
