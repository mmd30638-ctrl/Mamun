(function () {
  function closeDrawer() {
    var d = document.getElementById('da-drawer');
    if (d) d.classList.remove('is-on');
  }
  function openDrawer(html) {
    var d = document.getElementById('da-drawer');
    var box = document.getElementById('da-drawer-body');
    if (!d || !box) return;
    box.innerHTML = html;
    d.classList.add('is-on');
  }
  document.addEventListener('click', function (e) {
    var row = e.target.closest('[data-da-detail]');
    if (row) {
      e.preventDefault();
      var url = row.getAttribute('data-da-detail');
      openDrawer('<p class="da-load">লোড হচ্ছে…</p>');
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(openDrawer)
        .catch(function () { openDrawer('<p class="da-error">বিস্তারিত আনা যায়নি।</p>'); });
      return;
    }
    if (e.target.closest('[data-da-close]')) {
      closeDrawer();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });
})();
