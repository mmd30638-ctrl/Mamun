<div id="posGuestModal" class="pos-customer-modal" aria-hidden="true">
    <section class="pos-customer-dialog" role="dialog" aria-modal="true">
        <div class="pos-customer-colors">
            <span></span><span></span><span></span><span></span>
        </div>

        <header class="pos-customer-dialog-header">
            <h3>🧾 <?php echo $escape($customer_words['guest_title']); ?></h3>
            <button type="button" id="closeGuestModal" class="pos-customer-close">×</button>
        </header>

        <div class="pos-customer-dialog-body">
            <div class="pos-guest-fields">
                <input
                    type="text"
                    id="guestCustomerNameModal"
                    class="pos-customer-field"
                    maxlength="150"
                    autocomplete="off"
                    placeholder="<?php echo $escape($is_english
                        ? 'Name and mobile number (e.g. Karim, 017XXXXXXXX)'
                        : 'নাম ও মোবাইল নম্বর লিখুন (যেমন: করিম, 017XXXXXXXX)'); ?>"
                >
            </div>

            <button type="button" id="doneGuestModal" class="pos-customer-done">
                ✅ <?php echo $escape($customer_words['done']); ?>
            </button>
        </div>
    </section>
</div>

<?php include __DIR__ . '/pos_quick_customer_form.php'; ?>
<?php include __DIR__ . '/pos_customer_script.php'; ?>
