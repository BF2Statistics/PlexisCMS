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
namespace System\Cache;
use Exception;
use ReflectionClass;
use System\Autoloader;
use System\CacheOLD\CacheDriverInterface;
use System\Collections\Dictionary;
use System\IO\Path;

/**
 * This class serves as the central entry point for managing
 * and interacting with the caching layer. It abstracts away
 * the details of cache drivers (e.g., APCu, Redis, FileCache)
 * and provides a unified interface for the application to
 * perform caching operations.
 *
 * Responsibilities:
 * - Dynamically load and initialize cache drivers based on configuration.
 * - Provide high-level caching methods (e.g., get, set, delete).
 * - Handle fallback mechanisms and driver-specific nuances.
 */
class CacheService
{
    /**
     * @var ?Dictionary
     */
    protected static ?Dictionary $Cache = null;

    /**
     * @var ?Dictionary
     */
    protected static ?Dictionary $Config = null;

    /**
     * @var CacheInterface|null The default site cache instance based off of the cache.php config settings
     */
    protected static ?CacheInterface $DefaultDriver = null;

    /**
     * Retrieves the default site configured cache driver instance.
     *
     * @return CacheInterface The default cache driver instance.
     *
     * @throws Exception
     */
    public static function Default(): CacheInterface
    {
        if (is_null(self::$DefaultDriver))
        {
            // Ensure proper config format
            if (is_null(self::$Config))
            {
                self::LoadConfig();
            }

            // Extract the driver name, and check to see if it has configuration
            $name = self::$Config['cache_driver'];

            // Do we have config?
            if (empty($config) && !empty(self::$Config['driver_config'][$name]))
            {
                $config = self::$Config['driver_config'][$name];
                self::$DefaultDriver = self::CreateDriverClassInstance($name, $config);
            }
            else
            {
                // Load instance
                self::$DefaultDriver = self::CreateDriverClassInstance($name);
            }
        }

        return self::$DefaultDriver;
    }

    /**
     * Retrieves or Creates an instance of the specified cache driver by key. If the driver is not already loaded,
     * it attempts to load it dynamically.
     *
     * @param string|null $name The name of the cache instance. If null, the instance will not be stored internally
     * @param string $driverName The name of the cache driver to retrieve. The fully qualified class name is derived from this parameter.
     * @param array|null $config Optional configuration for the cache driver. If null, the default configuration will be used.
     *
     * @return CacheInterface The instance of the requested cache driver.
     *
     * @throws Exception If the cache driver cannot be located, loaded, or does not implement the ICacheDriver interface.
     */
    public static function Create(?string $name, string $driverName, ?array $config = null): CacheInterface
    {
        // Ensure the cache dictionary is set
        if (is_null(self::$Cache))
            self::$Cache = new Dictionary(false);

        // If null, the load the default site configured cache driver
        if (!empty($name) && self::$Cache->containsKey($name))
        {
            return self::$Cache[$name];
        }

        // Ensure proper config format
        if (empty(self::$Config))
        {
            self::LoadConfig();
        }

        // Do we have config?
        if (empty($config) && !empty(self::$Config['driver_config'][$driverName])) {
            $config = self::$Config['driver_config'][$driverName];
        }

        // Load driver file
        $class = self::CreateDriverClassInstance($driverName, $config);

        // Score for later
        if (!empty($name))
            self::$Cache->add($name, $class);

        return $class;
    }

    /**
     * Registers a named cache driver.
     *
     * @param string $name A unique name for the cache driver (e.g., 'apcu', 'file').
     * @param CacheInterface $driver The instance of the cache driver to register.
     *
     * @return void
     * @throws Exception If the driver name is already registered.
     */
    public static function RegisterDriver(string $name, CacheInterface $driver): void
    {
        // Ensure the cache dictionary is set
        if (is_null(self::$Cache))
            self::$Cache = new Dictionary(false);

        if (self::$Cache->containsKey($name)) {
            throw new Exception("Cache driver with name '$name' is already registered.");
        }

        self::$Cache->add($name, $driver);
    }

    /**
     * Creates an instance of the specified cache driver class.
     *
     * @param string $driverName The name of the cache driver to load, corresponding to a class under the namespace 'System\Cache\Drivers'.
     * @param array|null $config Optional configuration for the cache driver. If null, no configuration will be used.
     *
     * @return CacheInterface An instance of the specified cache driver class.
     *
     * @throws Exception If the cache driver class cannot be loaded.
     * @throws Exception If the cache driver class does not implement the 'System\Cache\ICacheDriver' interface.
     */
    private static function CreateDriverClassInstance(string $driverName, ?array $config = null): CacheInterface
    {
        // Load driver file
        $className = 'System\Cache\Drivers\\' . ucfirst($driverName) . 'Driver';
        if (!Autoloader::LoadClass($className))
        {
            throw new Exception("Unable to located Cache Driver: ". $className);
        }

        // Load the controller reflection class, and ensure it inherits the ICacheDriver interface
        $class = new ReflectionClass($className);
        if (!$class->implementsInterface('System\Cache\CacheInterface'))
        {
            throw new Exception(sprintf("Cache driver (%s) does not implement CacheDriverInterface", $className));
        }

        // Create instance
        if (!empty($config)) {
            $class = new $className($config);
        }
        else {
            $class = new $className();
        }

        return $class;
    }

    /**
     * Loads the cache configuration from the specified file.
     *
     * @return void
     *
     * @throws Exception If the configuration file is not found or cannot be parsed correctly.
     */
    private static function LoadConfig(): void
    {
        // Create a new driver instance!
        $filePath = Path::Combine(SYSTEM_DIR, 'config', 'cache.php');
        if (!file_exists($filePath))
            throw new Exception("Unable to locate cache configuration file: ". $filePath);

        $data = include $filePath;
        if (!is_array($data) || empty($data['cache_driver']))
            throw new Exception("Unable to parse cache configuration file: " . $filePath);

        self::$Config = new Dictionary(true, $data);
    }
}