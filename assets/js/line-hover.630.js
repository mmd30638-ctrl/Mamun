(function () {
  var hot = null;
  var spot = null;

  function clear() {
    if (hot) {
      hot.classList.remove('ame-line-hot');
      hot = null;
    }
    if (spot) {
      spot.classList.remove('ame-spot-zoom');
      spot = null;
    }
  }

  function lineOf(node) {
    if (!(node instanceof Element)) return null;
    if (node.closest('.master-header-fixed, .custom-sidebar, #ameTickerBar, #ameRunDock, .ame-run-dock, .overlay, .ame-cf-backdrop')) return null;
    var main = document.getElementById('main-content');
    if (!main || !main.contains(node)) return null;
    if (node.closest('input, textarea, select, option')) return null;
    return node.closest('tr, li, .cdesk-row, p, label, h1, h2, h3');
  }

  function spotOf(node, line) {
    if (!line) return null;
    var cell = node.closest('td, th, button, a, span, b, strong');
    if (!cell || cell === line || !line.contains(cell)) return null;
    if (cell.closest('input, textarea, select')) return null;
    return cell;
  }

  document.addEventListener('pointerover', function (ev) {
    var line = lineOf(ev.target);
    if (line !== hot) {
      if (hot) hot.classList.remove('ame-line-hot');
      hot = line;
      if (hot) hot.classList.add('ame-line-hot');
    }
    var next = spotOf(ev.target, line);
    if (next !== spot) {
      if (spot) spot.classList.remove('ame-spot-zoom');
      spot = next;
      if (spot) spot.classList.add('ame-spot-zoom');
    }
  });

  document.addEventListener('pointerout', function (ev) {
    if (!ev.relatedTarget || !document.documentElement.contains(ev.relatedTarget)) {
      clear();
    }
  });
})();
