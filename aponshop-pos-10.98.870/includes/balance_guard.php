<?php
/* 10.98.711 — সাপ্লায়ার/কাস্টমার ব্যালেন্সের পাহারা: প্রতিটি পরিবর্তনের অডিট (ডাটাবেজ ট্রিগার), শুধু লগ — হিসাব বদলায় না */

if (!function_exists('ame_bg_context')) {
    function ame_bg_context($db): void
    {
        if (!($db instanceof mysqli)) {
            return;
        }
        $uri = (string)($_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'cli' : ''));
        $uri = substr(preg_replace('/[^\x20-\x7E]/', '', $uri), 0, 150);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $ctx = $db->real_escape_string($uri . '|u' . $uid);
        @$db->query("SET @ame_ctx = '" . $ctx . "'");
    }
}

if (!function_exists('ame_bg_ensure_tables')) {
    function ame_bg_ensure_tables(mysqli $db): void
    {
        @$db->query("CREATE TABLE IF NOT EXISTS ame_balance_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            party_type VARCHAR(12) NOT NULL,
            party_id INT NOT NULL,
            old_value DECIMAL(15,2) NULL,
            new_value DECIMAL(15,2) NULL,
            ctx VARCHAR(190) NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bg_party (party_type, party_id, id),
            KEY idx_bg_time (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        @$db->query("CREATE TABLE IF NOT EXISTS ame_balance_lock (
            party_type VARCHAR(12) NOT NULL,
            party_id INT NOT NULL,
            locked_value DECIMAL(15,2) NOT NULL DEFAULT 0,
            locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_by INT NOT NULL DEFAULT 0,
            PRIMARY KEY (party_type, party_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('ame_bg_triggers_installed')) {
    function ame_bg_triggers_installed(mysqli $db): bool
    {
        $r = @$db->query("SHOW TRIGGERS WHERE `Trigger` IN ('ame_bg_sup_au','ame_bg_cus_au')");
        return $r && $r->num_rows >= 2;
    }
}

if (!function_exists('ame_bg_install')) {
    /** @return array{ok:bool,message:string} */
    function ame_bg_install(mysqli $db): array
    {
        ame_bg_ensure_tables($db);
        $sql = [
            'DROP TRIGGER IF EXISTS ame_bg_sup_au',
            'DROP TRIGGER IF EXISTS ame_bg_cus_au',
            "CREATE TRIGGER ame_bg_sup_au AFTER UPDATE ON suppliers FOR EACH ROW
             BEGIN
                IF NOT (OLD.total_due <=> NEW.total_due) THEN
                    INSERT INTO ame_balance_audit (party_type, party_id, old_value, new_value, ctx)
                    VALUES ('supplier', NEW.id, OLD.total_due, NEW.total_due, @ame_ctx);
                END IF;
             END",
            "CREATE TRIGGER ame_bg_cus_au AFTER UPDATE ON customers FOR EACH ROW
             BEGIN
                IF NOT (OLD.receivable_balance <=> NEW.receivable_balance) THEN
                    INSERT INTO ame_balance_audit (party_type, party_id, old_value, new_value, ctx)
                    VALUES ('customer', NEW.id, OLD.receivable_balance, NEW.receivable_balance, @ame_ctx);
                END IF;
             END",
        ];
        try {
            foreach ($sql as $q) {
                if (!$db->query($q)) {
                    return ['ok' => false, 'message' => 'ডাটাবেজ ট্রিগার চালু করা যায়নি: ' . $db->error];
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'ডাটাবেজ ট্রিগার চালু করা যায়নি: ' . $e->getMessage()];
        }
        return ['ok' => ame_bg_triggers_installed($db), 'message' => ''];
    }
}
