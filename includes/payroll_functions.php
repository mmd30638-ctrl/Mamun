<?php
/**
 * Legacy path. Payroll lives in HRM — keep this file so old require_once
 * includes do not fatal, then load the real helpers.
 */
if (!function_exists('ensure_hrm_schema')) {
    require_once __DIR__ . '/hrm_functions.php';
}
