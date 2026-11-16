<?php
/* 10.98.693 */
$sign_customer_id = (int)($sign_customer_id ?? 0);
$sign_existing = ame_customer_signature_path($sign_existing ?? '');
$sign_when = trim((string)($sign_when ?? ''));
$sign_mode = (($sign_mode ?? 'add') === 'edit') ? 'edit' : 'add';
$sign_shop = function_exists('ame_shop_identity_name') ? trim(ame_shop_identity_name()) : '';
if ($sign_shop === '') {
    $sign_shop = 'এই দোকান';
}
?>
<section class="ame-sign" id="customerSign" data-sign-pad<?php if ($sign_existing !== '' && $sign_customer_id > 0): ?> data-sign-delete-url="actions/delete_signature.php" data-sign-cid="<?php echo $sign_customer_id; ?>"<?php endif; ?>>
    <h2>চুক্তিপত্র</h2>
    <p class="ame-sign-lead">আমি <?php echo htmlspecialchars($sign_shop, ENT_QUOTES, 'UTF-8'); ?>-এর গ্রাহক হিসেবে নিচের পরিচয় সত্য জানাচ্ছি। বাকি, জমা ও শর্তের হিসাব মেনে এই চুক্তিতে সই দিচ্ছি।</p>
    <p class="ame-sign-meta" data-sign-meta>তারিখ <?php echo date('d/m/Y'); ?><?php if ($sign_when !== ''): ?> · আগের সই <?php echo htmlspecialchars($sign_when, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
    <?php if ($sign_existing !== ''): ?>
    <img class="ame-sign-old" data-sign-old src="/<?php echo htmlspecialchars($sign_existing, ENT_QUOTES, 'UTF-8'); ?>" alt="রাখা স্বাক্ষর">
    <?php endif; ?>
    <p class="ame-sign-ask">নিচের সাদা বোর্ডে আঙুল বা কলম দিয়ে সই দিন।</p>
    <canvas class="ame-sign-board" aria-label="গ্রাহকের স্বাক্ষর"></canvas>
    <input type="hidden" name="signature_png" value="">
    <input type="hidden" name="signature_clear" value="0">
    <p class="ame-sign-status" data-sign-status><?php echo $sign_mode === 'edit' ? 'বোর্ড খালি রাখলে আগের সই থাকবে। নতুন সই দিলে পুরোনোটা বদলে যাবে।' : 'সই না দিলেও কাস্টমার সেভ হবে। পরে এডিট থেকে যেকোনো সময় সই দেওয়া যাবে।'; ?></p>
    <div class="ame-sign-actions">
        <button type="button" data-sign-clear><?php echo $sign_existing !== '' ? 'পূর্বের সই বাতিল করুন' : 'সই মুছুন'; ?></button>
        <button type="button" data-sign-wipe>আবার লিখুন</button>
    </div>
</section>
