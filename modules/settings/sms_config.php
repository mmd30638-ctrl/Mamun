<?php
require_once '../../includes/config.php';
require_once '../../includes/sms_functions.php';
admin_only();

ensure_sms_sim_schema($db);
if (trim((string)(ame_sms_settings($db)['device_token'] ?? '')) === '') {
    ame_sms_device_token(true);
}

$sms = ame_sms_settings($db);
$counts = ame_sms_outbox_counts($db);
$channel = ame_sms_channel($sms);
$token = (string)($sms['device_token'] ?? '');
$base = rtrim(ame_sms_public_base(), '/');
$worker = $base . '/sms_sim.php?token=' . rawurlencode($token);
$phone = ame_sms_gateway_phone($sms);
$connected = ame_sms_device_connected($sms);
$active = strtolower((string)($sms['status'] ?? '')) === 'active';
$sims = ame_sms_sims($db);
$flash = $_SESSION['sms_flash'] ?? '';
unset($_SESSION['sms_flash']);

include '../../partials/header.php';
?>
<style>
.sms-wrap{padding:15px;}
.sms-card{background:#fff;border-radius:16px;max-width:520px;margin:0 auto 14px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.08);}
.sms-topbar{display:flex;height:6px;}
.sms-topbar span{flex:1;}
.sms-c1{background:var(--brand-navy,#0B1E4D);} .sms-c2{background:var(--brand-accent,#1D9BF0);} .sms-c3{background:var(--brand-gold,#C9A227);}
.sms-header{text-align:center;padding:20px 15px 12px;}
.sms-header h3{margin:0;font-size:19px;color:#1B5E20;}
.sms-header small{color:#666;font-size:13px;display:block;margin-top:6px;line-height:1.55;}
.sms-body{padding:0 20px 20px;}
.sms-group{margin-bottom:14px;}
.sms-group label{display:block;font-weight:700;font-size:13px;color:#0B1E4D;margin-bottom:6px;}
.sms-group input,.sms-group select,.sms-group textarea{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;box-sizing:border-box;font-size:15px;background:#fafafa;}
.sms-submit{width:100%;padding:15px;border:none;border-radius:10px;background:linear-gradient(135deg,#0B1E4D,#1565c0);color:#fff;font-weight:800;font-size:16px;cursor:pointer;margin-top:4px;}
.sms-note{background:#FFF8E1;color:#5D4037;font-size:13px;line-height:1.6;padding:12px 14px;border-radius:10px;margin-bottom:14px;}
.sms-ok{background:#E8F5E9;color:#1B5E20;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px;font-weight:600;}
.sms-link{word-break:break-all;font-size:12px;background:#0B1E4D;color:#fff;padding:12px;border-radius:10px;display:block;text-decoration:none;line-height:1.45;}
.sms-stat{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;}
.sms-stat div{background:#f8fafc;border-radius:10px;padding:10px;text-align:center;}
.sms-stat b{display:block;font-size:1.2rem;color:#0B1E4D;}
.sms-status{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;margin-bottom:14px;font-weight:800;font-size:14px;}
.sms-status.on{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7;}
.sms-status.wait{background:#fff8e1;color:#9a5b00;border:1px solid #ffcc80;}
.sms-status.off{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;}
.sms-dot{width:12px;height:12px;border-radius:50%;background:#94a3b8;flex-shrink:0;}
.sms-status.on .sms-dot{background:#22c55e;box-shadow:0 0 0 4px #dcfce7;}
.sms-status.wait .sms-dot{background:#f59e0b;box-shadow:0 0 0 4px #fef3c7;}
.ghost{display:block;width:100%;margin-top:8px;padding:12px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-weight:700;cursor:pointer;}
.sms-back{display:block;text-align:center;margin:8px 0 18px;color:#666;text-decoration:none;font-size:13px;}
.sms-copy{margin-top:8px;width:100%;padding:10px;border-radius:8px;border:1px dashed #90caf9;background:#e3f2fd;color:#0D47A1;font-weight:700;cursor:pointer;}
details.adv{margin-top:12px;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;background:#f8fafc;}
details.adv summary{cursor:pointer;font-weight:700;color:#334155;font-size:13px;}
.mode{display:flex;gap:8px;margin:10px 0;}
.mode label{flex:1;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font-size:13px;font-weight:700;background:#fff;}
</style>
<div class="sms-wrap">
  <div class="sms-card">
    <div class="sms-topbar"><span class="sms-c1"></span><span class="sms-c2"></span><span class="sms-c3"></span></div>
    <div class="sms-header">
      <h3>📱 ব্যক্তিগত সিম এসএমএস</h3>
      <small>শুধু আপনার সিম থাকা মোবাইল নম্বর দিন। ক্রেডিট সেই সিমেই থাকবে — OTP, বাকি রিমাইন্ডার সেই ফোন থেকে যাবে।</small>
    </div>
    <div class="sms-body">
      <?php if ($flash): ?><div class="sms-ok"><?php echo esc_html($flash); ?></div><?php endif; ?>

      <?php if (!$active): ?>
      <div class="sms-status off"><span class="sms-dot"></span>বন্ধ — নিচে নম্বর দিয়ে চালু করুন</div>
      <?php elseif ($connected): ?>
      <div class="sms-status on"><span class="sms-dot"></span>সংযুক্ত — সিম ফোন অনলাইন<?php echo $phone !== '' ? ' (' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . ')' : ''; ?></div>
      <?php else: ?>
      <div class="sms-status wait"><span class="sms-dot"></span>অপেক্ষা — সিম ফোনে নিচের লিংক খুলুন<?php echo $phone !== '' ? ' (' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . ')' : ''; ?></div>
      <?php endif; ?>

      <div class="sms-stat">
        <div><b><?php echo (int)$counts['pending']; ?></b><span>অপেক্ষা</span></div>
        <div><b><?php echo (int)$counts['sending']; ?></b><span>পাঠানো হচ্ছে</span></div>
        <div><b><?php echo (int)$counts['sent']; ?></b><span>সিম থেকে গেছে</span></div>
      </div>

      <form action="process_sms.php" method="POST" id="smsSimpleForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="sms_action" value="save_simple">
        <input type="hidden" name="channel" value="sim">

        <div class="sms-group">
          <label>সিম থাকা মোবাইল নম্বর</label>
          <input type="tel" name="gateway_phone" inputmode="tel" autocomplete="tel"
                 placeholder="01XXXXXXXXX"
                 value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="sms-group">
          <label>স্ট্যাটাস</label>
          <select name="status">
            <option value="active" <?php if ($active) echo 'selected'; ?>>চালু</option>
            <option value="inactive" <?php if (!$active) echo 'selected'; ?>>বন্ধ</option>
          </select>
        </div>

        <div class="sms-note">
          <b>কীভাবে চালাবেন</b><br>
          ১) উপরের নম্বর সেভ করুন (যে ফোনে সিম আছে)।<br>
          ২) <b>সেই ফোনের</b> Chrome/Safari-এ নিচের লিংক খুলুন ও ট্যাব খোলা রাখুন।<br>
          ৩) মেসেজ এলে «সিম দিয়ে পাঠান» চাপুন — ফোনের মেসেজিং অ্যাপ সিম ক্রেডিট ব্যবহার করবে।
        </div>

        <div class="sms-group">
          <label>সিম ফোনের লিংক</label>
          <a class="sms-link" id="smsWorkerLink" href="<?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($worker, ENT_QUOTES, 'UTF-8'); ?></a>
          <button type="button" class="sms-copy" id="smsCopyBtn">📋 লিংক কপি করুন</button>
        </div>

        <button type="submit" class="sms-submit">সেভ করুন</button>
      </form>

      <form action="process_sms.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="sms_action" value="rotate">
        <button type="submit" class="ghost" onclick="return confirm('পুরনো সিম লিংক কাজ করবে না। নতুন লিংক সিম ফোনে আবার খুলবেন?');">সিম লিংক নতুন করুন</button>
      </form>

      <details class="adv">
        <summary>উন্নত অপশন (API / SMSGate) — সাধারণত দরকার নেই</summary>
        <form action="process_sms.php" method="POST" style="margin-top:12px;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="sms_action" value="save">
          <input type="hidden" name="gateway_phone" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="status" value="<?php echo $active ? 'active' : 'inactive'; ?>">
          <div class="mode">
            <label><input type="radio" name="channel" value="sim" <?php echo $channel === 'sim' ? 'checked' : ''; ?>> সিম ফোন</label>
            <label><input type="radio" name="channel" value="api" <?php echo $channel === 'api' ? 'checked' : ''; ?>> গেটওয়ে API</label>
          </div>
          <div class="sms-group">
            <label>SMSGate ক্লাউড URL (ঐচ্ছিক)</label>
            <input type="text" name="smsgate_url" value="<?php echo htmlspecialchars($sms['smsgate_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="sms-group">
            <label>SMSGate ইউজারনেম</label>
            <input type="text" name="smsgate_user" value="<?php echo htmlspecialchars($sms['smsgate_user'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
          </div>
          <div class="sms-group">
            <label>SMSGate পাসওয়ার্ড</label>
            <input type="password" name="smsgate_pass" value="" autocomplete="new-password" placeholder="<?php echo ($sms['smsgate_pass'] ?? '') !== '' ? '•••• সেভ আছে' : 'ঐচ্ছিক'; ?>">
          </div>
          <div class="sms-group">
            <label>API URL</label>
            <input type="text" name="api_url" value="<?php echo htmlspecialchars($sms['api_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="sms-group">
            <label>API Key</label>
            <input type="password" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo !empty($sms['has_key']) ? '•••• সেভ আছে' : 'API কী'; ?>">
          </div>
          <div class="sms-group">
            <label>Sender ID</label>
            <input type="text" name="sender_id" value="<?php echo htmlspecialchars($sms['sender_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <button type="submit" class="ghost">উন্নত সেটিংস সেভ</button>
        </form>
      </details>
    </div>
  </div>

  <div class="sms-card">
    <div class="sms-header">
      <h3><?php echo te('sms_sim_list_title'); ?></h3>
      <small><?php echo te('sms_sim_list_lead'); ?></small>
    </div>
    <div class="sms-body">
      <?php foreach ($sims as $sim):
          $simLink = $base . '/sms_sim.php?token=' . rawurlencode((string)$sim['token']);
          $simOn = ame_sms_sim_connected($sim);
      ?>
      <div class="sms-note" style="margin-bottom:10px;">
        <b><?php echo htmlspecialchars($sim['label'], ENT_QUOTES, 'UTF-8'); ?></b>
        <?php if ($sim['is_default']): ?><span> · <?php echo te('sms_sim_default_badge'); ?></span><?php endif; ?>
        <div><?php echo htmlspecialchars($sim['phone'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo te('sms_sim_slot_n', ['n' => (string)$sim['slot']]); ?>
          · <?php echo $simOn ? te('sms_sim_online') : te('sms_sim_offline'); ?></div>
        <a class="sms-link" href="<?php echo htmlspecialchars($simLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($simLink, ENT_QUOTES, 'UTF-8'); ?></a>
        <button type="button" class="sms-copy" data-copy="<?php echo htmlspecialchars($simLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo te('sms_sim_copy'); ?></button>
        <div class="mode">
          <?php if (!$sim['is_default'] && $sim['status'] === 'active'): ?>
          <form action="process_sms.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sms_action" value="default_sim">
            <input type="hidden" name="sim_id" value="<?php echo (int)$sim['id']; ?>">
            <button type="submit" class="ghost"><?php echo te('sms_sim_make_default'); ?></button>
          </form>
          <?php endif; ?>
          <form action="process_sms.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sms_action" value="rotate_sim">
            <input type="hidden" name="sim_id" value="<?php echo (int)$sim['id']; ?>">
            <button type="submit" class="ghost" onclick="return confirm(<?php echo json_encode(t('sms_sim_rotate_confirm'), JSON_UNESCAPED_UNICODE); ?>);"><?php echo te('sms_sim_new_link'); ?></button>
          </form>
          <form action="process_sms.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sms_action" value="remove_sim">
            <input type="hidden" name="sim_id" value="<?php echo (int)$sim['id']; ?>">
            <button type="submit" class="ghost" onclick="return confirm(<?php echo json_encode(t('sms_sim_remove_confirm'), JSON_UNESCAPED_UNICODE); ?>);"><?php echo te('sms_sim_remove'); ?></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>

      <form action="process_sms.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="sms_action" value="save_sim">
        <div class="sms-group">
          <label><?php echo te('sms_sim_name'); ?></label>
          <input type="text" name="label" maxlength="80" required placeholder="<?php echo te('sms_sim_name_ph'); ?>">
        </div>
        <div class="sms-group">
          <label><?php echo te('sms_sim_phone'); ?></label>
          <input type="tel" name="phone" inputmode="tel" required placeholder="01XXXXXXXXX">
        </div>
        <div class="sms-group">
          <label><?php echo te('sms_sim_slot'); ?></label>
          <select name="slot">
            <option value="1"><?php echo te('sms_sim_slot_n', ['n' => '1']); ?></option>
            <option value="2"><?php echo te('sms_sim_slot_n', ['n' => '2']); ?></option>
          </select>
        </div>
        <button type="submit" class="sms-submit"><?php echo te('sms_sim_add'); ?></button>
      </form>
    </div>
  </div>

  <div class="sms-card">
    <div class="sms-body" style="padding-top:18px;">
      <form action="process_sms.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="sms_action" value="test">
        <div class="sms-group">
          <label><?php echo te('sms_sim_which'); ?></label>
          <select name="sim_id">
            <?php foreach ($sims as $sim): if ($sim['status'] !== 'active') continue; ?>
            <option value="<?php echo (int)$sim['id']; ?>" <?php echo $sim['is_default'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($sim['label'] . ' · ' . $sim['phone'] . ' · ' . t('sms_sim_slot_n', ['n' => (string)$sim['slot']]), ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sms-group">
          <label>টেস্ট নম্বর</label>
          <input type="text" name="test_mobile" inputmode="tel" placeholder="01XXXXXXXXX" required>
        </div>
        <div class="sms-group">
          <label>টেস্ট মেসেজ</label>
          <textarea name="test_message" rows="2">APONSHOP সিম এসএমএস টেস্ট</textarea>
        </div>
        <button type="submit" class="sms-submit">টেস্ট এসএমএস কিউ করুন</button>
      </form>
    </div>
  </div>
  <a href="sms_log.php" class="sms-back">এসএমএস লগ</a>
  <a href="dashboard_settings.php" class="sms-back">← ফিরে যান</a>
</div>
<script>
(function () {
  document.querySelectorAll('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var text = el.getAttribute('data-copy') || '';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
      } else {
        prompt('', text);
      }
    });
  });
  var btn = document.getElementById('smsCopyBtn');
  var link = document.getElementById('smsWorkerLink');
  if (!btn || !link) return;
  btn.addEventListener('click', function () {
    var t = link.getAttribute('href') || link.textContent || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () {
        btn.textContent = '✓ কপি হয়েছে';
        setTimeout(function () { btn.textContent = '📋 লিংক কপি করুন'; }, 1600);
      });
    } else {
      prompt('লিংক কপি করুন:', t);
    }
  });
})();
</script>
<?php include '../../partials/footer.php'; ?>
