<?php
// বিক্রয় ফেরত সিস্টেম (ইনভয়েসসহ/ছাড়া/এক্সচেঞ্জ)

function ensure_return_schema($db) {
    try {
        $cols = [];
        $res = $db->query("SHOW COLUMNS FROM sale_returns");
        if ($res) {
            while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
        }

        if (!in_array('product_id', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN product_id INT DEFAULT NULL");
        }
        if (!in_array('qty', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN qty DECIMAL(10,2) DEFAULT NULL");
        }
        if (!in_array('return_type', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN return_type ENUM('with_invoice','without_invoice','exchange') NOT NULL DEFAULT 'with_invoice'");
        } else {
            @$db->query("ALTER TABLE sale_returns MODIFY return_type ENUM('with_invoice','without_invoice','exchange') NOT NULL DEFAULT 'with_invoice'");
        }
        if (!in_array('customer_name', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN customer_name VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('reason', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN reason VARCHAR(255) DEFAULT NULL");
        }
        // ⭐ এক্সচেঞ্জের জন্য নতুন কলাম
        if (!in_array('exchange_product_id', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN exchange_product_id INT DEFAULT NULL");
        }
        if (!in_array('exchange_qty', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN exchange_qty DECIMAL(10,2) DEFAULT NULL");
        }
        if (!in_array('price_difference', $cols)) {
            @$db->query("ALTER TABLE sale_returns ADD COLUMN price_difference DECIMAL(12,2) DEFAULT NULL");
        }

        @$db->query("ALTER TABLE sale_returns MODIFY sale_id INT NULL");
        @$db->query("ALTER TABLE sale_returns MODIFY total_return_amount DECIMAL(12,2) NULL");
        @$db->query("ALTER TABLE sale_returns MODIFY account_id INT NULL");

    } catch (\Throwable $e) {
        error_log("ensure_return_schema error: " . $e->getMessage());
    }
}
?>