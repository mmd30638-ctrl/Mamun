<?php
/**
 * Bangladesh 09:00 — manager and super admin auto duty ON.
 * Hostinger cron, Asia/Dhaka:
 * 0 9 * * * php /home/u718102670/domains/mavo12.online/public_html/cron/duty_morning.php
 * The same pass also runs on the first page after 09:00 if cron is late.
 * A manual off after that stays off until the next morning.
 */
define('AME_DUTY_CRON', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/attendance_functions.php';
require_once dirname(__DIR__) . '/includes/staff_duty.php';

ame_staff_duty_tick($db);
$today = ame_staff_duty_today();
$on = ame_staff_on_duty_ids($db);

echo json_encode([
    'ok' => true,
    'day' => $today,
    'clock' => ame_staff_duty_now()->format('H:i'),
    'on_duty' => count($on),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
