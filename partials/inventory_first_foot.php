<?php
$if_en = function_exists('current_lang') && current_lang() === 'en';
?>
<div class="if-foot">
    <a class="if-cta" href="/modules/sales/pos.php"><?php echo $if_en ? 'Order now' : 'এখনই অর্ডার করুন'; ?></a>
    <p class="if-cta-note"><?php echo $if_en ? 'One small change, one bright tomorrow.' : 'একটি ছোট পরিবর্তন, একটি উজ্জ্বল আগামী'; ?></p>
    <p class="if-cta-en">Together for a Cleaner, Greener, Happier Planet.</p>
    <div class="if-foot-row">
        <span><?php echo $if_en ? 'Keep the earth green' : 'সবুজ থাকুক পৃথিবী'; ?></span>
        <span><?php echo $if_en ? 'Make life sustainable' : 'টেকসই হোক জীবন'; ?></span>
        <span><?php echo $if_en ? 'A happier next generation' : 'সুখী হোক আগামী প্রজন্ম'; ?></span>
    </div>
</div>
