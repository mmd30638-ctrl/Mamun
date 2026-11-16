<?php
/**
 * Safe Performance Helpers — APONSHOP v10.98.866
 * No auto-run. No login impact. No financial changes.
 */

if (!function_exists('ame_perf_note')) {
    function ame_perf_note(): string {
        return 'Follow SPEED.txt. Avoid N+1. Cache static data only. v10.98.866.';
    }
}

if (!function_exists('ame_cache_note')) {
    function ame_cache_note(): string {
        return 'Cache settings, categories, permissions. Never cache sales or stock.';
    }
}

if (!function_exists('ame_query_note')) {
    function ame_query_note(): string {
        return 'Always use prepared statements. Keep queries lean.';
    }
}
