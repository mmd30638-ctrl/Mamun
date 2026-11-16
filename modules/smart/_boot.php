<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/smart_functions.php';

if (!ame_can_see_smart($db)) {
    header('Location: /login.php');
    exit;
}
