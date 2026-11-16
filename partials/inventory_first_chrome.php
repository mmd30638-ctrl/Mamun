<?php
$if_en = function_exists('current_lang') && current_lang() === 'en';
?>
<div class="if-hero" aria-hidden="false">
    <p class="if-hero-kicker">Inventory First</p>
    <p class="if-hero-sub">Sustainable Stock for a Brighter Tomorrow</p>
    <h2 class="if-hero-title"><?php echo $if_en ? 'Smart sales system, green future' : 'স্মার্ট বিক্রয় সিস্টেম, সবুজ ভবিষ্যৎ'; ?></h2>
    <p class="if-hero-tag"><?php echo $if_en ? 'Eco-friendly stock, profitable business, a lasting tomorrow.' : 'পরিবেশবান্ধব পণ্য, লাভজনক ব্যবসা, টেকসই আগামী।'; ?></p>
    <div class="if-hero-4p">
        <span>PEOPLE</span>
        <span>PRODUCT</span>
        <span>PLANET</span>
        <span>PROFIT</span>
    </div>
</div>

<aside class="if-rail if-rail-left" aria-label="Inventory First">
    <div class="if-rail-item"><b><?php echo $if_en ? 'People' : 'মানুষ'; ?></b><small><?php echo $if_en ? 'Good goods, good life' : 'ভালো পণ্য ভালো জীবন'; ?></small></div>
    <div class="if-rail-item"><b><?php echo $if_en ? 'Product' : 'পণ্য'; ?></b><small><?php echo $if_en ? 'Reliable and eco-friendly' : 'নির্ভরযোগ্য ও পরিবেশবান্ধব'; ?></small></div>
    <div class="if-rail-item"><b><?php echo $if_en ? 'Planet' : 'পৃথিবী'; ?></b><small><?php echo $if_en ? 'A greener next generation' : 'একটি সবুজ আগামী প্রজন্মের জন্য'; ?></small></div>
    <div class="if-rail-item"><b><?php echo $if_en ? 'Profit' : 'লাভ'; ?></b><small><?php echo $if_en ? 'Lasting success' : 'টেকসই উন্নয়নে দীর্ঘমেয়াদি সফলতা'; ?></small></div>
</aside>

<aside class="if-rail if-rail-right" aria-label="Shop tools">
    <a class="if-rail-item" href="/index.php"><b><?php echo $if_en ? 'Smart ops' : 'স্মার্ট ব্যবস্থাপনা'; ?></b><small><?php echo $if_en ? 'Fast, simple, correct' : 'সহজ পদ্ধতি, দ্রুত কাজ'; ?></small></a>
    <a class="if-rail-item" href="/modules/sales/pos.php"><b><?php echo $if_en ? 'Sales bill' : 'বিক্রয় বিল'; ?></b><small><?php echo $if_en ? 'Goods and service bills' : 'পণ্যের বিক্রয় ও সার্ভিস বিল'; ?></small></a>
    <a class="if-rail-item" href="/modules/customers/payment-receive.php"><b><?php echo $if_en ? 'Accounts' : 'হিসাব-নিকাশ'; ?></b><small><?php echo $if_en ? 'Daily money in and out' : 'প্রতিদিনের লেনদেন, আয়-ব্যয়'; ?></small></a>
    <a class="if-rail-item" href="/modules/sales/pos.php"><b><?php echo $if_en ? 'Safe & trusted' : 'নিরাপদ ও নির্ভরযোগ্য'; ?></b><small><?php echo $if_en ? 'Your data stays protected' : 'আপনার ডাটা সর্বসময় নিরাপদ'; ?></small></a>
</aside>
