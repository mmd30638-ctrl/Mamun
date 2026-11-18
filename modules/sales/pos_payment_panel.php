<?php
/*
 * একাধিক অ্যাকাউন্টে বিভক্ত পরিশোধ Component
 */

$payment_is_english = (
    function_exists('current_lang')
    && current_lang() === 'en'
);

$payment_words = $payment_is_english
    ? [
        'title' => 'Split Payment',
        'subtitle' => 'Receive payment through one or multiple accounts.',
        'account' => 'Payment Account',
        'amount' => 'Amount',
        'reference' => 'Transaction ID (optional)',
        'add' => 'Add Payment Method',
        'remove' => 'Remove',
        'total' => 'Total Payment',
        'remaining' => 'Remaining',
        'change' => 'Change',
        'select' => 'Select account',
        'no_account' => 'No active payment account found'
    ]
    : [
        'title' => 'বিভক্ত পরিশোধ',
        'subtitle' => 'একটি অথবা একাধিক অ্যাকাউন্টের মাধ্যমে টাকা গ্রহণ করুন।',
        'account' => 'টাকা জমার অ্যাকাউন্ট',
        'amount' => 'পরিমাণ',
        'reference' => 'লেনদেন নম্বর (ঐচ্ছিক)',
        'add' => 'আরেকটি পরিশোধ মাধ্যম যোগ করুন',
        'remove' => 'বাদ দিন',
        'total' => 'মোট পরিশোধ',
        'remaining' => 'অবশিষ্ট',
        'change' => 'ফেরত',
        'select' => 'অ্যাকাউন্ট নির্বাচন করুন',
        'no_account' => 'কোনো সক্রিয় পেমেন্ট অ্যাকাউন্ট পাওয়া যায়নি'
    ];

$payment_escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$payment_accounts = [];

if (isset($ame_pos_accounts) && is_array($ame_pos_accounts)) {
    $payment_accounts = $ame_pos_accounts;
} elseif (isset($db) && $db instanceof mysqli) {
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/../../includes/account_functions.php';
    }
    $payment_accounts = ame_active_accounts($db);
}

$ame_pay_def = 0;
$ame_pay_mark_file = __DIR__ . '/../../includes/pay_mark.php';
if (is_file($ame_pay_mark_file)) {
    require_once $ame_pay_mark_file;
    if (function_exists('ame_pay_mark_get')) {
        $ame_pay_def = ame_pay_mark_get();
    }
}
?>

<style>
.pos-payment-panel {
    margin: 8px 0;
    overflow: hidden;
    border: 1px solid var(--pos-c-d8e1ec);
    border-radius: 16px;
    background: var(--brand-white);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
}

.pos-payment-colors {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 6px;
}

.pos-payment-colors span:nth-child(1),
.pos-payment-colors span:nth-child(2),
.pos-payment-colors span:nth-child(3),
.pos-payment-colors span:nth-child(4) { background: var(--pos-c-e8edf2); }

.pos-payment-heading {
    padding: 8px 15px;
    background: var(--brand-white);
    color: var(--pos-c-000000);
    text-shadow: none;
    border-bottom: 1px solid var(--pos-c-d8e1ec);
}

.pos-payment-heading h4 {
    margin: 0 0 4px;
    font-size: 17px;
    color: var(--pos-c-000000);
    text-shadow: none;
}

.pos-payment-heading small {
    color: var(--pos-c-9ca3af);
    text-shadow: none;
    font-weight: 500;
}

.pos-payment-body {
    padding: 8px 14px;
}

.pos-payment-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 7px;
    margin-bottom: 7px;
    padding: 8px 12px;
    border: 1px solid var(--pos-c-d8e1ec);
    border-left: 5px solid var(--pos-c-d8e1ec);
    border-radius: 11px;
    background: var(--brand-white);
}

.pos-payment-field label {
    display: block;
    margin-bottom: 5px;
    color: var(--brand-navy-dark);
    font-size: 11px;
    font-weight: 800;
}

.pos-payment-field select,
.pos-payment-field input {
    width: 100%;
    height: 43px;
    min-width: 0;
    padding: 0 10px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-aebccc);
    border-radius: 8px;
    background: var(--brand-white);
    color: var(--brand-navy-dark);
    font-size: 14px;
    outline: none;
}

.pos-payment-field select:focus,
.pos-payment-field input:focus {
    border-color: var(--brand-navy);
    box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.10);
}

.pos-payment-remove {
    align-self: end;
    height: 43px;
    padding: 0 12px;
    border: 0;
    border-radius: 8px;
    background: var(--pos-c-c62828);
    color: white;
    font-weight: 800;
    cursor: pointer;
}

.pos-payment-add {
    width: 100%;
    min-height: 36px;
    margin-top: 4px;
    border: 1.5px dashed var(--pos-c-d8e1ec);
    border-radius: 9px;
    background: var(--brand-white);
    color: var(--pos-c-000000);
    text-shadow: none;
    font-weight: 850;
    cursor: pointer;
}

.pos-payment-summary {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    margin-top: 8px;
}

