<?php
function ame_theme_grove(): array
{
    return [
        'header_color' => '#0B3A82',
        'primary_color' => '#123A7A',
        'accent_color' => '#4C1D95',
        'gold_color' => '#E0E7FF',
        'secondary_color' => '#15803D',
        'bg_color' => '#FFFFFF',
        'surface_color' => '#FFFFFF',
        'text_color' => '#0F172A',
        'muted_color' => '#334155',
        'sidebar_color' => '#0A2A5C',
        'border_color' => '#C9D7EE',
        'danger_color' => '#C62828',
        'clock_color' => '#FFFFFF',
    ];
}

function ame_theme_defaults(): array
{
    return ame_theme_grove();
}

function ame_theme_hex(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
        if (strlen($value) === 4) {
            $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }
        return strtoupper($value);
    }
    return $fallback;
}

function ame_theme_fields(): array
{
    return [
        'header_color' => ['হেডার', 'টপ বার, নেভিগেশন ও গ্র্যাডিয়েন্টের গভীর রং', 'Header', 'Top bar, navigation, gradient'],
        'primary_color' => ['প্রাইমারি', 'শিরোনাম, KPI ও জোরালো সংখ্যা', 'Primary', 'Titles, KPIs, strong numbers'],
        'accent_color' => ['অ্যাকসেন্ট', 'লিংক, অ্যাকটিভ মেনু ও প্রধান বাটন', 'Accent', 'Links, active menu, primary buttons'],
        'gold_color' => ['সিগনেচার গোল্ড', 'দোকানের নাম ও প্রিমিয়াম চিপ', 'Signature gold', 'Shop name and premium chips'],
        'secondary_color' => ['সফল', 'জমা, স্ট্যাটাস ও নিশ্চিতকরণ', 'Success', 'Paid, status, confirm'],
        'danger_color' => ['সতর্ক', 'বকেয়া, ডিলিট ও ত্রুটি', 'Alert', 'Dues, delete, errors'],
        'bg_color' => ['ক্যানভাস', 'পেজের পটভূমি', 'Canvas', 'Page background'],
        'surface_color' => ['সারফেস', 'কার্ড ও টেবিলের কাগজ', 'Surface', 'Cards and tables'],
        'text_color' => ['ইঙ্ক', 'মূল লেখার রং', 'Ink', 'Body text'],
        'muted_color' => ['মিউটেড', 'সাহায্যকারী লেখা ও লেবেল', 'Muted', 'Hints and labels'],
        'sidebar_color' => ['সাইডবার', 'বাম মেনু ও ডিভাইস রেল', 'Sidebar', 'Left menu rail'],
        'border_color' => ['বর্ডার', 'হেয়ারলাইন ও ডিভাইডার', 'Border', 'Hairlines and dividers'],
        'clock_color' => ['হেডার ঘড়ি', 'উপরের ঘড়ির সংখ্যা ও তারিখের রং', 'Header clock', 'Clock digits and date'],
    ];
}

function ame_theme_field_cards(bool $english = false): array
{
    $out = [];
    foreach (ame_theme_fields() as $key => $meta) {
        $out[$key] = [
            'name' => $english ? (string)($meta[2] ?? $meta[0]) : (string)$meta[0],
            'hint' => $english ? (string)($meta[3] ?? $meta[1]) : (string)$meta[1],
        ];
    }
    return $out;
}

