<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/sms_functions.php';

$token = trim((string)($_GET['token'] ?? ''));
$ok = ame_sms_token_ok($token);
$workerSim = ($ok && isset($db) && $db instanceof mysqli) ? ame_sms_sim_from_token($db, $token) : null;
$shopTitle = function_exists('shop_name') ? shop_name() : 'APONSHOP';
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang() === 'en' ? 'en' : 'bn'; ?>" class="ame-viewport-shell">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0B1E4D">
<title>সিম এসএমএস — <?php echo htmlspecialchars($shopTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<style>
:root{--navy:#0B1E4D;--accent:#1565c0;--ok:#15803d;--warn:#b45309;--bg:#eef3fb;}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:#0f172a;}
.wrap{max-width:480px;margin:0 auto;padding:16px 14px 40px;}
.card{background:#fff;border-radius:18px;padding:18px;box-shadow:0 8px 24px rgba(11,30,77,.08);margin-bottom:12px;}
h1{font-size:1.2rem;margin:0 0 6px;color:var(--navy);}
p{margin:0 0 10px;font-size:14px;line-height:1.55;color:#475569;}
.stat{display:flex;gap:8px;margin:12px 0;}
.pill{flex:1;text-align:center;background:#f8fafc;border-radius:12px;padding:10px;}
.pill b{display:block;font-size:1.3rem;color:var(--navy);}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#94a3b8;margin-right:6px;}
.dot.on{background:#22c55e;box-shadow:0 0 0 4px #dcfce7;}
.btn{display:block;width:100%;border:0;border-radius:12px;padding:14px;font-weight:800;font-size:15px;cursor:pointer;margin-top:8px;}
.btn-go{background:linear-gradient(135deg,#0B1E4D,#1565c0);color:#fff;}
.btn-ok{background:#15803d;color:#fff;}
.btn-no{background:#fff;color:#b91c1c;border:1px solid #fecaca;}
.msg{border:1px solid #e2e8f0;border-radius:12px;padding:12px;margin-top:10px;background:#f8fafc;}
.msg .n{font-weight:800;color:var(--navy);}
.err{background:#fef2f2;color:#991b1b;padding:12px;border-radius:12px;}
.hint{font-size:12px;color:#64748b;}
</style>
<link rel="stylesheet" href="/assets/css/ame-viewport-shell.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.80', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/ame-responsive-pro.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.80', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ame-sms-sim">
<div class="wrap">
  <div class="card">
    <h1><?php echo te('sms_sim_worker_title'); ?></h1>
    <p><?php echo te('sms_sim_worker_lead'); ?></p>
    <?php if ($ok && !empty($workerSim)): ?>
      <p><b><?php echo te('sms_sim_sending_as'); ?></b> <?php echo htmlspecialchars($workerSim['label'] . ' · ' . $workerSim['phone'] . ' · ' . t('sms_sim_slot_n', ['n' => (string)$workerSim['slot']]), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if (!$ok): ?>
      <div class="err"><?php echo te('sms_sim_worker_bad'); ?></div>
    <?php else: ?>
      <p><span class="dot" id="dot"></span><span id="live">সংযোগ হচ্ছে…</span></p>
      <div class="stat">
        <div class="pill"><b id="cPend">—</b><span><?php echo te('sms_sim_pending'); ?></span></div>
        <div class="pill"><b id="cSend">—</b><span><?php echo te('sms_sim_sending'); ?></span></div>
        <div class="pill"><b id="cSent">—</b><span><?php echo te('sms_sim_sent'); ?></span></div>
      </div>
      <div id="box"></div>
      <p class="hint"><?php echo te('sms_sim_worker_hint'); ?></p>
    <?php endif; ?>
  </div>
</div>
<?php if ($ok): ?>
<script>
(function () {
  var token = <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>;
  var api = '/sms_sim_api.php';
  var sim = <?php echo json_encode($workerSim ? ['label' => $workerSim['label'], 'phone' => $workerSim['phone'], 'slot' => (int)$workerSim['slot']] : null, JSON_UNESCAPED_UNICODE); ?>;
  var copy = <?php echo json_encode([
      'send' => t('sms_sim_worker_send'),
      'sent' => t('sms_sim_worker_sent'),
      'retry' => t('sms_sim_worker_retry'),
      'empty' => t('sms_sim_worker_empty'),
      'live' => t('sms_sim_worker_live'),
      'offline' => t('sms_sim_worker_offline'),
      'net' => t('sms_sim_worker_net'),
      'pick' => t('sms_sim_worker_pick'),
  ], JSON_UNESCAPED_UNICODE); ?>;
  var current = null;
  var busy = false;

  function $(id) { return document.getElementById(id); }
  function smsLink(num, text) {
    return 'sms:' + num + '?body=' + encodeURIComponent(text);
  }

  function render(msg) {
    var box = $('box');
    if (!msg) {
      box.innerHTML = '<p class="hint">' + escapeHtml(copy.empty) + '</p>';
      return;
    }
    var slot = (msg.sim_slot || (sim && sim.slot) || 1);
    var who = (msg.sim_phone || (sim && sim.phone) || '');
    box.innerHTML = '<div class="msg"><div class="n">' + escapeHtml(msg.e164 || msg.mobile) + '</div><div>' +
      escapeHtml(msg.message) + '</div><p class="hint">' + escapeHtml(copy.pick.replace(':slot', String(slot)).replace(':phone', who)) + '</p></div>' +
      '<button class="btn btn-go" id="sendBtn">' + escapeHtml(copy.send) + '</button>' +
      '<button class="btn btn-ok" id="okBtn" style="display:none">' + escapeHtml(copy.sent) + '</button>' +
      '<button class="btn btn-no" id="noBtn" style="display:none">' + escapeHtml(copy.retry) + '</button>';
    $('sendBtn').onclick = function () {
      window.location.href = smsLink(msg.e164 || msg.mobile, msg.message);
      $('sendBtn').style.display = 'none';
      $('okBtn').style.display = 'block';
      $('noBtn').style.display = 'block';
    };
    $('okBtn').onclick = function () { ack(msg.id, 'sent'); };
    $('noBtn').onclick = function () { ack(msg.id, 'pending'); };
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  function counts(c) {
    if (!c) return;
    $('cPend').textContent = c.pending;
    $('cSend').textContent = c.sending;
    $('cSent').textContent = c.sent;
  }

  function ping() {
    fetch(api + '?action=ping&token=' + encodeURIComponent(token), {cache:'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        $('dot').classList.toggle('on', !!d.ok);
        $('live').textContent = d.ok ? copy.live : copy.offline;
        counts(d.counts);
      }).catch(function () {
        $('dot').classList.remove('on');
        $('live').textContent = copy.net;
      });
  }

  function pull() {
    if (busy || current) return;
    busy = true;
    fetch(api + '?action=pull&limit=1&token=' + encodeURIComponent(token), {cache:'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        counts(d.counts);
        if (d.messages && d.messages[0]) {
          current = d.messages[0];
          render(current);
        } else if (!current) {
          render(null);
        }
      }).catch(function () {}).finally(function () { busy = false; });
  }

  function ack(id, status) {
    var body = new URLSearchParams({action:'ack', token:token, id:String(id), status:status, note:'sim_page'});
    fetch(api, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
      .then(function (r) { return r.json(); })
      .then(function () {
        current = null;
        render(null);
        pull();
        ping();
      });
  }

  if (navigator.wakeLock) {
    navigator.wakeLock.request('screen').catch(function () {});
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && navigator.wakeLock) {
        navigator.wakeLock.request('screen').catch(function () {});
      }
    });
  }
  ping();
  pull();
  setInterval(ping, 8000);
  setInterval(pull, 5000);
})();
</script>
<?php endif; ?>
</body>
</html>
