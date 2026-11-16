<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';
$en = is_en();
$cards = [
    ['1', $en ? 'Smart assistant' : 'স্মার্ট সহকারী', $en ? 'Ask in Bangla — today sales, profit, low stock, top products. Rule-based, no cloud AI.' : 'বাংলায় জিজ্ঞেস → আজকের বিক্রি, লাভ, কম স্টক, টপ পণ্য। নিয়ম-ভিত্তিক, কোনো ক্লাউড AI নয়।', '/modules/smart/assistant.php'],
    ['2', $en ? 'Stock forecast' : 'স্টক অনুমান', $en ? 'Last 14 days of sales say which product will run out and how many to buy.' : 'গত ১৪ দিনের বিক্রি থেকে কোন পণ্য কখন শেষ হতে পারে, কতটা কিনতে হবে।', '/modules/smart/stock.php'],
    ['3', $en ? 'Voice POS' : 'ভয়েস POS', $en ? 'POS mic: “Sell Rahim 3 nuts at 30” → Draft Bill. No sale without confirm.' : 'POS-এ মাইক: «রহিমকে ৩টা নাট ৩০ টাকা করে বিক্রি করো» → Draft Bill। Confirm ছাড়া সেল হয় না।', '/modules/sales/pos.php'],
    ['4', $en ? 'Camera / barcode' : 'ক্যামেরা / বারকোড', $en ? 'POS camera scans a code to search. New barcodes stay on the inventory page.' : 'POS ক্যামেরা দিয়ে কোড স্ক্যান করে সার্চ। নতুন বারকোড আগের ইনভেন্টরি পেজেই।', '/modules/sales/pos.php'],
    ['5', $en ? 'Customer & pay icons' : 'ক্রেতা ও পেমেন্ট', $en ? 'Small POS icons open the existing customer and payment panels.' : 'POS-এর নিচের ছোট আইকন আগের কাস্টমার ও পেমেন্ট প্যানেল খোলে।', '/modules/sales/pos.php'],
    ['6', $en ? 'Digital receipt' : 'ডিজিটাল রসিদ', $en ? 'After a sale: print, save image, share. It also lands in customer history.' : 'সেল হলে আগের ইনভয়েস: প্রিন্ট, ছবি নামানো, শেয়ার। ক্রেতার হিস্টরিতে নিজে যোগ হয়।', '/modules/sales/history.php'],
    ['7', $en ? 'Purchase intelligence' : 'ক্রয় বুদ্ধি', $en ? 'Low stock → suggestion + supplier compare + this-week buy list.' : 'কম স্টক → সাজেশন + সাপ্লায়ার দাম তুলনা + «এই সপ্তাহে কিনুন» তালিকা।', '/modules/smart/purchase.php'],
    ['8', $en ? 'Profit account' : 'লাভ হিসাব', $en ? 'Each sale: cost → sell → discount → other cost → real profit.' : 'প্রতি সেল: ক্রয় → বিক্রি → ডিসকাউন্ট → অন্যান্য খরচ → প্রকৃত লাভ।', '/modules/smart/profit.php'],
    ['9', $en ? 'Customer insight' : 'ক্রেতা বুদ্ধি', $en ? 'Spend, dues, last visit. VIP / regular / new / inactive.' : 'মোট কেনাকাটা, টাকা, বকেয়া, শেষ কেনাকাটা। VIP / নিয়মিত / নতুন / নিষ্ক্রিয়।', '/modules/smart/customers.php'],
    ['10', $en ? 'Business calendar' : 'ব্যবসা ক্যালেন্ডার', $en ? 'Dues, supplier pay, purchase, sale, stock check — one calendar.' : 'বকেয়া, সাপ্লায়ার পেমেন্ট, ক্রয়, সেল, স্টক চেক — এক ক্যালেন্ডারে।', '/modules/smart/calendar.php'],
    ['11', $en ? 'Family home' : 'পারিবারিক ঘর', $en ? 'Separate module — members, doctor, medicine, market, expense. No shop mix.' : 'আলাদা মডিউল — সদস্য, ডাক্তার, ওষুধ, বাজার, খরচ। ব্যবসার ডেটা মিশে না।', '/modules/family/index.php'],
    ['12', $en ? 'Roles, offline, home' : 'অনুমতি, অফলাইন, হোম', $en ? 'Roles stay split. Offline POS and payment queue. Home still shows today’s numbers.' : 'রোল আলাদা। নেট না থাকলে POS ও পেমেন্ট কিউ। হোমেই আজকের সংখ্যা।', '/modules/smart/privacy.php'],
];

ame_smart_open('smart_title_map', 'smart_lead_map');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">◎</i>
      <small><?php echo $en ? 'Modules' : 'মডিউল'; ?></small>
      <b>12</b>
      <em><?php echo $en ? 'Mapped on this page' : 'এই পেজে ম্যাপ'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">?</i>
      <small><?php echo $en ? 'Assistant' : 'সহকারী'; ?></small>
      <b><?php echo $en ? 'Rules' : 'নিয়ম'; ?></b>
      <em><?php echo $en ? 'No cloud AI' : 'ক্লাউড AI নয়'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">🛒</i>
      <small>POS</small>
      <b><?php echo $en ? 'Same' : 'আগের'; ?></b>
      <em><?php echo $en ? 'Sale engine unchanged' : 'সেল ইঞ্জিন অপরিবর্তিত'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">⌂</i>
      <small><?php echo $en ? 'Family' : 'পরিবার'; ?></small>
      <b><?php echo $en ? 'Separate' : 'আলাদা'; ?></b>
      <em><?php echo $en ? 'Does not mix shop data' : 'দোকানের ডেটা মিশে না'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">↺</i>
      <small><?php echo $en ? 'Offline' : 'অফলাইন'; ?></small>
      <b><?php echo $en ? 'Queue' : 'কিউ'; ?></b>
      <em><?php echo $en ? 'POS and payments' : 'POS ও পেমেন্ট'; ?></em>
    </article>
  </section>

  <section class="smt-map-grid">
    <?php foreach ($cards as [$num, $title, $lead, $href]): ?>
    <a class="smt-dash-panel smt-map-card" href="<?php echo esc_html($href); ?>">
      <span><?php echo esc_html($num); ?></span>
      <h3><?php echo esc_html($title); ?></h3>
      <p><?php echo esc_html($lead); ?></p>
    </a>
    <?php endforeach; ?>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