function ame_theme_palettes(): array
{
    $grove = ame_theme_grove();
    return [
        [
            'id' => 'grove',
            'name' => 'Grove Ledger',
            'city' => 'Dhaka',
            'colors' => $grove,
        ],
        [
            'id' => 'paper',
            'name' => 'Paper Ledger',
            'city' => 'Dhaka',
            'colors' => [
                'header_color' => '#FFFFFF',
                'primary_color' => '#111111',
                'accent_color' => '#111111',
                'gold_color' => '#111111',
                'secondary_color' => '#111111',
                'danger_color' => '#B91C1C',
                'bg_color' => '#FFFFFF',
                'surface_color' => '#FFFFFF',
                'text_color' => '#111111',
                'muted_color' => '#525252',
                'sidebar_color' => '#FFFFFF',
                'border_color' => '#E5E5E5',
                'clock_color' => '#111111',
            ],
        ],
        [
            'id' => 'atelier',
            'name' => 'Atelier Navy',
            'city' => 'Dhaka / London',
            'colors' => [
                'header_color' => '#0B1E4D',
                'primary_color' => '#0B1E4D',
                'accent_color' => '#1D9BF0',
                'gold_color' => '#C9A227',
                'secondary_color' => '#0E8F52',
                'danger_color' => '#B91C1C',
                'bg_color' => '#EAF4FF',
                'surface_color' => '#FFFFFF',
                'text_color' => '#0B1E4D',
                'muted_color' => '#5B6B82',
                'sidebar_color' => '#071534',
                'border_color' => '#C5D8EE',
                'clock_color' => '#2DD4BF',
            ],
        ],
        [
            'id' => 'kyoto',
            'name' => 'Kyoto Ink',
            'city' => 'Kyoto',
            'colors' => [
                'header_color' => '#1A2F23',
                'primary_color' => '#1A2F23',
                'accent_color' => '#2D6A4F',
                'gold_color' => '#C4A35A',
                'secondary_color' => '#40916C',
                'danger_color' => '#9B2226',
                'bg_color' => '#F3F0E8',
                'surface_color' => '#FFFDF7',
                'text_color' => '#1B1914',
                'muted_color' => '#6B6258',
                'sidebar_color' => '#122018',
                'border_color' => '#DDD4C4',
                'clock_color' => '#B7E4C7',
            ],
        ],
        [
            'id' => 'maison',
            'name' => 'Maison Noir',
            'city' => 'Paris',
            'colors' => [
                'header_color' => '#111111',
                'primary_color' => '#1A1A1A',
                'accent_color' => '#C9A227',
                'gold_color' => '#E8D5A3',
                'secondary_color' => '#3F7D4E',
                'danger_color' => '#B42318',
                'bg_color' => '#F6F3EC',
                'surface_color' => '#FFFCF7',
                'text_color' => '#161412',
                'muted_color' => '#6F675C',
                'sidebar_color' => '#0A0A0A',
                'border_color' => '#E4DDD0',
                'clock_color' => '#E8D5A3',
            ],
        ],
        [
            'id' => 'riviera',
            'name' => 'Riviera',
            'city' => 'Nice',
            'colors' => [
                'header_color' => '#0B3D4A',
                'primary_color' => '#0B3D4A',
                'accent_color' => '#1A9B8E',
                'gold_color' => '#E0B04B',
                'secondary_color' => '#0E8F7A',
                'danger_color' => '#C2410C',
                'bg_color' => '#E8F4F4',
                'surface_color' => '#FFFFFF',
                'text_color' => '#12303A',
                'muted_color' => '#5A7278',
                'sidebar_color' => '#082C36',
                'border_color' => '#C5DEDE',
                'clock_color' => '#5EEAD4',
            ],
        ],
        [
            'id' => 'burgundy',
            'name' => 'Burgundy Ledger',
            'city' => 'Lyon',
            'colors' => [
                'header_color' => '#4A1528',
                'primary_color' => '#4A1528',
                'accent_color' => '#9F1239',
                'gold_color' => '#D4A017',
                'secondary_color' => '#3F6B4A',
                'danger_color' => '#9F1239',
                'bg_color' => '#F7EFEA',
                'surface_color' => '#FFF9F5',
                'text_color' => '#2A1218',
                'muted_color' => '#7A5C64',
                'sidebar_color' => '#2C0D18',
                'border_color' => '#E8D4D0',
                'clock_color' => '#F9A8D4',
            ],
        ],
        [
            'id' => 'arctic',
            'name' => 'Arctic Ledger',
            'city' => 'Stockholm',
            'colors' => [
                'header_color' => '#0F172A',
                'primary_color' => '#0F172A',
                'accent_color' => '#38BDF8',
                'gold_color' => '#F59E0B',
                'secondary_color' => '#10B981',
                'danger_color' => '#EF4444',
                'bg_color' => '#F1F5F9',
                'surface_color' => '#FFFFFF',
                'text_color' => '#0F172A',
                'muted_color' => '#64748B',
                'sidebar_color' => '#020617',
                'border_color' => '#E2E8F0',
                'clock_color' => '#7DD3FC',
            ],
        ],
        [
            'id' => 'milan',
            'name' => 'Milan Carrara',
            'city' => 'Milan',
            'colors' => [
                'header_color' => '#2C2A28',
                'primary_color' => '#2C2A28',
                'accent_color' => '#8B7355',
                'gold_color' => '#C4A574',
                'secondary_color' => '#4F6F52',
                'danger_color' => '#9E2A2B',
                'bg_color' => '#F4F1EC',
                'surface_color' => '#FFFCF8',
                'text_color' => '#1C1917',
                'muted_color' => '#6F6A64',
                'sidebar_color' => '#1A1816',
                'border_color' => '#E4DFD6',
                'clock_color' => '#E8D5A3',
            ],
        ],
        [
            'id' => 'zurich',
            'name' => 'Zurich Glacier',
            'city' => 'Zurich',
            'colors' => [
                'header_color' => '#1B2838',
                'primary_color' => '#1B2838',
                'accent_color' => '#5B8DEF',
                'gold_color' => '#D4A017',
                'secondary_color' => '#2A9D8F',
                'danger_color' => '#E11D48',
                'bg_color' => '#EEF2F7',
                'surface_color' => '#FFFFFF',
                'text_color' => '#0F1724',
                'muted_color' => '#5C6B7A',
                'sidebar_color' => '#0E1622',
                'border_color' => '#D5DEE8',
                'clock_color' => '#93C5FD',
            ],
        ],
    ];
}

