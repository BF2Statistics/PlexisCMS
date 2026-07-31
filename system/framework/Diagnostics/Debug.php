<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System\Diagnostics;

/**
 * Class Debug
 * @package System
 */
class Debug
{
    public static function Trace(string $message, ?string $file, int $line = 0): void
    {

    }

    /**
     * Outputs the given item using var_dump, cleans the output buffer, and terminates the script execution.
     *
     * @param mixed $item The item to be dumped and displayed before terminating the script.
     *
     * @return never This method does not return as it terminates the script execution.
     */
    public static function DumpAndDie(mixed $item): never
    {
        ob_clean();
        ini_set("xdebug.var_display_max_children", '-1');
        ini_set("xdebug.var_display_max_data", '-1');
        ini_set("xdebug.var_display_max_depth", '-1');
        die('<pre>'. var_dump($item) . "</pre>");
    }
}