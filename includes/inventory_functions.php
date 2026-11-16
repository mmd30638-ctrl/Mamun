<?php
// Inventory Helper Functions

require_once __DIR__ . '/product_studio.php';

function ame_ensure_category_archive($db): void
{
    $tbl = @$db->query("SHOW TABLES LIKE 'categories'");
    if (!$tbl || $tbl->num_rows === 0) {
        return;
    }
    $archived = @$db->query("SHOW COLUMNS FROM categories LIKE 'archived'");
    if (!$archived || $archived->num_rows === 0) {
        @$db->query('ALTER TABLE categories ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0');
    }
    $at = @$db->query("SHOW COLUMNS FROM categories LIKE 'archived_at'");
    if (!$at || $at->num_rows === 0) {
        @$db->query('ALTER TABLE categories ADD COLUMN archived_at DATETIME NULL');
    }
}

function ensure_category_exchange_schema($db): void
{
    $tbl = @$db->query("SHOW TABLES LIKE 'categories'");
    if (!$tbl || $tbl->num_rows === 0) {
        return;
    }
    $col = @$db->query("SHOW COLUMNS FROM categories LIKE 'is_exchange'");
    if (!$col || $col->num_rows === 0) {
        @$db->query('ALTER TABLE categories ADD COLUMN is_exchange TINYINT(1) NOT NULL DEFAULT 0');
    }
}

function ame_exchange_category_id($db): int
{
    ensure_category_exchange_schema($db);
    $res = @$db->query('SELECT id FROM categories WHERE is_exchange = 1 ORDER BY id ASC LIMIT 1');
    if ($res && ($row = $res->fetch_assoc())) {
        return (int)$row['id'];
    }
    return 0;
}

function ame_category_is_exchange($db, int $category_id): bool
{
    if ($category_id <= 0) {
        return false;
    }
    return ame_exchange_category_id($db) === $category_id;
}

function ame_sync_old_battery_from_category($db, int $category_id): void
{
    ensure_category_exchange_schema($db);
    $prod_col = @$db->query("SHOW COLUMNS FROM products LIKE 'is_old_battery'");
    if (!$prod_col || $prod_col->num_rows === 0) {
        return;
    }
    if ($category_id > 0) {
        @$db->query(
            'UPDATE products SET is_old_battery = CASE WHEN category_id = '
            . (int)$category_id
            . ' THEN 1 ELSE 0 END'
        );
        $type_col = @$db->query("SHOW COLUMNS FROM products LIKE 'product_type'");
        if ($type_col && $type_col->num_rows > 0) {
            @$db->query(
                "UPDATE products SET product_type = 'old_battery' WHERE category_id = "
                . (int)$category_id
            );
        }
        return;
    }
    @$db->query('UPDATE products SET is_old_battery = 0');
}

function ame_set_exchange_category($db, int $category_id): void
{
    ensure_category_exchange_schema($db);
    @$db->query('UPDATE categories SET is_exchange = 0');
    if ($category_id > 0) {
        $stmt = $db->prepare('UPDATE categories SET is_exchange = 1 WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $category_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    ame_sync_old_battery_from_category($db, $category_id);
}

function ame_apply_exchange_category_to_product($db, int $product_id, int $category_id): void
{
    if ($product_id <= 0) {
        return;
    }
    $ex = ame_exchange_category_id($db);
    if ($ex <= 0) {
        return;
    }
    $flag = $category_id === $ex ? 1 : 0;
    $stmt = $db->prepare('UPDATE products SET is_old_battery = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $flag, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    if ($flag === 1) {
        $type_col = @$db->query("SHOW COLUMNS FROM products LIKE 'product_type'");
        if ($type_col && $type_col->num_rows > 0) {
            $upd = $db->prepare("UPDATE products SET product_type = 'old_battery' WHERE id = ?");
            if ($upd) {
                $upd->bind_param('i', $product_id);
                $upd->execute();
                $upd->close();
            }
        }
    }
}

// ⭐ ইনভেন্টরির নতুন ফিচারের জন্য দরকারি কলাম — একবারই অটো-তৈরি হবে
function ensure_inventory_schema($db) {
    ensure_product_studio_schema($db);
    ensure_category_exchange_schema($db);
}