function ame_site_palette_id(): string
{
    return 'custom';
}

function ame_resolve_theme_tokens(array $theme): array
{
    $id = ame_site_palette_id();
    if ($id === 'custom') {
        return $theme;
    }
    foreach (ame_theme_palettes() as $p) {
        if ($p['id'] === $id) {
            return array_merge($theme, $p['colors']);
        }
    }
    return $theme;
}

function ame_theme_css_var_map(): array
{
    return [
        'header_color' => ['--brand-navy', '--dark-blue'],
        'primary_color' => ['--brand-navy-dark'],
        'accent_color' => ['--brand-accent'],
        'gold_color' => ['--brand-gold'],
        'secondary_color' => ['--brand-success', '--dark-green'],
        'bg_color' => ['--brand-bg'],
        'surface_color' => ['--brand-bg-soft', '--brand-white'],
        'text_color' => ['--brand-black'],
        'muted_color' => ['--brand-muted'],
        'sidebar_color' => ['--brand-sidebar', '--brand-navy-deep'],
        'border_color' => ['--brand-border'],
        'danger_color' => ['--brand-danger', '--red-alert'],
        'clock_color' => ['--hdr-clock'],
    ];
}

function ame_clock_presets(): array
{
    return [
        ['hex' => '#2DD4BF', 'bn' => 'টিল', 'en' => 'Teal'],
        ['hex' => '#F5D76E', 'bn' => 'সোনা', 'en' => 'Gold'],
        ['hex' => '#FFFFFF', 'bn' => 'সাদা', 'en' => 'White'],
        ['hex' => '#38BDF8', 'bn' => 'আকাশ', 'en' => 'Sky'],
        ['hex' => '#FBBF24', 'bn' => 'অ্যামবার', 'en' => 'Amber'],
        ['hex' => '#FDA4AF', 'bn' => 'গোলাপ', 'en' => 'Rose'],
        ['hex' => '#86EFAC', 'bn' => 'মিন্ট', 'en' => 'Mint'],
        ['hex' => '#C4B5FD', 'bn' => 'বেগুনি', 'en' => 'Violet'],
        ['hex' => '#F97316', 'bn' => 'কমলা', 'en' => 'Orange'],
        ['hex' => '#E2E8F0', 'bn' => 'বরফ', 'en' => 'Ice'],
    ];
}

