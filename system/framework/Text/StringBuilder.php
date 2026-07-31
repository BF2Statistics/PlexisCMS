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
namespace System\Text;

/**
 * Represents a mutable string of characters.
 *
 * Class StringBuilder
 * @package System\Text
 */
class StringBuilder
{
    protected string $string = '';

    /**
     * @var string
     */
    public string $newLineCharacter = PHP_EOL;

    /**
     * StringBuilder constructor.
     *
     * @param string $string
     */
    public function __construct(string $string = '')
    {
        $this->string = $this->getTypeValue($string);
    }

    /**
     * Appends the string representation of a specified object to this instance.
     *
     * @param bool|int|string $value The string to append.
     *
     * @return $this
     */
    public function append(bool|int|string $value): static
    {
        $this->string .= $this->getTypeValue($value);
        return $this;
    }

    /**
     * Appends the default line terminator, or a copy of a specified string and the default line terminator,
     * to the end of this instance.
     *
     * @param string|null $value
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     */
    public function appendLine(string $value = null): static
    {
        if (!empty($value))
            $this->string .= $this->getTypeValue($value);

        $this->string .= $this->newLineCharacter;
        return $this;
    }

    /**
     * Removes all characters from the current StringBuilder instance.
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     */
    public function clear(): static
    {
        $this->string = '';
        return $this;
    }

    /**
     * Inserts a string into this instance at the specified character position.
     *
     * @param int $index The position in this instance where insertion begins.
     * @param string $value The string to insert.
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     *
     * @throws \System\ArgumentOutOfRangeException if index less than zero or greater than the current length of this instance.
     */
    public function insert(int $index, string $value): static
    {
        // Ensure proper index
        if ($index < 0)
            throw new \System\ArgumentOutOfRangeException('Negative index passed');

        $this->string = substr_replace($this->string, $this->getTypeValue($value), $index, 0);
        return $this;
    }

    /**
     * Removes the specified range of characters from this instance.
     *
     * @param int $startIndex The zero-based position in this instance where removal begins.
     * @param int $length The number of characters to remove.
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     *
     * @throws \System\ArgumentOutOfRangeException
     */
    public function remove(int $startIndex, int $length): static
    {
        // Ensure proper index
        if ($startIndex < 0)
            throw new \System\ArgumentOutOfRangeException('Negative startIndex passed');

        // Ensure proper index
        if ($length < 0)
            throw new \System\ArgumentOutOfRangeException('Negative length passed');

        $this->string = substr_replace($this->string, '', $startIndex, $length);
        return $this;
    }

    /**
     * Replaces all occurrences of a specified string in this instance with another specified string.
     *
     * @param string $oldValue The string to replace.
     * @param string $newValue The string that replaces $oldValue, or null.
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     */
    public function replace(string $oldValue, string $newValue): static
    {
        $this->string = str_replace($oldValue, $newValue, $this->string);
        return $this;
    }

    /**
     * Cuts a string to the specified length
     *
     * @param int $maxLength
     * @param string $suffix
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     */
    public function subString(int $maxLength, string $suffix = '...'): static
    {
        $totalLength = strlen($this->string);
        if ($totalLength > $maxLength)
        {
            $suffixLen = strlen($suffix);
            $cutLength = max($maxLength - $suffixLen, 0);
            $this->string = ($cutLength == 0) ? '' : substr($this->string, 0, $cutLength) . $suffix;
        }

        return $this;
    }

    /**
     * Cuts a string to the specified length, while maintaining full words.
     *
     * @param int $maxLength
     * @param string $suffix
     *
     * @return StringBuilder A reference to this instance after the excise operation has completed.
     */
    public function subStringWords(int $maxLength, string $suffix = '...'): static
    {
        if (strlen($this->string) > $maxLength)
        {
            // Convert text into an array of words
            $words = preg_split('/\s/', $this->string);

            // Create buffer, and set length of the suffix
            $output = '';
            $suffixLen = strlen($suffix);
            $i = 0;

            while (true)
            {
                // Calculate length when adding the next word.
                // Add suffix length, as well as +1 for the space
                $length = strlen($output) + strlen($words[$i]) + $suffixLen + 1;
                if ($length > $maxLength)
                    break;

                $output .= " " . $words[$i];
                ++$i;
            }

            $output .= $suffix;
            $this->string = $output;
        }

        return $this;
    }

    /**
     * Returns the value of the type of the var passed.
     *
     * @param mixed $var Variable
     * @return string
     */
    protected function getTypeValue(mixed $var): string
    {
        if (is_string($var)) return $var;
        if (is_int($var)) return "{$var}";
        if (is_bool($var)) return ($var) ? "true" : "false";
        if (is_numeric($var) || is_float($var)) return "{$var}";
        if (is_object($var)) return strval($var);
        return "";
    }

    public function __toString()
    {
        return $this->string;
    }
}