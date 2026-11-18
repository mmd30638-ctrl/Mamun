<?php
if (!empty($GLOBALS['ame_pos_customer_panel_rendered'])) {
    return;
}
$GLOBALS['ame_pos_customer_panel_rendered'] = true;

include __DIR__ . '/pos_customer_data.php';
include __DIR__ . '/pos_customer_style.php';
include __DIR__ . '/pos_customer_body.php';