function ame_resolved_clock_color(?array $theme = null): string
{
    $defaults = ame_theme_defaults();
    $theme = $theme ?? ($GLOBALS['theme'] ?? $defaults);
    return ame_theme_hex((string)($theme['clock_color'] ?? ''), $defaults['clock_color']);
}

/**
 * প্রতি-মডিউল কালার সিস্টেম — কোন মডিউলে আলাদা কালার সেট করা যাবে তার তালিকা।
 * key = modules/ ফোল্ডারের নাম (URL থেকেই সনাক্ত হয়), value = লেবেল।
 */
function ame_theme_modules(bool $english = false): array
{
    return [
        'sales' => $english ? 'Sales / POS' : 'বিক্রয় / POS',
        'purchase' => $english ? 'Purchase' : 'ক্রয়',
        'inventory' => $english ? 'Inventory / Stock' : 'স্টক ও ইনভেন্টরি',
        'customers' => $english ? 'Customers' : 'ক্রেতা',
        'suppliers' => $english ? 'Suppliers' : 'সাপ্লায়ার',
        'accounts' => $english ? 'Accounts / Ledger' : 'হিসাব ও লেজার',
        'reports' => $english ? 'Reports' : 'রিপোর্ট',
        'hrm' => $english ? 'HR / Staff' : 'এইচআর ও স্টাফ',
        'returns' => $english ? 'Returns' : 'রিটার্ন',
        'rental' => $english ? 'Rental' : 'ভাড়া',
        'loans' => $english ? 'Loans' : 'ঋণ',
        'family' => $english ? 'Family' : 'পরিবার',
        'partners' => $english ? 'Partners' : 'পার্টনার',
        'settings' => $english ? 'Settings' : 'সেটিংস',
    ];
}

/** সাইট-জোড়া (গ্লোবাল) সেটিংসের জন্য key খালি স্ট্রিং। */
function ame_theme_normalize_module_key(?string $key): string
{
    $key = strtolower(trim((string)$key));
    if ($key === '') {
        return '';
    }
    return array_key_exists($key, ame_theme_modules()) ? $key : '';
}

/** বর্তমান পেজের URL (modules/<name>/...) থেকে মডিউল-কী বের করা — কোনো সেটিং না
 *  থাকলে খালি স্ট্রিং (মানে গ্লোবাল কালার) ফেরত দেয়, যাতে অচেনা পেজ কখনও ভাঙে না। */
function ame_current_module_key(): string
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (!preg_match('#/modules/([a-z0-9_]+)/#', $script, $m)) {
        return '';
    }
    return ame_theme_normalize_module_key($m[1]);
}

function ame_theme_column_sql(): array
{
    $d = ame_theme_defaults();
    $out = [];
    foreach ($d as $col => $hex) {
        $out[$col] = "ALTER TABLE theme_settings ADD COLUMN {$col} VARCHAR(20) DEFAULT '" . $hex . "'";
    }
    return $out;
}

