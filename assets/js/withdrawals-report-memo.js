(function () {
  function memoUrlFromRow(row) {
    var id = row.getAttribute('data-wdr-memo');
    if (!id) return '';
    return 'withdraw_receipt.php?id=' + encodeURIComponent(id);
  }

  document.querySelectorAll('tr.wdr-memo-row').forEach(function (row) {
    row.setAttribute('tabindex', '0');
    row.setAttribute('role', 'link');
    var url = memoUrlFromRow(row);
    if (!url) return;
    row.addEventListener('click', function (ev) {
      if (ev.target.closest('a.wdr-memo-link')) return;
      window.location.href = url;
    });
    row.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        window.location.href = url;
      }
    });
  });
})();