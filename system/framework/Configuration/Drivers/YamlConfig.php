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
 * Handles YAML configuration files by reading and writing
 * variables as key-value pairs.
 *
 * Uses the native php-yaml extension when available, otherwise
 * falls back to a pure-PHP parser/emitter that supports:
 *   - Key-value mappings & nested mappings
 *   - Sequences / lists
 *   - Quoted strings (single & double)
 *   - Comments & inline comments
 *   - Booleans, nulls, integers, floats
 *   - Block scalars (literal `|` and folded `>`, with chomping indicators `+`/`-`)
 *   - Flow collections (`{key: val}` and `[a, b, c]`)
 *   - Anchors (`&name`) and aliases (`*name`)
 *   - Tags (`!!str`, `!!int`, etc.)
 *   - Multi-document (`---` / `...`)
 */
class YamlConfig extends ConfigBase
{
    /**
     * Whether the native yaml extension is available
     */
    private static bool $hasNativeYaml;

    /**
     * Anchors registry used during parsing
     */
    private array $anchors = [];

    /**
     * @inheritDoc
     */
    public function __construct(string $_filepath)
    {
        $this->validateAndSetPath($_filepath);
        $contents = File::ReadAllText($this->filePath);

        self::$hasNativeYaml = function_exists('yaml_parse');
        if (self::$hasNativeYaml)
        {
            $data = yaml_parse($contents);
        }
        else
        {
            $data = self::parseYaml($contents);
        }

        if (!is_array($data))
            throw new \RuntimeException("Failed to parse YAML config file '{$this->filePath}'");

        $this->variables = $data;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     */
    public function save(): void
    {
        if (self::$hasNativeYaml)
        {
            $yaml = yaml_emit($this->variables, YAML_UTF8_ENCODING);
        }
        else
        {
            $yaml = self::emitYaml($this->variables);
        }

        // Copy the current config file for backup
        $this->backup();

        // Save the new configuration
        File::WriteAllText($this->filePath, $yaml);
    }

    // =========================================================================
    //  Pure-PHP YAML Parser
    // =========================================================================

    /**
     * Parses a YAML string into a PHP array.
     * Supports multi-document: returns the first document.
     *
     * @param string $input Raw YAML text
     * @return array
     */
    private function parseYaml(string $input): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $input));

        // Strip document markers — use first document only
        $docLines = [];
        $inDoc = false;
        $foundFirst = false;

        foreach ($lines as $line)
        {
            $trimmed = trim($line);

            if ($trimmed === '---')
            {
                if ($foundFirst)
                    break; // start of second document, stop
                $inDoc = true;
                $foundFirst = true;
                continue;
            }

            if ($trimmed === '...')
            {
                if ($inDoc)
                    break;
                continue;
            }

            $docLines[] = $line;
        }

        if (empty($docLines))
            $docLines = $lines;

        $result = [];
        $index = 0;
        $this->parseLines($docLines, $index, 0, $result);
        return $result;
    }

    /**
     * Recursively parses lines of YAML.
     */
    private function parseLines(array $lines, int &$index, int $baseIndent, array &$result): void
    {
        $lineCount = count($lines);

        while ($index < $lineCount)
        {
            $line = $lines[$index];
            $trimmed = ltrim($line);

            if ($trimmed === '' || $trimmed[0] === '#')
            {
                $index++;
                continue;
            }

            $indent = strlen($line) - strlen($trimmed);

            if ($indent < $baseIndent)
                return;

            // Sequence item
            if (str_starts_with($trimmed, '- ') || $trimmed === '-')
            {
                $itemText = $trimmed === '-' ? '' : substr($trimmed, 2);
                $itemText = trim($itemText);

                // Check for flow collection on the item
                if ($itemText !== '' && ($itemText[0] === '{' || $itemText[0] === '['))
                {
                    $result[] = self::parseFlowValue($itemText);
                    $index++;
                    continue;
                }

                if ($itemText !== '' && strpos($itemText, ':') !== false && !self::isQuotedString($itemText))
                {
                    $child = [];
                    $colonPos = self::findKeyColon($itemText);
                    if ($colonPos !== false)
                    {
                        $k = trim(substr($itemText, 0, $colonPos));
                        $v = trim(substr($itemText, $colonPos + 1));
                        $k = self::stripAnchorFromKey($k);
                        $k = self::unquote(self::stripTag($k));
                        $v = self::stripInlineComment($v);

                        // Check for anchor on value
                        $anchor = null;
                        $v = self::extractAnchor($v, $anchor);

                        // Check for alias
                        if (str_starts_with(trim($v), '*'))
                        {
                            $alias = substr(trim($v), 1);
                            $resolved = $this->anchors[$alias] ?? null;
                            $child[$k] = $resolved;
                        }
                        else
                        {
                            $child[$k] = self::castValue($v);
                        }

                        if ($anchor !== null)
                            $this->anchors[$anchor] = $child[$k];

                        $index++;

                        $childIndent = self::peekIndent($lines, $index);
                        if ($childIndent > $indent)
                        {
                            self::parseLines($lines, $index, $childIndent, $child);
                        }

                        $result[] = $child;
                    }
                    else
                    {
                        $result[] = self::castValue($itemText);
                        $index++;
                    }
                }
                else if ($itemText === '')
                {
                    $index++;
                    $childIndent = self::peekIndent($lines, $index);
                    if ($childIndent > $indent)
                    {
                        $nextTrimmed = ltrim($lines[$index] ?? '');
                        if (str_starts_with($nextTrimmed, '- '))
                        {
                            $child = [];
                            self::parseLines($lines, $index, $childIndent, $child);
                            $result[] = $child;
                        }
                        else
                        {
                            $child = [];
                            self::parseLines($lines, $index, $childIndent, $child);
                            $result[] = $child;
                        }
                    }
                    else
                    {
                        $result[] = null;
                    }
                }
                else
                {
                    $anchor = null;
                    $itemText = self::extractAnchor($itemText, $anchor);
                    $val = self::castValue(self::stripTag($itemText));
                    if ($anchor !== null)
                        $this->anchors[$anchor] = $val;
                    $result[] = $val;
                    $index++;
                }
                continue;
            }

            // Key: value
            $colonPos = self::findKeyColon($trimmed);
            if ($colonPos === false)
            {
                $index++;
                continue;
            }

            $key = trim(substr($trimmed, 0, $colonPos));
            $key = self::stripTag($key);

            // Extract anchor from key if present
            $keyAnchor = null;
            $key = self::extractAnchor($key, $keyAnchor);
            $key = self::unquote($key);

            $rest = trim(substr($trimmed, $colonPos + 1));
            $rest = self::stripInlineComment($rest);

            // Check for anchor on value side
            $valAnchor = null;
            $rest = self::extractAnchor($rest, $valAnchor);
            $rest = self::stripTag($rest);

            // Alias reference
            if (str_starts_with(trim($rest), '*'))
            {
                $alias = substr(trim($rest), 1);
                $result[$key] = $this->anchors[$alias] ?? null;
                $index++;
                continue;
            }

            // Block scalar: | or >
            if ($rest === '|' || $rest === '>' ||
                preg_match('/^[|>][+-]?$/', $rest) ||
                preg_match('/^[|>]\d[+-]?$/', $rest))
            {
                $index++;
                $blockValue = self::parseBlockScalar($lines, $index, $indent, $rest);
                $result[$key] = $blockValue;
                if ($valAnchor !== null)
                    $this->anchors[$valAnchor] = $blockValue;
                continue;
            }

            // Flow collection
            if ($rest !== '' && ($rest[0] === '{' || $rest[0] === '['))
            {
                $result[$key] = self::parseFlowValue($rest);
                if ($valAnchor !== null)
                    $this->anchors[$valAnchor] = $result[$key];
                $index++;
                continue;
            }

            if ($rest === '')
            {
                $index++;
                $childIndent = self::peekIndent($lines, $index);

                if ($childIndent > $indent)
                {
                    $child = [];
                    self::parseLines($lines, $index, $childIndent, $child);
                    $result[$key] = $child;
                    if ($valAnchor !== null)
                        $this->anchors[$valAnchor] = $result[$key];
                }
                else
                {
                    $result[$key] = null;
                }
            }
            else
            {
                $result[$key] = self::castValue($rest);
                if ($valAnchor !== null)
                    $this->anchors[$valAnchor] = $result[$key];
                $index++;
            }
        }
    }

    /**
     * Finds the colon that separates key from value, ignoring colons inside quotes.
     */
    private static function findKeyColon(string $line): int|false
    {
        $inSingle = false;
        $inDouble = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++)
        {
            $ch = $line[$i];

            if ($ch === "'" && !$inDouble)
                $inSingle = !$inSingle;
            else if ($ch === '"' && !$inSingle)
                $inDouble = !$inDouble;
            else if ($ch === ':' && !$inSingle && !$inDouble)
            {
                // Must be followed by space, end of string, or nothing
                if ($i + 1 >= $len || $line[$i + 1] === ' ')
                    return $i;
            }
        }

        return false;
    }

    /**
     * Parses a block scalar (literal `|` or folded `>`).
     */
    private static function parseBlockScalar(array $lines, int &$index, int $parentIndent, string $indicator): string
    {
        $fold = str_starts_with($indicator, '>');
        $chomp = 'clip'; // default
        if (str_contains($indicator, '+'))
            $chomp = 'keep';
        else if (str_contains($indicator, '-'))
            $chomp = 'strip';

        $blockLines = [];
        $blockIndent = null;
        $lineCount = count($lines);

        while ($index < $lineCount)
        {
            $line = $lines[$index];

            // Completely empty line
            if (trim($line) === '')
            {
                $blockLines[] = '';
                $index++;
                continue;
            }

            $lineIndent = strlen($line) - strlen(ltrim($line));

            if ($blockIndent === null)
                $blockIndent = $lineIndent;

            if ($lineIndent < $blockIndent)
                break;

            $blockLines[] = substr($line, $blockIndent);
            $index++;
        }

        // Remove trailing empty lines for indent detection
        if ($fold)
        {
            $text = '';
            $prevEmpty = false;
            foreach ($blockLines as $bl)
            {
                if ($bl === '')
                {
                    $text .= "\n";
                    $prevEmpty = true;
                }
                else
                {
                    if ($prevEmpty)
                    {
                        $text .= $bl;
                        $prevEmpty = false;
                    }
                    else
                    {
                        if ($text !== '')
                            $text .= ' ';
                        $text .= $bl;
                    }
                }
            }
        }
        else
        {
            $text = implode("\n", $blockLines);
        }

        // Apply chomping
        if ($chomp === 'strip')
            $text = rtrim($text, "\n");
        else if ($chomp === 'clip')
            $text = rtrim($text, "\n") . "\n";
        // 'keep' leaves trailing newlines as-is

        return $text;
    }

    /**
     * Parses a flow collection value (inline `{...}` or `[...]`).
     */
    private function parseFlowValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '{}')
            return [];
        if ($value === '[]')
            return [];

        // Flow mapping {key: val, key2: val2}
        if ($value[0] === '{' && str_ends_with($value, '}'))
        {
            $inner = substr($value, 1, -1);
            $parts = self::splitFlowItems($inner);
            $result = [];

            foreach ($parts as $part)
            {
                $part = trim($part);
                $colonPos = self::findKeyColon($part);
                if ($colonPos !== false)
                {
                    $k = self::unquote(trim(substr($part, 0, $colonPos)));
                    $v = trim(substr($part, $colonPos + 1));
                    $result[$k] = self::parseFlowValue($v);
                }
            }

            return $result;
        }

        // Flow sequence [a, b, c]
        if ($value[0] === '[' && str_ends_with($value, ']'))
        {
            $inner = substr($value, 1, -1);
            $parts = self::splitFlowItems($inner);
            $result = [];

            foreach ($parts as $part)
            {
                $result[] = self::parseFlowValue(trim($part));
            }

            return $result;
        }

        // Scalar
        return $this->castValue($value);
    }

    /**
     * Splits flow collection items by comma, respecting nested braces/brackets and quotes.
     */
    private static function splitFlowItems(string $input): array
    {
        $items = [];
        $depth = 0;
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++)
        {
            $ch = $input[$i];

            if ($ch === "'" && !$inDouble)
                $inSingle = !$inSingle;
            else if ($ch === '"' && !$inSingle)
                $inDouble = !$inDouble;

            if (!$inSingle && !$inDouble)
            {
                if ($ch === '{' || $ch === '[')
                    $depth++;
                else if ($ch === '}' || $ch === ']')
                    $depth--;
                else if ($ch === ',' && $depth === 0)
                {
                    $items[] = $current;
                    $current = '';
                    continue;
                }
            }

            $current .= $ch;
        }

        if (trim($current) !== '')
            $items[] = $current;

        return $items;
    }

    /**
     * Extracts an anchor (&name) from a string, returning the cleaned string.
     */
    private static function extractAnchor(string $str, ?string &$anchor): string
    {
        $str = trim($str);
        if (preg_match('/&(\S+)/', $str, $m))
        {
            $anchor = $m[1];
            $str = trim(str_replace('&' . $m[1], '', $str));
        }
        return $str;
    }

    /**
     * Strips a YAML tag (!!type) from a string.
     */
    private static function stripTag(string $str): string
    {
        return trim(preg_replace('/^!!\S+\s*/', '', trim($str)));
    }

    /**
     * Strips anchor notation from a key.
     */
    private static function stripAnchorFromKey(string $key): string
    {
        return trim(preg_replace('/&\S+/', '', $key));
    }

    /**
     * Checks if a string looks like a quoted value.
     */
    private static function isQuotedString(string $str): bool
    {
        $str = trim($str);
        return (str_starts_with($str, '"') && str_ends_with($str, '"')) ||
            (str_starts_with($str, "'") && str_ends_with($str, "'"));
    }

    /**
     * Peeks ahead to find the indentation of the next non-empty, non-comment line.
     */
    private static function peekIndent(array $lines, int $index): int
    {
        $count = count($lines);
        while ($index < $count)
        {
            $t = ltrim($lines[$index]);
            if ($t !== '' && $t[0] !== '#')
                return strlen($lines[$index]) - strlen($t);
            $index++;
        }
        return 0;
    }

    /**
     * Casts a raw YAML scalar string to the appropriate PHP type.
     */
    private function castValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '')
            return '';

        // Quoted strings
        if (str_starts_with($value, '"') && str_ends_with($value, '"'))
            return stripcslashes(substr($value, 1, -1));

        if (str_starts_with($value, "'") && str_ends_with($value, "'"))
            return str_replace("''", "'", substr($value, 1, -1));

        // Alias
        if (str_starts_with($value, '*'))
        {
            $alias = substr($value, 1);
            return $this->anchors[$alias] ?? null;
        }

        // Strip tag for casting
        $value = self::stripTag($value);

        $lower = strtolower($value);

        if ($lower === 'null' || $lower === '~')
            return null;

        if ($lower === 'true' || $lower === 'yes' || $lower === 'on')
            return true;
        if ($lower === 'false' || $lower === 'no' || $lower === 'off')
            return false;

        if (preg_match('/^-?\d+$/', $value))
            return (int)$value;

        if (preg_match('/^-?\d+\.\d+$/', $value))
            return (float)$value;

        // Octal
        if (preg_match('/^0o[0-7]+$/i', $value))
            return intval(substr($value, 2), 8);

        // Hex
        if (preg_match('/^0x[0-9a-fA-F]+$/', $value))
            return hexdec($value);

        // Infinity / NaN
        if ($lower === '.inf' || $lower === '+.inf')
            return INF;
        if ($lower === '-.inf')
            return -INF;
        if ($lower === '.nan')
            return NAN;

        return $value;
    }

    /**
     * Removes surrounding quotes from a string if present.
     */
    private static function unquote(string $str): string
    {
        $str = trim($str);
        if (
            (str_starts_with($str, '"') && str_ends_with($str, '"')) ||
            (str_starts_with($str, "'") && str_ends_with($str, "'"))
        ) {
            return substr($str, 1, -1);
        }
        return $str;
    }

    /**
     * Strips an inline comment from a value, respecting quoted strings.
     */
    private static function stripInlineComment(string $value): string
    {
        $value = trim($value);
        if ($value === '')
            return '';

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return $value;
        }

        $pos = strpos($value, ' #');
        if ($pos !== false)
            return trim(substr($value, 0, $pos));

        return $value;
    }

    // =========================================================================
    //  Pure-PHP YAML Emitter
    // =========================================================================

    /**
     * Converts a PHP array to a YAML string.
     */
    private static function emitYaml(array $data, int $indent = 0): string
    {
        $output = '';

        if ($indent === 0)
            $output .= "---\n";

        $prefix = str_repeat('  ', $indent);

        if (array_is_list($data))
        {
            foreach ($data as $item)
            {
                if (is_array($item))
                {
                    if (empty($item))
                    {
                        $output .= $prefix . "- []\n";
                        continue;
                    }

                    $keys = array_keys($item);
                    $firstKey = $keys[0];
                    $firstVal = $item[$firstKey];

                    if (is_array($firstVal))
                    {
                        $output .= $prefix . '- ' . self::emitKey($firstKey) . ":\n";
                        $output .= self::emitYaml($firstVal, $indent + 2);
                    }
                    else
                    {
                        $output .= $prefix . '- ' . self::emitKey($firstKey) . ': ' . self::emitScalar($firstVal) . "\n";
                    }

                    for ($i = 1; $i < count($keys); $i++)
                    {
                        $k = $keys[$i];
                        $v = $item[$k];
                        if (is_array($v))
                        {
                            $output .= $prefix . '  ' . self::emitKey($k) . ":\n";
                            $output .= self::emitYaml($v, $indent + 2);
                        }
                        else
                        {
                            $output .= $prefix . '  ' . self::emitKey($k) . ': ' . self::emitScalar($v) . "\n";
                        }
                    }
                }
                else
                {
                    $output .= $prefix . '- ' . self::emitScalar($item) . "\n";
                }
            }
        }
        else
        {
            foreach ($data as $key => $value)
            {
                if (is_array($value))
                {
                    if (empty($value))
                    {
                        $output .= $prefix . self::emitKey($key) . ": {}\n";
                    }
                    else
                    {
                        $output .= $prefix . self::emitKey($key) . ":\n";
                        $output .= self::emitYaml($value, $indent + 1);
                    }
                }
                else if (is_string($value) && str_contains($value, "\n"))
                {
                    // Use literal block scalar for multiline strings
                    $output .= $prefix . self::emitKey($key) . ": |\n";
                    $blockLines = explode("\n", $value);
                    $blockPrefix = str_repeat('  ', $indent + 1);
                    foreach ($blockLines as $bl)
                    {
                        $output .= $blockPrefix . $bl . "\n";
                    }
                }
                else
                {
                    $output .= $prefix . self::emitKey($key) . ': ' . self::emitScalar($value) . "\n";
                }
            }
        }

        return $output;
    }

    /**
     * Formats a key for YAML output, quoting if necessary.
     */
    private static function emitKey(string|int $key): string
    {
        $key = (string)$key;

        if (preg_match('/[:{}\[\],&*?|>!%@`#\-]/', $key) || is_numeric($key) || $key === '')
            return '"' . addcslashes($key, '"\\') . '"';

        return $key;
    }

    /**
     * Formats a scalar value for YAML output.
     */
    private static function emitScalar(mixed $value): string
    {
        if ($value === null)
            return 'null';

        if (is_bool($value))
            return $value ? 'true' : 'false';

        if (is_int($value) || is_float($value))
        {
            if (is_nan($value)) return '.nan';
            if (is_infinite($value)) return $value > 0 ? '.inf' : '-.inf';
            return (string)$value;
        }

        $str = (string)$value;

        if (
            $str === '' ||
            $str === 'null' || $str === 'true' || $str === 'false' ||
            $str === 'yes' || $str === 'no' || $str === 'on' || $str === 'off' ||
            $str === '~' ||
            is_numeric($str) ||
            preg_match('/[:{}\[\],&*?|>!%@`#]/', $str)
        ) {
            return '"' . addcslashes($str, '"\\') . '"';
        }

        return $str;
    }
}