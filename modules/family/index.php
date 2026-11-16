<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_home', 'fam_lead_home');

$contacts = fam_count('family_contacts');
$members = fam_count('family_members');
$appts = fam_today_appts();
$meds = fam_active_meds();
$low = fam_low_meds();
$medCost = fam_can_see('money') ? fam_month_expense('চিকিৎসা') + fam_month_expense('ওষুধ') : 0;
$totalCost = fam_can_see('money') ? fam_month_expense() : 0;
$shopN = fam_count('family_shopping', 'is_bought=0');
$bazaarOpen = 0;
try {
    $bz = fam_one("SELECT COUNT(*) n FROM family_bazaar_lines l JOIN family_bazaar_demand d ON d.id=l.demand_id WHERE d.ym='" . date('Y-m') . "' AND l.is_bought=0");
    $bazaarOpen = (int) ($bz['n'] ?? 0);
} catch (Throwable $e) {
    $bazaarOpen = 0;
}
$books = fam_count('family_books', "created_at>='" . date('Y-m-01') . "'");
$rems = fam_reminders();
$bdays = fam_birthdays_soon(21);
$notes = fam_rows('SELECT * FROM family_notes WHERE is_pinned=1 ORDER BY id DESC LIMIT 5');
$newBooks = fam_rows('SELECT * FROM family_books ORDER BY id DESC LIMIT 4');
$seeMoney = fam_can_see('money');
$barMax = max(1, $members, $contacts, count($meds), $shopN, count($rems), count($notes), count($bdays));
$en = function_exists('current_lang') && current_lang() === 'en';

function ifd_txt(string $bn, string $enStr): string
{
    global $en;
    return $en ? $enStr : $bn;
}
function ifd_kpi($href, $bn, $enLabel, $value, $bnHint, $enHint, $icon, $extra = '')
{
    echo '<a class="fam-kpi ' . esc_html($extra) . '" href="' . esc_html($href) . '">';
    echo '<span class="ifd-ico" aria-hidden="true">' . $icon . '</span>';
    echo '<span class="ifd-lbl">' . esc_html(ifd_txt($bn, $enLabel)) . '</span>';
    echo '<b>' . $value . '</b>';
    echo '<em>' . esc_html(ifd_txt($bnHint, $enHint)) . '</em>';
    echo '</a>';
}
function ifd_bar($bn, $enLabel, $n, $max)
{
    $pct = max(6, (int)round(($n / max(1, $max)) * 100));
    echo '<div class="ifd-bar-row"><span>' . esc_html(ifd_txt($bn, $enLabel)) . '</span>';
    echo '<div class="ifd-bar-track"><div class="ifd-bar-fill" style="width:' . $pct . '%"></div></div>';
    echo '<b>' . (int)$n . '</b></div>';
}
$apptHintBn = $appts ? (string)$appts[0]['title'] : 'আজ খালি';
$apptHintEn = $appts ? (string)$appts[0]['title'] : 'None today';
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker"><?php echo esc_html(ifd_txt('পারিবারিক ঘর', 'Family house')); ?></p>
  <h2><?php echo esc_html(ifd_txt('ড্যাশবোর্ড', 'Dashboard')); ?></h2>
  <p><?php echo esc_html(ifd_txt('বিক্রয় পাতার মতো নেভি রং — সদস্য, খরচ, ওষুধ ও আজকের কাজ দূর থেকে পড়া যায়।', 'Same navy as the sales desk — members, spend and today, readable from a distance.')); ?></p>
</header>

