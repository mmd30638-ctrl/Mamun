<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../customers/customer_schema.php';
ensure_customer_tables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_area'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO customer_areas (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }
    header('Location: customer-areas.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM customer_areas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: customer-areas.php');
    exit;
}

$areas = get_customer_areas($db);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<link rel="stylesheet" href="/assets/css/fulldisplay-fix.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>কাস্টমার এরিয়া ম্যানেজমেন্ট</title>
<style>
    body { font-family: sans-serif; background:#f4f7f9; padding:15px; }
    .card { background:#fff; border-radius:10px; padding:18px; max-width:600px; margin:0 auto; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    input[type=text] { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
    .btn { padding:10px 16px; background:#0D47A1; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer; }
    table { width:100%; border-collapse:collapse; margin-top:15px; font-size:14px; }
    th, td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
    .del { color:#C62828; text-decoration:none; font-size:12px; }
</style>

</head>
<body>
<div class="card">
    <h3>📍 কাস্টমার এরিয়া ম্যানেজমেন্ট</h3>
    <form method="POST" style="display:flex; gap:8px;">
        <input type="text" name="name" placeholder="নতুন এরিয়ার নাম" required>
        <button type="submit" name="add_area" class="btn">➕ যোগ</button>
    </form>
    <table>
        <thead><tr><th>এরিয়ার নাম</th><th>একশন</th></tr></thead>
        <tbody>
            <?php if(count($areas) > 0): foreach($areas as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><a href="?delete=<?php echo $a['id']; ?>" class="del" onclick="return confirm('ডিলিট করবেন?');">🗑️ ডিলিট</a></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="2" style="text-align:center; color:#888;">কোনো এরিয়া নেই</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>