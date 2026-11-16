<?php
/**
 * Safe Security Helpers — APONSHOP v10.98.864
 * No auto-run. No login impact. No financial changes.
 */

if (!function_exists('ame_csrf_token')) {
    function ame_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['ame_csrf'])) {
            $_SESSION['ame_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['ame_csrf'];
    }
}

if (!function_exists('ame_csrf_field')) {
    function ame_csrf_field(): string {
        $t = ame_csrf_token();
        if ($t === '') return '';
        return '<input type="hidden" name="ame_csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('ame_clean')) {
    function ame_clean($val): string {
        return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ame_security_note')) {
    function ame_security_note(): string {
        return 'CSRF, input clean, session safe. Applied in v10.98.864.';
    }
}
