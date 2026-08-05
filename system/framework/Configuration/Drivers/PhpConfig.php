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
use System\IO\DirectoryNotFoundException;
use System\IO\File;
use System\IO\FileNotFoundException;
use System\ObjectDisposedException;

/**
 * Handles PHP configuration files by reading and writing
 * variables as key-value pairs.
 *
 * This class provides the functionality to load configuration data
 * from a PHP file and save changes back to it. It supports arrays and
 * basic data types for configuration values. A backup of the
 * configuration file is created before saving changes.
 *
 * @file        system/framework/Configuration/Drivers/PhpConfig.php
 * @copyright   2013, Plexis Dev Team
 * @license     GNU GPL v3
 */
class PhpConfig extends ConfigBase
{
    private bool $arrayFormat = false;

    /**
     * @inheritDoc
     */
    public function __construct(string $_filepath)
    {
        $this->validateAndSetPath($_filepath);
        unset($_filepath);

        // Get defined variables
        $returned = include( $this->filePath );
        if (is_array($returned))
        {
            $this->arrayFormat = true;
            $vars = $returned;
        }
        else
        {
            unset($returned);
            $vars = get_defined_vars();
        }

        // Add the variables to the $data[$name] array
        foreach ($vars as $key => $val)
        {
            if ($key != 'this')
                $this->variables[$key] = $val;
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     */
    public function save(): void
    {
        $cfg = "<?php\n";

        // Get each of the new set variables
        if ($this->arrayFormat)
        {
            $cfg .= "return " . var_export($this->variables, true) . ";\n";
        }
        else
        {
            foreach ($this->variables as $key => $val)
            {
                $cfg .= "\${$key} = " . $this->formatValue($val) . ";\n";
            }
        }

        // Copy the current config file for backup
        $this->backup();

        // Save the new configuration
        File::WriteAllText($this->filePath, $cfg);
    }

    /**
     * Formats a given value into a string representation suitable for output or storage.
     *
     * @param mixed $value The value to be formatted. It can be of any type including array, null, boolean, numeric, or string.
     *
     * @return string The formatted string representation of the provided value.
     */
    protected function formatValue(mixed $value): string
    {
        return var_export($value, true);

        /*
        if (is_array($value))
        {
            // Quote string values when imploding
            $formattedArray = array_map(function ($item) {
                return $this->formatValue($item);
            }, $value);

            return "[" . implode(', ', $formattedArray) . "]";
        }
        if (is_null($value))
        {
            return "null";
        }
        else if (is_bool($value))
        {
            return ($value) ? 'true' : 'false';
        }
        else if (is_numeric($value))
        {
            return "{$value}";
        }
        else
        {
            return "'" . addslashes($value) . "'";
        }
        */
    }
}