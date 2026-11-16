<?php
/**
 * ভাড়া মালিক/সোর্স — বিদ্যমান rental_contracts-এ মালিক যোগ করে।
 * কাস্টমার ভাড়া আগের মতোই কাস্টমার প্রোফাইলে থাকে।
 */

function ensure_rental_schema(mysqli $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_owners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            pay_cycle VARCHAR(16) NOT NULL DEFAULT 'monthly',
            pay_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            custom_days INT NOT NULL DEFAULT 7,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ro_status (status),
            INDEX idx_ro_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    rental_schema_add_columns($db, 'rental_owners', [
        'vehicle_no' => 'VARCHAR(40) DEFAULT NULL',
        'vehicle_name' => 'VARCHAR(120) DEFAULT NULL',
        'entry_date' => 'DATE DEFAULT NULL',
    ]);
    $db->query(
        "UPDATE rental_owners SET entry_date = DATE(created_at)
         WHERE entry_date IS NULL AND created_at IS NOT NULL"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            vehicle_no VARCHAR(40) NOT NULL,
            vehicle_name VARCHAR(120) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rv_owner (owner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_owner_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            method VARCHAR(30) NOT NULL DEFAULT 'CASH',
            paid_at DATETIME NOT NULL,
            ref VARCHAR(80) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'posted',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rop_owner (owner_id),
            INDEX idx_rop_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(40) NOT NULL,
            row_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
            old_json TEXT DEFAULT NULL,
            new_json TEXT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_raud_row (table_name, row_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $chk = $db->query("SHOW TABLES LIKE 'rental_contracts'");
    if ($chk && $chk->num_rows > 0) {
        rental_schema_add_columns($db, 'rental_contracts', [
            'owner_id' => 'INT DEFAULT NULL',
            'owner_rate' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'owner_cycle' => "VARCHAR(16) NOT NULL DEFAULT 'monthly'",
            'owner_custom_days' => 'INT NOT NULL DEFAULT 7',
            'owner_start_date' => 'DATE DEFAULT NULL',
            'vehicle_no' => 'VARCHAR(40) DEFAULT NULL',
            'vehicle_id' => 'INT DEFAULT NULL',
        ]);
    }
}

function rental_schema_add_columns(mysqli $db, string $table, array $columns): void
{
    $have = [];
    $res = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[] = $row['Field'];
        }
    }
    foreach ($columns as $name => $ddl) {
        if (in_array($name, $have, true)) {
            continue;
        }
        $safe = preg_replace('/[^a-z0-9_]/i', '', $name);
        $db->query('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . $safe . '` ' . $ddl);
    }
}
