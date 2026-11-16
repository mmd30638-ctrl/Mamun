<?php
// ইনভয়েস টেমপ্লেট কাস্টমাইজেশন

function ensure_invoice_designer_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS invoice_layout_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        layout_json LONGTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_invoice_settings_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS invoice_settings (
        id INT PRIMARY KEY DEFAULT 1,
        footer_note VARCHAR(255) DEFAULT 'ধন্যবাদ, আবার আসবেন!',
        show_discount TINYINT(1) NOT NULL DEFAULT 1,
        show_payment_method TINYINT(1) NOT NULL DEFAULT 1,
        show_address TINYINT(1) NOT NULL DEFAULT 1,
        paper_width VARCHAR(10) NOT NULL DEFAULT '80mm',
        accent_color VARCHAR(20) NOT NULL DEFAULT 'green',
        pos_discount_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
        pos_discount_auto TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pctCol = @$db->query("SHOW COLUMNS FROM invoice_settings LIKE 'pos_discount_percent'");
    if (!$pctCol || $pctCol->num_rows === 0) {
        @$db->query("ALTER TABLE invoice_settings ADD COLUMN pos_discount_percent DECIMAL(8,2) NOT NULL DEFAULT 0");
    }
    $autoCol = @$db->query("SHOW COLUMNS FROM invoice_settings LIKE 'pos_discount_auto'");
    if (!$autoCol || $autoCol->num_rows === 0) {
        @$db->query("ALTER TABLE invoice_settings ADD COLUMN pos_discount_auto TINYINT(1) NOT NULL DEFAULT 0");
    }

    $check = $db->query("SELECT id FROM invoice_settings WHERE id = 1");
    if (!$check || $check->num_rows === 0) {
        $db->query("INSERT INTO invoice_settings (id) VALUES (1)");
    }
}

function get_invoice_settings($db) {
    ensure_invoice_settings_schema($db);
    return $db->query("SELECT * FROM invoice_settings WHERE id = 1")->fetch_assoc();
}

// ⭐ থিম কালার প্যালেট থেকেই accent color বাছাই (green/black/red)
function get_invoice_accent_hex($accent) {
    $map = ['green' => '#2E7D32', 'black' => '#000000', 'red' => '#B71C1C'];
    return $map[$accent] ?? '#2E7D32';
}
?>