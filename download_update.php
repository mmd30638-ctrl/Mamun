<?php
/**
 * Settings → আপডেট ডাউনলোড থেকে আসা আলিয়াস।
 */
if (!isset($_GET['go'])) {
    $_GET['go'] = '1';
}
require __DIR__ . '/download.php';
