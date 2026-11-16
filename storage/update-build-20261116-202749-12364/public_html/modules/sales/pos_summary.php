<?php
$draft_words = $is_english
    ? [
        'draft_title' => 'Invoice draft',
        'draft_note' => 'Draft note (optional)',
        'draft_placeholder' => 'Write a short note for this draft...',
        'save_draft' => 'Save draft',
        'draft_list' => 'Draft list',
        'drawer_title' => 'Recent sales · Draft memos',
        'complete_title' => 'Recent sales · Draft memos',
        'history_link' => 'All history',
        'col_serial' => '#',
        'col_time' => 'Time',
        'col_invoice' => 'Invoice',
        'col_customer' => 'Customer',
        'col_price' => 'Price',
        'col_print' => 'Print',
        'col_edit' => 'Edit',
        'col_actions' => '',
        'print' => 'Print',
        'share' => 'Share',
        'close' => 'Close'
    ]
    : [
        'draft_title' => 'ইনভয়েস ড্রাফট',
        'draft_note' => 'ড্রাফট নোট (ঐচ্ছিক)',
        'draft_placeholder' => 'এই ড্রাফট সম্পর্কে সংক্ষিপ্ত নোট লিখুন...',
        'save_draft' => 'ড্রাফট সংরক্ষণ',
        'draft_list' => 'ড্রাফট তালিকা',
        'drawer_title' => 'সাম্প্রতিক বিক্রয় · ড্রাফট মেমো',
        'complete_title' => 'সাম্প্রতিক বিক্রয় · ড্রাফট মেমো',
        'history_link' => 'সব ইতিহাস',
        'col_serial' => '#',
        'col_time' => 'সময়',
        'col_invoice' => 'ইনভয়েস',
        'col_customer' => 'ক্রেতা',
        'col_price' => 'মূল্য',
        'col_print' => 'প্রিন্ট',
        'col_edit' => 'এডিট',
        'col_actions' => '',
        'print' => 'প্রিন্ট',
        'share' => 'শেয়ার',
        'close' => 'বন্ধ'
    ];

include __DIR__ . '/pos_draft_style.php';

$ame_pos_disc_pct = 0;
$ame_pos_disc_auto = 0;
if (function_exists('get_invoice_settings') && isset($db) && $db instanceof mysqli) {
    try {
        $ame_inv_set = get_invoice_settings($db);
        $ame_pos_disc_auto = !empty($ame_inv_set['pos_discount_auto']) ? 1 : 0;
        $ame_pos_disc_pct = $ame_pos_disc_auto ? 12.0 : 0.0;
    } catch (Throwable $e) {
        $ame_pos_disc_pct = 0;
        $ame_pos_disc_auto = 0;
    }
}
?>

