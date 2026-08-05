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
use System\IO\File;
use System\IO\FileNotFoundException;
use System\IO\DirectoryNotFoundException;

/**
 * Handles INI configuration files by reading and writing
 * variables as key-value pairs, with optional section support.
 */
class IniConfig extends ConfigBase
{
    /**
     * @inheritDoc
     * @throws FileNotFoundException
     */
    public function __construct(string $_filepath)
    {
        $this->validateAndSetPath($_filepath);
        $data = parse_ini_file($_filepath, true);

        if ($data === false)
            throw new \RuntimeException("Failed to parse INI config file '{$this->filePath}'");

        $this->variables = $data;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     */
    public function save(): void
    {
        $output = '';

        foreach ($this->variables as $key => $value)
        {
            if (is_array($value))
            {
                // This is a [section]
                $output .= "[{$key}]" . PHP_EOL;
                foreach ($value as $k => $v)
                {
                    $output .= "{$k} = " . $this->formatIniValue($v) . PHP_EOL;
                }
                $output .= PHP_EOL;
            }
            else
            {
                $output .= "{$key} = " . $this->formatIniValue($value) . PHP_EOL;
            }
        }

        // Copy the current config file for backup
        $this->backup();

        // Save the new configuration
        File::WriteAllText($this->filePath, $output);
    }

    /**
     * Formats a value for writing to an INI file.
     *
     * @param mixed $value The value to format
     * @return string The formatted INI value
     */
    private function formatIniValue(mixed $value): string
    {
        if (is_bool($value))
            return $value ? 'true' : 'false';

        if (is_numeric($value))
            return (string)$value;

        return '"' . addcslashes((string)$value, '"') . '"';
    }
}