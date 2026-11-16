<?php
/**
 * কাস্টমার ও সাপ্লায়ার চিরতরে মুছা — শুধু সুপার অ্যাডমিন।
 * খাতার সারি মুছে না। পাসওয়ার্ড + নাম + একবারের কোড লাগে।
 */

if (is_file(__DIR__ . '/super_admin.php')) {
    require_once __DIR__ . '/super_admin.php';
}

function ame_party_purge_can(): bool
{
    return function_exists('ame_is_super_admin') && ame_is_super_admin();
}

function ame_party_purge_normalize_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($name, 'UTF-8');
    }
    return strtolower($name);
}

function ame_party_purge_user_hash(mysqli $db, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }
    $stmt = @$db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? (string) ($row['password'] ?? '') : '';
}

function ame_party_purge_challenge_bag(): array
{
    $bag = $_SESSION['ame_party_purge_gate'] ?? [];
    return is_array($bag) ? $bag : [];
}

function ame_party_purge_locked(?array $bag = null): bool
{
    $bag = $bag ?? ame_party_purge_challenge_bag();
    $fails = (int) ($bag['fails'] ?? 0);
    $until = (int) ($bag['lock_until'] ?? 0);
    return $fails >= 5 && $until > time();
}

function ame_party_purge_challenge_make(string $kind, int $id): string
{
    $bag = ame_party_purge_challenge_bag();
    if (ame_party_purge_locked($bag)) {
        throw new RuntimeException('পাঁচবার ভুল হয়েছে। দশ মিনিট পর আবার চেষ্টা করুন।');
    }
    $kind = $kind === 'customer' ? 'customer' : 'supplier';
    $code = strtoupper(bin2hex(random_bytes(3)));
    $_SESSION['ame_party_purge_gate'] = [
        'kind' => $kind,
        'id' => $id,
        'code' => $code,
        'exp' => time() + 180,
        'fails' => (int) ($bag['fails'] ?? 0),
        'lock_until' => (int) ($bag['lock_until'] ?? 0),
    ];
    return $code;
}

function ame_party_purge_note_fail(): void
{
    $bag = ame_party_purge_challenge_bag();
    $fails = (int) ($bag['fails'] ?? 0) + 1;
    $bag['fails'] = $fails;
    if ($fails >= 5) {
        $bag['lock_until'] = time() + 600;
    }
    $_SESSION['ame_party_purge_gate'] = $bag;
}

function ame_party_purge_verify(
    mysqli $db,
    string $kind,
    int $id,
    string $expectedName,
    string $typedName,
    string $password,
    string $code
): void {
    if (!ame_party_purge_can()) {
        throw new RuntimeException('চিরতরে মুছতে সুপার অ্যাডমিন লাগবে। সাধারণ অ্যাডমিন পারেন না।');
    }
    $kind = $kind === 'customer' ? 'customer' : 'supplier';
    $bag = ame_party_purge_challenge_bag();
    if (ame_party_purge_locked($bag)) {
        throw new RuntimeException('পাঁচবার ভুল হয়েছে। দশ মিনিট পর আবার চেষ্টা করুন।');
    }
    $wantKind = (string) ($bag['kind'] ?? '');
    $wantId = (int) ($bag['id'] ?? 0);
    $wantCode = strtoupper((string) ($bag['code'] ?? ''));
    $exp = (int) ($bag['exp'] ?? 0);
    if ($wantKind !== $kind || $wantId !== $id || $exp < time() || $wantCode === '') {
        throw new RuntimeException('নিরাপত্তা কোড মেয়াদোত্তীর্ণ। আবার খুলুন।');
    }
    $gotCode = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
    $nameOk = $expectedName !== ''
        && ame_party_purge_normalize_name($typedName) === ame_party_purge_normalize_name($expectedName);
    $uid = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
    $hash = ame_party_purge_user_hash($db, $uid);
    $passOk = $password !== '' && $hash !== '' && password_verify($password, $hash);
    $codeOk = $gotCode !== '' && hash_equals($wantCode, $gotCode);
    if (!$nameOk || !$passOk || !$codeOk) {
        ame_party_purge_note_fail();
        throw new RuntimeException('নাম, পাসওয়ার্ড বা নিরাপত্তা কোড মেলেনি।');
    }
    unset($_SESSION['ame_party_purge_gate']);
}

