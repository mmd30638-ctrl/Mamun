<?php
/**
 * Same book as Settings → Activity. Old links keep working.
 */
if (!isset($_GET['range']) && (isset($_GET['start_date']) || isset($_GET['end_date']))) {
    $_GET['range'] = 'custom';
    if (empty($_GET['from']) && !empty($_GET['start_date'])) {
        $_GET['from'] = $_GET['start_date'];
    }
    if (empty($_GET['to']) && !empty($_GET['end_date'])) {
        $_GET['to'] = $_GET['end_date'];
    }
}
if (empty($_GET['user']) && !empty($_GET['username'])) {
    $_GET['user'] = $_GET['username'];
}
require __DIR__ . '/../settings/activity_logs.php';
