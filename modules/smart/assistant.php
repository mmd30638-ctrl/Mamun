<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$hello = ame_smart_ask($db, 'অবস্থা');
$en = is_en();
$chips = $en
    ? ['How much sold today?', 'How much profit today?', 'Which stock is low?', 'Top products?', 'Who has dues?', 'What to buy this week?']
    : ['আজকের বিক্রি কত?', 'আজ কত লাভ?', 'কম স্টক কোনগুলো?', 'বেশি বিক্রি কোন পণ্য?', 'কার বকেয়া আছে?', 'এই সপ্তাহে কী কিনব?'];

ame_smart_open('smart_title_assistant', 'smart_lead_assistant');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">?</i>
      <small><?php echo $en ? 'Answers from' : 'উত্তর আসে'; ?></small>
      <b><?php echo $en ? 'Ledger' : 'খাতা'; ?></b>
      <em><?php echo $en ? 'Not a cloud AI' : 'ক্লাউড AI নয়'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">৳</i>
      <small><?php echo $en ? 'Can tell' : 'বলতে পারে'; ?></small>
      <b><?php echo $en ? 'Sales' : 'বিক্রি'; ?></b>
      <em><?php echo $en ? 'Today bills and total' : 'আজকের বিল ও যোগফল'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">✓</i>
      <small><?php echo $en ? 'Can tell' : 'বলতে পারে'; ?></small>
      <b><?php echo $en ? 'Profit' : 'লাভ'; ?></b>
      <em><?php echo $en ? 'After cost and discount' : 'খরচ ও ছাড় বাদ'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">△</i>
      <small><?php echo $en ? 'Can tell' : 'বলতে পারে'; ?></small>
      <b><?php echo $en ? 'Stock' : 'স্টক'; ?></b>
      <em><?php echo $en ? 'Low and top items' : 'কম স্টক ও টপ পণ্য'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">▤</i>
      <small><?php echo $en ? 'Can tell' : 'বলতে পারে'; ?></small>
      <b><?php echo $en ? 'Dues' : 'বকেয়া'; ?></b>
      <em><?php echo $en ? 'Who should pay' : 'কার কাছে নেওয়া বাকি'; ?></em>
    </article>
  </section>

  <section class="smt-dash-detail smt-ask-card">
    <header>
      <div>
        <h3><?php echo $en ? 'Ask the shop' : 'দোকানকে জিজ্ঞেস করুন'; ?></h3>
        <p><?php echo esc_html((string)($hello['text'] ?? t('smart_lead_assistant'))); ?></p>
      </div>
    </header>
    <div class="smt-chips">
      <?php foreach ($chips as $chip): ?>
        <button type="button" class="smt-chip" data-q="<?php echo htmlspecialchars($chip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($chip, ENT_QUOTES, 'UTF-8'); ?></button>
      <?php endforeach; ?>
    </div>
    <div class="smt-chat" id="smtChat">
      <div class="smt-bubble">
        <strong><?php echo htmlspecialchars((string)$hello['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <p><?php echo htmlspecialchars((string)$hello['text'], ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    </div>
    <form class="smt-ask" id="smtAskForm">
      <input type="text" id="smtAskQ" name="q" placeholder="<?php echo $en ? 'Example: how much sold today?' : 'যেমন: আজ কত বিক্রি হলো?'; ?>" autocomplete="off" required>
      <button class="smt-q-btn" type="submit"><?php echo $en ? 'Ask' : 'জিজ্ঞেস'; ?></button>
    </form>
  </section>
</div>
<script>
(function () {
  var form = document.getElementById('smtAskForm');
  var input = document.getElementById('smtAskQ');
  var chat = document.getElementById('smtChat');
  var token = window.AME_CSRF_TOKEN || '';

  function add(html, me) {
    var d = document.createElement('div');
    d.className = 'smt-bubble' + (me ? ' me' : '');
    d.innerHTML = html;
    chat.appendChild(d);
    d.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }

  function ask(q) {
    add('<p>' + q.replace(/</g, '&lt;') + '</p>', true);
    fetch('/modules/smart/ask.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({ q: q, mode: 'ask' })
    }).then(function (r) { return r.json(); }).then(function (d) {
      var cards = '';
      (d.cards || []).forEach(function (c) {
        cards += '<span class="smt-mini">' + (c.k || '') + ': ' + (c.v || '') + '</span> ';
      });
      add('<strong>' + (d.title || '') + '</strong><p>' + (d.text || d.message || 'উত্তর আসেনি') + '</p><div>' + cards + '</div>', false);
    }).catch(function () {
      add('<p>নেট বা সার্ভার সাড়া দেয়নি। পরে চেষ্টা করুন।</p>', false);
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var q = input.value.trim();
    if (!q) return;
    input.value = '';
    ask(q);
  });
  document.querySelectorAll('.smt-chip').forEach(function (b) {
    b.addEventListener('click', function () { ask(b.getAttribute('data-q') || b.textContent); });
  });
})();
</script>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
