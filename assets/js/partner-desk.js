(function () {
  'use strict';
  var page = document.querySelector('.pa-page');
  if (!page || page.getAttribute('data-ame-simple') === '1') return;
  page.setAttribute('data-ame-simple', '1');

  var hero = page.querySelector('.pa-hero');
  var cards = page.querySelector('.pa-cards');
  if (hero && cards) hero.insertAdjacentElement('afterend', cards);

  page.querySelectorAll('.pa-panel .sub').forEach(function (sub) {
    if (!sub.parentNode || sub.closest('details')) return;
    var details = document.createElement('details');
    details.className = 'pa-help';
    var summary = document.createElement('summary');
    summary.textContent = 'ব্যাখ্যা';
    details.appendChild(summary);
    sub.parentNode.insertBefore(details, sub);
    details.appendChild(sub);
  });

  var start = page.querySelector('.pa-start');
  if (start && start.parentNode === page) page.appendChild(start);

  function tidyName(text) {
    return String(text || '').replace(/\s+/g, ' ').trim();
  }
  function keepName(name) {
    return name.length > 1 && name !== 'লেজার' && name !== 'এডিট';
  }
  var people = {};
  function remember(id, name) {
    id = String(id || '').replace(/[^\d]/g, '');
    name = tidyName(name);
    if (!id || id === '0' || !keepName(name) || people[id]) return;
    people[id] = name;
  }
  page.querySelectorAll('a[href*="edit="]').forEach(function (link) {
    var found = String(link.getAttribute('href') || '').match(/[?&]edit=(\d+)/);
    if (found) remember(found[1], link.textContent);
  });
  page.querySelectorAll('input[name="partner_id"]').forEach(function (input) {
    if (String(input.getAttribute('type') || '') !== 'radio') return;
    var label = input.closest('label');
    var title = label ? label.querySelector('b') : null;
    remember(input.getAttribute('value'), title ? title.textContent : '');
  });
  page.querySelectorAll('select[name="partner_id"] option').forEach(function (opt) {
    remember(opt.getAttribute('value'), opt.textContent);
  });

  var extraForm = null;
  var picks = null;
  page.querySelectorAll('form').forEach(function (node) {
    var action = node.querySelector('input[name="action"]');
    var type = node.querySelector('input[name="type"]');
    if (!extraForm && action && action.getAttribute('value') === 'save_capital' && type && type.getAttribute('value') === 'investment') {
      extraForm = node;
    }
  });
  if (extraForm && !page.querySelector('.pa-extra')) {
    var partner = extraForm.querySelector('select[name="partner_id"]');
    if (partner) {
      Object.keys(people).forEach(function (id) {
        var exists = false;
        Array.prototype.forEach.call(partner.options, function (opt) {
          if (opt.getAttribute('value') === id) exists = true;
        });
        if (exists) return;
        var opt = document.createElement('option');
        opt.setAttribute('value', id);
        opt.textContent = people[id];
        partner.appendChild(opt);
      });
      picks = document.createElement('div');
      picks.className = 'pa-extra-picks';
      picks.setAttribute('role', 'listbox');
      picks.setAttribute('aria-label', 'পার্টনার');
      function mark(id) {
        Array.prototype.forEach.call(partner.options, function (opt) {
          opt.selected = opt.getAttribute('value') === id;
        });
        picks.querySelectorAll('.pa-extra-pick').forEach(function (btn) {
          var on = btn.getAttribute('data-partner') === id;
          btn.classList.toggle('is-on', on);
          btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }
      Array.prototype.forEach.call(partner.options, function (opt) {
        var id = opt.getAttribute('value');
        if (!id || id === '0') return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pa-extra-pick';
        btn.setAttribute('data-partner', id);
        btn.setAttribute('role', 'option');
        var name = document.createElement('span');
        name.textContent = tidyName(opt.textContent);
        btn.appendChild(name);
        btn.addEventListener('click', function () {
          mark(id);
        });
        picks.appendChild(btn);
      });
      var chosen = '';
      Array.prototype.forEach.call(partner.options, function (opt) {
        if (!chosen && opt.selected) chosen = opt.getAttribute('value') || '';
      });
      if (!chosen && partner.options.length) chosen = partner.options[0].getAttribute('value') || '';
      partner.classList.add('pa-extra-native');
      partner.setAttribute('aria-hidden', 'true');
      mark(chosen);
      extraForm.insertBefore(picks, partner);
    }
    var card = document.createElement('section');
    card.className = 'pa-extra pa-panel';
    var title = document.createElement('h2');
    title.textContent = 'অতিরিক্ত টাকা';
    var note = document.createElement('p');
    note.className = 'pa-extra-note';
    note.textContent = 'ক্রেডিটে ব্যবসা টাকা নেয়, ডেবিটে ব্যবসা টাকা দেয়। পার্টনার বাছুন, তারিখ ও টাকা দিন। গ্রাহক বা সাপ্লায়ারের হিসাব বদলায় না।';
    var amount = extraForm.querySelector('input[name="amount"]');
    var when = extraForm.querySelector('input[name="transaction_date"]');
    var memo = extraForm.querySelector('input[name="note"]');
    var button = extraForm.querySelector('button[type="submit"]');
    var typeInput = extraForm.querySelector('input[name="type"]');
    var ways = document.createElement('div');
    ways.className = 'pa-extra-ways';
    ways.setAttribute('role', 'group');
    ways.setAttribute('aria-label', 'টাকা দেওয়া বা নেওয়া');
    function setWay(kind) {
      if (typeInput) typeInput.setAttribute('value', kind === 'debit' ? 'capital_withdrawal' : 'investment');
      ways.querySelectorAll('.pa-extra-way').forEach(function (node) {
        var on = node.getAttribute('data-way') === kind;
        node.classList.toggle('is-on', on);
        node.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (button) {
        button.textContent = kind === 'debit' ? 'এই তারিখে টাকা দিন' : 'এই তারিখে টাকা নিন';
        button.classList.toggle('is-debit', kind === 'debit');
      }
    }
    function addWay(kind, label, hint) {
      var node = document.createElement('button');
      node.type = 'button';
      node.className = 'pa-extra-way';
      node.setAttribute('data-way', kind);
      var strong = document.createElement('strong');
      strong.textContent = label;
      var small = document.createElement('small');
      small.textContent = hint;
      node.appendChild(strong);
      node.appendChild(small);
      node.addEventListener('click', function () { setWay(kind); });
      ways.appendChild(node);
    }
    addWay('credit', '＋ ক্রেডিট · নেওয়া', 'ব্যবসা পার্টনারের কাছ থেকে টাকা নিচ্ছে');
    addWay('debit', '− ডেবিট · দেওয়া', 'ব্যবসা পার্টনারকে টাকা দিচ্ছে');
    if (picks && picks.parentNode === extraForm) extraForm.insertBefore(ways, picks);
    else extraForm.insertBefore(ways, extraForm.firstChild);
    setWay('credit');
    if (amount) {
      amount.placeholder = 'টাকার পরিমাণ';
      amount.setAttribute('aria-label', 'টাকার পরিমাণ');
    }
    if (when) when.setAttribute('aria-label', 'তারিখ');
    if (memo) memo.placeholder = 'কারণ, ঐচ্ছিক';
    extraForm.classList.add('pa-extra-form');
    card.appendChild(title);
    card.appendChild(note);
    card.appendChild(extraForm);
    var heroBox = page.querySelector('.pa-hero');
    if (heroBox && heroBox.parentNode === page) heroBox.insertAdjacentElement('afterend', card);
    else page.insertBefore(card, page.firstChild);
  }
})();
