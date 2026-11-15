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
  var fab = document.getElementById('iaFab');
  var panel = document.getElementById('iaPanel');
  var closeBtn = document.getElementById('iaClose');
  var listEl = document.getElementById('iaList');
  var playerHost = document.getElementById('iaPlayerHost');
  var emptyEl = document.getElementById('iaEmpty');
  var hasTracks = tracks.length > 0;
  if (!hasTracks && emptyEl) {
    emptyEl.hidden = false;
  }
  if (!hasTracks && playerHost) {
    playerHost.hidden = true;
  }

  var audio = document.createElement('audio');
  audio.preload = 'auto';
  audio.setAttribute('playsinline', '');
  audio.setAttribute('webkit-playsinline', '');
  audio.volume = 1;
  var currentId = 0;
  var raf = 0;
  var seekDragging = false;
  var ui = { btn: null, wave: null, timeEl: null, seek: null, load: null };

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

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function setLoading(on) {
    if (fab) {
      fab.classList.toggle('ia-fab--loading', on);
    }
    if (ui.btn) {
      ui.btn.classList.toggle('ia-play--loading', on);
    }
    if (ui.load) {
      ui.load.hidden = !on;
    }
  }

  function durMax() {
    var d = audio.duration;
    if (d && isFinite(d) && d > 0) {
      return d;
    }
    var t = find(currentId);
    return t && t.duration ? t.duration : 1;
  }

  function updateSeekFromAudio() {
    if (!ui.seek || seekDragging) {
      return;
    }
    var max = durMax();
    ui.seek.max = String(max);
    ui.seek.value = String(Math.min(max, audio.currentTime || 0));
    if (ui.timeEl) {
      ui.timeEl.textContent = fmt(audio.currentTime) + ' / ' + fmt(max);
    }
  }

  function bindSeek(seek) {
    seek.addEventListener('input', function () {
      seekDragging = true;
      var v = parseFloat(seek.value) || 0;
      audio.currentTime = v;
      if (ui.timeEl) {
        ui.timeEl.textContent = fmt(v) + ' / ' + fmt(durMax());
      }
    });
    seek.addEventListener('change', function () {
      seekDragging = false;
    });
    seek.addEventListener('pointerdown', function () {
      seekDragging = true;
    });
    seek.addEventListener('pointerup', function () {
      seekDragging = false;
    });
  }

  function tick() {
    updateSeekFromAudio();
    if (!audio.paused) {
      raf = requestAnimationFrame(tick);
    }
  }

  audio.addEventListener('loadstart', function () {
    setLoading(true);
  });
  audio.addEventListener('waiting', function () {
    setLoading(true);
  });
  audio.addEventListener('canplay', function () {
    setLoading(false);
    updateSeekFromAudio();
  });
  audio.addEventListener('playing', function () {
    setLoading(false);
  });
  audio.addEventListener('loadedmetadata', function () {
    updateSeekFromAudio();
  });
  audio.addEventListener('timeupdate', updateSeekFromAudio);
  audio.addEventListener('play', function () {
    if (ui.btn) {
      ui.btn.textContent = '⏸';
    }
    if (ui.wave) {
      ui.wave.classList.add('is-playing');
    }
    cancelAnimationFrame(raf);
    tick();
  });
  audio.addEventListener('pause', function () {
    if (ui.btn) {
      ui.btn.textContent = '▶';
    }
    if (ui.wave) {
      ui.wave.classList.remove('is-playing');
    }
    cancelAnimationFrame(raf);
    updateSeekFromAudio();
  });
  audio.addEventListener('ended', function () {
    if (ui.btn) {
      ui.btn.textContent = '▶';
    }
    if (ui.wave) {
      ui.wave.classList.remove('is-playing');
    }
    cancelAnimationFrame(raf);
  });
  audio.addEventListener('error', function () {
    setLoading(false);
    if (ui.timeEl) {
      ui.timeEl.textContent = 'লোড ব্যর্থ — আবার চেষ্টা করুন';
    }
  });

  function renderPlayer(track) {
    if (!playerHost) {
      return;
    }
    playerHost.hidden = false;
    var dur = track.duration || 1;
    playerHost.innerHTML =
      '<p class="ia-now-title">' + escapeHtml(track.title) + '</p>' +
      '<p class="ia-now-meta">#' + track.serial + ' · ' + escapeHtml(track.speaker || track.category_label) + '</p>' +
      '<div class="ia-seek-wrap">' +
      '<input type="range" class="ia-seek" id="iaSeek" min="0" max="' + dur + '" value="0" step="0.25" ' +
      'aria-label="সামনে বা পিছনে টানুন">' +
      '<span class="ia-load-spin" id="iaLoadSpin" hidden aria-hidden="true"></span></div>' +
      '<div class="ia-controls">' +
      '<button type="button" class="ia-play" id="iaMainPlay" aria-label="প্লে/পজ">▶</button>' +
      '<div class="ia-wave" id="iaWave" aria-hidden="true">' +
      '<span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>' +
      '<span class="ia-time" id="iaTime">00:00 / ' + fmt(dur) + '</span></div>';
    ui.btn = document.getElementById('iaMainPlay');
    ui.wave = document.getElementById('iaWave');
    ui.timeEl = document.getElementById('iaTime');
    ui.seek = document.getElementById('iaSeek');
    ui.load = document.getElementById('iaLoadSpin');
    bindSeek(ui.seek);
    ui.btn.addEventListener('click', function () {
      if (audio.paused) {
        audio.play().catch(function () {});
      } else {
        audio.pause();
      }
    });
  }

  function renderList() {
    if (!listEl) {
      return;
    }
    listEl.innerHTML = '';
    tracks.forEach(function (t) {
      var li = document.createElement('li');
      var loadingCls = t.id === currentId && !audio.paused && audio.readyState < 3 ? ' ia-track--loading' : '';
      li.className = 'ia-track' + (t.id === currentId ? ' is-active' : '') + loadingCls;
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
      renderList();
      return;
    }
    if (currentId !== id) {
      audio.pause();
      setLoading(true);
      audio.src = t.url;
      currentId = id;
      renderPlayer(t);
      renderList();
    }
    audio.play().catch(function () {
      setLoading(false);
    });
  }

  function openPanel() {
    panel.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
  }
  function closePanel() {
    panel.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
  }

  if (fab && panel) {
    fab.addEventListener('click', function () {
      if (panel.hidden) {
        openPanel();
        if (hasTracks && !currentId && tracks[0]) {
          playTrack(tracks[0].id);
        }
      } else {
        closePanel();
      }
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      closePanel();
    });
  }

  if (hasTracks) {
    renderList();
  }
})();