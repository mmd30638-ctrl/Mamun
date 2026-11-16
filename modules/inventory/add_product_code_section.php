<?php
$code_is_english = function_exists('current_lang') && current_lang() === 'en';
$code_words = $code_is_english
    ? [
        'title' => 'Product ID / SKU',
        'auto' => 'Automatic ID',
        'auto_note' => 'Example: PRD-000123',
        'manual' => 'Manual ID',
        'manual_note' => 'Example: MOTOR-001',
        'label' => 'SKU',
        'auto_placeholder' => 'Generated automatically after saving',
        'manual_placeholder' => 'Enter a unique SKU',
        'hint_auto' => 'Leave blank for automatic ID, or type your own SKU here.',
        'hint_manual' => 'At least 3 English letters or numbers.',
    ]
    : [
        'title' => 'পণ্য আইডি / SKU',
        'auto' => 'স্বয়ংক্রিয় আইডি',
        'auto_note' => 'উদাহরণ: PRD-000123',
        'manual' => 'ম্যানুয়াল আইডি',
        'manual_note' => 'উদাহরণ: MOTOR-001',
        'label' => 'SKU',
        'auto_placeholder' => 'সেভের পর স্বয়ংক্রিয়ভাবে তৈরি হবে',
        'manual_placeholder' => 'ইউনিক SKU লিখুন',
        'hint_auto' => 'খালি রাখলে আইডি নিজে তৈরি হবে। নিজের আইডি চাইলে এখানে লিখুন।',
        'hint_manual' => 'কমপক্ষে ৩টি ইংরেজি অক্ষর বা সংখ্যা লিখুন।',
    ];

$existing_sku = '';
if (isset($p) && is_array($p)) {
    $existing_sku = trim((string)($p['sku'] ?? ''));
}
$sku_is_edit = !empty($is_edit);
?>
<div class="ps-sku">
    <?php if ($sku_is_edit): ?>
        <input type="hidden" name="code_mode" value="manual">
        <div class="ps-field">
            <label><?php echo htmlspecialchars($code_words['label'], ENT_QUOTES, 'UTF-8'); ?> <span class="ps-req">*</span></label>
            <input type="text" id="productCodeInput" name="sku" maxlength="50" required
                   value="<?php echo htmlspecialchars($existing_sku, ENT_QUOTES, 'UTF-8'); ?>">
            <small id="productCodeHint" class="ps-hint"><?php echo htmlspecialchars($code_words['hint_manual'], ENT_QUOTES, 'UTF-8'); ?></small>
            <div id="productCodeStatus" class="ps-status" aria-live="polite"></div>
        </div>
    <?php else: ?>
        <p class="ps-label"><?php echo htmlspecialchars($code_words['title'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="ps-code-options">
            <label class="ps-code-option">
                <input type="radio" name="code_mode" value="auto" checked>
                <strong><?php echo htmlspecialchars($code_words['auto'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars($code_words['auto_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            </label>
            <label class="ps-code-option">
                <input type="radio" name="code_mode" value="manual">
                <strong><?php echo htmlspecialchars($code_words['manual'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars($code_words['manual_note'], ENT_QUOTES, 'UTF-8'); ?></small>
            </label>
        </div>
        <div class="ps-field">
            <label><?php echo htmlspecialchars($code_words['label'], ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" id="productCodeInput" name="sku" maxlength="50"
                   inputmode="text" autocomplete="off" autocapitalize="characters"
                   placeholder="<?php echo htmlspecialchars($code_words['auto_placeholder'], ENT_QUOTES, 'UTF-8'); ?>">
            <small id="productCodeHint" class="ps-hint"><?php echo htmlspecialchars($code_words['hint_auto'], ENT_QUOTES, 'UTF-8'); ?></small>
            <div id="productCodeStatus" class="ps-status" aria-live="polite"></div>
        </div>
    <?php endif; ?>
</div>
