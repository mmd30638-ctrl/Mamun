<?php

function normalize_product_code($code)
{
    $code = strtoupper(trim((string)$code));
    $code = preg_replace('/\s+/', '-', $code);

    if (
        strlen($code) < 3
        || strlen($code) > 50
        || !preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $code)
    ) {
        throw new InvalidArgumentException(
            'পণ্য আইডিতে শুধু ইংরেজি অক্ষর, সংখ্যা, ড্যাশ, ডট ও আন্ডারস্কোর ব্যবহার করুন।'
        );
    }

    return $code;
}

function product_code_exists($db, $code, $exclude_id = 0)
{
    $stmt = $db->prepare(
        'SELECT id
         FROM products
         WHERE sku = ? AND id != ?
         LIMIT 1'
    );

    $stmt->bind_param(
        'si',
        $code,
        $exclude_id
    );

    $stmt->execute();

    $exists = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    return $exists;
}

function product_code_holder($db, $code, $exclude_id = 0)
{
    $stmt = $db->prepare(
        'SELECT id, name, sku, status
         FROM products
         WHERE sku = ? AND id != ?
         ORDER BY id ASC
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('si', $code, $exclude_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'name' => (string)($row['name'] ?? ''),
        'sku' => (string)($row['sku'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
    ];
}

function generate_product_code($db, $product_id)
{
    $base_code = 'PRD-' . str_pad(
        (string)$product_id,
        6,
        '0',
        STR_PAD_LEFT
    );

    $product_code = $base_code;
    $suffix = 1;

    while (
        product_code_exists(
            $db,
            $product_code,
            $product_id
        )
    ) {
        $product_code = $base_code . '-' . $suffix;
        $suffix++;
    }

    return $product_code;
}