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
namespace System;

/**
 * This class facilitates the dynamic loading of classes by interacting
 * with PHP's SPL autoload system. It supports the registration of custom
 * paths and namespaces for efficient autoloading of application classes.
 *
 * Key features include:
 * - Integration with PHP's SPL autoload functionality
 * - Support for namespace-to-path mapping
 * - Custom directory registration for locating non-namespaced or prefixed classes
 * - Dynamic registration and un-registration of the autoloader
 *
 * Usage:
 * - Call `Autoloader::Register()` to enable the autoloader.
 * - Use `Autoloader::RegisterPath()` to register directories for class searching.
 * - Map namespaces to specific paths using `Autoloader::RegisterNamespace()`.
 *
 * Example:
 * ```
 * Autoloader::Register();
 * Autoloader::RegisterPath('/path/to/classes');
 * Autoloader::RegisterNamespace('MyApp', '/path/to/myapp');
 * ```
 *
 * @package     System
 * @author      Steven Wilson
 * @license     GNU GPL v3
 * @copyright   Copyright 2025, Steven Wilson, All rights reserved.
 * @since       PHP 8.2 or newer
 */

class Autoloader
{
    /**
     * Indicates whether this Autoloader is registered with spl_autoload
     * @var bool
     */
    protected static bool $isRegistered = false;

    /**
     * An array of registered paths
     * @var string[]
     */
    protected static array $paths = array();

    /**
     * An array of registered namespace => path
     * @var array
     */
    protected static array $namespaces = array();

    /**
     * Registers the AutoLoader class with spl_autoload. Multiple
     * calls to this method will not yield any additional results.
     *
     * @return void
     */
    public static function Register(): void
    {
        if (self::$isRegistered) return;

        // Register this autoloader
        spl_autoload_register([self::class, 'LoadClass']);

        // Add paths for the system
        self::$namespaces['System'] = array(__DIR__);
        self::$namespaces['Application'] = array(APP_DIR . DIRECTORY_SEPARATOR . 'framework');
        self::$namespaces['Modules'] = array(APP_DIR . DIRECTORY_SEPARATOR . 'modules');

        self::$isRegistered = true;
    }

    /**
     * Un-Registers the AutoLoader class with spl_autoload
     *
     * @return void
     */
    public static function UnRegister(): void
    {
        if (!self::$isRegistered) return;

        spl_autoload_unregister([self::class, 'LoadClass']);

        self::$isRegistered = false;
    }

    /**
     * Registers a path for the autoload to search for classes. Namespaced
     * and prefixed registered paths will be searched first if the class
     * is namespaced, or prefixed.
     *
     * @param string $path Full path to search for a class
     *
     * @return void
     */
    public static function RegisterPath(string $path): void
    {
        if (!in_array($path, self::$paths))
            self::$paths[] = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Registers a path for the autoloader to search in when searching
     * for a specific namespaced class. When calling this method more
     * than once with the same namespace, the path(s) will just be added
     * to the current running list of paths for that namespace
     *
     * @param string $namespace The namespace we are registering
     * @param array|string $path Full path, or an array of paths
     *   to search for the namespaced class'.
     *
     * @return void
     */
    public static function RegisterNamespace(string $namespace, array|string $path): void
    {
        // Make sure path is array
        if (!is_array($path))
        {
            // Fix path, providing correct directory separator
            $path = array(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path));
        }
        else
        {
            // Normalize paths and ensure uniqueness
            foreach ($path as &$p)
            {
                $p = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $p), DIRECTORY_SEPARATOR);
            }
        }

        // Set namespace paths
        if (isset(self::$namespaces[$namespace]))
            self::$namespaces[$namespace] = array_unique(array_merge(self::$namespaces[$namespace], $path));
        else
            self::$namespaces[$namespace] = $path;
    }

    /**
     * Returns an array of all registered namespaces as keys, and an array
     * of registered paths for that namespace as values
     *
     * @return string[]
     */
    public static function GetNamespaces(): array
    {
        return self::$namespaces;
    }

    /**
     * Method used to search all registered paths for a missing class
     * reference (used by the spl_autoload method)
     *
     * @param string $class The class being loaded
     *
     * @return bool Returns TRUE if the class is found, and file was
     *   included successfully.
     */
    public static function LoadClass(string $class): bool
    {
        // Normalize class name
        $class = trim($class, '\\');
        $classPath = str_replace(['_', '\\'], DIRECTORY_SEPARATOR, $class) . '.php';

        // Look for the class in registered namespaces
        foreach (self::$namespaces as $namespace => $dirs)
        {
            // Check if the class belongs to this namespace
            if (str_starts_with($class, $namespace . '\\'))
            {
                // Remove the namespace from the class and get the subpath
                $subClass = substr($class, strlen($namespace) + 1); // +1 to skip the backslash
                $subPath = str_replace('\\', DIRECTORY_SEPARATOR, $subClass) . '.php';

                // Search in all registered directories for this namespace
                foreach ($dirs as $dir)
                {
                    $file = $dir . DIRECTORY_SEPARATOR . $subPath;
                    if (file_exists($file))
                    {
                        require $file;
                        return true;
                    }
                }
            }
        }

        // Fallback: Check registered non-namespaced paths
        foreach (self::$paths as $dir)
        {
            $file = $dir . DIRECTORY_SEPARATOR . $classPath;
            if (file_exists($file))
            {
                require $file;
                return true;
            }
        }

        // Class not found
        return false;
    }
}