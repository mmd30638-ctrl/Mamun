<?php
declare(strict_types=1);
define('VX_ROOT', __DIR__);
define('VX_APP_FILE', __DIR__ . '/app.php');
define('VX_CORE', is_dir(__DIR__ . '/core') ? __DIR__ . '/core' : dirname(__DIR__) . '/core');
require VX_CORE . '/bootstrap.php';
