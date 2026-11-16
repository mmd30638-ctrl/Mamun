<?php
require_once '../../includes/config.php';
admin_only();
ensure_provenance_schema($db);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$checks = [
    ['CSRF টোকেন', 'CSRF token', !empty($_SESSION['csrf_token']), 'ফর্ম আটকানো আছে', 'Forms are locked down'],
    ['৮ ঘণ্টা নিষ্ক্রিয় লগআউট', '8-hour idle logout', true, 'সেশন কুকি httponly', 'Session cookie httponly'],
    ['HTTPS কুকি', 'HTTPS cookie', $https, $https ? 'সিকিউর ফ্ল্যাগ চালু' : 'এখন HTTP — লাইভে HTTPS চালান', $https ? 'Secure flag on' : 'HTTP now — turn on HTTPS live'],
    ['প্রুভেন্যান্স টেবিল', 'Provenance table', true, (string)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM ledger_provenance", 0) . 'টা স্ট্যাম্প', (string)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM ledger_provenance", 0) . ' stamps'],
    ['আইডেমপোটেন্সি', 'Idempotency', true, 'ডাবল সেল/পেমেন্ট আটকানো', 'Double sale/payment blocked'],
    ['ক্লাউড AI কী নেই', 'No cloud AI key', true, 'রায় দোকানের খাতা থেকে — বাইরে পাঠানো হয় না', 'Verdicts stay in the shop ledger — nothing is sent out'],
];
include '../../partials/header.php';
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
?>
<link rel="stylesheet" href="/assets/css/trust-vault.808.css">
<script src="/assets/js/device-display.js?v=8.08"></script>

<div class="tv-wrap">
  <header class="tv-vault">
    <p class="tv-kicker">AI trust · Next-gen shop security</p>
    <h1><?php echo ame_pair('কেন বিশ্বাস করবেন', 'Why trust this shop'); ?> — <em><?php echo htmlspecialchars('খোলা হিসাব', ENT_QUOTES, 'UTF-8'); ?></em></h1>
    <p><?php echo ame_pair(
        'এজেন্টের স্কোর গোপন নয়। কোয়ান্টাম বা কনফিডেনশিয়াল এনক্লেভ এখানে নেই — Hostinger-এ যা সত্যি চলে, সেটাই।',
        'Agent scores are not secret. No quantum box or confidential enclave here — only what actually runs on Hostinger.'
    ); ?></p>
  </header>

  <p class="tv-sec"><?php echo ame_pair('সুরক্ষা অবস্থা', 'Security posture'); ?></p>
  <div class="tv-matrix">
    <?php foreach ($checks as $i => $c): ?>
    <article class="tv-seal">
      <span class="tv-no"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
      <div>
        <div class="nm"><?php echo ame_pair($c[0], $c[1]); ?></div>
        <div class="meta"><?php echo ame_pair($c[3], $c[4]); ?></div>
      </div>
      <div class="tv-lamp <?php echo $c[2] ? 'ok' : 'late'; ?>"><?php echo $c[2] ? ame_pair('ঠিক', 'OK') : ame_pair('দেখুন', 'Check'); ?></div>
    </article>
    <?php endforeach; ?>
  </div>

  <section class="tv-dossier">
    <h2><?php echo ame_pair('এজেন্ট স্কোর কীভাবে', 'How agent scores work'); ?></h2>
    <div class="tv-formula">
      <article>
        <b><?php echo ame_pair('কালেকশন', 'Collection'); ?></b>
        <p><?php echo ame_pair('পার হওয়া দিন × ২ + বকেয়া/৫০০ + নীরব দিন/২ — সর্বোচ্চ ১০০।', 'Days overdue × 2 + due/500 + silent days/2 — cap 100.'); ?></p>
      </article>
      <article>
        <b><?php echo ame_pair('স্টক', 'Stock'); ?></b>
        <p><?php echo ame_pair('১৪ দিনের বিক্রির গতি vs স্টক কভার। জরুরি হলে স্কোর বাড়ে।', '14-day sales velocity vs stock cover. Urgency raises the score.'); ?></p>
      </article>
      <article>
        <b><?php echo ame_pair('ক্যাশ', 'Cash'); ?></b>
        <p><?php echo ame_pair('আদায়/বিক্রির অনুপাত + ফাঁক + পার হওয়া বকেয়া।', 'Collection/sales ratio + gap + overdue dues.'); ?></p>
      </article>
      <article>
        <b><?php echo ame_pair('ডোমেইন মডেল', 'Domain model'); ?></b>
        <p><?php echo ame_pair('কোনো স্কোরই বাইরের মডেলে যায় না। এটাই ডোমেইন-স্পেসিফিক মডেল।', 'No score leaves the shop. This is the domain-specific model.'); ?></p>
      </article>
    </div>
  </section>

  <nav class="tv-links" aria-label="Trust links">
    <a href="/modules/agents/index.php"><?php echo ame_pair('এজেন্ট ডেস্ক', 'Agent desk'); ?></a>
    <a href="/modules/provenance/index.php"><?php echo ame_pair('প্রুভেন্যান্স', 'Provenance'); ?></a>
    <a href="/modules/settings/system_health.php"><?php echo ame_pair('সিস্টেম হেলথ', 'System health'); ?></a>
  </nav>
</div>
<?php include '../../partials/footer.php'; ?>
