<?php
require_once __DIR__ . '/../../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function ame_layout_fail(int $code): void
{
    http_response_code($code);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

function ame_layout_ensure(mysqli $db): void
{
    $db->query(
        'CREATE TABLE IF NOT EXISTS ame_page_layouts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            page_path VARCHAR(190) NOT NULL,
            layout_json MEDIUMTEXT NOT NULL,
            locked TINYINT NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_page (user_id, page_path)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ame_layout_path(string $raw): string
{
    $path = explode('?', $raw, 2)[0];
    if (!str_starts_with($path, '/') || str_contains($path, '..') || strlen($path) > 190) {
        ame_layout_fail(400);
    }
    if (!preg_match('#^/[A-Za-z0-9_./-]*$#', $path)) {
        ame_layout_fail(400);
    }
    return $path;
}

function ame_layout_items($raw): array
{
    if (!is_array($raw) || count($raw) > 80) {
        ame_layout_fail(400);
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $path = (string) ($row['path'] ?? '');
        if ($path === '' || strlen($path) > 400 || !preg_match('/^[a-z0-9:>()-]+$/i', $path)) {
            continue;
        }
        $out[] = [
            'path' => $path,
            'x' => max(0, (float) ($row['x'] ?? 0)),
            'y' => max(0, (float) ($row['y'] ?? 0)),
            'w' => max(36, (float) ($row['w'] ?? 96)),
            'h' => max(24, (float) ($row['h'] ?? 36)),
        ];
    }
    return $out;
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    ame_layout_fail(401);
}
ame_layout_ensure($db);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $path = ame_layout_path((string) ($_GET['path'] ?? ''));
    $stmt = $db->prepare('SELECT layout_json, locked FROM ame_page_layouts WHERE user_id = ? AND page_path = ? LIMIT 1');
    $stmt->bind_param('is', $uid, $path);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $items = [];
    if ($row) {
        $decoded = json_decode((string) $row['layout_json'], true);
        $items = is_array($decoded) ? ame_layout_items($decoded) : [];
    }
    echo json_encode(['ok' => true, 'items' => $items, 'locked' => $row ? ((int) $row['locked'] === 1) : false], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ame_layout_fail(405);
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!function_exists('csrf_token') || $token === '' || !hash_equals(csrf_token(), $token)) {
    ame_layout_fail(403);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    ame_layout_fail(400);
}
$path = ame_layout_path((string) ($payload['path'] ?? ''));
$items = ame_layout_items($payload['items'] ?? []);
if (!empty($payload['reset']) || $items === []) {
    $stmt = $db->prepare('DELETE FROM ame_page_layouts WHERE user_id = ? AND page_path = ?');
    $stmt->bind_param('is', $uid, $path);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$json = json_encode($items, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare(
    'INSERT INTO ame_page_layouts (user_id, page_path, layout_json, locked) VALUES (?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE layout_json = ?, locked = 1'
);
$stmt->bind_param('isss', $uid, $path, $json, $json);
$stmt->execute();
$stmt->close();
echo json_encode(['ok' => true, 'locked' => true], JSON_UNESCAPED_UNICODE);
