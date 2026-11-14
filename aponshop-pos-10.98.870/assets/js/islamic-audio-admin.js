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
  var pickMobile = document.getElementById('iaPickMobile');
  var fileQueue = document.getElementById('iaFileQueue');
  var uploadBtn = document.getElementById('iaUploadBtn');
  var uploadProgress = document.getElementById('iaUploadProgress');
  var uploadBar = document.getElementById('iaUploadBar');
  var uploadTxt = document.getElementById('iaUploadTxt');
  var uploading = false;

  function renderFileQueue() {
    if (!fileInp || !fileQueue || !uploadBtn) {
      return;
    }
    var files = fileInp.files ? Array.prototype.slice.call(fileInp.files) : [];
    fileQueue.innerHTML = '';
    if (files.length === 0) {
      fileQueue.hidden = true;
      uploadBtn.disabled = true;
      return;
    }
    fileQueue.hidden = false;
    uploadBtn.disabled = uploading;
    files.forEach(function (f, i) {
      var li = document.createElement('li');
      li.textContent = (i + 1) + '. ' + f.name + ' (' + (f.size < 1048576 ? Math.round(f.size / 1024) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB') + ')';
      fileQueue.appendChild(li);
    });
    uploadBtn.textContent = files.length > 1 ? files.length + 'টি আপলোড ও অপ্টিমাইজ' : 'আপলোড ও অপ্টিমাইজ';
  }

  function openFilePicker() {
    if (fileInp) {
      fileInp.click();
    }
  }

  if (pick) {
    pick.addEventListener('click', openFilePicker);
  }
  if (pickMobile) {
    pickMobile.addEventListener('click', openFilePicker);
  }
  if (fileInp) {
    fileInp.addEventListener('change', renderFileQueue);
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
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        try {
          fileInp.files = e.dataTransfer.files;
        } catch (err) {
          say('একাধিক ফাইল ড্রপ করতে「PC ফাইল」বাটন ব্যবহার করুন');
        }
        renderFileQueue();
      }
    });
  }

  function setProgress(pct, label) {
    if (!uploadProgress || !uploadBar || !uploadTxt) {
      return;
    }
    uploadProgress.hidden = false;
    uploadBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    uploadTxt.textContent = label;
  }

  function uploadOneFile(file, form, useMultiTitle) {
    var fd = new FormData();
    fd.append('action', 'upload');
    fd.append('csrf_token', csrf);
    fd.append('audio', file);
    fd.append('speaker', form.speaker ? form.speaker.value : '');
    fd.append('category', form.category ? form.category.value : 'general');
    fd.append('is_published', form.is_published && form.is_published.checked ? '1' : '0');
    if (!useMultiTitle && form.title && form.title.value.trim() !== '') {
      fd.append('title', form.title.value.trim());
    }
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  if (formUp) {
    formUp.addEventListener('submit', function (e) {
      e.preventDefault();
      if (uploading) {
        return;
      }
      if (!fileInp || !fileInp.files || !fileInp.files.length) {
        say('ফাইল বাছুন');
        return;
      }
      var files = Array.prototype.slice.call(fileInp.files);
      if (files.length > 40) {
        say('একবারে সর্বোচ্চ ৪০টি ফাইল');
        return;
      }
      uploading = true;
      uploadBtn.disabled = true;
      var done = 0;
      var okCount = 0;
      var errs = [];
      var multi = files.length > 1;

      function next() {
        if (done >= files.length) {
          uploading = false;
          if (uploadProgress) {
            uploadProgress.hidden = true;
          }
          renderFileQueue();
          if (okCount > 0) {
            load().then(function () {
              say(okCount + 'টি অডিও আপলোড হয়েছে' + (errs.length ? ' (' + errs.length + ' ব্যর্থ)' : ''));
            });
            formUp.reset();
            if (fileInp) {
              fileInp.value = '';
            }
            renderFileQueue();
          } else {
            say(errs[0] || 'আপলোড ব্যর্থ');
          }
          return;
        }
        var f = files[done];
        var pct = Math.round((done / files.length) * 100);
        setProgress(pct, (done + 1) + '/' + files.length + ' — ' + f.name);
        say('আপলোড ' + (done + 1) + '/' + files.length + '…');
        uploadOneFile(f, formUp, multi)
          .then(function (data) {
            if (!data.ok) {
              throw new Error(data.error || 'ব্যর্থ');
            }
            okCount++;
            if (data.tracks) {
              render(data.tracks);
            }
          })
          .catch(function (err) {
            errs.push(f.name + ': ' + (err.message || 'ব্যর্থ'));
          })
          .finally(function () {
            done++;
            next();
          });
      }
      next();
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

  load();
})();