function ame_customer_purge_schema_boot(mysqli $db): void
{
    static $done = false;
    if ($done || !($db instanceof mysqli)) {
        return;
    }
    $done = true;
    $col = @$db->query("SHOW COLUMNS FROM customers LIKE 'purged_at'");
    if ($col && $col->num_rows === 0) {
        @$db->query('ALTER TABLE customers ADD COLUMN purged_at DATETIME NULL DEFAULT NULL');
    }
    $col = @$db->query("SHOW COLUMNS FROM customers LIKE 'purged_by'");
    if ($col && $col->num_rows === 0) {
        @$db->query('ALTER TABLE customers ADD COLUMN purged_by INT NULL DEFAULT NULL');
    }
    ame_customer_restore_deleted_once($db);
}

function ame_customer_live_sql(mysqli $db, string $alias = ''): string
{
    ame_customer_purge_schema_boot($db);
    $p = ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) ? ($alias . '.') : '';
    return $p . 'purged_at IS NULL';
}

function ame_customer_purged_sql(mysqli $db, string $alias = ''): string
{
    ame_customer_purge_schema_boot($db);
    $p = ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) ? ($alias . '.') : '';
    return $p . "purged_at IS NOT NULL AND " . $p . "purged_at <> '0000-00-00 00:00:00'";
}

function ame_customer_is_purged(?array $row): bool
{
    if (!$row) {
        return false;
    }
    $at = trim((string) ($row['purged_at'] ?? ''));
    return $at !== '' && $at !== '0000-00-00 00:00:00';
}

function ame_customer_purge(mysqli $db, int $id): void
{
    ame_customer_purge_schema_boot($db);
    $stmt = $db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('কাস্টমার খোঁজা যায়নি।');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new InvalidArgumentException('কাস্টমার খুঁজে পাওয়া যায়নি।');
    }
    if (ame_customer_is_purged($row)) {
        return;
    }
    $by = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
    $done = false;
    $upd = $db->prepare("UPDATE customers
        SET purged_at = NOW(), purged_by = ?, status = 0, updated_at = NOW()
        WHERE id = ? AND purged_at IS NULL");
    if ($upd) {
        $upd->bind_param('ii', $by, $id);
        if ($upd->execute() && $upd->affected_rows >= 1) {
            $done = true;
        }
        $upd->close();
    }
    if (!$done) {
        $upd = $db->prepare("UPDATE customers SET purged_at = NOW(), status = 0 WHERE id = ? AND (purged_at IS NULL OR purged_at = '0000-00-00 00:00:00')");
        if ($upd) {
            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows >= 1) {
                $done = true;
            }
            $upd->close();
        }
    }
    if (!$done) {
        $upd = $db->prepare('UPDATE customers SET purged_at = NOW() WHERE id = ?');
        if ($upd) {
            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows >= 1) {
                $done = true;
            }
            $upd->close();
        }
    }
    if (!$done) {
        throw new RuntimeException('কাস্টমার সরানো যায়নি। বিক্রয় খাতা মুছা হয়নি।');
    }
    if (function_exists('log_activity')) {
        log_activity(
            'Customer Purged',
            'Customers',
            'কাস্টমার #' . $id . ' তালিকা থেকে চিরতরে সরানো। বিক্রয়, পেমেন্ট ও লেজার আছে।'
        );
    }
}

function ame_customer_restore(mysqli $db, int $id): void
{
    ame_customer_purge_schema_boot($db);
    if ($id <= 0) {
        throw new InvalidArgumentException('কাস্টমার খুঁজে পাওয়া যায়নি।');
    }
    $stmt = $db->prepare('SELECT id, purged_at FROM customers WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('কাস্টমার খোঁজা যায়নি।');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || !ame_customer_is_purged($row)) {
        return;
    }
    $on = function_exists('ame_customer_status_store') ? ame_customer_status_store($db, true) : '1';
    $done = false;
    $upd = $db->prepare('UPDATE customers SET purged_at = NULL, purged_by = NULL, status = ?, updated_at = NOW() WHERE id = ? AND purged_at IS NOT NULL');
    if ($upd) {
        $upd->bind_param('si', $on, $id);
        if ($upd->execute() && $upd->affected_rows >= 1) {
            $done = true;
        }
        $upd->close();
    }
    if (!$done) {
        $upd = $db->prepare('UPDATE customers SET purged_at = NULL, status = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('si', $on, $id);
            if ($upd->execute() && $upd->affected_rows >= 1) {
                $done = true;
            }
            $upd->close();
        }
    }
    if (!$done) {
        $upd = $db->prepare('UPDATE customers SET purged_at = NULL WHERE id = ? AND purged_at IS NOT NULL');
        if ($upd) {
            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows >= 1) {
                $done = true;
            }
            $upd->close();
        }
    }
    if (!$done) {
        throw new RuntimeException('কাস্টমার ফেরত আনা যায়নি। টাকা বদলায়নি।');
    }
    if (function_exists('log_activity')) {
        log_activity(
            'Customer Restored',
            'Customers',
            'কাস্টমার #' . $id . ' আবার তালিকায় ফিরে এসেছে। বিক্রয়, পেমেন্ট ও টাকা আগের মতো।'
        );
    }
}

