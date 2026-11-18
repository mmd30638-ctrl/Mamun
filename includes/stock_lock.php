<?php
/**
 * Row lock for a product. Does not change stock, price, or any balance.
 */

function ame_in_transaction(mysqli $db): bool
{
    try {
        $res = $db->query('SELECT @@in_transaction AS t');
        if ($res && ($row = $res->fetch_assoc())) {
            return (int) ($row['t'] ?? 0) === 1;
        }
    } catch (Throwable $error) {
        error_log('ame_in_transaction: ' . $error->getMessage());
    }
    return false;
}

function ame_tx_open(mysqli $db): bool
{
    if (ame_in_transaction($db)) {
        return false;
    }
    return $db->begin_transaction();
}

function ame_lock_product_row(mysqli $db, int $productId): void
{
    if ($productId <= 0) {
        return;
    }
    $stmt = $db->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $stmt->free_result();
    $stmt->close();
}
