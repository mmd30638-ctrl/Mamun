<div id="quickCustomerModal" class="pos-customer-modal" aria-hidden="true">
    <section class="pos-customer-dialog" role="dialog" aria-modal="true">
        <div class="pos-customer-colors">
            <span></span><span></span><span></span><span></span>
        </div>

        <header class="pos-customer-dialog-header">
            <h3>👤 <?php echo $escape($customer_words['popup_title']); ?></h3>
            <button type="button" id="closeQuickCustomer" class="pos-customer-close">×</button>
        </header>

        <form id="quickCustomerForm" class="pos-customer-form">
            <div>
                <label for="quickCustomerName">
                    <?php echo $escape($customer_words['name']); ?> *
                </label>
                <input type="text" id="quickCustomerName" maxlength="150" autocomplete="off" required>
            </div>

            <div>
                <label for="quickCustomerPhone">
                    <?php echo $escape($customer_words['phone']); ?> *
                </label>
                <input type="tel" id="quickCustomerPhone" maxlength="20" autocomplete="off" required>
            </div>

            <div class="pos-customer-full">
                <label for="quickCustomerAddress">
                    <?php echo $escape($customer_words['address']); ?>
                </label>
                <input type="text" id="quickCustomerAddress" maxlength="500" autocomplete="off">
            </div>

            <div>
                <label for="quickCustomerOpeningType">
                    <?php echo $escape($customer_words['opening_type']); ?>
                </label>
                <select id="quickCustomerOpeningType">
                    <option value="due"><?php echo $escape($customer_words['due']); ?></option>
                    <option value="advance"><?php echo $escape($customer_words['advance']); ?></option>
                </select>
            </div>

            <div>
                <label for="quickCustomerOpeningAmount">
                    <?php echo $escape($customer_words['opening_amount']); ?>
                </label>
                <input type="number" id="quickCustomerOpeningAmount" min="0" step="0.01" value="0" inputmode="decimal">
            </div>

            <div class="pos-customer-full">
                <label for="quickCustomerDueDate">
                    <?php echo $escape($customer_words['due_date']); ?>
                </label>
                <input type="date" id="quickCustomerDueDate">
            </div>

            <div class="pos-customer-full">
                <label for="quickCustomerNote">
                    <?php echo $escape($customer_words['note']); ?>
                </label>
                <textarea id="quickCustomerNote" maxlength="255"></textarea>
            </div>

            <label class="pos-due-watch pos-customer-full">
                <input type="checkbox" id="quickCustomerDueWatch" value="1">
                <span>
                    <strong><?php echo $escape($customer_words['due_watch']); ?></strong>
                    <em><?php echo $escape($customer_words['due_watch_hint']); ?></em>
                </span>
            </label>

            <div id="quickCustomerError" class="pos-customer-error pos-customer-full"></div>

            <div class="pos-customer-form-actions pos-customer-full">
                <button type="button" id="cancelQuickCustomer" class="pos-customer-cancel">
                    <?php echo $escape($customer_words['cancel']); ?>
                </button>
                <button type="submit" id="saveQuickCustomer" class="pos-customer-save">
                    <?php echo $escape($customer_words['save']); ?>
                </button>
            </div>
        </form>
    </section>
</div>