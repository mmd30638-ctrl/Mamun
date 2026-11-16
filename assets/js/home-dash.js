(function () {
  var openBtn = document.getElementById('hdOpen');
  var draw = document.getElementById('hdDraw');
  var body = document.getElementById('hdBody');
  var closeBtn = document.getElementById('hdClose');
  if (!openBtn || !draw || !body) {
    return;
  }
  var timer = 0;

  function field(id) {
    var node = body.querySelector('#' + id);
    return node ? String(node.value || '') : '';
  }

  function currentMode() {
    var now = body.querySelector('[data-hd-mode-now]');
    return (now && now.getAttribute('data-hd-mode-now')) || 'live';
  }

  function query(extra) {
    extra = extra || {};
    var params = new URLSearchParams();
    params.set('mode', extra.mode || currentMode());
    params.set('date', extra.date != null ? extra.date : field('hdDate'));
    params.set('month', extra.month != null ? extra.month : field('hdMonth'));
    params.set('year', extra.year != null ? extra.year : field('hdYear'));
    params.set('user', extra.user != null ? String(extra.user) : (field('hdUser') || '0'));
    return params.toString();
  }

  function pulse() {
    if (timer) {
      window.clearInterval(timer);
      timer = 0;
    }
    var now = body.querySelector('[data-hd-mode-now]');
    if (!draw.hidden && now && now.getAttribute('data-hd-mode-now') === 'live') {
      timer = window.setInterval(function () {
        load(query({ mode: 'live' }));
      }, 8000);
    }
  }

  function load(qs) {
    return fetch('/modules/reports/home_board.php?' + qs, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('board');
        }
        return response.text();
      })
      .then(function (html) {
        body.innerHTML = html;
        pulse();
      })
      .catch(function () {
        body.innerHTML = '<p class="hd-note">এখন খোলা যায়নি।</p>';
      });
  }

  function openBoard() {
    draw.hidden = false;
    openBtn.setAttribute('aria-expanded', 'true');
    if (!body.querySelector('.hd-split')) {
      load(query({ mode: 'live' }));
    } else {
      pulse();
    }
  }

  function closeBoard() {
    draw.hidden = true;
    openBtn.setAttribute('aria-expanded', 'false');
    if (timer) {
      window.clearInterval(timer);
      timer = 0;
    }
  }

  openBtn.addEventListener('click', function (event) {
    event.preventDefault();
    openBoard();
  });
  if (closeBtn) {
    closeBtn.addEventListener('click', closeBoard);
  }
  draw.addEventListener('click', function (event) {
    if (event.target === draw) {
      closeBoard();
    }
  });
  body.addEventListener('click', function (event) {
    var modeBtn = event.target.closest ? event.target.closest('[data-hd-mode]') : null;
    if (modeBtn) {
      load(query({ mode: modeBtn.getAttribute('data-hd-mode') || 'live' }));
      return;
    }
    var person = event.target.closest ? event.target.closest('[data-hd-user]') : null;
    if (person) {
      var id = person.getAttribute('data-hd-user') || '0';
      var current = field('hdUser') || '0';
      load(query({ user: current === id ? '0' : id }));
      return;
    }
    if (event.target && event.target.id === 'hdApply') {
      load(query());
    }
  });
  body.addEventListener('change', function (event) {
    var id = event.target && event.target.id;
    var split = body.querySelector('[data-hd-mode-now]');
    if (!split) {
      return;
    }
    var next = '';
    if (id === 'hdDate') {
      next = 'date';
    } else if (id === 'hdMonth') {
      next = 'month';
    } else if (id === 'hdYear') {
      next = 'year';
    }
    if (!next) {
      return;
    }
    split.setAttribute('data-hd-mode-now', next);
    body.querySelectorAll('[data-hd-mode]').forEach(function (chip) {
      chip.classList.toggle('on', chip.getAttribute('data-hd-mode') === next);
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !draw.hidden) {
      closeBoard();
    }
  });
})();