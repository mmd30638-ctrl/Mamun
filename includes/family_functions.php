<?php
/**
 * পারিবারিক ব্যবস্থাপনা — helpers (mysqli $db)
 */
require_once __DIR__ . '/family_schema.php';

function fam_db(): mysqli
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db instanceof mysqli) {
        throw new RuntimeException(function_exists('ame_l') ? ame_l('ডাটাবেজ নেই', 'Database is not available') : 'ডাটাবেজ নেই');
    }
    ame_family_ensure($db);
    return $db;
}

function fam_role(): array
{
    $db = fam_db();
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $isAdmin = (strtolower((string)($_SESSION['role'] ?? '')) === 'admin');
    $row = null;
    if ($uid > 0) {
        $st = $db->prepare('SELECT * FROM family_access WHERE user_id=? LIMIT 1');
        $st->bind_param('i', $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
    }
    if (!$row) {
        if ($isAdmin) {
            $role = 'admin';
            $ch = 1;
            $cm = 1;
            $st = $db->prepare('INSERT INTO family_access (user_id, role_key, can_health, can_money) VALUES (?,?,?,?)');
            $st->bind_param('isii', $uid, $role, $ch, $cm);
            $st->execute();
            $st->close();
            $row = ['user_id' => $uid, 'role_key' => $role, 'can_health' => $ch, 'can_money' => $cm];
        } else {
            return [
                'user_id' => $uid,
                'role' => 'none',
                'can_health' => 0,
                'can_money' => 0,
                'is_admin' => false,
                'can_write' => false,
            ];
        }
    }
    $role = (string)($row['role_key'] ?? 'member');
    return [
        'user_id' => $uid,
        'role' => $role,
        'can_health' => (int)($row['can_health'] ?? 0),
        'can_money' => (int)($row['can_money'] ?? 0),
        'is_admin' => $isAdmin || $role === 'admin',
        'can_write' => $isAdmin || in_array($role, ['admin', 'member', 'limited'], true),
    ];
}

function fam_can_write(?string $area = null): bool
{
    $r = fam_role();
    if (!empty($r['is_admin'])) {
        return true;
    }
    if (($r['role'] ?? '') === 'view') {
        return false;
    }
    if ($area === 'health') {
        return !empty($r['can_health']);
    }
    if ($area === 'money') {
        return !empty($r['can_money']);
    }
    return !empty($r['can_write']);
}

function fam_can_see(?string $area = null): bool
{
    $r = fam_role();
    if (!empty($r['is_admin'])) {
        return true;
    }
    if ($area === 'health') {
        return !empty($r['can_health']);
    }
    if ($area === 'money') {
        return !empty($r['can_money']);
    }
    return true;
}

function fam_guard_write(?string $area = null): void
{
    if (!fam_can_write($area)) {
        $_SESSION['fam_err'] = 'এই কাজের অনুমতি নেই।';
        header('Location: /modules/family/index.php');
        exit;
    }
}

function fam_int($v): int
{
    return (int)$v;
}

function fam_money($v): float
{
    return round((float)str_replace(',', '', (string)$v), 2);
}

function fam_date($v): ?string
{
    $v = trim((string)$v);
    if ($v === '' || $v === '0000-00-00') {
        return null;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function fam_date_sql($v): string
{
    return (string)(fam_date($v) ?? '');
}

function fam_null_int($v): ?int
{
    $n = (int)$v;
    return $n > 0 ? $n : null;
}

function fam_id($v): int
{
    return (int)(fam_null_int($v) ?? 0);
}

function fam_run(mysqli $db, string $sql, string $types, array $vals): mysqli_stmt
{
    $st = $db->prepare($sql);
    if (!$st) {
        throw new RuntimeException($db->error ?: 'SQL prepare fail');
    }
    $clean = [];
    $len = strlen($types);
    for ($i = 0; $i < $len; $i++) {
        $t = $types[$i];
        $v = $vals[$i] ?? null;
        if ($t === 'i') {
            $clean[$i] = ($v === null || $v === '') ? 0 : (int)$v;
        } elseif ($t === 'd') {
            $clean[$i] = ($v === null || $v === '') ? 0.0 : (float)$v;
        } else {
            $clean[$i] = $v === null ? '' : (string)$v;
        }
    }
    if ($types !== '' && !$st->bind_param($types, ...$clean)) {
        throw new RuntimeException($st->error ?: 'SQL bind fail');
    }
    if (!$st->execute()) {
        throw new RuntimeException($st->error ?: 'SQL execute fail');
    }
    return $st;
}

function fam_file_url(?string $name): string
{
    $name = basename((string)$name);
    return $name !== '' ? '/uploads/family/' . rawurlencode($name) : '';
}

function fam_is_image(?string $name): bool
{
    $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

function fam_rows(string $sql): array
{
    $out = [];
    $r = fam_db()->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[] = $row;
        }
    }
    return $out;
}

function fam_one(string $sql): ?array
{
    $r = fam_db()->query($sql);
    if (!$r) {
        return null;
    }
    $row = $r->fetch_assoc();
    return $row ?: null;
}

function fam_role_from_relation(string $relation): string
{
    $map = [
        'বাবা' => 'father',
        'মা' => 'mother',
        'ভাই' => 'brother',
        'বোন' => 'sister',
    ];
    return $map[$relation] ?? 'other';
}

function fam_member_relation(array $m): string
{
    $rel = trim((string)($m['relation_name'] ?? ''));
    if ($rel !== '') {
        return $rel;
    }
    $labels = ['father' => 'বাবা', 'mother' => 'মা', 'brother' => 'ভাই', 'sister' => 'বোন'];
    $role = (string)($m['role'] ?? '');
    return $labels[$role] ?? '';
}

function fam_members(): array
{
    return fam_rows('SELECT * FROM family_members ORDER BY name');
}

function fam_member(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = fam_db()->prepare('SELECT * FROM family_members WHERE id=? LIMIT 1');
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function fam_groups(): array
{
    return fam_rows('SELECT g.*, (SELECT COUNT(*) FROM family_contacts c WHERE c.group_id=g.id) AS n FROM family_groups g ORDER BY g.name');
}

function fam_doctors(?int $memberId = null): array
{
    $sql = 'SELECT d.*, m.name AS member_name FROM family_doctors d LEFT JOIN family_members m ON m.id=d.member_id';
    if ($memberId) {
        $sql .= ' WHERE d.member_id=' . (int)$memberId;
    }
    $sql .= ' ORDER BY d.name';
    return fam_rows($sql);
}

function fam_low_meds(): array
{
    return fam_rows('SELECT med.*, m.name AS member_name FROM family_medicines med LEFT JOIN family_members m ON m.id=med.member_id WHERE med.stock>0 AND med.stock<=GREATEST(med.times_per_day,2) ORDER BY med.stock ASC');
}

function fam_active_meds(?int $memberId = null): array
{
    $sql = 'SELECT med.*, m.name AS member_name FROM family_medicines med LEFT JOIN family_members m ON m.id=med.member_id WHERE med.stock>0';
    if ($memberId) {
        $sql .= ' AND med.member_id=' . (int)$memberId;
    }
    $sql .= ' ORDER BY med.name';
    return fam_rows($sql);
}

function fam_month_expense(?string $cat = null): float
{
    $m = date('Y-m-01');
    $sql = "SELECT COALESCE(SUM(amount),0) AS t FROM family_expenses WHERE exp_date>='" . $m . "'";
    if ($cat) {
        $c = fam_db()->real_escape_string($cat);
        $sql .= " AND category='$c'";
    }
    $row = fam_one($sql);
    return (float)($row['t'] ?? 0);
}

function fam_count(string $table, string $where = '1'): int
{
    $row = fam_one("SELECT COUNT(*) AS n FROM `$table` WHERE $where");
    return (int)($row['n'] ?? 0);
}

function fam_upcoming_events(int $days = 30): array
{
    $to = date('Y-m-d', strtotime("+$days days"));
    $today = date('Y-m-d');
    return fam_rows("SELECT e.*, m.name AS member_name FROM family_events e LEFT JOIN family_members m ON m.id=e.member_id WHERE e.event_date BETWEEN '$today' AND '$to' ORDER BY e.event_date LIMIT 40");
}

function fam_today_appts(): array
{
    $d = date('Y-m-d');
    return fam_rows("SELECT e.*, m.name AS member_name FROM family_events e LEFT JOIN family_members m ON m.id=e.member_id WHERE e.event_date='$d' AND e.kind='appointment'");
}

function fam_next_birthday(string $dob): ?array
{
    try {
        $now = new DateTime('today');
        $born = new DateTime($dob);
        $next = DateTime::createFromFormat('Y-m-d', $now->format('Y') . '-' . $born->format('m-d'));
        if (!$next) {
            return null;
        }
        if ($next < $now) {
            $next->modify('+1 year');
        }
        return ['next' => $next->format('Y-m-d'), 'in_days' => (int)$now->diff($next)->days];
    } catch (Throwable $e) {
        return null;
    }
}

function fam_birthdays_soon(int $within = 21): array
{
    $out = [];
    foreach (fam_members() as $row) {
        if (empty($row['birth_date'])) {
            continue;
        }
        $nb = fam_next_birthday($row['birth_date']);
        if ($nb && $nb['in_days'] <= $within) {
            $row['in_days'] = $nb['in_days'];
            $row['next'] = $nb['next'];
            $out[] = $row;
        }
    }
    usort($out, static fn($a, $b) => $a['in_days'] <=> $b['in_days']);
    return $out;
}

function fam_sync_event(string $kind, string $title, ?string $date, ?int $memberId, string $refKind, ?int $refId, string $notes = ''): void
{
    if (!$date) {
        return;
    }
    $db = fam_db();
    $mid = $memberId ?: 0;
    $rid = $refId ?: 0;
    $st = $db->prepare('SELECT id FROM family_events WHERE kind=? AND ref_kind=? AND ref_id=? LIMIT 1');
    $st->bind_param('ssi', $kind, $refKind, $rid);
    $st->execute();
    $ex = $st->get_result()->fetch_assoc();
    $st->close();
    if ($ex) {
        $id = (int)$ex['id'];
        $st = $db->prepare('UPDATE family_events SET title=?, event_date=?, member_id=?, notes=? WHERE id=?');
        $st->bind_param('ssisi', $title, $date, $mid, $notes, $id);
        $st->execute();
        $st->close();
        return;
    }
    $st = $db->prepare('INSERT INTO family_events (title,kind,event_date,member_id,ref_kind,ref_id,notes) VALUES (?,?,?,?,?,?,?)');
    $st->bind_param('sssisis', $title, $kind, $date, $mid, $refKind, $rid, $notes);
    $st->execute();
    $st->close();
}

function fam_add_expense(string $date, string $cat, float $amt, string $purpose, ?int $memberId, string $notes = '', string $pay = 'নগদ', string $spentBy = ''): int
{
    if ($amt <= 0) {
        return 0;
    }
    $db = fam_db();
    $mid = $memberId ?: 0;
    if ($spentBy === '') {
        $spentBy = (string)($_SESSION['username'] ?? '');
    }
    $st = $db->prepare('INSERT INTO family_expenses (member_id,exp_date,category,amount,spent_by,purpose,pay_method,notes) VALUES (?,?,?,?,?,?,?,?)');
    $st->bind_param('isdsssss', $mid, $date, $cat, $amt, $spentBy, $purpose, $pay, $notes);
    $st->execute();
    $id = (int)$db->insert_id;
    $st->close();
    return $id;
}

function fam_opts(array $items, $sel = null, string $empty = ''): string
{
    $h = $empty !== '' ? '<option value="">' . esc_html($empty) . '</option>' : '';
    foreach ($items as $k => $v) {
        $val = is_int($k) ? (string)$v : (string)$k;
        $lab = (string)$v;
        $s = ((string)$val === (string)$sel) ? ' selected' : '';
        $h .= '<option value="' . esc_html($val) . '"' . $s . '>' . esc_html(ame_word($lab)) . '</option>';
    }
    return $h;
}

function fam_member_opts($sel = null): string
{
    $items = [];
    foreach (fam_members() as $m) {
        $items[(int)$m['id']] = $m['name'];
    }
    return fam_opts($items, $sel, t('fam_pick_member'));
}

function fam_group_opts($sel = null): string
{
    $items = [];
    foreach (fam_groups() as $g) {
        $items[(int)$g['id']] = $g['name'];
    }
    return fam_opts($items, $sel, t('fam_pick_group'));
}

function fam_doctor_opts($sel = null): string
{
    $items = [];
    foreach (fam_doctors() as $d) {
        $items[(int)$d['id']] = $d['name'] . ' · ' . fam_spec_label((string)$d['specialist']);
    }
    return fam_opts($items, $sel, t('fam_pick_doctor'));
}

function fam_nav(): array
{
    return [
        'home' => [
            'label' => t('fam_g_home'),
            'items' => [
                ['href' => '/modules/family/index.php', 'icon' => '▣', 'label' => t('fam_nav_dash')],
                ['href' => '/modules/family/search.php', 'icon' => '⌕', 'label' => t('fam_nav_search')],
                ['href' => '/modules/family/notes.php', 'icon' => '✎', 'label' => t('fam_nav_notes')],
                ['href' => '/modules/family/documents.php', 'icon' => '▤', 'label' => t('fam_nav_docs')],
            ],
        ],
        'people' => [
            'label' => t('fam_g_people'),
            'items' => [
                ['href' => '/modules/family/members.php', 'icon' => '◎', 'label' => t('fam_nav_members')],
                ['href' => '/modules/family/contacts.php', 'icon' => '☎', 'label' => t('fam_nav_contacts')],
                ['href' => '/modules/family/groups.php', 'icon' => '⊞', 'label' => t('fam_nav_groups')],
            ],
        ],
        'health' => [
            'label' => t('fam_g_health'),
            'items' => [
                ['href' => '/modules/family/doctors.php', 'icon' => '+', 'label' => t('fam_nav_doctors')],
                ['href' => '/modules/family/doctors.php#rx', 'icon' => '▤', 'label' => t('fam_nav_rx')],
                ['href' => '/modules/family/medicines.php', 'icon' => '◈', 'label' => t('fam_nav_meds')],
                ['href' => '/modules/family/medicine_buy.php', 'icon' => '▤', 'label' => t('fam_nav_med_buy')],
            ],
        ],
        'house' => [
            'label' => t('fam_g_house'),
            'items' => [
                ['href' => '/modules/family/bazaar.php', 'icon' => '▹', 'label' => t('fam_nav_bazaar')],
                ['href' => '/modules/family/bazaar_statement.php', 'icon' => '▦', 'label' => t('fam_nav_bazaar_stmt')],
                ['href' => '/modules/family/shopping.php', 'icon' => '▹', 'label' => t('fam_nav_market')],
                ['href' => '/modules/family/inventory.php', 'icon' => '▦', 'label' => t('fam_nav_inventory')],
                ['href' => '/modules/family/library.php', 'icon' => '☰', 'label' => t('fam_nav_library')],
            ],
        ],
        'money' => [
            'label' => t('fam_g_money'),
            'items' => [
                ['href' => '/modules/family/expenses.php', 'icon' => '৳', 'label' => t('fam_nav_expense')],
                ['href' => '/modules/family/reports.php', 'icon' => '▦', 'label' => t('fam_nav_report')],
                ['href' => '/modules/family/export.php', 'icon' => '↓', 'label' => t('fam_nav_backup')],
            ],
        ],
        'time' => [
            'label' => t('fam_g_time'),
            'items' => [
                ['href' => '/modules/family/calendar.php', 'icon' => '◷', 'label' => t('fam_nav_calendar')],
                ['href' => '/modules/family/reminders.php', 'icon' => '◉', 'label' => t('fam_nav_remind')],
                ['href' => '/modules/family/access.php', 'icon' => '⚙', 'label' => t('fam_nav_access')],
            ],
        ],
    ];
}

function fam_search(string $q): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $db = fam_db();
    $like = '%' . $db->real_escape_string($q) . '%';
    $hits = [];
    $maps = [
        ['family_members', 'name', t('fam_nav_members'), '/modules/family/member.php?id='],
        ['family_contacts', 'name', t('fam_nav_contacts'), '/modules/family/contacts.php?q='],
        ['family_doctors', 'name', t('fam_nav_doctors'), '/modules/family/doctors.php?q='],
        ['family_medicines', 'name', t('fam_nav_meds'), '/modules/family/medicines.php?q='],
        ['family_books', 'title', t('word_book'), '/modules/family/library.php?q='],
        ['family_shopping', 'name', t('fam_nav_market'), '/modules/family/shopping.php?q='],
        ['family_bazaar_lines', 'name', t('fam_nav_bazaar'), '/modules/family/bazaar.php?q='],
        ['family_inventory', 'name', t('fam_nav_inventory'), '/modules/family/inventory.php?q='],
        ['family_notes', 'title', t('fam_nav_notes'), '/modules/family/notes.php?q='],
        ['family_events', 'title', t('word_event'), '/modules/family/calendar.php?q='],
        ['family_expenses', 'purpose', t('fam_nav_expense'), '/modules/family/expenses.php?q='],
    ];
    foreach ($maps as [$table, $col, $kind, $url]) {
        $r = $db->query("SELECT id, `$col` AS label FROM `$table` WHERE `$col` LIKE '$like' LIMIT 8");
        if (!$r) {
            continue;
        }
        while ($row = $r->fetch_assoc()) {
            $link = str_contains($url, '?id=') ? $url . (int)$row['id'] : $url . urlencode($q);
            $hits[] = ['kind' => $kind, 'label' => $row['label'], 'href' => $link];
        }
    }
    return $hits;
}

function fam_kind_label(string $k): string
{
    $map = fam_event_kinds();
    return $map[$k] ?? $k;
}

function fam_reminders(): array
{
    $items = [];
    foreach (fam_today_appts() as $e) {
        $items[] = ['tone' => 'hot', 'title' => t('fam_today_doctor'), 'meta' => $e['title'] . ' · ' . ($e['member_name'] ?: ''), 'href' => '/modules/family/calendar.php'];
    }
    foreach (fam_low_meds() as $m) {
        $items[] = ['tone' => 'hot', 'title' => t('fam_med_low'), 'meta' => $m['name'] . ' · ' . t('fam_stock_word') . ' ' . $m['stock'] . ' · ' . ($m['member_name'] ?: ''), 'href' => '/modules/family/medicines.php'];
    }
    foreach (fam_rows("SELECT * FROM family_medicines WHERE refill_date IS NOT NULL AND refill_date<=DATE_ADD(CURDATE(), INTERVAL 5 DAY) ORDER BY refill_date") as $m) {
        $items[] = ['tone' => 'gold', 'title' => t('fam_med_buy_soon'), 'meta' => $m['name'] . ' · ' . $m['refill_date'], 'href' => '/modules/family/medicine_buy.php'];
    }
    foreach (fam_rows("SELECT * FROM family_shopping WHERE is_bought=0 AND priority IN ('জরুরি','উচ্চ') ORDER BY id DESC LIMIT 8") as $s) {
        $items[] = ['tone' => 'gold', 'title' => t('fam_shop_due'), 'meta' => $s['name'] . ' · ' . ame_word($s['priority']), 'href' => '/modules/family/shopping.php'];
    }
    $ymNow = date('Y-m');
    foreach (fam_rows("SELECT l.name, l.category FROM family_bazaar_lines l JOIN family_bazaar_demand d ON d.id=l.demand_id WHERE d.ym='{$ymNow}' AND l.is_bought=0 ORDER BY l.id DESC LIMIT 8") as $s) {
        $items[] = ['tone' => 'gold', 'title' => t('fam_nav_bazaar'), 'meta' => $s['name'] . ' · ' . $s['category'], 'href' => '/modules/family/bazaar.php'];
    }
    foreach (fam_birthdays_soon(7) as $b) {
        $when = ((int)$b['in_days'] === 0) ? t('pulse_today') : ((int)$b['in_days'] . (is_en() ? ' days later' : ' দিন পরে'));
        $items[] = ['tone' => 'ok', 'title' => t('word_birthday'), 'meta' => $b['name'] . ' · ' . $when, 'href' => '/modules/family/member.php?id=' . (int)$b['id']];
    }
    foreach (fam_rows("SELECT * FROM family_inventory WHERE warranty_until IS NOT NULL AND warranty_until<=DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY warranty_until LIMIT 6") as $i) {
        $items[] = ['tone' => 'gold', 'title' => t('word_warranty_end'), 'meta' => $i['name'] . ' · ' . $i['warranty_until'], 'href' => '/modules/family/inventory.php'];
    }
    foreach (fam_upcoming_events(7) as $e) {
        if (in_array($e['kind'], ['appointment', 'birthday'], true)) {
            continue;
        }
        $items[] = ['tone' => '', 'title' => fam_kind_label($e['kind']), 'meta' => $e['title'] . ' · ' . $e['event_date'], 'href' => '/modules/family/reminders.php'];
    }
    return $items;
}

function fam_flash(): string
{
    $ok = $_SESSION['fam_ok'] ?? '';
    $err = $_SESSION['fam_err'] ?? '';
    unset($_SESSION['fam_ok'], $_SESSION['fam_err']);
    $h = '';
    if ($ok) {
        $h .= '<div class="fam-flash ok">' . esc_html($ok) . '</div>';
    }
    if ($err) {
        $h .= '<div class="fam-flash err">' . esc_html($err) . '</div>';
    }
    return $h;
}

function fam_here(): string
{
    return (string)($_SERVER['SCRIPT_NAME'] ?? '');
}

function fam_desk_key(): string
{
    $file = basename(fam_here());
    $map = [
        'index.php' => 'home',
        'members.php' => 'members',
        'member.php' => 'members',
        'contacts.php' => 'contacts',
        'groups.php' => 'contacts',
        'doctors.php' => 'doctors',
        'medicines.php' => 'doctors',
        'medicine_buy.php' => 'doctors',
        'shopping.php' => 'shopping',
        'bazaar.php' => 'shopping',
        'bazaar_statement.php' => 'shopping',
        'bazaar_memo.php' => 'shopping',
        'inventory.php' => 'shopping',
        'expenses.php' => 'shopping',
        'reports.php' => 'shopping',
        'calendar.php' => 'calendar',
        'reminders.php' => 'calendar',
        'library.php' => 'library',
        'read.php' => 'library',
        'kitab_file.php' => 'library',
        'notes.php' => 'home',
        'documents.php' => 'members',
        'access.php' => 'home',
        'search.php' => 'home',
        'export.php' => 'shopping',
    ];
    if (isset($map[$file])) {
        return $map[$file];
    }
    $name = pathinfo($file, PATHINFO_FILENAME);
    return $name !== '' ? $name : 'home';
}

function fam_open(string $title, string $lead = ''): void
{
    $here = fam_here();
    $desk = fam_desk_key();
    $role = fam_role();
    $title = tx($title);
    $lead = $lead !== '' ? tx($lead) : '';
    $shop = function_exists('shop_name') ? shop_name() : t('fam_brand_title');
    echo '<link rel="stylesheet" href="/assets/css/family.css?v=' . esc_html($GLOBALS['sys_version'] ?? AME_ASSET_VERSION) . '">';
    echo '<link rel="stylesheet" href="/assets/css/family-desks.818.css?v=' . esc_html($GLOBALS['sys_version'] ?? AME_ASSET_VERSION) . '">';
    echo '<div class="fam-app" id="famApp" data-lang="' . esc_html(current_lang()) . '" data-desk="' . esc_html($desk) . '">';
    echo '<aside class="fam-rail" id="famRail">';
    echo '<div class="fam-rail-brand"><span>' . esc_html(t('fam_brand_kicker')) . '</span><b>' . esc_html(t('fam_brand_title')) . '</b></div>';
    foreach (fam_nav() as $gid => $g) {
        echo '<div class="fam-nav-g" data-g="' . esc_html((string)$gid) . '"><em>' . esc_html($g['label']) . '</em>';
        foreach ($g['items'] as $it) {
            $base = basename(parse_url($it['href'], PHP_URL_PATH) ?: $it['href']);
            $on = str_contains($here, $base) ? ' on' : '';
            $itemKey = pathinfo($base, PATHINFO_FILENAME);
            echo '<a class="fam-nav-a' . $on . '" data-item="' . esc_html($itemKey) . '" href="' . esc_html($it['href']) . '">'
                . '<span class="fam-nav-ico" aria-hidden="true">' . esc_html($it['icon'] ?? '') . '</span>'
                . '<span class="fam-nav-lab">' . esc_html($it['label']) . '</span>'
                . '</a>';
        }
        echo '</div>';
    }
    echo '</aside>';
    echo '<div class="fam-main">';
    echo '<header class="fam-top">';
    echo '<div class="fam-top-l"><p class="fam-kicker">' . esc_html($shop) . ' · ' . esc_html(t('fam_page_kicker')) . '</p><h1>' . esc_html($title) . '</h1>';
    if ($lead !== '') {
        echo '<p class="fam-lead">' . esc_html($lead) . '</p>';
    }
    echo '</div><div class="fam-top-r">';
    echo '<form class="fam-q" action="/modules/family/search.php" method="get"><input name="q" placeholder="' . esc_html(t('fam_search_ph')) . '" value="' . esc_html($_GET['q'] ?? '') . '"><button class="fam-q-btn" type="submit">' . esc_html(t('fam_search_btn')) . '</button></form>';
    echo '<button type="button" class="fam-ghost" id="famThemeBtn" title="' . esc_html(t('fam_theme')) . '">◐</button>';
    echo '<span class="fam-role">' . esc_html(ame_word($role['role'])) . '</span>';
    echo '</div></header>';
    echo fam_flash();
}

function fam_close(): void
{
    echo '</div></div>';
    echo '<script src="/assets/js/family.js?v=' . esc_html($GLOBALS['sys_version'] ?? AME_ASSET_VERSION) . '"></script>';
}

function fam_empty(string $msg, string $href = '', string $cta = ''): string
{
    $h = '<div class="fam-empty"><p>' . esc_html($msg) . '</p>';
    if ($href && $cta) {
        $h .= '<a class="fam-btn" href="' . esc_html($href) . '">' . esc_html($cta) . '</a>';
    }
    return $h . '</div>';
}

function fam_money_txt($n): string
{
    return number_format((float)$n, 0) . ' ৳';
}

function fam_tel_btns(?string $mobile): string
{
    $m = preg_replace('/\s+/', '', (string)$mobile);
    if ($m === '') {
        return '<span class="fam-muted">' . esc_html(t('fam_no_number')) . '</span>';
    }
    $wa = preg_replace('/\D+/', '', $m);
    if (str_starts_with($wa, '0')) {
        $wa = '88' . $wa;
    }
    return '<a class="fam-mini" href="tel:' . esc_html($m) . '">' . esc_html(t('fam_call')) . '</a>'
        . '<a class="fam-mini" href="sms:' . esc_html($m) . '">' . esc_html(t('fam_sms')) . '</a>'
        . '<a class="fam-mini" href="https://wa.me/' . esc_html($wa) . '" target="_blank" rel="noopener">' . esc_html(t('whatsapp')) . '</a>';
}

function fam_upload(string $field, bool $required = false): ?string
{
    $info = $_FILES[$field] ?? null;
    if (!is_array($info) || (int)($info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new RuntimeException(ame_l('ফাইল লাগবে। ক্যামেরা বা গ্যালারি থেকে দিন।', 'A file is required. Use the camera or gallery.'));
        }
        return null;
    }
    $err = (int)($info['error'] ?? 1);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException(ame_l('ফাইল বড়। ৮ এমবির নিচে দিন।', 'The file is too large. Keep it under 8 MB.'));
    }
    if ($err !== UPLOAD_ERR_OK || empty($info['tmp_name']) || !is_uploaded_file($info['tmp_name'])) {
        throw new RuntimeException(ame_l('ফাইল আপলোড হয়নি। আবার চেষ্টা করুন।', 'The file did not upload. Try again.'));
    }
    if ((int)($info['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException(ame_l('ফাইল বড়। ৮ এমবির নিচে দিন।', 'The file is too large. Keep it under 8 MB.'));
    }
    $ext = strtolower(pathinfo((string)($info['name'] ?? ''), PATHINFO_EXTENSION));
    $ok = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx'];
    if (!in_array($ext, $ok, true)) {
        throw new RuntimeException(ame_l('শুধু ছবি বা PDF দিন।', 'Use a photo or a PDF.'));
    }
    if (class_exists('finfo')) {
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($info['tmp_name']);
        $allowMime = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        ];
        if ($mime !== '' && isset($allowMime[$ext]) && !in_array($mime, $allowMime[$ext], true)) {
            throw new RuntimeException(ame_l('এই ফাইলটি গ্রহণ করা যায়নি।', 'This file was not accepted.'));
        }
    }
    $dir = dirname(__DIR__) . '/uploads/family';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException(ame_l('আপলোড ফোল্ডার তৈরি হয়নি।', 'The upload folder could not be created.'));
    }
    if (!is_writable($dir)) {
        throw new RuntimeException(ame_l('আপলোড ফোল্ডারে লেখা যাচ্ছে না।', 'The upload folder is not writable.'));
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($info['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException(ame_l('ফাইল সেভ হয়নি।', 'The file was not saved.'));
    }
    return $name;
}
