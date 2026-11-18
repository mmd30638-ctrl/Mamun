(function () {
  var KEY = 'ame_edge_pulse';
  function save(data) {
    try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) {}
  }
  window.ameEdgePulseSave = save;
  if (!navigator.onLine) return;
  fetch('/modules/pulse/edge-json.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) { if (j && j.ok) save(j.pulse); })
    .catch(function () {});
})();
