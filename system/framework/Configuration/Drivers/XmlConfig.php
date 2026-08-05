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
 * Handles XML configuration files by reading and writing
 * variables as key-value pairs.
 */
class XmlConfig extends ConfigBase
{
    private string $rootElementName = 'config';

    /**
     * @inheritDoc
     */
    public function __construct(string $_filepath)
    {
        $this->validateAndSetPath($_filepath);
        $contents = File::ReadAllText($this->filePath);
        $xml = simplexml_load_string($contents);

        if ($xml === false)
            throw new \RuntimeException("Failed to parse XML config file '{$this->filePath}'");

        // Preserve the original root element name
        $this->rootElementName = $xml->getName();

        // Convert SimpleXMLElement to a nested associative array
        $this->variables = json_decode(json_encode($xml), true);
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     * @throws \Exception
     */
    public function save(): void
    {
        $xml = new \SimpleXMLElement('<' . $this->rootElementName . '/>');
        $this->arrayToXml($this->variables, $xml);

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        $output = $dom->saveXML();

        // Copy the current config file for backup
        $this->backup();

        // Save the new configuration
        File::WriteAllText($this->filePath, $output);
    }

    /**
     * Recursively converts an associative array into SimpleXMLElement nodes.
     *
     * @param array $data The data to convert
     * @param \SimpleXMLElement $xml The XML element to append to
     * @return void
     */
    private function arrayToXml(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value)
        {
            // Numeric keys get a generic element name
            if (is_numeric($key))
                $key = 'item';

            if (is_array($value))
            {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            }
            else
            {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}