function ensure_theme_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('theme_schema')) {
        $done = true;
        return;
    }
    $done = true;
    $d = ame_theme_defaults();
    $db->query("CREATE TABLE IF NOT EXISTS theme_settings (
        id INT PRIMARY KEY,
        header_color VARCHAR(20) DEFAULT '{$d['header_color']}',
        primary_color VARCHAR(20) DEFAULT '{$d['primary_color']}',
        secondary_color VARCHAR(20) DEFAULT '{$d['secondary_color']}',
        accent_color VARCHAR(20) DEFAULT '{$d['accent_color']}',
        gold_color VARCHAR(20) DEFAULT '{$d['gold_color']}',
        bg_color VARCHAR(20) DEFAULT '{$d['bg_color']}',
        surface_color VARCHAR(20) DEFAULT '{$d['surface_color']}',
        text_color VARCHAR(20) DEFAULT '{$d['text_color']}',
        muted_color VARCHAR(20) DEFAULT '{$d['muted_color']}',
        sidebar_color VARCHAR(20) DEFAULT '{$d['sidebar_color']}',
        border_color VARCHAR(20) DEFAULT '{$d['border_color']}',
        danger_color VARCHAR(20) DEFAULT '{$d['danger_color']}'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM theme_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    foreach (ame_theme_column_sql() as $col => $sql) {
        if (!in_array($col, $cols, true)) {
            $db->query($sql);
        }
    }

    // প্রতি-মডিউল (পেজভিত্তিক) কালার ওভাররাইডের জন্য module_key কলাম — self-healing।
    if (!in_array('module_key', $cols, true)) {
        $db->query("ALTER TABLE theme_settings ADD COLUMN module_key VARCHAR(40) NOT NULL DEFAULT ''");
    }
    $hasUniqueIdx = false;
    $idxRes = $db->query("SHOW INDEX FROM theme_settings WHERE Key_name='uniq_theme_module_key'");
    if ($idxRes && $idxRes->num_rows > 0) {
        $hasUniqueIdx = true;
    }
    if (!$hasUniqueIdx) {
        // পুরনো ডেটাতে যদি (অস্বাভাবিকভাবে) একাধিক খালি module_key থাকে, ইউনিক ইনডেক্স
        // ব্যর্থ হতে পারে — সেক্ষেত্রে চুপচাপ বাদ দেওয়া হলো, প্রোগ্রাম ভাঙবে না।
        @$db->query("ALTER TABLE theme_settings ADD UNIQUE KEY uniq_theme_module_key (module_key)");
    }

    $one = $db->query("SELECT id FROM theme_settings WHERE id=1");
    if (!$one || $one->num_rows === 0) {
        // Seed directly here. Calling ame_save_theme() would call this
        // function again and recurse forever on a fresh database.
        $stmt = $db->prepare("INSERT IGNORE INTO theme_settings (
            id, header_color, primary_color, secondary_color, accent_color, gold_color,
            bg_color, surface_color, text_color, muted_color, sidebar_color, border_color,
            danger_color, clock_color, module_key
        ) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,'')");
        if (!$stmt) {
            throw new RuntimeException('Theme defaults could not be prepared: ' . $db->error);
        }
        $stmt->bind_param(
            'sssssssssssss',
            $d['header_color'], $d['primary_color'], $d['secondary_color'], $d['accent_color'],
            $d['gold_color'], $d['bg_color'], $d['surface_color'], $d['text_color'],
            $d['muted_color'], $d['sidebar_color'], $d['border_color'], $d['danger_color'],
            $d['clock_color']
        );
        if (!$stmt->execute()) {
            $why = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Theme defaults could not be created: ' . $why);
        }
        $stmt->close();
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('theme_schema', true);
    }
}

