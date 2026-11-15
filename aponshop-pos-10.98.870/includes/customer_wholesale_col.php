<?php
/**
 * POS party search — always_wholesale কলাম (পুরো customer_schema ছাড়া)।
 */
function ame_customer_wholesale_col_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $res = @$db->query("SHOW COLUMNS FROM customers LIKE 'always_wholesale'");
    if ($res && $res->num_rows > 0) {
        $ready = true;
        return true;
    }
    @$db->query('ALTER TABLE customers ADD COLUMN always_wholesale TINYINT(1) NOT NULL DEFAULT 0');
    $res2 = @$db->query("SHOW COLUMNS FROM customers LIKE 'always_wholesale'");
    $ready = $res2 && $res2->num_rows > 0;
    return $ready;
}