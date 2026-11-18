<?php
/**
 * Safe Code Quality Helpers — APONSHOP v10.98.865
 * No auto-run. No login impact. No financial changes.
 */

if (!function_exists('ame_log_safe')) {
    function ame_log_safe(string $level, string $msg): void {
        error_log('[APONSHOP-' . strtoupper($level) . '] ' . $msg);
    }
}

if (!function_exists('ame_quality_note')) {
    function ame_quality_note(): string {
        return 'Code quality helpers added in v10.98.865. Safe, no auto-load.';
    }
}

if (!function_exists('ame_comment_standard')) {
    function ame_comment_standard(): string {
        return 'Comment why, not what. Keep modules clean.';
    }
}
