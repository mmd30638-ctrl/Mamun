<?php
/**
 * Sales aliases now open the Settings layout studio.
 */
$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
$to = '/modules/settings/layout_designer.php';
if ($qs !== '') {
    $to .= '?' . $qs;
}
header('Location: ' . $to, true, 302);
exit;
