<?php
$code_is_english = current_lang() === 'en';

$code_words = $code_is_english
    ? [
        'title' => 'Product Identification',
        'auto' => 'Automatic ID',
        'auto_note' => 'Example: PRD-000123',
        'manual' => 'Manual ID',
        'manual_note' => 'Example: MOTOR-001',
        'label' => 'Product ID / SKU',
        'auto_placeholder' => 'Generated automatically after saving',
        'manual_placeholder' => 'Enter a unique product ID',
        'hint_auto' => 'A unique ID will be generated automatically.',
        'hint_manual' => 'Enter at least 3 English letters or numbers.',
        'checking' => 'Checking Product ID...',
        'available' => 'This Product ID is available.',
        'duplicate' => 'This Product ID has already been used.',
        'invalid' => 'Enter at least 3 valid English letters or numbers.',
        'failed' => 'Product ID could not be checked. Try again.'
    ]
    : [
        'title' => 'পণ্য শনাক্তকরণ',
        'auto' => 'স্বয়ংক্রিয় আইডি',
        'auto_note' => 'উদাহরণ: PRD-000123',
        'manual' => 'ম্যানুয়াল আইডি',
        'manual_note' => 'উদাহরণ: MOTOR-001',
        'label' => 'পণ্য আইডি / SKU',
        'auto_placeholder' => 'Save করার পর স্বয়ংক্রিয়ভাবে তৈরি হবে',
        'manual_placeholder' => 'ইউনিক পণ্য আইডি লিখুন',
        'hint_auto' => 'স্বয়ংক্রিয়ভাবে একটি ইউনিক আইডি তৈরি হবে।',
        'hint_manual' => 'কমপক্ষে ৩টি ইংরেজি অক্ষর বা সংখ্যা লিখুন।',
        'checking' => 'পণ্য আইডি পরীক্ষা হচ্ছে...',
        'available' => 'এই পণ্য আইডি ব্যবহার করা যাবে।',
        'duplicate' => 'এই পণ্য আইডি আগে ব্যবহার করা হয়েছে।',
        'invalid' => 'কমপক্ষে ৩টি বৈধ ইংরেজি অক্ষর বা সংখ্যা লিখুন।',
        'failed' => 'পণ্য আইডি পরীক্ষা করা যায়নি। আবার চেষ্টা করুন।'
    ];

include __DIR__ . '/add_product_code_style.php';
?>

<section class="product-section product-code-section">
    <h3 class="product-section-title">
        🆔 <?php echo htmlspecialchars(
            $code_words['title'],
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </h3>

    <div class="product-code-options">
        <label class="product-code-option">
            <input
                type="radio"
                name="code_mode"
                value="auto"
                checked
            >
            <strong><?php echo $code_words['auto']; ?></strong>
            <small><?php echo $code_words['auto_note']; ?></small>
        </label>

        <label class="product-code-option">
            <input
                type="radio"
                name="code_mode"
                value="manual"
            >
            <strong><?php echo $code_words['manual']; ?></strong>
            <small><?php echo $code_words['manual_note']; ?></small>
        </label>
    </div>

    <div class="product-field">
        <label>
            <?php echo $code_words['label']; ?>
            <span class="product-required">*</span>
        </label>

        <input
            type="text"
            id="productCodeInput"
            name="sku"
            maxlength="50"
            autocomplete="off"
            readonly
            placeholder="<?php echo $code_words['auto_placeholder']; ?>"
        >

        <small id="productCodeHint" class="product-code-hint">
            <?php echo $code_words['hint_auto']; ?>
        </small>

        <div
            id="productCodeStatus"
            class="product-code-status"
            aria-live="polite"
        ></div>
    </div>
</section>

<?php include __DIR__ . '/add_product_code_script.php'; ?>