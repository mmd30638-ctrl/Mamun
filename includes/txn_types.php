<?php
/* 10.98.789 */

/**
 * খরচ/আয় ধরন — ডাটাবেসের প্রতিটি সারি, আইডি অনুযায়ী একবার, নাম অনুযায়ী সাজানো।
 * এক টেবিল থেকে সব সারি; কাটা-ছেঁড়া কুয়েরি নেই, তাই কোনো ধরন বাদ যায় না।
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array{id:int,name:string}>
 */
function ame_txn_types_unique(array $rows): array
{
    $byId = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            continue;
        }
        $byId[$id] = ['id' => $id, 'name' => $name];
    }
    $list = array_values($byId);
    usort($list, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });
    return $list;
}

function ame_txn_type_normalize_name(string $name): string
{
    $name = trim($name);
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 100);
    }
    return substr($name, 0, 100);
}

/**
 * সাধারণ লেনদেনের ধরন। expense_categories-এর সব সারি।
 *
 * @return list<array{id:int,name:string}>
 */
function ame_txn_types(mysqli $db): array
{
    $rows = [];
    $res = @$db->query('SELECT id, name FROM expense_categories');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return ame_txn_types_unique($rows);
}

function ame_txn_types_can_admin(): bool
{
    if (!empty($_SESSION['is_super_admin'])) {
        return true;
    }
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    return in_array($role, ['admin', 'super_admin', 'superadmin'], true);
}

/**
 * ধরনের নাম বদলান। লেজারের টাকা, জমা-খরচ বা category_id বদলায় না।
 *
 * @return array{ok:bool,message:string,id:int,name:string}
 */
function ame_txn_type_rename(mysqli $db, int $id, string $name): array
{
    $name = ame_txn_type_normalize_name($name);
    if ($id <= 0 || $name === '') {
        return ['ok' => false, 'message' => 'ধরনের নাম দিন।', 'id' => 0, 'name' => ''];
    }

    $find = $db->prepare('SELECT id, name FROM expense_categories WHERE id = ? LIMIT 1');
    if (!$find) {
        return ['ok' => false, 'message' => 'ধরন খোঁজা যায়নি।', 'id' => 0, 'name' => ''];
    }
    $find->bind_param('i', $id);
    $find->execute();
    $row = $find->get_result()->fetch_assoc();
    $find->close();
    if (!$row) {
        return ['ok' => false, 'message' => 'এই ধরন পাওয়া যায়নি।', 'id' => 0, 'name' => ''];
    }

    $dup = $db->prepare('SELECT id FROM expense_categories WHERE name = ? AND id <> ? LIMIT 1');
    if ($dup) {
        $dup->bind_param('si', $name, $id);
        $dup->execute();
        $exists = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($exists) {
            return ['ok' => false, 'message' => 'এই নামে ধরন আগেই আছে।', 'id' => $id, 'name' => $name];
        }
    }

    if (trim((string) $row['name']) === $name) {
        return ['ok' => true, 'message' => 'ধরনের নাম আগের মতোই আছে।', 'id' => $id, 'name' => $name];
    }

    $up = $db->prepare('UPDATE expense_categories SET name = ? WHERE id = ? LIMIT 1');
    if (!$up) {
        return ['ok' => false, 'message' => 'ধরনের নাম সংরক্ষণ যায়নি।', 'id' => $id, 'name' => $name];
    }
    $up->bind_param('si', $name, $id);
    $saved = $up->execute();
    $err = $up->error;
    $up->close();
    if (!$saved) {
        return ['ok' => false, 'message' => 'ধরনের নাম সংরক্ষণ যায়নি: ' . $err, 'id' => $id, 'name' => $name];
    }
    return ['ok' => true, 'message' => 'ধরনের নাম সংরক্ষণ হয়েছে। টাকার হিসাব বদলায়নি।', 'id' => $id, 'name' => $name];
}

/**
 * ধরন মুছুন। লেনদেনের টাকা, জমা-খরচ বা লেজার বদলায় না; শুধু ওই লেনদেনগুলো «ধরন নেই» হয়।
 *
 * @return int যতগুলো লেনদেন «ধরন নেই» হলো
 */
function ame_txn_type_delete(mysqli $db, int $id): int
{
    if ($id <= 0) {
        return 0;
    }
    $cleared = 0;
    $up = $db->prepare('UPDATE account_ledger SET category_id = NULL WHERE category_id = ?');
    if ($up) {
        $up->bind_param('i', $id);
        $up->execute();
        $cleared = max(0, (int) $up->affected_rows);
        $up->close();
    }
    $del = $db->prepare('DELETE FROM expense_categories WHERE id = ?');
    if ($del) {
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
    }
    return $cleared;
}

/** একবারই চলে: তালিকা থেকে «পুরাতন ব্যাটারি» ধরন সরায়। */
function ame_txn_types_cleanup_once(mysqli $db): void
{
    $key = 'txn_type_cleanup_10_98_690';
    if (!function_exists('get_app_setting')) {
        return;
    }
    if ((string) get_app_setting($db, $key, '') === '1') {
        return;
    }
    $res = @$db->query("SELECT id FROM expense_categories WHERE TRIM(name) = 'পুরাতন ব্যাটারি'");
    if ($res) {
        $ids = array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'id'));
        foreach ($ids as $id) {
            ame_txn_type_delete($db, $id);
        }
    }
    set_app_setting($db, $key, '1');
}