<aside id="amePosPay" class="ame-pos-card ame-pos-summary">
    <div class="ame-desk-pay">
        <?php $ame_can_edit_sale_date = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin'; ?>
        <section class="ame-desk-box">
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.8"/></svg><span><?php echo $escape($is_english ? 'Sale date' : 'বিক্রয়ের তারিখ'); ?></span></h3>
            <input id="ameSaleDate" class="ame-desk-date-input" type="date" value="<?php echo date('Y-m-d'); ?>" <?php echo $ame_can_edit_sale_date ? '' : 'disabled'; ?> aria-label="<?php echo $escape($is_english ? 'Sale date' : 'বিক্রয়ের তারিখ'); ?>">
        </section>
        <section class="ame-desk-box ame-desk-custbal" id="ameDeskCustBal" hidden>
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 19c1.5-3 3.8-4.5 7-4.5S17.5 16 19 19" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?php echo $escape($is_english ? 'Customer account' : 'গ্রাহকের হিসাব'); ?></h3>
            <div class="ame-desk-fields ame-desk-fields-2">
                <label><?php echo $escape($is_english ? 'Deposit (৳)' : 'জমা আছে (৳)'); ?><output id="deskCustDeposit">0.00</output></label>
                <label><?php echo $escape($is_english ? 'Due (৳)' : 'বাকি আছে (৳)'); ?><output id="deskCustDue">0.00</output></label>
            </div>
        </section>
        <section class="ame-desk-box ame-desk-runbal" id="ameDeskRunBal" hidden>
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h10M4 17h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><?php echo $escape($is_english ? 'After this sale' : 'এই বিক্রয়ের পর'); ?></h3>
            <p id="deskRunTag" style="margin:8px 0 0;font-weight:800;"><?php echo $escape($is_english ? 'Settled' : 'সমান'); ?></p>
            <output id="deskRunAmount" style="display:block;font-size:22px;font-weight:800;line-height:1.2;">0.00</output>
            <p id="deskRunLine" style="margin:6px 0 0;font-size:12px;line-height:1.45;font-weight:700;"></p>
        </section>
        <section class="ame-desk-box">
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 8h8M8 12h5" stroke="currentColor" stroke-width="1.8"/></svg><?php echo $escape($is_english ? 'Total and discount' : 'মোট ও ছাড়'); ?></h3>
            <div class="ame-desk-fields ame-desk-fields-2">
                <label><?php echo $escape($is_english ? 'Subtotal (৳)' : 'সাব-টোটাল (৳)'); ?><output id="deskSubtotal">0.00</output></label>
                <label><?php echo $escape($is_english ? 'After discount (৳)' : 'ছাড়ের পর (৳)'); ?><output id="deskAfter">0</output></label>
            </div>
            <div class="ame-desk-disc<?php echo $ame_pos_disc_auto ? ' is-locked' : ''; ?>" id="deskDiscBox" data-on="1" data-mode="<?php echo $ame_pos_disc_auto ? 'pct' : 'taka'; ?>" data-lock="<?php echo $ame_pos_disc_auto ? '1' : '0'; ?>" data-auto-pct="<?php echo $ame_pos_disc_auto ? '12' : '0'; ?>"<?php echo $ame_pos_disc_auto ? '' : ' data-pct-off="1"'; ?>>
                <button
                    type="button"
                    id="deskDiscToggle"
                    class="ame-desk-disc-toggle is-on"
                    aria-pressed="true"
                    data-on-label="<?php echo $escape($is_english ? 'Discount on' : 'ছাড় চালু'); ?>"
                    data-off-label="<?php echo $escape($is_english ? 'Discount off' : 'ছাড় বন্ধ'); ?>"
                ><?php echo $escape($is_english ? 'Discount on' : 'ছাড় চালু'); ?></button>
                <div class="ame-desk-fields ame-desk-fields-2">
                    <?php if ($ame_pos_disc_auto): ?>
                    <label class="ame-desk-disc-field is-live" data-disc-field="pct"><?php echo $escape($is_english ? 'Auto 12%' : 'অটো ১২%'); ?>
                        <span class="ame-desk-lock-row">
                            <input id="deskDiscountPct" type="number" min="12" max="12" step="0.01" value="<?php echo $escape(number_format($ame_pos_disc_pct, 2, '.', '')); ?>" inputmode="decimal" readonly>
                            <button type="button" id="deskDiscLock" class="ame-desk-lock is-locked" aria-pressed="true" data-lock-label="<?php echo $escape($is_english ? 'Lock' : 'লক'); ?>" data-open-label="<?php echo $escape($is_english ? 'Open' : 'খোলা'); ?>"><?php echo $escape($is_english ? 'Lock' : 'লক'); ?></button>
                        </span>
                    </label>
                    <?php endif; ?>
                    <label class="ame-desk-disc-field<?php echo $ame_pos_disc_auto ? '' : ' is-live'; ?>" data-disc-field="taka"><?php echo $escape($is_english ? 'Taka (৳)' : 'টাকা (৳)'); ?>
                        <input id="deskDiscountTaka" type="number" min="0" step="0.01" value="0" inputmode="decimal" placeholder="0"<?php echo $ame_pos_disc_auto ? ' readonly' : ''; ?>>
                    </label>
                </div>
                <?php if ($ame_pos_disc_auto): ?>
                <p class="ame-desk-lock-note" id="deskDiscLockNote"><?php echo $escape($is_english ? '12% is subtracted from every sale automatically.' : 'প্রতি বিক্রয়ের মোট থেকে শতকরা ১২% অটো মাইনাস হয়।'); ?></p>
                <?php endif; ?>
            </div>
            <div class="ame-partner-cost-box" id="amePartnerCostSaleDesk">
                <div class="ame-partner-cost-head">
                    <span class="ame-partner-cost-badge" aria-hidden="true">PEX</span>
                    <div class="ame-partner-cost-title">
                        <strong><?php echo $escape($is_english ? 'Partner cost report' : 'পার্টনার খরচ রিপোর্ট'); ?></strong>
                        <small><?php echo $escape($is_english ? 'Discount here posts to partner expenses automatically.' : 'ডিসকাউন্ট এখানে দিলে খরচ বিভাগে স্বয়ংক্রিয় এন্ট্রি হবে'); ?></small>
                    </div>
                </div>
                <div class="ame-partner-cost-grid">
                    <div class="ame-partner-cost-field">
                        <label class="cpd-label cpd-c-amber" for="partnerCostDiscountDesk"><?php echo $escape($is_english ? 'Partner cost discount (৳)' : 'পার্টনার খরচ · ডিসকাউন্ট (৳)'); ?></label>
                        <input class="cpd-input" type="number" step="0.01" min="0" value="0" placeholder="0" id="partnerCostDiscountDesk" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="ame-partner-cost-field ame-partner-cost-field--note">
                        <label class="cpd-label cpd-c-teal" for="partnerCostNoteDesk"><?php echo $escape($is_english ? 'Partner cost note' : 'পার্টনার খরচ · নোট'); ?></label>
                        <textarea class="cpd-input" id="partnerCostNoteDesk" rows="2" maxlength="255" placeholder="<?php echo $escape($is_english ? 'Shown in partner expense report (optional)' : 'খরচ রিপোর্টে দেখাবে (ঐচ্ছিক)'); ?>" autocomplete="off"></textarea>
                    </div>
                </div>
            </div>
            <input id="deskDiscountAuto" type="hidden" value="<?php echo $ame_pos_disc_auto ? '1' : '0'; ?>">
            <div class="ame-pos-calc-row ame-pos-round-row">
                <label for="rounding"><?php echo $escape($words['rounding']); ?></label>
                <input
                    type="number"
                    id="rounding"
                    value="0"
                    step="0.01"
                    oninput="calculateAll()"
                >
            </div>
        </section>
        <section class="ame-desk-box ame-desk-paybox">
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.8"/></svg><?php echo $escape($is_english ? 'Payment' : 'পেমেন্ট'); ?></h3>
            <section class="ame-desk-box ame-desk-grand">
                <h3><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 12h4" stroke="currentColor" stroke-width="1.8"/></svg><?php echo $escape($is_english ? 'Total payable' : 'সর্বমোট পাওনা'); ?></h3>
                <strong id="deskGrand">৳ 0.00</strong>
            </section>
            <div class="ame-desk-payline">
                <label><?php echo $escape($is_english ? 'Amount (৳)' : 'টাকা (৳)'); ?>
                    <span class="ame-desk-money-row">
                        <input id="deskPaid" type="number" min="0" step="0.01" value="0">
                        <button
                            type="button"
                            id="deskPayArrow"
                            class="ame-desk-pay-arrow"
                            aria-expanded="false"
                            aria-controls="deskPaySecond"
                            title="<?php echo $escape($is_english ? 'Another account' : 'অন্য অ্যাকাউন্ট'); ?>"
                        >▸</button>
                    </span>
                </label>
            </div>
            <label class="ame-desk-change-line"><?php echo $escape($is_english ? 'Change (৳)' : 'চেঞ্জ (৳)'); ?>
                <output id="deskChange">0</output>
            </label>
            <div class="ame-desk-payline">
                <label><?php echo $escape($is_english ? 'Your account' : 'আপনার অ্যাকাউন্ট'); ?>
                    <select id="deskPayMethod" class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" data-ame-sheet="desk">
                        <option value=""><?php echo $escape($is_english ? 'Select' : 'নির্বাচন'); ?></option>
                        <?php foreach (($ame_pos_accounts ?? []) as $desk_account): ?>
                            <option value="<?php echo (int)$desk_account['id']; ?>"><?php echo $escape($desk_account['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div id="deskPaySecond" class="ame-desk-payline" hidden>
                <label><?php echo $escape($is_english ? 'Amount (৳)' : 'টাকা (৳)'); ?>
                    <input id="deskPaid2" type="number" min="0" step="0.01" value="0">
                </label>
                <label><?php echo $escape($is_english ? 'Second account' : 'দ্বিতীয় অ্যাকাউন্ট'); ?>
                    <select id="deskPayMethod2" class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" data-ame-sheet="desk">
                        <option value=""><?php echo $escape($is_english ? 'Select' : 'নির্বাচন'); ?></option>
                        <?php foreach (($ame_pos_accounts ?? []) as $desk_account): ?>
                            <option value="<?php echo (int)$desk_account['id']; ?>"><?php echo $escape($desk_account['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="ame-desk-due-line"><?php echo $escape($is_english ? 'Due (৳)' : 'বাকি (৳)'); ?><output id="deskDue">0.00</output></label>
            <div id="ameDeskExtra" class="ame-desk-extra" hidden>
                <div class="ame-desk-extra-modes">
                    <label>
                        <input type="radio" name="ameExtraMode" id="ameExtraReturn" value="return">
                        <?php echo $escape($is_english ? 'Return the extra cash' : 'অতিরিক্ত টাকা ফেরত'); ?>
                    </label>
                    <label id="ameExtraApplyLabel">
                        <input type="radio" name="ameExtraMode" id="ameExtraApply" value="apply">
                        <?php echo $escape($is_english ? 'Cut due, or keep on the account' : 'বাকি কাটুন বা অ্যাকাউন্টে রাখুন'); ?>
                    </label>
                </div>
                <p id="ameDeskReturnLine" hidden></p>
                <p id="ameDeskDueCutLine" hidden></p>
                <p id="ameDeskCreditLine" hidden></p>
            </div>
        </section>
        <section class="ame-desk-box">
            <h3><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.8"/></svg><span><?php echo $escape($is_english ? 'Next payment' : 'পরবর্তী পেমেন্ট'); ?></span></h3>
            <div class="ame-desk-fields ame-desk-fields-2">
                <label><?php echo $escape($is_english ? 'Date' : 'তারিখ'); ?>
                    <input id="ameNextPayDate" class="ame-desk-date-input" type="date" aria-label="<?php echo $escape($is_english ? 'Next payment date' : 'পরবর্তী পেমেন্টের তারিখ'); ?>">
                </label>
                <label><?php echo $escape($is_english ? 'Amount (৳)' : 'কত টাকা দেবে (৳)'); ?>
                    <input id="ameNextPayAmount" type="number" min="0" step="0.01" placeholder="0.00" inputmode="decimal" aria-label="<?php echo $escape($is_english ? 'Expected amount (৳)' : 'কত টাকা দেবে (৳)'); ?>">
                </label>
            </div>
        </section>
        <section class="ame-desk-actions">
            <button
                type="button"
                class="ame-pos-complete"
                id="amePosCompleteBtn"
                data-complete-label="<?php echo $escape($words['complete_sale']); ?>"
                data-draft-label="💾 <?php echo $escape($draft_words['save_draft']); ?>"
                onclick="submitFinalSale()"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5 11 15.5 16.5 9" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                <?php echo $escape($words['complete_sale']); ?>
            </button>
            <button type="button" class="ame-desk-draft" id="ameDeskDraftBack">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8 4 12l4 4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 12h10a6 6 0 0 1 0 8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                <?php echo $escape($is_english ? 'Back to draft' : 'ড্রাফটে ফিরে যান'); ?>
            </button>
            <label class="ame-pos-due-check ame-desk-sms">
                <span><?php echo $escape($is_english ? 'SMS (optional)' : 'SMS পাঠান (ঐচ্ছিক)'); ?></span>
                <input type="checkbox" id="send-sms">
            </label>
        </section>
    </div>
    <div class="ame-phase"><?php echo $escape($is_english ? '04 Price and discount  ·  05 Payment  ·  06 Confirm' : '০৪ মূল্য ও ছাড়  ·  ০৫ পেমেন্ট  ·  ০৬ নিশ্চিত'); ?></div>
    <h3 class="ame-pos-card-title">
        🧮 <?php echo $escape($words['calculator']); ?>
    </h3>

    <div class="ame-pos-calc-row">
        <label><?php echo $escape($words['subtotal']); ?></label>
        <input
            type="text"
            id="sub-total"
            value="0.00"
            readonly
        >
    </div>

    <div class="ame-pos-calc-row">
        <label><?php echo $escape($words['vat']); ?></label>
        <input
            type="number"
            id="vat-per"
            value="0"
            min="0"
            max="100"
            step="0.01"
            oninput="calculateAll()"
        >
    </div>

    <div class="ame-pos-calc-row">
        <label><?php echo $escape($words['discount']); ?></label>
        <input
            type="number"
            id="discount"
            value="0"
            min="0"
            step="0.01"
            oninput="calculateAll()"
        >
    </div>

    <div class="ame-partner-cost-box ame-pos-partner-cost" id="amePartnerCostSaleMobile">
        <div class="ame-partner-cost-head">
            <span class="ame-partner-cost-badge" aria-hidden="true">PEX</span>
            <div class="ame-partner-cost-title">
                <strong><?php echo $escape($is_english ? 'Partner cost report' : 'পার্টনার খরচ রিপোর্ট'); ?></strong>
                <small><?php echo $escape($is_english ? 'Discount here posts to partner expenses automatically.' : 'ডিসকাউন্ট এখানে দিলে খরচ বিভাগে স্বয়ংক্রিয় এন্ট্রি হবে'); ?></small>
            </div>
        </div>
        <div class="ame-partner-cost-grid">
            <div class="ame-partner-cost-field">
                <label for="partnerCostDiscountMobile"><?php echo $escape($is_english ? 'Partner cost discount (৳)' : 'পার্টনার খরচ · ডিসকাউন্ট (৳)'); ?></label>
                <input type="number" step="0.01" min="0" value="0" placeholder="0" id="partnerCostDiscountMobile" inputmode="decimal" autocomplete="off">
            </div>
            <div class="ame-partner-cost-field ame-partner-cost-field--note">
                <label for="partnerCostNoteMobile"><?php echo $escape($is_english ? 'Partner cost note' : 'পার্টনার খরচ · নোট'); ?></label>
                <textarea id="partnerCostNoteMobile" rows="2" maxlength="255" placeholder="<?php echo $escape($is_english ? 'Shown in partner expense report (optional)' : 'খরচ রিপোর্টে দেখাবে (ঐচ্ছিক)'); ?>" autocomplete="off"></textarea>
            </div>
        </div>
    </div>

    <div class="ame-pos-total">
        <small><?php echo $escape($words['net_payable']); ?></small>
        <strong id="net-payable">0.00 ৳</strong>
    </div>

    <div class="ame-pos-calc-row ame-pos-exchange-row" hidden>
        <label><?php echo $escape($is_english ? 'Old battery exchange' : 'পুরাতন ব্যাটারি এক্সচেঞ্জ'); ?></label>
        <strong id="posExchangeValue">৳0.00</strong>
    </div>
    <div class="ame-pos-calc-row ame-pos-exchange-row" hidden>
        <label><?php echo $escape($is_english ? 'Net cash due' : 'নগদ বাকি'); ?></label>
        <strong id="posNetCashDue">৳0.00</strong>
    </div>
    <div class="ame-pos-calc-row ame-pos-exchange-row" hidden>
        <label><?php echo $escape($is_english ? 'Total paid (cash + exchange)' : 'মোট পরিশোধ (নগদ + এক্সচেঞ্জ)'); ?></label>
        <strong id="posTotalSettled">৳0.00</strong>
    </div>

    <?php include __DIR__ . '/pos_payment_panel.php'; ?>

    <section class="ame-pos-due" id="amePosDueSection" hidden>
        <label class="ame-pos-due-check" id="ameAdjustDueBox" hidden>
            <input
                type="checkbox"
                id="adjust-due"
                onchange="calculateAll()"
            >
            <?php echo $escape($words['adjust_due']); ?>
        </label>

        <div class="ame-pos-calc-row">
            <label id="prev-due-label"><?php echo $escape($words['previous_due']); ?></label>
            <input
                type="text"
                id="prev-due"
                value="0.00"
                readonly
            >
            <small id="prev-due-status" class="ame-balance-status" aria-live="polite"></small>
        </div>

        <div class="ame-pos-calc-row">
            <label id="curr-due-label"><?php echo $escape($words['current_due']); ?></label>
            <input
                type="text"
                id="curr-due"
                value="0.00"
                readonly
                style="color:var(--pos-c-c62828);"
            >
            <small id="curr-due-status" class="ame-balance-status" aria-live="polite"></small>
        </div>
    </section>

    <section class="pos-draft-controls ame-pos-draft-slot">
        <input
            type="hidden"
            id="currentDraftId"
            value=""
        >

        <textarea
            id="draftNote"
            class="pos-draft-note"
            maxlength="255"
            hidden
            placeholder="<?php echo $escape(
                $draft_words['draft_placeholder']
            ); ?>"
            aria-label="<?php echo $escape(
                $draft_words['draft_note']
            ); ?>"
        ></textarea>

        <span
            id="currentDraftLabel"
            class="pos-draft-current"
            hidden
        ></span>

        <button
            type="button"
            id="saveDraftButton"
            class="pos-draft-button save"
            hidden
        >
            <?php echo $escape($draft_words['save_draft']); ?>
        </button>

        <button
            type="button"
            id="openDraftsButton"
            class="pos-draft-button list"
            hidden
        >
            <?php echo $escape($draft_words['draft_list']); ?>
        </button>
    </section>

    <a href="/index.php" class="ame-pos-home" hidden>
        🏠 <?php echo $escape($words['dashboard']); ?>
    </a>
</aside>

<div
    id="draftDrawer"
    class="pos-draft-drawer"
    aria-hidden="true"
>
    <section
        class="pos-draft-panel pos-recent-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="draftDrawerTitle"
    >
        <header class="pos-recent-modal-header">
            <h3 id="draftDrawerTitle">
                <?php echo $escape($draft_words['drawer_title']); ?>
            </h3>
        </header>

        <div class="pos-recent-table-wrap">
            <table class="pos-recent-table">
                <colgroup>
                    <col class="pos-recent-col-cust">
                    <col class="pos-recent-col-ico">
                </colgroup>
                <thead>
                    <tr>
                        <th><?php echo $escape($draft_words['col_customer']); ?></th>
                        <th><?php echo $escape($draft_words['col_actions']); ?></th>
                    </tr>
                </thead>
                <tbody id="draftList"></tbody>
            </table>
        </div>

        <footer class="pos-recent-modal-footer">
            <button
                type="button"
                id="closeDraftsButton"
                class="pos-recent-close-btn"
            >
                <?php echo $escape($draft_words['close']); ?>
            </button>
        </footer>
    </section>
</div>

<div
    id="completeDrawer"
    class="pos-draft-drawer"
    hidden
    aria-hidden="true"
>
    <section
        class="pos-draft-panel pos-recent-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="completeDrawerTitle"
    >
        <header class="pos-recent-modal-header">
            <h3 id="completeDrawerTitle">
                <?php echo $escape($draft_words['complete_title']); ?>
            </h3>
        </header>

        <div class="pos-recent-table-wrap">
            <table class="pos-recent-table">
                <colgroup>
                    <col class="pos-recent-col-sl">
                    <col class="pos-recent-col-time">
                    <col class="pos-recent-col-inv">
                    <col class="pos-recent-col-cust">
                    <col class="pos-recent-col-price">
                    <col class="pos-recent-col-ico">
                </colgroup>
                <thead>
                    <tr>
                        <th><?php echo $escape($draft_words['col_serial']); ?></th>
                        <th><?php echo $escape($draft_words['col_time']); ?></th>
                        <th><?php echo $escape($draft_words['col_invoice']); ?></th>
                        <th><?php echo $escape($draft_words['col_customer']); ?></th>
                        <th><?php echo $escape($draft_words['col_price']); ?></th>
                        <th><?php echo $escape($draft_words['col_actions']); ?></th>
                    </tr>
                </thead>
                <tbody id="completeList"></tbody>
            </table>
        </div>

        <footer class="pos-recent-modal-footer">
            <a href="history.php" class="pos-draft-history-link"><?php echo $escape($draft_words['history_link']); ?></a>
            <button
                type="button"
                id="closeCompleteButton"
                class="pos-recent-close-btn"
            >
                <?php echo $escape($draft_words['close']); ?>
            </button>
        </footer>
    </section>
</div>