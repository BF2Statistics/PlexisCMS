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
namespace System\Configuration;

use Exception;
use System\Configuration\Drivers\IniConfig;
use System\Configuration\Drivers\PhpConfig;
use System\Configuration\Drivers\XmlConfig;
use System\IO\FileNotFoundException;

/**
 * Handles the loading and management of configuration files in various formats.
 *
 * This class provides functionality to load configuration files of different
 * types, such as PHP, INI, XML, and JSON, and returns an abstraction that
 * allows for interaction with the loaded configuration data.
 */
class ConfigManager
{

    /**
     * Loads a configuration file based on the specified filename and configuration type.
     *
     * @param string $filename The path to the configuration file to be loaded.
     * @param ConfigType $configType The type of configuration file (e.g., PHP, INI, XML, JSON).
     *
     * @return ConfigBase An instance of the loaded configuration.
     *
     * @throws FileNotFoundException If the specified file does not exist.
     * @throws Exception If an invalid configuration type is provided or if the type is not implemented.
     */
    public static function Load(string $filename, ConfigType $configType = ConfigType::PHP): ConfigBase
	{
        // Load the config driver and file
        switch ($configType)
        {
            case ConfigType::PHP:
                return new PhpConfig($filename);
            case ConfigType::INI:
                return new IniConfig($filename);
            case ConfigType::XML:
                return new XmlConfig($filename);
            case ConfigType::JSON:
                throw new \Exception('To be implemented');
        }
	}
}