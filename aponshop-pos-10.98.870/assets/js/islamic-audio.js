(function () {
  var root = document.getElementById('iaHome');
  if (!root) {
    return;
  }
  var tracks = [];
  try {
    tracks = JSON.parse(root.getAttribute('data-ia-tracks') || '[]');
  } catch (e) {
    tracks = [];
  }
  if (!tracks.length) {
    return;
  }

  var fab = document.getElementById('iaFab');
  var panel = document.getElementById('iaPanel');
  var closeBtn = document.getElementById('iaClose');
  var listEl = document.getElementById('iaList');
  var playerHost = document.getElementById('iaPlayerHost');
  var audio = new Audio();
  audio.preload = 'metadata';
  var currentId = 0;
  var raf = 0;

  function fmt(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function find(id) {
    for (var i = 0; i < tracks.length; i++) {
      if (tracks[i].id === id) {
        return tracks[i];
      }
    }
    return null;
  }

  function renderPlayer(track) {
    playerHost.innerHTML =
      '<p class="ia-now-title">' + escapeHtml(track.title) + '</p>' +
      '<p class="ia-now-meta">#' + track.serial + ' · ' + escapeHtml(track.speaker || track.category_label) + '</p>' +
      '<div class="ia-controls">' +
      '<button type="button" class="ia-play" id="iaMainPlay" aria-label="প্লে/পজ">▶</button>' +
      '<div class="ia-wave" id="iaWave" aria-hidden="true">' +
      '<span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>' +
      '<span class="ia-time" id="iaTime">00:00 / ' + fmt(track.duration) + '</span></div>';
    var btn = document.getElementById('iaMainPlay');
    var wave = document.getElementById('iaWave');
    var timeEl = document.getElementById('iaTime');
    btn.addEventListener('click', function () {
      if (audio.paused) {
        audio.play().catch(function () {});
      } else {
        audio.pause();
      }
    });
    function tick() {
      if (!timeEl) {
        return;
      }
      timeEl.textContent = fmt(audio.currentTime) + ' / ' + fmt(audio.duration || track.duration);
      if (!audio.paused) {
        raf = requestAnimationFrame(tick);
      }
    }
    audio.addEventListener('play', function () {
      btn.textContent = '⏸';
      wave.classList.add('is-playing');
      cancelAnimationFrame(raf);
      tick();
    });
    audio.addEventListener('pause', function () {
      btn.textContent = '▶';
      wave.classList.remove('is-playing');
      cancelAnimationFrame(raf);
    });
    audio.addEventListener('ended', function () {
      btn.textContent = '▶';
      wave.classList.remove('is-playing');
    });
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function renderList() {
    listEl.innerHTML = '';
    tracks.forEach(function (t) {
      var li = document.createElement('li');
      li.className = 'ia-track' + (t.id === currentId ? ' is-active' : '');
      li.setAttribute('role', 'listitem');
      li.innerHTML =
        '<span class="ia-serial">#' + t.serial + '</span>' +
        '<div><p class="ia-track-title">' + escapeHtml(t.title) + '</p>' +
        '<p class="ia-track-sub">' + escapeHtml(t.speaker || t.category_label) + '</p></div>' +
        '<button type="button" class="ia-mini-play" aria-label="চালান">▶</button>';
      li.addEventListener('click', function (ev) {
        if (ev.target && ev.target.classList.contains('ia-mini-play')) {
          ev.stopPropagation();
        }
        playTrack(t.id);
      });
      listEl.appendChild(li);
    });
  }

  function playTrack(id) {
    var t = find(id);
    if (!t) {
      return;
    }
    if (currentId === id && !audio.paused) {
      audio.pause();
      return;
    }
    if (currentId !== id) {
      audio.pause();
      audio.src = t.url;
      currentId = id;
      renderPlayer(t);
      renderList();
    }
    audio.play().catch(function () {});
  }

  function openPanel() {
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
  }
  function closePanel() {
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
  }

  fab.addEventListener('click', function () {
    if (panel.hidden) {
      openPanel();
      if (!currentId && tracks[0]) {
        playTrack(tracks[0].id);
      }
    } else {
      closePanel();
    }
  });
  closeBtn.addEventListener('click', function () {
    closePanel();
  });

  renderList();
})();