function ame_customer_restore_purged(mysqli $db): int
{
    ame_customer_purge_schema_boot($db);
    $on = function_exists('ame_customer_status_store') ? ame_customer_status_store($db, true) : '1';
    $onSql = $db->real_escape_string($on);
    $where = ame_customer_purged_sql($db) . ' AND (is_customer = 1 OR is_customer IS NULL)';
    $sql = "UPDATE customers SET purged_at = NULL, purged_by = NULL, status = '{$onSql}', updated_at = NOW() WHERE {$where}";
    if (!$db->query($sql)) {
        $sql = "UPDATE customers SET purged_at = NULL, status = '{$onSql}' WHERE {$where}";
        if (!$db->query($sql)) {
            throw new RuntimeException('কাস্টমার ফেরত আনা যায়নি। টাকা বদলায়নি।');
        }
    }
    $count = (int) $db->affected_rows;
    if ($count > 0 && function_exists('log_activity')) {
        log_activity(
            'Customer Restored',
            'Customers',
            'মুছে ফেলা ' . $count . ' জন কাস্টমার আবার তালিকায় ফিরে এসেছে। টাকা বদলায়নি।'
        );
    }
    return $count;
}

function ame_customer_restore_deleted_once(mysqli $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    $flag = 'cus_restore_deleted_678';
    $known = @$db->query("SHOW TABLES LIKE 'system_flags'");
    if (!$known || $known->num_rows === 0) {
        return;
    }
    $seen = @$db->prepare('SELECT flag_value FROM system_flags WHERE flag_name = ? LIMIT 1');
    if (!$seen) {
        return;
    }
    $seen->bind_param('s', $flag);
    $seen->execute();
    $seenRes = $seen->get_result();
    $have = $seenRes ? $seenRes->fetch_assoc() : null;
    $seen->close();
    if (is_array($have) && (string) ($have['flag_value'] ?? '') === '1') {
        return;
    }
    try {
        $count = ame_customer_restore_purged($db);
    } catch (Throwable $e) {
        return;
    }
    $mark = @$db->prepare(
        'INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)'
    );
    if ($mark) {
        $one = '1';
        $mark->bind_param('ss', $flag, $one);
        $mark->execute();
        $mark->close();
    }
    if ($count > 0 && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['ame_cus_status_note'] = 'মুছে ফেলা কাস্টমার আবার তালিকায় ফিরে এসেছে।';
    }
}

