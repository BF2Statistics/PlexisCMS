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
namespace System\Configuration\Drivers;
use System\Configuration\ConfigBase;

/**
 * IniConfig Class
 *
 * @author      Steven Wilson 
 * @package     System
 * @subpackage  Configuration
 */
class IniConfig extends ConfigBase
{
    /**
     * @throws \System\IO\FileNotFoundException
     */
    public function __construct($_filepath)
    {
        // make sure the file exists
        if(!file_exists($_filepath))
            throw new \System\IO\FileNotFoundException("Config file '{$_filepath}' does not exist!");

        // Set filepath variable
        $this->filePath = $_filepath;

        // Load the ini file
        $this->variables = parse_ini_file($_filepath, true);
        if($this->variables === false)
            throw new \Exception("Failed to parse ini config file.");
    }

    public function save(): bool
    {}
}