<div class="fam-kpis" id="if-dash-root" data-lang="<?php echo esc_html(function_exists('current_lang') ? current_lang() : 'bn'); ?>">
  <?php if ($seeMoney): ?>
  <?php ifd_kpi('/modules/family/expenses.php', 'এই মাসের খরচ', 'This month', esc_html(fam_money_txt($totalCost)), 'সব ক্যাটাগরি', 'All categories', '৳'); ?>
  <?php endif; ?>
  <?php ifd_kpi('/modules/family/members.php', 'সদস্য', 'Members', (int)$members, 'পরিবারের সদস্য', 'People in this home', '👥'); ?>
  <?php ifd_kpi('/modules/family/contacts.php', 'পরিচিত', 'Contacts', (int)$contacts, 'পুরো খাতা', 'Full contact book', '📒'); ?>
  <?php ifd_kpi('/modules/family/medicines.php', 'কম ওষুধ', 'Low meds', count($low), 'স্টক কম — কিনতে হবে', 'Stock is low', '💊', $low ? 'hot' : ''); ?>
  <?php if (!$seeMoney): ?>
  <?php ifd_kpi('/modules/family/reminders.php', 'রিমাইন্ডার', 'Reminders', count($rems), '৭–৩০ দিন', 'Next 7–30 days', '⏰'); ?>
  <?php endif; ?>
</div>

<div class="fam-kpis">
  <?php ifd_kpi('/modules/family/calendar.php', 'অ্যাপয়েন্টমেন্ট', 'Appointments', count($appts), $apptHintBn, $apptHintEn, '📅', 'gold'); ?>
  <?php ifd_kpi('/modules/family/medicines.php', 'চলমান ওষুধ', 'Active meds', count($meds), 'স্টক আছে', 'In stock', '🧪'); ?>
  <?php if ($seeMoney): ?>
  <?php ifd_kpi('/modules/family/reports.php', 'স্বাস্থ্য খরচ', 'Health spend', esc_html(fam_money_txt($medCost)), 'ওষুধ + ডাক্তার', 'Medicine + doctor', '🏥'); ?>
  <?php ifd_kpi('/modules/family/reminders.php', 'রিমাইন্ডার', 'Reminders', count($rems), '৭–৩০ দিন', 'Next 7–30 days', '⏰'); ?>
  <?php endif; ?>
  <?php ifd_kpi('/modules/family/bazaar.php', 'বাজার চাহিদা', 'Market list', (int)$bazaarOpen, 'এই মাসে কেনা বাকি', 'Still to buy this month', '🛒', 'gold'); ?>
  <?php ifd_kpi('/modules/family/shopping.php', 'কেনার তালিকা', 'To buy', (int)$shopN, 'এখনো কেনা হয়নি', 'Still on the list', '🛒'); ?>
  <?php ifd_kpi('/modules/family/library.php', 'নতুন বই', 'New books', (int)$books, 'এই মাসে', 'Bought this month', '📚'); ?>
  <?php ifd_kpi('/modules/family/calendar.php', 'জন্মদিন', 'Birthdays', count($bdays), '২১ দিনের মধ্যে', 'Within 21 days', '🎂', 'ok'); ?>
  <?php ifd_kpi('/modules/family/notes.php', 'পিন করা নোট', 'Pinned notes', count($notes), 'গুরুত্বপূর্ণ কাজ', 'Kept on top', '📌'); ?>
</div>

<div class="ifd-split">
  <section class="ifd-card">
    <h2><?php echo esc_html(ifd_txt('পারিবারিক পালস', 'Family pulse')); ?> <a href="/modules/family/reports.php"><?php echo esc_html(ifd_txt('বিস্তারিত', 'Details')); ?></a></h2>
    <div class="ifd-bars">
      <?php
      ifd_bar('সদস্য', 'Members', (int)$members, $barMax);
      ifd_bar('পরিচিত', 'Contacts', (int)$contacts, $barMax);
      ifd_bar('ওষুধ', 'Medicine', count($meds), $barMax);
      ifd_bar('কেনা বাকি', 'To buy', (int)$shopN, $barMax);
      ifd_bar('রিমাইন্ডার', 'Reminders', count($rems), $barMax);
      ifd_bar('জন্মদিন', 'Birthdays', count($bdays), $barMax);
      ?>
    </div>
  </section>
  <section class="ifd-card">
    <h2><?php echo esc_html(ifd_txt('আজকের সারাংশ', "Today's summary")); ?> <a href="/modules/family/calendar.php"><?php echo esc_html(ifd_txt('ক্যালেন্ডার', 'Calendar')); ?></a></h2>
    <div class="ifd-today">
      <a href="/modules/family/calendar.php"><span><?php echo esc_html(ifd_txt('অ্যাপয়েন্টমেন্ট', 'Appointments')); ?></span><b><?php echo count($appts); ?></b></a>
      <a href="/modules/family/shopping.php"><span><?php echo esc_html(ifd_txt('বাজার', 'Shopping')); ?></span><b><?php echo (int)$shopN; ?></b></a>
      <a href="/modules/family/notes.php"><span><?php echo esc_html(ifd_txt('নোট', 'Notes')); ?></span><b><?php echo count($notes); ?></b></a>
      <a href="/modules/family/library.php"><span><?php echo esc_html(ifd_txt('বই', 'Books')); ?></span><b><?php echo (int)$books; ?></b></a>
    </div>
  </section>
