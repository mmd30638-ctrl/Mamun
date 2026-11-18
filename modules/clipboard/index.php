<?php
require_once '../../includes/config.php';
require_once '../../includes/clipboard.php';
ame_clip_schema($db);
$lists = ame_clip_lists($db);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include '../../partials/header.php';
$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
?>
<style>
.clip-wrap { max-width: 880px; margin: 0 auto; padding: 12px; }
.clip-wrap h1 { margin: 0 0 6px; color: #0f172a; font-size: 22px; }
.clip-card { background: #fff; border: 2px solid #0f766e; border-radius: 16px; padding: 14px; margin: 0 0 12px; }
.clip-card h2 { margin: 0 0 8px; font-size: 16px; }
.clip-card.note h2 { color: #7c3aed; }
.clip-card.bulk h2 { color: #1d4ed8; }
.clip-card.log h2 { color: #b45309; }
.clip-wrap label { display: flex; flex-direction: column; gap: 4px; font-weight: 700; color: #0e7490; margin: 0 0 8px; }
.clip-wrap input, .clip-wrap select, .clip-wrap textarea { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px; font: inherit; background: #fff; color: #0f172a; }
.clip-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.clip-actions button { background: #fff !important; border: 2px solid #15803d !important; color: #15803d !important; -webkit-text-fill-color: #15803d !important; border-radius: 12px; padding: 10px 14px; font-weight: 800; cursor: pointer; }
.clip-actions button.alt { border-color: #1d4ed8 !important; color: #1d4ed8 !important; -webkit-text-fill-color: #1d4ed8 !important; }
.clip-row { display: flex; gap: 8px; align-items: flex-start; padding: 8px 0; border-top: 1px solid #e2e8f0; }
.clip-row b { color: #0f172a; }
.clip-row span { display: block; color: #64748b; font-size: 12px; }
.clip-log { color: #334155; font-size: 13px; margin: 0; padding: 4px 0; }
</style>
<div class="clip-wrap">
    <h1>কপি-পোস্ট</h1>
    <p>একসাথে অনেক সারি বেছে কপি করুন। ড্রাফট রেখে পরে পোস্ট করা যায়। স্টক, বকেয়া ও ইনভয়েস কপি হয় না।</p>
    <section class="clip-card bulk">
        <h2>বাল্ক কপি</h2>
        <label>ধরন
            <select id="clipKind">
                <option value="product">পণ্য</option>
                <option value="customer">ক্রেতা</option>
                <option value="note">নোট</option>
            </select>
        </label>
        <label>শিরোনাম<input id="clipTitle" placeholder="যেমন: ব্যাটারির কপি"></label>
        <label id="clipSearchLabel">খুঁজুন<input id="clipQ" placeholder="নাম লিখুন"></label>
        <div id="clipHits"></div>
        <label id="clipNoteLabel" hidden>নোট<textarea id="clipNote" rows="4"></textarea></label>
        <div class="clip-actions">
            <button type="button" id="clipDraft" class="alt">ড্রাফট রাখুন</button>
            <button type="button" id="clipPost">পোস্ট করুন</button>
        </div>
        <p id="clipMsg"></p>
    </section>
    <section class="clip-card">
        <h2 style="color:#0f766e;">ড্রাফট</h2>
        <?php if (!$lists['drafts']): ?>
        <p>এখনো ড্রাফট নেই।</p>
        <?php else: foreach ($lists['drafts'] as $d): ?>
        <div class="clip-row">
            <div><b><?php echo $h($d['title']); ?></b><span><?php echo $h($d['kind'] . ' · ' . $d['status'] . ' · ' . $d['created_at']); ?></span></div>
            <?php if (($d['status'] ?? '') === 'draft'): ?>
            <button type="button" data-post="<?php echo (int)$d['id']; ?>" style="margin-left:auto;background:#fff;border:2px solid #15803d;color:#15803d;border-radius:10px;font-weight:800;padding:8px 10px;">পোস্ট</button>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </section>
    <section class="clip-card log">
        <h2>অ্যাক্টিভিটি লগ</h2>
        <?php if (!$lists['logs']): ?>
        <p>এখনো লগ নেই।</p>
        <?php else: foreach ($lists['logs'] as $log): ?>
        <p class="clip-log"><?php echo $h($log['created_at'] . ' · ' . $log['action'] . ' · ' . $log['detail']); ?></p>
        <?php endforeach; endif; ?>
    </section>
</div>
<script>
(function () {
  var csrf = <?php echo json_encode((string)$_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE); ?>;
  var kind = document.getElementById('clipKind');
  var hits = document.getElementById('clipHits');
  var picked = {};
  function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); }); }
  function syncKind() {
    var note = kind.value === 'note';
    document.getElementById('clipSearchLabel').hidden = note;
    document.getElementById('clipNoteLabel').hidden = !note;
    hits.hidden = note;
  }
  kind.addEventListener('change', function () { picked = {}; hits.innerHTML = ''; syncKind(); });
  syncKind();
  var timer;
  document.getElementById('clipQ').addEventListener('input', function () {
    clearTimeout(timer);
    var q = this.value.trim();
    timer = setTimeout(function () {
      if (kind.value === 'note' || q.length < 1) return;
      fetch('api.php?action=search&kind=' + encodeURIComponent(kind.value) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          hits.innerHTML = (data.rows || []).map(function (row) {
            var on = picked[row.id] ? ' checked' : '';
            return '<label class="clip-row"><input type="checkbox" data-id="' + row.id + '"' + on + '><span><b>' + esc(row.name) + '</b><span>' + esc(row.meta) + '</span></span></label>';
          }).join('') || '<p>কিছু মেলেনি।</p>';
        });
    }, 220);
  });
  hits.addEventListener('change', function (ev) {
    var box = ev.target.closest('input[data-id]');
    if (!box) return;
    var id = box.getAttribute('data-id');
    if (box.checked) picked[id] = true; else delete picked[id];
  });
  function send(action, extra) {
    var body = new FormData();
    body.set('csrf_token', csrf);
    body.set('action', action);
    body.set('kind', kind.value);
    body.set('title', document.getElementById('clipTitle').value);
    body.set('note', document.getElementById('clipNote').value);
    body.set('ids', JSON.stringify(Object.keys(picked)));
    Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
    return fetch('api.php?action=' + encodeURIComponent(action), { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function done(res) {
    var msg = document.getElementById('clipMsg');
    if (!res.ok) { msg.textContent = res.error || 'হয়নি'; return; }
    msg.textContent = res.message || 'রাখা হয়েছে';
    setTimeout(function () { location.reload(); }, 500);
  }
  document.getElementById('clipDraft').addEventListener('click', function () { send('draft').then(done); });
  document.getElementById('clipPost').addEventListener('click', function () { send('post').then(done); });
  document.querySelectorAll('[data-post]').forEach(function (btn) {
    btn.addEventListener('click', function () { send('post', { draft_id: btn.getAttribute('data-post') }).then(done); });
  });
})();
</script>
<?php include '../../partials/footer.php'; ?>
