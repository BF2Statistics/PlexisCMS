<?php
declare(strict_types=1);
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System\IO;

/**
 * Performs operations on String instances that contain file or directory path information.
 * These operations are performed in a cross-platform manner.
 *
 * @package System\IO
 */
class Path
{
    /**
     * Changes the extension of a path string
     *
     * @param string $path The path information to modify
     * @param ?string $extension The new extension. Leave null to remove extension
     *
     * @return string Returns the full path using the correct system
     *   directory separator
     */
    public static function ChangeExtension(string $path, ?string $extension = null): string
    {
        $dir = dirname($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if (empty($extension))
            return ($dir === '.') ? $filename : $dir . DIRECTORY_SEPARATOR . $filename;

        $ext = ltrim($extension, '.');
        $newFile = $filename . '.' . $ext;
        return ($dir === '.') ? $newFile : $dir . DIRECTORY_SEPARATOR . $newFile;
    }

    /**
     * Combines multiple path segments into a single path, handling redundant separators,
     * relative path references (e.g., "." and ".."), and trimming unnecessary whitespace.
     *
     * @param string ...$paths The path segments to combine
     *
     * @return string The combined path with correct directory separators
     */
    public static function Combine(string ...$paths): string
    {
        $parts = [];

        foreach ($paths as $part)
        {
            // Trim whitespace and separators to avoid double slashes
            $part = trim($part, " \t\n\r\0\x0B/\\");

            if ($part === '.' || $part === '')
                continue;
            elseif ($part === '..')
                array_pop($parts);
            else
                $parts[] = $part;
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Normalizes the given file path by replacing all slashes with the system's directory separator.
     *
     * @param string $path The file path to normalize
     *
     * @return string
     */
    public static function Normalize(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Returns the directory name for the specified path string.
     *
     * @param string $path The path we are getting the directory name for
     *
     * @return string Returns the full path using the correct system
     *   directory separator
     */
    public static function GetDirectoryName(string $path): string
    {
        return self::Normalize(dirname($path));
    }

    /**
     * Returns the extension of the specified path string.
     *
     * @param string $path The file path we are getting the extension for
     *
     * @return string
     */
    public static function GetExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Checks if the specified file path has an extension.
     *
     * @param string $path The file path to check for a file extension
     *
     * @return bool
     */
    public static function HasExtension(string $path): bool
    {
        return pathinfo($path, PATHINFO_EXTENSION) !== '';
    }

    /**
     * Gets the file name and extension of the specified path string if the path points to a file. If path
     * is a directory, gets the base folder name.
     *
     * @param string $path The file path we are getting the name of
     *
     * @return string
     */
    public static function GetFilename(string $path): string
    {
        return basename($path);
    }

    /**
     * Returns the file name of the specified path string without the extension
     *
     * @param string $path The file path we are getting the name of
     *
     * @return string
     */
    public static function GetFilenameWithoutExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Returns the absolute (full) path for the specified path string.
     * Resolves relative paths against the current working directory.
     *
     * @param string $path The file or directory path
     *
     * @return string The full absolute path with normalized separators
     */
    public static function GetFullPath(string $path): string
    {
        $realPath = realpath($path);

        if ($realPath !== false)
            return self::Normalize($realPath);

        // Path doesn't exist yet — resolve it manually
        if (!self::IsPathRooted($path))
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;

        return self::Normalize($path);
    }

    /**
     * Determines whether a path string includes a root (is absolute).
     *
     * @param string $path The path to test
     *
     * @return bool True if the path is absolute, false if relative
     */
    public static function IsPathRooted(string $path): bool
    {
        if ($path === '')
            return false;

        $path = self::Normalize($path);

        // Unix absolute path
        if ($path[0] === DIRECTORY_SEPARATOR)
            return true;

        // Windows drive letter (e.g., C:\)
        if (strlen($path) >= 2 && ctype_alpha($path[0]) && $path[1] === ':')
            return true;

        return false;
    }

    /**
     * Returns the path to the system's temporary directory.
     *
     * @return string The temp directory path with normalized separators
     */
    public static function GetTempPath(): string
    {
        return self::Normalize(sys_get_temp_dir());
    }

    /**
     * Creates a uniquely named temporary file path (does not create the file).
     *
     * @param string $prefix Optional filename prefix
     * @param string $extension Optional extension (default: "tmp")
     *
     * @return string A unique temporary file path
     */
    public static function GetTempFileName(string $prefix = 'tmp', string $extension = 'tmp'): string
    {
        $ext = ltrim($extension, '.');
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        return self::Combine(self::GetTempPath(), $filename);
    }

    /**
     * Returns a random directory or file name without creating it.
     *
     * @return string A random name string (no extension)
     */
    public static function GetRandomFileName(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Computes the relative path from one path to another.
     *
     * @param string $from The source path (directory)
     * @param string $to The target path
     *
     * @return string The relative path from $from to $to
     */
    public static function GetRelativePath(string $from, string $to): string
    {
        $from = self::Normalize(rtrim($from, " \t\n\r\0\x0B/\\"));
        $to = self::Normalize($to);

        $fromParts = explode(DIRECTORY_SEPARATOR, $from);
        $toParts = explode(DIRECTORY_SEPARATOR, $to);

        // Find common prefix length
        $commonLength = 0;
        $max = min(count($fromParts), count($toParts));

        for ($i = 0; $i < $max; $i++)
        {
            if (strcasecmp($fromParts[$i], $toParts[$i]) !== 0)
                break;

            $commonLength++;
        }

        // Build relative path: go up from $from, then down to $to
        $ups = array_fill(0, count($fromParts) - $commonLength, '..');
        $downs = array_slice($toParts, $commonLength);

        $relative = array_merge($ups, $downs);

        return empty($relative) ? '.' : implode(DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * Determines whether the given path ends with a directory separator.
     *
     * @param string $path The path to check
     *
     * @return bool
     */
    public static function EndsInDirectorySeparator(string $path): bool
    {
        if ($path === '')
            return false;

        $last = $path[strlen($path) - 1];
        return $last === '/' || $last === '\\';
    }

    /**
     * Adds a trailing directory separator to the path if one is not already present.
     *
     * @param string $path The path to modify
     *
     * @return string The path with a trailing directory separator
     */
    public static function EnsureTrailingSeparator(string $path): string
    {
        if ($path === '' || self::EndsInDirectorySeparator($path))
            return self::Normalize($path);

        return self::Normalize($path) . DIRECTORY_SEPARATOR;
    }

    /**
     * Removes the trailing directory separator from the path if present.
     *
     * @param string $path The path to modify
     *
     * @return string The path without a trailing directory separator
     */
    public static function TrimTrailingSeparator(string $path): string
    {
        return rtrim(self::Normalize($path), DIRECTORY_SEPARATOR);
    }
}