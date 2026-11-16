<?php
/**
 * Bundled 25 Sep 2026 old-POS dump used by the confirmed full shop replace.
 */

function ame_oldpos_sql_path(): string
{
    return dirname(__DIR__) . '/data/old_pos_20260925.sql';
}

function ame_oldpos_sql_ready(): bool
{
    $path = ame_oldpos_sql_path();
    return is_file($path) && filesize($path) > 20000000;
}