</div>

<div class="ifd-split">
  <section class="ifd-card fam-panel">
    <h2><?php echo esc_html(ifd_txt('আসন্ন রিমাইন্ডার', 'Upcoming reminders')); ?> <a href="/modules/family/reminders.php"><?php echo esc_html(ifd_txt('সব', 'All')); ?></a></h2>
    <?php if (!$rems) echo fam_empty(ifd_txt('এখন কোনো তাড়া নেই।', 'Nothing urgent right now.')); ?>
    <?php foreach (array_slice($rems, 0, 8) as $r): ?>
      <a class="ifd-move fam-row <?php echo $r['tone'] === 'hot' ? 'hot' : ''; ?>" href="<?php echo esc_html($r['href']); ?>">
        <span class="ifd-dot"><?php echo $r['tone'] === 'hot' ? '!' : '•'; ?></span>
        <div><strong><?php echo esc_html($r['title']); ?></strong><small><?php echo esc_html($r['meta']); ?></small></div>
        <span class="fam-mini"><?php echo $r['tone'] === 'hot' ? esc_html(ifd_txt('জরুরি', 'Urgent')) : esc_html(ifd_txt('দেখুন', 'View')); ?></span>
      </a>
    <?php endforeach; ?>
  </section>
  <section class="ifd-card fam-panel">
    <h2><?php echo esc_html(ifd_txt('জন্মদিন ও নতুন বই', 'Birthdays and new books')); ?> <a href="/modules/family/library.php"><?php echo esc_html(ifd_txt('লাইব্রেরি', 'Library')); ?></a></h2>
    <?php if ($bdays || $newBooks): ?>
    <table class="ifd-table fam-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php echo esc_html(ifd_txt('নাম', 'Name')); ?></th>
          <th><?php echo esc_html(ifd_txt('ধরন', 'Type')); ?></th>
          <th><?php echo esc_html(ifd_txt('সময়', 'When')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $n = 1; foreach ($bdays as $b): ?>
        <tr>
          <td><?php echo $n++; ?></td>
          <td><a href="/modules/family/member.php?id=<?php echo (int)$b['id']; ?>"><?php echo esc_html($b['name']); ?></a></td>
          <td><?php echo esc_html(ifd_txt('জন্মদিন', 'Birthday')); ?></td>
          <td><?php echo (int)$b['in_days'] === 0 ? esc_html(ifd_txt('আজ', 'Today')) : (int)$b['in_days'] . ' ' . esc_html(ifd_txt('দিন', 'days')); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($newBooks as $b): ?>
        <tr>
          <td><?php echo $n++; ?></td>
          <td><a href="/modules/family/library.php"><?php echo esc_html($b['title']); ?></a></td>
          <td><?php echo esc_html(ifd_txt('বই', 'Book')); ?></td>
          <td><?php echo esc_html($b['author'] ?: $b['subject'] ?: '—'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <?php echo fam_empty(ifd_txt('জন্মদিন বা নতুন বই নেই।', 'No birthdays or new books.'), '/modules/family/members.php', ifd_txt('সদস্য যোগ করুন', 'Add a member')); ?>
    <?php endif; ?>
  </section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
