<?php
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.82', ENT_QUOTES, 'UTF-8');
$lang_en = function_exists('current_lang') && current_lang() === 'en';
$studio = [
    'isEdit' => !empty($is_edit),
    'productId' => (int)($p['id'] ?? 0),
    'lookupUrl' => 'product_lookup.php',
    'csrf' => function_exists('csrf_token') ? csrf_token() : '',
    'saving' => $words['saving'] ?? '',
    'profitOk' => $words['profit_ok'] ?? '',
    'lossWarning' => $words['loss_warning'] ?? '',
    'missingLabel' => $words['missing'] ?? '',
    'autoPlaceholder' => $lang_en ? 'Generated automatically after saving' : 'সেভের পর স্বয়ংক্রিয়ভাবে তৈরি হবে',
    'manualPlaceholder' => $lang_en ? 'Enter a unique SKU' : 'ইউনিক SKU লিখুন',
    'hintAuto' => $lang_en ? 'Leave blank for automatic ID, or type your own SKU here.' : 'খালি রাখলে আইডি নিজে তৈরি হবে। নিজের আইডি চাইলে এখানে লিখুন।',
    'hintManual' => $lang_en ? 'At least 3 English letters or numbers.' : 'কমপক্ষে ৩টি ইংরেজি অক্ষর বা সংখ্যা লিখুন।',
    'checking' => $lang_en ? 'Checking…' : 'পরীক্ষা হচ্ছে…',
    'available' => $lang_en ? 'Available.' : 'ব্যবহার করা যাবে।',
    'duplicateSku' => $lang_en ? 'This SKU is already used.' : 'এই SKU আগে ব্যবহার করা হয়েছে।',
    'duplicateBarcode' => $lang_en ? 'This barcode is already used.' : 'এই বারকোড আগে ব্যবহার করা হয়েছে।',
    'invalidCode' => $lang_en ? 'Use at least 3 English letters or numbers.' : 'কমপক্ষে ৩টি বৈধ ইংরেজি অক্ষর বা সংখ্যা লিখুন।',
    'failed' => $lang_en ? 'Could not check. Try again.' : 'পরীক্ষা করা যায়নি। আবার চেষ্টা করুন।',
    'labels' => [
        'name' => $words['name'] ?? '',
        'sku' => 'SKU',
        'purchase' => $words['purchase'] ?? '',
        'retail' => $words['retail'] ?? '',
        'wholesale' => $words['wholesale'] ?? '',
        'dealer' => $words['dealer'] ?? '',
        'category' => $words['category'] ?? '',
        'brand' => $words['brand'] ?? '',
        'barcode' => $words['barcode'] ?? '',
        'photo' => $words['photo'] ?? '',
        'stock' => $words['stock'] ?? '',
        'oem' => $words['oem'] ?? '',
    ],
];
?>
<script>
window.PRODUCT_STUDIO = <?php echo json_encode($studio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/assets/js/product-studio.js?v=<?php echo $ver; ?>-ps82"></script>