function ame_save_theme(mysqli $db, array $data, string $moduleKey = ''): void
{
    ensure_theme_schema($db);
    $moduleKey = ame_theme_normalize_module_key($moduleKey);
    $d = ame_theme_defaults();
    foreach ($d as $key => $fallback) {
        $d[$key] = ame_theme_hex($data[$key] ?? '', $fallback);
    }
    $clock = $d['clock_color'];

    if ($moduleKey === '') {
        // গ্লোবাল (সব পেজ) সারি — সবসময় id=1
        $stmt = $db->prepare("UPDATE theme_settings SET
            header_color=?, primary_color=?, secondary_color=?, accent_color=?, gold_color=?,
            bg_color=?, surface_color=?, text_color=?, muted_color=?, sidebar_color=?,
            border_color=?, danger_color=?, clock_color=?
            WHERE id=1");
        $stmt->bind_param(
            'sssssssssssss',
            $d['header_color'], $d['primary_color'], $d['secondary_color'], $d['accent_color'],
            $d['gold_color'], $d['bg_color'], $d['surface_color'], $d['text_color'],
            $d['muted_color'], $d['sidebar_color'], $d['border_color'], $d['danger_color'],
            $clock
        );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected === 0) {
            $chk = $db->query("SELECT id FROM theme_settings WHERE id=1");
            if ($chk && $chk->num_rows > 0) {
                return;
            }
            $ins = $db->prepare("INSERT INTO theme_settings (
                id, header_color, primary_color, secondary_color, accent_color, gold_color,
                bg_color, surface_color, text_color, muted_color, sidebar_color, border_color,
                danger_color, clock_color, module_key
            ) VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,'')");
            $ins->bind_param(
                'sssssssssssss',
                $d['header_color'], $d['primary_color'], $d['secondary_color'], $d['accent_color'],
                $d['gold_color'], $d['bg_color'], $d['surface_color'], $d['text_color'],
                $d['muted_color'], $d['sidebar_color'], $d['border_color'], $d['danger_color'],
                $clock
            );
            $ins->execute();
            $ins->close();
        }
        return;
    }

    // মডিউল-নির্দিষ্ট (এক পেজের জন্য) সারি — আগে থেকে থাকলে আপডেট, না থাকলে নতুন সারি
    $existingId = null;
    $find = $db->prepare("SELECT id FROM theme_settings WHERE module_key=? LIMIT 1");
    $find->bind_param('s', $moduleKey);
    $find->execute();
    $found = $find->get_result()->fetch_assoc();
    $find->close();
    if ($found) {
        $existingId = (int)$found['id'];
    }

    if ($existingId !== null) {
        $stmt = $db->prepare("UPDATE theme_settings SET
            header_color=?, primary_color=?, secondary_color=?, accent_color=?, gold_color=?,
            bg_color=?, surface_color=?, text_color=?, muted_color=?, sidebar_color=?,
            border_color=?, danger_color=?, clock_color=?
            WHERE id=?");
        $stmt->bind_param(
            'sssssssssssssi',
            $d['header_color'], $d['primary_color'], $d['secondary_color'], $d['accent_color'],
            $d['gold_color'], $d['bg_color'], $d['surface_color'], $d['text_color'],
            $d['muted_color'], $d['sidebar_color'], $d['border_color'], $d['danger_color'],
            $clock, $existingId
        );
        $stmt->execute();
        $stmt->close();
        return;
    }

    $nextIdRes = $db->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM theme_settings");
    $nextId = $nextIdRes ? (int)$nextIdRes->fetch_assoc()['next_id'] : 2;
    $ins = $db->prepare("INSERT INTO theme_settings (
        id, header_color, primary_color, secondary_color, accent_color, gold_color,
        bg_color, surface_color, text_color, muted_color, sidebar_color, border_color,
        danger_color, clock_color, module_key
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param(
        'issssssssssssss',
        $nextId,
        $d['header_color'], $d['primary_color'], $d['secondary_color'], $d['accent_color'],
        $d['gold_color'], $d['bg_color'], $d['surface_color'], $d['text_color'],
        $d['muted_color'], $d['sidebar_color'], $d['border_color'], $d['danger_color'],
        $clock, $moduleKey
    );
    $ins->execute();
    $ins->close();
}

/** নির্দিষ্ট মডিউলের কাস্টম কালার (যদি থাকে) মুছে দেয় — তখন সেই পেজ আবার গ্লোবাল
 *  কালার ব্যবহার করবে। গ্লোবাল সারি (module_key='') কখনও মোছা হয় না। */
function ame_delete_theme_module_override(mysqli $db, string $moduleKey): bool
{
    $moduleKey = ame_theme_normalize_module_key($moduleKey);
    if ($moduleKey === '') {
        return false;
    }
    $stmt = $db->prepare("DELETE FROM theme_settings WHERE module_key=? AND module_key<>'' LIMIT 1");
    $stmt->bind_param('s', $moduleKey);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

/** মডিউলের কাস্টম কালার-সারি (raw row) — না থাকলে null। */
function ame_theme_module_row(mysqli $db, string $moduleKey): ?array
{
    $moduleKey = ame_theme_normalize_module_key($moduleKey);
    if ($moduleKey === '') {
        return null;
    }
    ensure_theme_schema($db);
    $stmt = $db->prepare("SELECT * FROM theme_settings WHERE module_key=? LIMIT 1");
    $stmt->bind_param('s', $moduleKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** সবসময় গ্লোবাল (সব পেজ) থিম — $moduleKey দিলে সেই মডিউলে কাস্টম কালার থাকলে
 *  তা ফেরত দেয়, না থাকলে গ্লোবাল কালার। পুরনো কোড যেভাবে ame_load_theme($db)
 *  কল করত সেভাবেই কাজ করে (module ছাড়া কল করলে সবসময় গ্লোবাল)। */
function ame_load_theme(mysqli $db, string $moduleKey = ''): array
{
    ensure_theme_schema($db);
    $moduleKey = ame_theme_normalize_module_key($moduleKey);
    if ($moduleKey !== '') {
        $row = ame_theme_module_row($db, $moduleKey);
        if ($row) {
            $d = ame_theme_defaults();
            foreach ($d as $key => $fallback) {
                $d[$key] = ame_theme_hex($row[$key] ?? '', $fallback);
            }
            return $d;
        }
    }
    $res = $db->query("SELECT * FROM theme_settings WHERE id=1 LIMIT 1");
    $row = $res ? $res->fetch_assoc() : [];
    $d = ame_theme_defaults();
    foreach ($d as $key => $fallback) {
        $d[$key] = ame_theme_hex($row[$key] ?? '', $fallback);
    }
    return $d;
}

/**
 * চলতি রিকোয়েস্টের জন্য চূড়ান্ত থিম রেজল্ভ করে — includes/config.php থেকে কল হয়।
 * মডিউলে কাস্টম কালার থাকলে সেটাই (প্যালেট কুকি ছাড়াই) ব্যবহার হয়; না থাকলে
 * গ্লোবাল কালার + সাইট-প্যালেট কুকি (Workspace ট্যাবের প্রিসেট) প্রয়োগ হয়।
 */
function ame_theme_factory_keys(): array
{
    $sets = [
        ['header_color' => '#FFFFFF', 'primary_color' => '#111111', 'accent_color' => '#111111', 'bg_color' => '#FFFFFF', 'surface_color' => '#FFFFFF', 'sidebar_color' => '#FFFFFF'],
        ['header_color' => '#0B1E4D', 'primary_color' => '#0B1E4D', 'accent_color' => '#1D9BF0', 'bg_color' => '#EAF4FF', 'surface_color' => '#FFFFFF', 'sidebar_color' => '#071534'],
        ['header_color' => '#0B1E4D', 'primary_color' => '#0B1E4D', 'accent_color' => '#0091EA', 'bg_color' => '#FFFFFF', 'surface_color' => '#FFFFFF', 'sidebar_color' => '#071534'],
        ['header_color' => '#3D1A6E', 'primary_color' => '#3D1A6E', 'accent_color' => '#0B6B3A', 'bg_color' => '#E7F6EC', 'surface_color' => '#F3E8FF', 'sidebar_color' => '#2A1248'],
    ];
    $out = [];
    foreach ($sets as $set) {
        $parts = [];
        foreach ($set as $value) {
            $parts[] = ame_theme_hex($value, '#000000');
        }
        $out[] = implode('|', $parts);
    }
    return $out;
}

function ame_theme_row_key(array $row): string
{
    $parts = [];
    foreach (['header_color', 'primary_color', 'accent_color', 'bg_color', 'surface_color', 'sidebar_color'] as $key) {
        $parts[] = ame_theme_hex((string)($row[$key] ?? ''), '#000000');
    }
    return implode('|', $parts);
}

/** ফ্যাক্টরি পেপার/নেভি থিম একবার গ্রোভ প্যালেটে ওঠে। নিজের সেভ করা রং আর বদলায় না। */
function ame_theme_adopt_grove_once(mysqli $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    @$db->query("CREATE TABLE IF NOT EXISTS system_flags (
        flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
        flag_value VARCHAR(50) NOT NULL DEFAULT '1',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $flag = 'theme_grove_195';
    $chk = $db->prepare('SELECT flag_value FROM system_flags WHERE flag_name = ? LIMIT 1');
    if (!$chk) {
        return;
    }
    $chk->bind_param('s', $flag);
    $chk->execute();
    $got = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($got) {
        return;
    }
    $res = @$db->query('SELECT * FROM theme_settings WHERE id = 1 LIMIT 1');
    $row = $res ? $res->fetch_assoc() : null;
    if ($row && in_array(ame_theme_row_key($row), ame_theme_factory_keys(), true)) {
        ame_save_theme($db, ame_theme_grove(), '');
    }
    $one = '1';
    $ins = $db->prepare('INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)');
    if ($ins) {
        $ins->bind_param('ss', $flag, $one);
        $ins->execute();
        $ins->close();
    }
}

/** ১০.৯৮.১৯৫-এর সবুজ/বেগুনি ফ্যাক্টরি রং একবার নীল প্যালেটে ওঠে। নিজের সেভ করা রং থাকে। */
function ame_theme_adopt_blue_once(mysqli $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    $flag = 'theme_blue_196';
    $chk = $db->prepare('SELECT flag_value FROM system_flags WHERE flag_name = ? LIMIT 1');
    if (!$chk) {
        return;
    }
    $chk->bind_param('s', $flag);
    $chk->execute();
    $got = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($got) {
        return;
    }
    $res = @$db->query('SELECT * FROM theme_settings WHERE id = 1 LIMIT 1');
    $row = $res ? $res->fetch_assoc() : null;
    if ($row && in_array(ame_theme_row_key($row), ame_theme_factory_keys(), true)) {
        ame_save_theme($db, ame_theme_grove(), '');
    }
    $one = '1';
    $ins = $db->prepare('INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)');
    if ($ins) {
        $ins->bind_param('ss', $flag, $one);
        $ins->execute();
        $ins->close();
    }
}

/** ১০.৯৮.১৯৬-এর হালকা নীল/বেগুনি ক্যানভাস একবার সাদা হয়। হেডার ও নিজের সেভ করা অন্য রং থাকে। */
function ame_theme_adopt_white_once(mysqli $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    $flag = 'theme_white_197';
    $chk = $db->prepare('SELECT flag_value FROM system_flags WHERE flag_name = ? LIMIT 1');
    if (!$chk) {
        return;
    }
    $chk->bind_param('s', $flag);
    $chk->execute();
    $got = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($got) {
        return;
    }
    $res = @$db->query('SELECT bg_color, surface_color FROM theme_settings WHERE id = 1 LIMIT 1');
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $bg = strtoupper(ame_theme_hex($row['bg_color'] ?? '', '#FFFFFF'));
        $surface = strtoupper(ame_theme_hex($row['surface_color'] ?? '', '#FFFFFF'));
        if (in_array($bg, ['#E8F1FC', '#F3E8FF', '#EAF4FF'], true) || in_array($surface, ['#F3E8FF', '#E8F1FC'], true)) {
            $white = '#FFFFFF';
            $upd = $db->prepare('UPDATE theme_settings SET bg_color = ?, surface_color = ? WHERE id = 1');
            if ($upd) {
                $upd->bind_param('ss', $white, $white);
                $upd->execute();
                $upd->close();
            }
        }
    }
    $one = '1';
    $ins = $db->prepare('INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)');
    if ($ins) {
        $ins->bind_param('ss', $flag, $one);
        $ins->execute();
        $ins->close();
    }
}

function ame_theme_for_request(mysqli $db, string $moduleKey = ''): array
{
    ensure_theme_schema($db);
    if (!(function_exists('ame_schema_stamp') && ame_schema_stamp('theme_adopt'))) {
        ame_theme_adopt_grove_once($db);
        ame_theme_adopt_blue_once($db);
        ame_theme_adopt_white_once($db);
        if (function_exists('ame_schema_stamp')) {
            ame_schema_stamp('theme_adopt', true);
        }
    }
    $moduleKey = ame_theme_normalize_module_key($moduleKey);
    if ($moduleKey !== '') {
        $row = ame_theme_module_row($db, $moduleKey);
        if ($row) {
            $d = ame_theme_defaults();
            foreach ($d as $key => $fallback) {
                $d[$key] = ame_theme_hex($row[$key] ?? '', $fallback);
            }
            return $d;
        }
    }
    $theme = ame_load_theme($db, '');
    return ame_resolve_theme_tokens($theme);
}