function ame_party_purge_handle(mysqli $db, string $kind): void
{
    $kind = $kind === 'customer' ? 'customer' : 'supplier';
    $id = (int) ($_POST['id'] ?? 0);
    $ajax = (string) ($_POST['ajax'] ?? '') === '1'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $intent = (string) ($_POST['intent'] ?? 'purge');

    $respond = static function (bool $ok, string $message, array $extra = [], int $http = 200) use ($ajax, $id, $kind): void {
        if ($ajax) {
            http_response_code($http);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array_merge(['ok' => $ok, 'message' => $message, 'id' => $id, 'kind' => $kind], $extra), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $home = $kind === 'customer' ? '../index.php' : 'list.php';
        $profile = $kind === 'customer' ? '../profile.php?id=' : 'profile.php?id=';
        if ($ok) {
            redirect($home . ($kind === 'customer' ? '?purged=1' : '?purged=1'));
        }
        redirect($profile . $id . '&err=' . rawurlencode($message));
    };

    if (!ame_party_purge_can()) {
        $respond(false, 'চিরতরে মুছতে সুপার অ্যাডমিন লাগবে। সাধারণ অ্যাডমিন পারেন না।', [], 403);
    }
    if ($id <= 0) {
        $respond(false, $kind === 'customer' ? 'কাস্টমার সিলেক্ট করা নেই।' : 'সাপ্লায়ার সিলেক্ট করা নেই।', [], 400);
    }

    if ($kind === 'customer') {
        ame_customer_purge_schema_boot($db);
        $st = $db->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    } else {
        if (function_exists('ame_supplier_schema_boot')) {
            ame_supplier_schema_boot($db);
        }
        $st = $db->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
    }
    if (!$st) {
        $respond(false, 'রেকর্ড খোঁজা যায়নি।', [], 400);
    }
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        $respond(false, $kind === 'customer' ? 'কাস্টমার খুঁজে পাওয়া যায়নি।' : 'সাপ্লায়ার খুঁজে পাওয়া যায়নি।', [], 404);
    }
    $name = trim((string) ($row['name'] ?? ''));

    if ($intent === 'challenge') {
        try {
            $code = ame_party_purge_challenge_make($kind, $id);
            $respond(true, 'নিরাপত্তা কোড তৈরি হয়েছে।', [
                'code' => $code,
                'name' => $name,
                'expires_in' => 180,
            ]);
        } catch (Throwable $e) {
            $respond(false, $e->getMessage(), [], 429);
        }
    }

    try {
        ame_party_purge_verify(
            $db,
            $kind,
            $id,
            $name,
            (string) ($_POST['typed_name'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['code'] ?? '')
        );
        if ($kind === 'customer') {
            ame_customer_purge($db, $id);
            $respond(true, 'কাস্টমার তালিকা থেকে চিরতরে সরানো হয়েছে। বিক্রয়, পেমেন্ট ও লেজার আছে। পরে নতুন নাম্বার দিয়ে আবার যোগ করা যাবে।');
        }
        if (!function_exists('ame_supplier_purge')) {
            require_once __DIR__ . '/supplier_functions.php';
        }
        ame_supplier_purge($db, $id);
        $respond(true, 'সাপ্লায়ার তালিকা থেকে চিরতরে সরানো হয়েছে। ক্রয়, পেমেন্ট ও লেজার আছে। পরে নতুন নাম্বার দিয়ে আবার যোগ করা যাবে।');
    } catch (Throwable $e) {
        $respond(false, $e->getMessage(), [], 400);
    }
}

function ame_party_purge_assets(): void
{
    static $once = false;
    if ($once) {
        return;
    }
    $once = true;
    $can = ame_party_purge_can();
    ?>
<style>
.ame-pp-ov{position:fixed;inset:0;background:rgba(11,30,77,.55);z-index:2400;display:flex;align-items:center;justify-content:center;padding:16px;}
.ame-pp-ov[hidden]{display:none !important;}
.ame-pp-card{background:#fff;border-radius:14px;max-width:420px;width:100%;padding:18px 18px 16px;box-shadow:0 16px 40px rgba(11,30,77,.22);}
.ame-pp-card h3{margin:0 0 8px;font-size:17px;color:#7f1d1d;}
.ame-pp-card p{margin:0 0 10px;font-size:13px;color:#334155;line-height:1.45;}
.ame-pp-card label{display:block;font-size:12px;font-weight:800;margin:10px 0 4px;color:#0B1E4D;}
.ame-pp-card input{width:100%;box-sizing:border-box;padding:9px 10px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:15px;}
.ame-pp-code{font-family:ui-monospace,Menlo,monospace;letter-spacing:.12em;background:#fff7ed;border:1px dashed #ea580c;padding:6px 10px;border-radius:8px;font-weight:800;color:#9a3412;}
.ame-pp-acts{display:flex;gap:8px;margin-top:14px;}
.ame-pp-acts button{flex:1;border:0;border-radius:8px;padding:10px;font-weight:800;cursor:pointer;}
.ame-pp-go{background:#b91c1c;color:#fff;}
.ame-pp-no{background:#e2e8f0;color:#0f172a;}
.ame-pp-msg{min-height:18px;font-size:12px;font-weight:700;color:#b91c1c;margin-top:8px;}
</style>
<div class="ame-pp-ov" id="amePartyPurgeOv" hidden style="display:none">
  <form class="ame-pp-card" id="amePartyPurgeForm" autocomplete="off">
    <h3>চিরতরে মুছুন</h3>
    <p>শুধু সুপার অ্যাডমিন। খাতা মুছবে না। নাম, নিজের পাসওয়ার্ড ও নিচের কোড দিন।</p>
    <p>মুছতে এই নাম হুবহু লিখুন: <b id="amePartyPurgeHint"></b></p>
    <label for="amePartyPurgeName">নাম</label>
    <input id="amePartyPurgeName" name="typed_name" required autocomplete="off">
    <label for="amePartyPurgePass">নিজের লগইন পাসওয়ার্ড</label>
    <input id="amePartyPurgePass" name="password" type="password" required autocomplete="current-password">
    <label>নিরাপত্তা কোড</label>
    <div class="ame-pp-code" id="amePartyPurgeCode">——</div>
    <label for="amePartyPurgeCodeIn">কোডটি লিখুন</label>
    <input id="amePartyPurgeCodeIn" name="code" required autocomplete="off" maxlength="12">
    <div class="ame-pp-msg" id="amePartyPurgeMsg"></div>
    <div class="ame-pp-acts">
      <button type="button" class="ame-pp-no" id="amePartyPurgeCancel">বাতিল</button>
      <button type="submit" class="ame-pp-go">হ্যাঁ, চিরতরে মুছুন</button>
    </div>
  </form>
</div>
<script>
window.AME_CAN_PARTY_PURGE = <?php echo $can ? 'true' : 'false'; ?>;
(function () {
  var ov = document.getElementById('amePartyPurgeOv');
  var form = document.getElementById('amePartyPurgeForm');
  if (!ov || !form) return;
  var state = { url: '', id: 0, name: '', kind: '' };

  function csrf() {
    return (typeof window.AME_CSRF_TOKEN === 'string') ? window.AME_CSRF_TOKEN : '';
  }
  function msg(t) {
    var el = document.getElementById('amePartyPurgeMsg');
    if (el) el.textContent = t || '';
  }
  function close() {
    ov.hidden = true;
    ov.style.display = 'none';
    form.reset();
    msg('');
  }
  function post(body) {
    return fetch(state.url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf() },
      body: body
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); });
  }
  function open(btn) {
    if (!window.AME_CAN_PARTY_PURGE) {
      window.alert('চিরতরে মুছতে সুপার অ্যাডমিন লাগবে।');
      return;
    }
    state.url = btn.getAttribute('data-action') || '';
    state.id = parseInt(btn.getAttribute('data-id') || '0', 10);
    state.name = btn.getAttribute('data-name') || '';
    state.kind = btn.getAttribute('data-ame-party-purge') || '';
    if (!state.url || state.id < 1) return;
    document.getElementById('amePartyPurgeHint').textContent = state.name;
    document.getElementById('amePartyPurgeCode').textContent = '…';
    msg('');
    ov.hidden = false;
    ov.style.display = 'flex';
    var fd = new FormData();
    fd.append('csrf_token', csrf());
    fd.append('ajax', '1');
    fd.append('intent', 'challenge');
    fd.append('id', String(state.id));
    post(fd).then(function (res) {
      var j = res.json || {};
      if (!j.ok || !j.code) {
        msg(j.message || 'কোড পাওয়া যায়নি।');
        return;
      }
      document.getElementById('amePartyPurgeCode').textContent = j.code;
      if (j.name) {
        state.name = j.name;
        document.getElementById('amePartyPurgeHint').textContent = j.name;
      }
    }).catch(function () { msg('কোড আনা যায়নি।'); });
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    var btn = t.closest('[data-ame-party-purge]');
    if (!btn) return;
    ev.preventDefault();
    open(btn);
  });
  document.getElementById('amePartyPurgeCancel').addEventListener('click', close);
  ov.addEventListener('click', function (ev) { if (ev.target === ov) close(); });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(form);
    fd.append('csrf_token', csrf());
    fd.append('ajax', '1');
    fd.append('intent', 'purge');
    fd.append('id', String(state.id));
    msg('যাচাই হচ্ছে…');
    post(fd).then(function (res) {
      var j = res.json || {};
      if (!j.ok) {
        msg(j.message || 'মুছা যায়নি।');
        return;
      }
      window.location.reload();
    }).catch(function () { msg('সংযোগ যায়নি।'); });
  });
})();
</script>
    <?php
}
