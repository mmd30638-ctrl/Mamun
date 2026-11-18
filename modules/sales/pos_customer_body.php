<?php
include __DIR__ . '/pos_customer_modal_style.php';
include __DIR__ . '/pos_quick_form_style.php';
?>

<section class="pos-customer-panel" data-ame-primary-customer="1">
    <div class="pos-customer-tabs">
        <button type="button" class="pos-customer-tab active" id="posTabRegistered" data-tab="registered">
            <?php echo $escape($customer_words['title']); ?>
        </button>
        <button type="button" class="pos-customer-tab" id="posTabGuest" data-tab="guest">
            <?php echo $escape($customer_words['guest_title']); ?>
        </button>
    </div>

    <div class="pos-customer-modes" hidden aria-hidden="true">
        <label>
            <input type="radio" name="cus_type" id="posRetailCustomerMode" value="Retail" checked>
            <?php echo $escape($customer_words['retail_short']); ?>
        </label>
        <label>
            <input type="radio" name="cus_type" id="posRegisteredCustomerMode" value="Registered">
            <?php echo $escape($customer_words['registered_short']); ?>
        </label>
    </div>

    <input
        type="hidden"
        id="posCustomerCsrf"
        value="<?php echo $escape((string)$_SESSION['pos_customer_csrf']); ?>"
    >

    <?php include __DIR__ . '/pos_customer_select.php'; ?>

    <div id="posCustomerSummary" class="pos-customer-summary">
        <?php echo $escape($customer_words['no_selection']); ?>
    </div>

    <div id="registeredCustomerInfo" class="pos-registered-info">
        <strong id="selectedCustomerName"></strong>
        <small id="selectedCustomerMeta" class="pos-customer-meta-line"></small>
        <span>
            <?php echo $escape($customer_words['balance']); ?>:
            <b id="selectedCustomerBalance">৳0.00</b>
            <em id="selectedCustomerBalanceTag" class="pos-customer-balance-tag"></em>
        </span>
    </div>

    <div id="guestCustomerFields" hidden>
        <input type="tel" id="guestCustomerPhone" hidden aria-hidden="true" tabindex="-1">
        <input type="text" id="guestCustomerNote" hidden aria-hidden="true" tabindex="-1">
    </div>
</section>

<?php include __DIR__ . '/pos_customer_modals.php'; ?>
