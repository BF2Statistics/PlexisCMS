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
 * Handles JSON configuration files by reading and writing
 * variables as key-value pairs.
 */
class JsonConfig extends ConfigBase
{
    /**
     * @inheritDoc
     */
    public function __construct(string $_filepath)
    {
        $this->validateAndSetPath($_filepath);
        $contents = File::ReadAllText($this->filePath);
        $data = json_decode($contents, true);

        if (!is_array($data))
            throw new \RuntimeException("Failed to parse JSON config file '{$this->filePath}'");

        $this->variables = $data;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     */
    public function save(): void
    {
        $json = json_encode($this->variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Check for errors during JSON encoding
        if ($json === false)
            throw new \RuntimeException("Failed to encode configuration to JSON: " . json_last_error_msg());

        // Copy the current config file for backup
        $this->backup();

        // Save the new configuration
        File::WriteAllText($this->filePath, $json);
    }
}