<?php
/**
 * Default payment account mark. Column is auto-ALTERed on settings.
 * Never ships config.php / db.php.
 */

if (!function_exists('ame_pay_mark_db')) {
    function ame_pay_mark_db()
    {
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) {
            return $GLOBALS['db'];
        }
        if (!function_exists('ame_brand_type_db')) {
            $bt = __DIR__ . '/brand_type.php';
            if (is_file($bt)) {
                require_once $bt;
            }
        }
        return function_exists('ame_brand_type_db') ? ame_brand_type_db() : null;
    }
}

if (!function_exists('ame_pay_mark_ensure')) {
    function ame_pay_mark_ensure($db = null): void
    {
        $db = $db instanceof mysqli ? $db : ame_pay_mark_db();
        if (!$db instanceof mysqli) {
            return;
        }
        if (function_exists('ame_schema_stamp') && ame_schema_stamp('pay_mark')) {
            return;
        }
        $ok = @$db->query("SHOW COLUMNS FROM settings LIKE 'default_pay_account_id'");
        if ($ok && $ok->num_rows === 0) {
            @$db->query('ALTER TABLE settings ADD COLUMN default_pay_account_id INT NULL DEFAULT NULL');
        }
        $acc = @$db->query("SHOW COLUMNS FROM accounts LIKE 'is_default'");
        if ($acc && $acc->num_rows === 0) {
            @$db->query('ALTER TABLE accounts ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (function_exists('ame_schema_stamp')) {
            ame_schema_stamp('pay_mark', true);
        }
    }
}

if (!function_exists('ame_pay_mark_has_is_default')) {
    function ame_pay_mark_has_is_default($db): bool
    {
        if (function_exists('ame_schema_stamp') && ame_schema_stamp('pay_mark')) {
            return true;
        }
        $acc = @$db->query("SHOW COLUMNS FROM accounts LIKE 'is_default'");
        return $acc && $acc->num_rows > 0;
    }
}

if (!function_exists('ame_pay_mark_get')) {
    function ame_pay_mark_get($db = null): int
    {
        $db = $db instanceof mysqli ? $db : ame_pay_mark_db();
        if (!$db instanceof mysqli) {
            return 0;
        }
        ame_pay_mark_ensure($db);
        $res = @$db->query('SELECT default_pay_account_id FROM settings LIMIT 1');
        $id = 0;
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $id = (int)($row['default_pay_account_id'] ?? 0);
        }
        if ($id <= 0 && ame_pay_mark_has_is_default($db)) {
            $fb = @$db->query('SELECT id FROM accounts WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
            if ($fb && $fb->num_rows > 0) {
                $id = (int)($fb->fetch_assoc()['id'] ?? 0);
            }
        }
        return $id;
    }
}

if (!function_exists('ame_pay_mark_set')) {
    function ame_pay_mark_set(int $id, $db = null): bool
    {
        $db = $db instanceof mysqli ? $db : ame_pay_mark_db();
        if (!$db instanceof mysqli) {
            return false;
        }
        ame_pay_mark_ensure($db);
        if ($id > 0) {
            $chk = $db->prepare('SELECT id FROM accounts WHERE id = ? LIMIT 1');
            if (!$chk) {
                return false;
            }
            $chk->bind_param('i', $id);
            $chk->execute();
            $found = $chk->get_result();
            $ok = $found && $found->num_rows > 0;
            $chk->close();
            if (!$ok) {
                return false;
            }
            $stmt = $db->prepare('UPDATE settings SET default_pay_account_id = ? LIMIT 1');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok && ame_pay_mark_has_is_default($db)) {
                @$db->query('UPDATE accounts SET is_default = 0');
                $one = $db->prepare('UPDATE accounts SET is_default = 1 WHERE id = ?');
                if ($one) {
                    $one->bind_param('i', $id);
                    $one->execute();
                    $one->close();
                }
            }
            return (bool)$ok;
        }
        $cleared = (bool)@$db->query('UPDATE settings SET default_pay_account_id = NULL LIMIT 1');
        if (ame_pay_mark_has_is_default($db)) {
            @$db->query('UPDATE accounts SET is_default = 0');
        }
        return $cleared;
    }
}
