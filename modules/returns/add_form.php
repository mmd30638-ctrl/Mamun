<div class="rtn-body">

    <div class="rtn-toggle" id="modeToggle">
        <label data-mode="with_invoice" class="rtn-tab-active">
            <input type="radio" name="return_mode_ui" value="with_invoice" checked onclick="switchMode('with_invoice')">
            <span>🧾 <?php echo t('mode_with_invoice'); ?></span>
        </label>
        <label data-mode="without_invoice">
            <input type="radio" name="return_mode_ui" value="without_invoice" onclick="switchMode('without_invoice')">
            <span>📦 <?php echo t('mode_without_invoice'); ?></span>
        </label>
        <label data-mode="exchange">
            <input type="radio" name="return_mode_ui" value="exchange" onclick="switchMode('exchange')">
            <span>🔁 <?php echo t('mode_exchange'); ?></span>
        </label>
    </div>

    <form action="process_return.php" method="POST" id="returnForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="idempotency_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16)), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="return_type" id="return_type" value="with_invoice">

        <!-- ইনভয়েসসহ -->
        <div id="withInvoiceBox" class="rtn-mode-panel">
            <div class="rtn-group">
                <label><?php echo t('invoice_number'); ?></label>
                <input type="number" name="sale_id" id="sale_id" placeholder="<?php echo t('invoice_number'); ?>">
                <button type="button" class="rtn-load-btn" onclick="loadSaleItems()">🔍 <?php echo t('load_items'); ?></button>
            </div>
            <div id="itemsResult" class="rtn-items-box"></div>
        </div>

        <!-- ইনভয়েস ছাড়া -->
        <div id="withoutInvoiceBox" class="rtn-mode-panel rtn-hide">
            <div class="rtn-group">
                <label><?php echo t('customer_name_optional'); ?></label>
                <input type="text" name="customer_name" placeholder="<?php echo t('customer_placeholder'); ?>">
            </div>
            <div class="rtn-group">
                <label><?php echo t('select_product'); ?></label>
                <select name="product_id_manual" id="product_id_manual">
                    <option value=""><?php echo t('select_product_placeholder'); ?></option>
                    <?php foreach ($products_list as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rtn-group">
                <label><?php echo t('quantity'); ?></label>
                <input type="number" step="0.01" name="qty_manual" id="qty_manual" placeholder="<?php echo t('quantity_placeholder'); ?>">
            </div>
        </div>

        <!-- এক্সচেঞ্জ -->
        <div id="exchangeBox" class="rtn-mode-panel rtn-hide">
            <div class="rtn-group">
                <label><?php echo t('customer_name_optional'); ?></label>
                <input type="text" name="customer_name_exchange" placeholder="<?php echo t('customer_placeholder'); ?>">
            </div>
            <div class="rtn-group">
                <label><?php echo t('old_product'); ?></label>
                <select name="old_product_id" id="old_product_id" onchange="calcExchange()">
                    <option value=""><?php echo t('select_product_placeholder'); ?></option>
                    <?php foreach ($products_list as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['sale_price']; ?>"><?php echo htmlspecialchars($p['name']); ?> (৳<?php echo number_format((float)($p['sale_price'] ?? 0), 2); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rtn-group">
                <label><?php echo t('old_quantity'); ?></label>
                <input type="number" step="0.01" name="old_qty" id="old_qty" value="1" oninput="calcExchange()">
            </div>
            <div class="rtn-group">
                <label><?php echo t('new_product'); ?></label>
                <select name="new_product_id" id="new_product_id" onchange="calcExchange()">
                    <option value=""><?php echo t('select_product_placeholder'); ?></option>
                    <?php foreach ($products_list as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['sale_price']; ?>"><?php echo htmlspecialchars($p['name']); ?> (৳<?php echo number_format((float)($p['sale_price'] ?? 0), 2); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rtn-group">
                <label><?php echo t('new_quantity'); ?></label>
                <input type="number" step="0.01" name="new_qty" id="new_qty" value="1" oninput="calcExchange()">
            </div>

            <div id="diffBox" class="rtn-diff-box rtn-diff-zero"><?php echo t('price_difference_zero'); ?></div>
            <input type="hidden" name="price_difference" id="price_difference" value="0">
        </div>

        <div id="amountReasonBox" class="rtn-group">
            <label><?php echo t('return_amount'); ?></label>
            <input type="number" step="0.01" name="amount" id="amount" placeholder="<?php echo t('return_amount_placeholder'); ?>">
        </div>

        <div class="rtn-group">
            <label><?php echo t('reason_optional'); ?></label>
            <input type="text" name="reason" placeholder="<?php echo t('reason_placeholder'); ?>">
        </div>

        <div class="rtn-group">
            <label><?php echo t('account_select_label'); ?></label>
            <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id" id="account_id" required onchange="updateAccountBalance()">
                <?php foreach ($accs_list as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" data-balance="<?php echo ame_balance_number_masked($a['balance']); ?>"><?php echo htmlspecialchars(ame_account_option_label($a)); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="accBalanceHint" class="rtn-acc-hint"></div>
        </div>

        <button type="submit" class="rtn-submit">✅ <?php echo t('confirm_button'); ?></button>
    </form>
</div>