.pos-payment-summary-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 7px 11px;
    border-radius: 9px;
    background: var(--brand-white);
    color: var(--pos-c-000000);
    text-shadow: none;
    border: 1px solid var(--pos-c-d8e1ec);
    font-size: 13px;
}

.pos-payment-summary-item.total strong,
.pos-payment-summary-item.remaining strong,
.pos-payment-summary-item.change strong {
    color: var(--pos-c-000000);
    text-shadow: none;
}

.pos-payment-empty {
    padding: 14px;
    border-radius: 9px;
    background: var(--pos-c-ffebee);
    color: var(--pos-c-c62828);
    text-align: center;
    font-weight: 750;
}

@media (min-width: 760px) {
    .pos-payment-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .pos-payment-row .pos-payment-field {
        flex: 1 1 150px;
        min-width: 0;
    }

    .pos-payment-row .pos-payment-remove {
        flex: 0 0 auto;
    }

    .pos-payment-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .pos-payment-summary-item {
        flex: 1 1 200px;
    }
}
</style>

<section class="pos-payment-panel">
    <div class="pos-payment-colors">
        <span></span><span></span><span></span><span></span>
    </div>

    <header class="pos-payment-heading">
        <h4>💳 <?php echo $payment_escape($payment_words['title']); ?></h4>
    </header>

    <div class="pos-payment-body">
        <?php if (empty($payment_accounts)): ?>
            <div class="pos-payment-empty">
                <?php echo $payment_escape($payment_words['no_account']); ?>
            </div>
        <?php else: ?>
            <div id="posPaymentRows">
                <div class="pos-payment-row">
                    <div class="pos-payment-field">
                        <label><?php echo $payment_escape($payment_words['account']); ?></label>

                        <select class="pos-payment-account ame-account-select" data-ame-account-select="1" data-ame-direction="receive">
                            <option value="">
                                <?php echo $payment_escape($payment_words['select']); ?>
                            </option>

                            <?php foreach ($payment_accounts as $account): ?>
                                <option
                                    value="<?php echo (int)$account['id']; ?>"
                                    data-balance="<?php echo ame_balance_number_masked($account['balance'] ?? 0); ?>"
                                    <?php echo ($ame_pay_def && (int)$account['id'] === $ame_pay_def) ? 'selected' : ''; ?>
                                >
                                    <?php echo $payment_escape($account['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pos-payment-field">
                        <label><?php echo $payment_escape($payment_words['amount']); ?></label>

                        <input
                            type="number"
                            class="pos-payment-amount"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            value="0"
                        >
                    </div>

                    <div class="pos-payment-field pos-payment-ref">
                        <label><?php echo $payment_escape($payment_words['reference']); ?></label>

                        <input
                            type="text"
                            class="pos-payment-reference"
                            maxlength="100"
                            tabindex="-1"
                            autocomplete="off"
                        >
                    </div>
                </div>
            </div>

            <button
                type="button"
                id="addPosPayment"
                class="pos-payment-add"
            >
                ➕ <?php echo $payment_escape($payment_words['add']); ?>
            </button>

            <div class="pos-payment-summary">
                <div class="pos-payment-summary-item total">
                    <span><?php echo $payment_escape($payment_words['total']); ?></span>
                    <strong id="posPaymentTotal">৳0.00</strong>
                </div>

                <div class="pos-payment-summary-item remaining">
                    <span><?php echo $payment_escape($payment_words['remaining']); ?></span>
                    <strong id="posPaymentRemaining">৳0.00</strong>
                </div>

                <div class="pos-payment-summary-item change">
                    <span><?php echo $payment_escape($payment_words['change']); ?></span>
                    <strong id="posPaymentChange">৳0.00</strong>
                </div>
            </div>

            <template id="posPaymentRowTemplate">
                <div class="pos-payment-row">
                    <div class="pos-payment-field">
                        <label><?php echo $payment_escape($payment_words['account']); ?></label>

                        <select class="pos-payment-account ame-account-select" data-ame-account-select="1" data-ame-direction="receive">
                            <option value="">
                                <?php echo $payment_escape($payment_words['select']); ?>
                            </option>

                            <?php foreach ($payment_accounts as $account): ?>
                                <option value="<?php echo (int)$account['id']; ?>">
                                    <?php echo $payment_escape($account['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pos-payment-field">
                        <label><?php echo $payment_escape($payment_words['amount']); ?></label>

                        <input
                            type="number"
                            class="pos-payment-amount"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            value="0"
                        >
                    </div>

                    <div class="pos-payment-field pos-payment-ref">
                        <label><?php echo $payment_escape($payment_words['reference']); ?></label>

                        <input
                            type="text"
                            class="pos-payment-reference"
                            maxlength="100"
                            tabindex="-1"
                            autocomplete="off"
                        >
                    </div>

                    <button
                        type="button"
                        class="pos-payment-remove"
                    >
                        ✕ <?php echo $payment_escape($payment_words['remove']); ?>
                    </button>
                </div>
            </template>
        <?php endif; ?>
    </div>
</section>