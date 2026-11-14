(function () {
  var root = document.getElementById('iaAdmin');
  if (!root) {
    return;
  }
  var csrf = root.getAttribute('data-csrf') || '';
  var statusEl = document.getElementById('iaStatus');
  var bodyEl = document.getElementById('iaTableBody');
  var api = '/modules/settings/islamic_audio_api.php';

  function say(msg) {
    if (statusEl) {
      statusEl.textContent = msg;
    }
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function load() {
    say('লোড হচ্ছে…');
    return fetch(api + '?action=list', { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          throw new Error(data.error || 'লোড ব্যর্থ');
        }
        render(data.tracks || []);
        say((data.tracks || []).length + 'টি অডিও');
      })
      .catch(function (e) {
        say(e.message || 'ত্রুটি');
      });
  }

  function render(tracks) {
    bodyEl.innerHTML = '';
    tracks.forEach(function (t) {
      var tr = document.createElement('tr');
      var td0 = document.createElement('td');
      var ser = document.createElement('input');
      ser.type = 'number';
      ser.min = '1';
      ser.value = String(t.serial);
      ser.className = 'ia-serial-inp';
      ser.style.width = '56px';
      td0.appendChild(ser);
      tr.appendChild(td0);
      var td1 = document.createElement('td');
      var titleInp = document.createElement('input');
      titleInp.type = 'text';
      titleInp.value = t.title || '';
      titleInp.dataset.field = 'title';
      titleInp.style.width = '100%';
      td1.appendChild(titleInp);
      tr.appendChild(td1);
      var td2 = document.createElement('td');
      var spInp = document.createElement('input');
      spInp.type = 'text';
      spInp.value = t.speaker || '';
      spInp.dataset.field = 'speaker';
      td2.appendChild(spInp);
      tr.appendChild(td2);
      var td3 = document.createElement('td');
      td3.textContent = t.category_label || '';
      tr.appendChild(td3);
      var td4 = document.createElement('td');
      td4.textContent = String(t.size_mb || 0) + (t.orig_mb > t.size_mb ? ' ←' + t.orig_mb : '');
      tr.appendChild(td4);
      var td5 = document.createElement('td');
      var pill = document.createElement('span');
      pill.className = 'ia-pill ' + (t.published ? 'on' : 'off');
      pill.textContent = t.published ? 'চালু' : 'বন্ধ';
      td5.appendChild(pill);
      tr.appendChild(td5);
      var td6 = document.createElement('td');
      td6.className = 'ia-row-actions';
      ['save', 'toggle', 'delete'].forEach(function (act) {
        var b = document.createElement('button');
        b.type = 'button';
        b.dataset.act = act;
        b.dataset.id = String(t.id);
        if (act === 'delete') {
          b.className = 'danger';
          b.textContent = 'ডিলিট';
        } else if (act === 'toggle') {
          b.dataset.pub = t.published ? '1' : '0';
          b.textContent = t.published ? 'আনপাবলিশ' : 'পাবলিশ';
        } else {
          b.textContent = 'সেভ';
        }
        td6.appendChild(b);
      });
      tr.appendChild(td6);
      bodyEl.appendChild(tr);
    });
  }

  function post(action, fd) {
    fd.append('action', action);
    fd.append('csrf_token', csrf);
    say('প্রসেসিং…');
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          throw new Error(data.error || 'ব্যর্থ');
        }
        if (data.tracks) {
          render(data.tracks);
          say('তালিকা আপডেট (' + data.tracks.length + ')');
        } else {
          load();
        }
      });
  }

  bodyEl.addEventListener('click', function (ev) {
    var btn = ev.target.closest('button[data-act]');
    if (!btn) {
      return;
    }
    var id = btn.getAttribute('data-id');
    var act = btn.getAttribute('data-act');
    if (act === 'delete') {
      if (!window.confirm('এই অডিও মুছবেন?')) {
        return;
      }
      var fd = new FormData();
      fd.append('id', id);
      post('delete', fd).catch(function (e) {
        say(e.message);
      });
      return;
    }
    if (act === 'toggle') {
      var fd2 = new FormData();
      fd2.append('id', id);
      fd2.append('is_published', btn.getAttribute('data-pub') === '1' ? '0' : '1');
      post('update', fd2).catch(function (e) {
        say(e.message);
      });
      return;
    }
    if (act === 'save') {
      var row = btn.closest('tr');
      var titleInp = row.querySelector('[data-field="title"]');
      var spInp = row.querySelector('[data-field="speaker"]');
      var serInp = row.querySelector('.ia-serial-inp');
      var fd3 = new FormData();
      fd3.append('id', id);
      fd3.append('title', titleInp ? titleInp.value : '');
      fd3.append('speaker', spInp ? spInp.value : '');
      fd3.append('display_serial', serInp ? serInp.value : '');
      post('update', fd3).catch(function (e) {
        say(e.message);
      });
    }
  });

  var formUp = document.getElementById('iaFormUpload');
  var formUrl = document.getElementById('iaFormUrl');
  var drop = document.getElementById('iaDrop');
  var fileInp = document.getElementById('iaFile');
  var pick = document.getElementById('iaPick');
  var fileName = document.getElementById('iaFileName');

  if (pick && fileInp) {
    pick.addEventListener('click', function () {
      fileInp.click();
    });
    fileInp.addEventListener('change', function () {
      if (fileInp.files && fileInp.files[0]) {
        fileName.hidden = false;
        fileName.textContent = fileInp.files[0].name;
      }
    });
  }

  if (drop && fileInp) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.classList.add('is-drag');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.classList.remove('is-drag');
      });
    });
    drop.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInp.files = e.dataTransfer.files;
        fileName.hidden = false;
        fileName.textContent = e.dataTransfer.files[0].name;
      }
    });
  }

  if (formUp) {
    formUp.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!fileInp || !fileInp.files || !fileInp.files[0]) {
        say('ফাইল বাছুন');
        return;
      }
      var fd = new FormData(formUp);
      fd.append('audio', fileInp.files[0]);
      post('upload', fd)
        .then(function () {
          formUp.reset();
          if (fileName) {
            fileName.hidden = true;
          }
        })
        .catch(function (err) {
          say(err.message);
        });
    });
  }

  if (formUrl) {
    formUrl.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(formUrl);
      post('import_url', fd)
        .then(function () {
          formUrl.reset();
        })
        .catch(function (err) {
          say(err.message);
        });
    });
  }

  var ytAuto = root.getAttribute('data-yt-auto') === '1';
  var formYt = document.getElementById('iaFormYt');
  if (formYt) {
    formYt.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!ytAuto || formYt.getAttribute('data-disabled') === '1') {
        say('শেয়ারড হোস্টিং: PC তে MP3 নামিয়ে「ফাইল আপলোড」ব্যবহার করুন। OFFLINE_YOUTUBE_BN.txt দেখুন।');
        return;
      }
      if (!window.confirm('YouTube থেকে অডিও ডাউনলোড ও কম্প্রেস করতে কয়েক মিনিট লাগতে পারে। চালাবেন?')) {
        return;
      }
      var fd = new FormData(formYt);
      post('import_youtube', fd)
        .then(function (data) {
          formYt.reset();
          if (data.imported) {
            say(data.imported + 'টি YouTube অডিও যোগ হয়েছে');
          }
        })
        .catch(function (err) {
          say(err.message || 'YouTube ইম্পোর্ট ব্যর্থ');
        });
    });
  }

  var copyBtn = document.getElementById('iaHostCopy');
  var copyDone = document.getElementById('iaHostCopyDone');
  var ticketTa = document.getElementById('iaHostTicket');
  if (copyBtn && ticketTa) {
    copyBtn.addEventListener('click', function () {
      var text = ticketTa.value;
      function done() {
        if (copyDone) {
          copyDone.hidden = false;
          setTimeout(function () {
            copyDone.hidden = true;
          }, 4000);
        }
        say('হোস্টিং মেসেজ কপি হয়েছে — সাপোর্ট টিকেটে পেস্ট করুন');
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(fallback);
      } else {
        fallback();
      }
      function fallback() {
        ticketTa.focus();
        ticketTa.select();
        try {
          document.execCommand('copy');
          done();
        } catch (e) {
          say('অটো কপি ব্যর্থ — মাউস দিয়ে টেক্সট সিলেক্ট করে কপি করুন');
        }
      }
    });
  }

  load();
})();