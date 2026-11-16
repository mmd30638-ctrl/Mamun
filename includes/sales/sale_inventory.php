<?php

function ame_sale_prepare_items(
    $db,
    $cart,
    $is_english,
    $require_stock = true
) {
    if (function_exists('ame_sale_stock_required') && !ame_sale_stock_required()) {
        $require_stock = false;
    } elseif (!empty($_SESSION['ame_sell_without_stock'])) {
        $require_stock = false;
    }
    if (!is_array($cart)) {
        $cart = [];
    }

    if (count($cart) > 100) {
        ame_sale_fail(
            $is_english,
            'একটি বিক্রয়ে অতিরিক্ত পণ্য দেওয়া হয়েছে।',
            'Too many products in one sale.'
        );
    }

    $combined = [];
    $custom_items = [];

    foreach ($cart as $row) {
        $quantity = (float)($row['qty'] ?? 0);
        $requested_price = (float)(
            $row['current_price'] ?? -1
        );
        $product_id = (int)($row['id'] ?? $row['product_id'] ?? 0);
        $is_custom = !empty($row['is_custom']) || $product_id <= 0;

        if ($quantity <= 0) {
            ame_sale_fail(
                $is_english,
                'পণ্য অথবা পরিমাণ সঠিক নয়।',
                'Invalid product or quantity.'
            );
        }

        if ($is_custom) {
            $name = trim((string)($row['name'] ?? $row['item_name'] ?? ''));

            if ($name === '' || $requested_price < 0) {
                ame_sale_fail(
                    $is_english,
                    'কাস্টম পণ্যের নাম ও মূল্য সঠিক নয়।',
                    'Custom item name or price is invalid.'
                );
            }

            // ব্যবহারকারী POS পেজের "স্টকে নেই এমন পণ্য হাতে যোগ করুন" বক্সে
            // ক্রয়মূল্য দিলে সেটা এখানে ব্যবহার হবে (না দিলে আগের মতোই ০)।
            $custom_buy_price = (float)($row['buy_price'] ?? 0);
            if ($custom_buy_price < 0) {
                $custom_buy_price = 0.0;
            }

            $custom_items[] = [
                'product_id' => null,
                'name' => $name,
                'quantity' => $quantity,
                'purchase_price' => $custom_buy_price,
                'sale_price' => $requested_price,
                'is_custom' => true
            ];

            continue;
        }

        $unit_id = (int)($row['unit_id'] ?? 0);
        $key = $product_id . ':' . $unit_id;

        if (isset($combined[$key])) {
            if (
                abs(
                    $combined[$key]['requested_price']
                    - $requested_price
                ) >= 0.01
            ) {
                ame_sale_fail(
                    $is_english,
                    'একই পণ্যে একই ইউনিটে একাধিক মূল্য ব্যবহার করা যাবে না।',
                    'A product cannot use multiple prices in the same unit.'
                );
            }

            $combined[$key]['quantity'] += $quantity;
        } else {
            $combined[$key] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'requested_price' => $requested_price,
                'unit_id' => $unit_id
            ];
        }
    }

    /*
     * একই সময়ে একাধিক বিক্রয়ে Deadlock কমাতে
     * Product ID অনুযায়ী Lock নেওয়া হবে।
     */
    ksort($combined);

    $items = [];
    $subtotal = 0;
    $base_used = [];

    foreach ($combined as $requested) {
        $product_id = (int)$requested['product_id'];

        $stmt = $db->prepare(
            "SELECT
                id,
                name,
                purchase_price,
                sale_price,
                wholesale_price,
                dealer_price,
                stock
             FROM products
             WHERE id = ?
               AND (
                    status = 'active'
                    OR status IS NULL
                    OR status = ''
               )
             FOR UPDATE"
        );

        $stmt->bind_param(
            'i',
            $product_id
        );

        $stmt->execute();

        $product = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$product) {
            ame_sale_fail(
                $is_english,
                'নির্বাচিত একটি পণ্য পাওয়া যায়নি।',
                'A selected product was not found.'
            );
        }

        $quantity = (float)$requested['quantity'];
        $unit_id = (int)($requested['unit_id'] ?? 0);
        $base_qty = $quantity;
        if ($unit_id > 0 && is_file(__DIR__ . '/../unit_engine.php')) {
            require_once __DIR__ . '/../unit_engine.php';
            $base_qty = (float)ame_unit_to_base($db, $product_id, $quantity, $unit_id);
        }
        if (!isset($base_used[$product_id])) {
            $base_used[$product_id] = 0.0;
        }
        $base_used[$product_id] += $base_qty;

        $on_hand = (float)$product['stock'];
        $store_id = 0;
        if (function_exists('ame_store_stock_qty') && function_exists('current_store_id')) {
            $store_id = (int)current_store_id();
            if ($store_id > 0) {
                $on_hand = ame_store_stock_qty($db, $store_id, $product_id);
            }
        }

        $reserved = 0.0;
        if (function_exists('ame_workshop_reserved_qty')) {
            $reserved = (float)ame_workshop_reserved_qty($db, $product_id, $store_id);
        }
        $available = function_exists('ame_stock_sellable')
            ? ame_stock_sellable((float)$on_hand, $reserved)
            : max(0, round((float)$on_hand - $reserved, 3));

        if ($require_stock && $available + 0.0000001 < $base_used[$product_id]) {
            ame_sale_fail(
                $is_english,
                'পর্যাপ্ত স্টক নেই: ' . $product['name'],
                'Insufficient stock: ' . $product['name']
            );
        }

        $requested_price = (float)(
            $requested['requested_price']
        );

        if ($requested_price < 0) {
            $requested_price =
                (float)$product['sale_price'];
        }

        $sale_price = $requested_price;

        $subtotal += $sale_price * $quantity;

        $items[] = [
            'product_id' => $product_id,
            'name' => $product['name'],
            'quantity' => $quantity,
            'purchase_price' =>
                (float)$product['purchase_price'],
            'sale_price' => $sale_price,
            'is_custom' => false,
            'unit_id' => $unit_id,
            'base_qty' => $base_qty
        ];
    }

    foreach ($custom_items as $custom) {
        $subtotal += $custom['sale_price'] * $custom['quantity'];
        $items[] = $custom;
    }

    return [
        'items' => $items,
        'subtotal' => round($subtotal, 2)
    ];
}

function ame_sale_store_items(
    $db,
    $sale_id,
    $items
) {
    foreach ($items as $item) {
        ame_sale_write_item_row(
            $db,
            $sale_id,
            $item,
            true
        );
    }
}
