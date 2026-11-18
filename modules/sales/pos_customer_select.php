<select id="customer_id" class="pos-customer-select-native" tabindex="-1" aria-hidden="true">
    <option value="">
        <?php echo $escape($customer_words['retail']); ?>
    </option>

    <?php
    $plain = static function ($value) use ($escape) {
        return $escape(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    };
    ?>

    <?php foreach ($ame_pos_customer_rows as $customer): ?>
        <?php
        $is_supplier = !empty($customer['is_supplier']);
        $rec_bal = round((float)($customer['receivable_balance'] ?? 0), 2);
        $pay_bal = round((float)($customer['payable_balance'] ?? 0), 2);

        if (function_exists('ame_resolve_party_sale_balance')) {
            $balance = ame_resolve_party_sale_balance($customer, null);
        } elseif (abs($rec_bal) > 0.009 || $pay_bal > 0.009) {
            $balance = round($rec_bal - max(0.0, $pay_bal), 2);
            $sup_due = $is_supplier ? round((float)($customer['total_due'] ?? 0), 2) : 0.0;
            if ($pay_bal < 0.009 && $sup_due > 0.009) {
                $balance = round($balance - $sup_due, 2);
            }
        } elseif (isset($customer['total_due']) && (float)$customer['total_due'] > 0.009) {
            $balance = $is_supplier ? -round((float)$customer['total_due'], 2) : round((float)$customer['total_due'], 2);
        } else {
            $balance = 0.0;
        }

        $credit_limit = (float)(
            $customer['credit_limit'] ?? 0
        );
        $party_code = trim((string)($customer['supplier_party_code'] ?? ''));
        $code = (string)(
            ($party_code !== '' ? $party_code : null)
            ?? $customer['customer_code']
            ?? $customer['cus_id_code']
            ?? $customer['id']
        );
        $phone = (string)($customer['phone'] ?? '');
        $alt_phone = (string)($customer['alt_phone'] ?? '');
        $name = (string)($customer['name'] ?? '');
        $is_customer = !isset($customer['is_customer']) || (string)$customer['is_customer'] === '' || (int)$customer['is_customer'] === 1;
        $role_badge = $is_supplier ? ' [সাপ্লায়ার]' : '';

        if ($balance < -0.009) {
            $bal_text = ' - পাওনা ৳' . number_format(abs($balance), 2);
        } elseif ($balance > 0.009) {
            $bal_text = ' - বকেয়া ৳' . number_format($balance, 2);
        } else {
            $bal_text = ' - ৳0.00';
        }
        ?>
        <option
            value="<?php echo (int)$customer['id']; ?>"
            data-name="<?php echo $plain($name . $role_badge); ?>"
            data-raw-name="<?php echo $plain($name); ?>"
            data-is-supplier="<?php echo $is_supplier ? '1' : '0'; ?>"
            data-is-customer="<?php echo $is_customer ? '1' : '0'; ?>"
            data-party-code="<?php echo $plain($party_code); ?>"
            data-code="<?php echo $plain($code); ?>"
            data-phone="<?php echo $plain($phone); ?>"
            data-alt="<?php echo $plain($alt_phone); ?>"
            data-due="<?php echo $escape(number_format($balance, 2, '.', '')); ?>"
            data-credit="<?php echo $escape(number_format($credit_limit, 2, '.', '')); ?>"
        >
            <?php echo $plain($name); ?><?php echo $role_badge; ?>
            - <?php echo $plain($code); ?>
            <?php echo $bal_text; ?>
        </option>
    <?php endforeach; ?>
</select>
