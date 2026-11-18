<?php

function get_invoice_return_balance(
    $db,
    $sale_id,
    $product_id,
    $lock_rows = false
) {
    if ($sale_id <= 0 || $product_id <= 0) {
        throw new InvalidArgumentException(
            'ইনভয়েস অথবা পণ্য সঠিক নয়।'
        );
    }

    $item_sql = '
        SELECT qty, sale_price
        FROM sale_items
        WHERE sale_id = ? AND product_id = ?
    ';

    if ($lock_rows) {
        $item_sql .= ' FOR UPDATE';
    }

    $item_stmt = $db->prepare($item_sql);
    $item_stmt->bind_param(
        'ii',
        $sale_id,
        $product_id
    );
    $item_stmt->execute();

    $item_result = $item_stmt->get_result();
    $sold_quantity = 0;
    $sales_value = 0;

    while ($item = $item_result->fetch_assoc()) {
        $quantity = (float)$item['qty'];
        $sold_quantity += $quantity;
        $sales_value += $quantity * (float)$item['sale_price'];
    }

    $item_stmt->close();

    if ($sold_quantity <= 0) {
        throw new InvalidArgumentException(
            'এই পণ্যটি উক্ত ইনভয়েসে পাওয়া যায়নি।'
        );
    }

    $return_sql = "
        SELECT qty
        FROM sale_returns
        WHERE sale_id = ?
          AND product_id = ?
          AND return_type = 'with_invoice'
    ";

    if ($lock_rows) {
        $return_sql .= ' FOR UPDATE';
    }

    $return_stmt = $db->prepare($return_sql);
    $return_stmt->bind_param(
        'ii',
        $sale_id,
        $product_id
    );
    $return_stmt->execute();

    $return_result = $return_stmt->get_result();
    $returned_quantity = 0;

    while ($return = $return_result->fetch_assoc()) {
        $returned_quantity += (float)$return['qty'];
    }

    $return_stmt->close();

    return [
        'sold_quantity' => $sold_quantity,
        'returned_quantity' => $returned_quantity,
        'remaining_quantity' => max(
            0,
            $sold_quantity - $returned_quantity
        ),
        'average_sale_price' => $sold_quantity > 0
            ? $sales_value / $sold_quantity
            : 0
    ];
}

function validate_invoice_return_quantity(
    $db,
    $sale_id,
    $product_id,
    $requested_quantity,
    $is_english = false
) {
    $balance = get_invoice_return_balance(
        $db,
        $sale_id,
        $product_id,
        true
    );

    $remaining = (float)$balance['remaining_quantity'];

    if ($remaining <= 0) {
        throw new InvalidArgumentException(
            $is_english
                ? 'This product has already been returned completely.'
                : 'এই পণ্যটি ইতিমধ্যে সম্পূর্ণ ফেরত দেওয়া হয়েছে।'
        );
    }

    if ($requested_quantity > $remaining + 0.000001) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Maximum returnable quantity: ' . $remaining
                : 'সর্বোচ্চ ফেরতযোগ্য পরিমাণ: ' . $remaining
        );
    }

    return $balance;
}