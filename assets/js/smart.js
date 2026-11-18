'use strict';

(function () {
  var app = document.getElementById('smtApp');
  var btn = document.getElementById('smtThemeBtn');
  if (!app) return;

  var key = 'ame-smart-theme';
  var saved = localStorage.getItem(key);
  if (saved === 'dark') {
    app.setAttribute('data-theme', 'dark');
  }

  if (btn) {
    btn.addEventListener('click', function () {
      var next = app.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      if (next === 'dark') {
        app.setAttribute('data-theme', 'dark');
      } else {
        app.removeAttribute('data-theme');
      }
      localStorage.setItem(key, next);
    });
  }
})();
