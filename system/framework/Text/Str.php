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
 * Static helper utilities for common string operations.
 */
class Str
{
    /**
     * Truncates a string to a maximum length, attempting to keep whole words.
     *
     * If truncation is required, words are appended until adding the next word plus the suffix
     * would exceed `$maxLength`, then `$suffix` is appended.
     *
     * @param string $text      The input text to truncate.
     * @param int    $maxLength Maximum length of the returned string (in bytes).
     * @param string $suffix    Suffix appended when truncation occurs (default: "...").
     *
     * @return string The original text when within the limit, otherwise a word-boundary truncation with suffix.
     */
    public static function TruncateWords(string $text, int $maxLength, string $suffix = '...'): string
    {
        if (strlen($text) > $maxLength)
        {
            // Convert text into an array of words
            $words = preg_split('/\s/', $text);

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
        }
        else
        {
            $output = $text;
        }

        return $output;
    }

    /**
     * Truncates a string at the character level (not on word boundaries) and appends a suffix.
     *
     * The returned string length will not exceed `$maxLength` (in bytes), including the suffix,
     * provided `$maxLength >= strlen($suffix)`.
     *
     * @param string $text      The input text to truncate.
     * @param int    $maxLength Maximum length of the returned string (in bytes).
     * @param string $suffix    Suffix appended when truncation occurs (default: "...").
     *
     * @return string The original text when within the limit, otherwise a truncated string with suffix.
     */
    public static function Truncate(string $text, int $maxLength, string $suffix = '...'): string
    {
        if (strlen($text) <= $maxLength) return $text;

        $truncated = substr($text, 0, $maxLength - strlen($suffix));
        return $truncated . $suffix;
    }

    /**
     * Truncates a string in the middle and inserts a separator.
     *
     * Useful for long file names, paths, or URLs where both the start and end are meaningful.
     *
     * Example:
     * - "very_long_filename.txt" => "very_lo...ame.txt"
     *
     * @param string $text      The input text to truncate.
     * @param int    $maxLength Maximum length of the returned string (in bytes).
     * @param string $separator String inserted between the retained front and back parts (default: "...").
     *
     * @return string The original text when within the limit, otherwise the middle-truncated string.
     */
    public static function TruncateMiddle(string $text, int $maxLength, string $separator = '...'): string
    {
        if (strlen($text) <= $maxLength) return $text;

        $sepLen = strlen($separator);
        $charsToShow = $maxLength - $sepLen;
        $frontChars = (int)ceil($charsToShow / 2);
        $backChars = (int)floor($charsToShow / 2);

        return substr($text, 0, $frontChars) . $separator . substr($text, -$backChars);
    }

    /**
     * Repeats a string a specified number of times.
     *
     * @param string $text  The string to repeat.
     * @param int    $count Number of repetitions.
     *
     * @return string The repeated string.
     */
    public static function Repeat(string $text, int $count): string
    {
        return str_repeat($text, $count);
    }

    /**
     * Splits a string by a delimiter and removes empty entries.
     *
     * This is similar to C# `String.Split(..., StringSplitOptions.RemoveEmptyEntries)`.
     *
     * @param string $text      The input string to split.
     * @param string $delimiter The delimiter to split on (not a regex).
     *
     * @return array<int, string> List of non-empty segments, re-indexed from 0.
     */
    public static function SplitRemoveEmpty(string $text, string $delimiter): array
    {
        return array_values(array_filter(explode($delimiter, $text), fn($s) => $s !== ''));
    }

    /**
     * Converts a string to Title Case (each word capitalized) using UTF-8 rules.
     *
     * @param string $text The input text.
     *
     * @return string Title-cased text.
     */
    public static function ToTitleCase(string $text): string
    {
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Converts a camelCase or PascalCase identifier to snake_case.
     *
     * @param string $text The input identifier (camelCase/PascalCase).
     *
     * @return string The converted snake_case string.
     */
    public static function ToSnakeCase(string $text): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $text));
    }

    /**
     * Converts a snake_case or kebab-case identifier to camelCase.
     *
     * @param string $text The input identifier (snake_case or kebab-case).
     *
     * @return string The converted camelCase string.
     */
    public static function ToCamelCase(string $text): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $text))));
    }

    /**
     * Converts a snake_case or kebab-case identifier to PascalCase.
     *
     * @param string $text The input identifier (snake_case or kebab-case).
     *
     * @return string The converted PascalCase string.
     */
    public static function ToPascalCase(string $text): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $text)));
    }

    /**
     * Converts a camelCase, PascalCase, or snake_case identifier to kebab-case.
     *
     * Handles consecutive uppercase letters intelligently (e.g., XMLParser → xml-parser).
     *
     * @param string $text The input identifier.
     *
     * @return string The converted kebab-case string.
     */
    public static function ToKebabCase(string $text): string
    {
        // Replace underscores with hyphens first
        $text = str_replace('_', '-', $text);

        // Insert hyphens before uppercase letters that follow lowercase letters or numbers
        $text = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $text);

        // Insert hyphens before uppercase letters that are followed by lowercase letters
        // This handles cases like "XMLParser" -> "XML-Parser"
        $text = preg_replace('/([A-Z])([A-Z][a-z])/', '$1-$2', $text);

        // Convert to lowercase
        return strtolower($text);
    }

    /**
     * Pads a string on both sides to center it within a total width.
     *
     * If the input length is already greater than or equal to `$totalWidth`, the input is returned unchanged.
     *
     * @param string $text       The input text to pad.
     * @param int    $totalWidth The desired total width (in bytes).
     * @param string $padChar    Padding character(s) used to fill the space (default: space).
     *
     * @return string The centered, padded string.
     */
    public static function PadCenter(string $text, int $totalWidth, string $padChar = ' '): string
    {
        $textLen = strlen($text);
        if ($textLen >= $totalWidth) return $text;

        $padTotal = $totalWidth - $textLen;
        $padLeft = (int)floor($padTotal / 2);
        $padRight = $padTotal - $padLeft;

        return str_repeat($padChar, $padLeft) . $text . str_repeat($padChar, $padRight);
    }

    /**
     * Compares two strings case-insensitively.
     *
     * Similar to C# `String.Equals(a, b, StringComparison.OrdinalIgnoreCase)`.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return bool True if the strings are equal ignoring case; otherwise false.
     */
    public static function EqualsIgnoreCase(string $str1, string $str2): bool
    {
        return strcasecmp($str1, $str2) === 0;
    }

    /**
     * Checks whether a string matches a wildcard pattern.
     *
     * Supported wildcards:
     * - `*` matches zero or more characters
     * - `?` matches exactly one character
     *
     * Matching is case-insensitive.
     *
     * @param string $text    The input text to test.
     * @param string $pattern Wildcard pattern containing `*` and/or `?`.
     *
     * @return bool True if the text matches the pattern; otherwise false.
     */
    public static function MatchesPattern(string $text, string $pattern): bool
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace(['\*', '\?'], ['.*', '.'], $pattern);
        return (bool)preg_match('/^' . $pattern . '$/i', $text);
    }

    /**
     * Extracts the substring found between two delimiters.
     *
     * Returns `null` if the start delimiter is not found, or if the end delimiter does not appear
     * after the start delimiter.
     *
     * @param string $text  The input text to search within.
     * @param string $start The starting delimiter (excluded from result).
     * @param string $end   The ending delimiter (excluded from result).
     *
     * @return string|null The substring between delimiters, or null when not found.
     */
    public static function Between(string $text, string $start, string $end): ?string
    {
        $startPos = strpos($text, $start);
        if ($startPos === false) return null;

        $startPos += strlen($start);
        $endPos = strpos($text, $end, $startPos);

        if ($endPos === false) return null;

        return substr($text, $startPos, $endPos - $startPos);
    }

    /**
     * Removes all whitespace characters from a string.
     *
     * This removes spaces, tabs, newlines, and other Unicode whitespace matched by `\s`.
     *
     * @param string $text The input text.
     *
     * @return string The text with all whitespace removed.
     */
    public static function RemoveWhitespace(string $text): string
    {
        return preg_replace('/\s+/', '', $text);
    }

    /**
     * Normalizes whitespace by converting consecutive whitespace into a single space and trimming ends.
     *
     * @param string $text The input text.
     *
     * @return string The normalized text.
     */
    public static function NormalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Checks whether a string is null, empty, or contains only whitespace.
     *
     * @param string|null $text The input string (nullable).
     *
     * @return bool True when null/empty/whitespace-only; otherwise false.
     */
    public static function IsNullOrWhitespace(?string $text): bool
    {
        return $text === null || trim($text) === '';
    }

    /**
     * Checks whether a string consists only of alphabetic characters.
     *
     * Uses `ctype_alpha`, which is locale dependent and operates on bytes.
     *
     * @param string $text The input text.
     *
     * @return bool True if all characters are alphabetic and string is non-empty; otherwise false.
     */
    public static function IsAlpha(string $text): bool
    {
        return ctype_alpha($text);
    }

    /**
     * Checks whether a string consists only of alphanumeric characters.
     *
     * Uses `ctype_alnum`, which is locale dependent and operates on bytes.
     *
     * @param string $text The input text.
     *
     * @return bool True if all characters are alphanumeric and string is non-empty; otherwise false.
     */
    public static function IsAlphanumeric(string $text): bool
    {
        return ctype_alnum($text);
    }

    /**
     * Checks if a string contains only hexadecimal characters (0-9, A-F, a-f).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is valid hexadecimal; otherwise false.
     */
    public static function IsHexadecimal(string $text): bool
    {
        return ctype_xdigit($text) && $text !== '';
    }

    /**
     * Checks if a string is a valid email address format.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is a valid email format; otherwise false.
     */
    public static function IsEmail(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Checks if a string contains only numeric digits (0-9).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains only digits; otherwise false.
     */
    public static function IsNumeric(string $text): bool
    {
        return ctype_digit($text);
    }

    /**
     * Checks if a string is a valid URL format.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is a valid URL; otherwise false.
     */
    public static function IsUrl(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Checks if a string is a valid IP address (IPv4 or IPv6).
     *
     * @param string $text The input string.
     * @param bool $ipv4Only If true, only validates IPv4 addresses.
     *
     * @return bool True if the string is a valid IP address; otherwise false.
     */
    public static function IsIpAddress(string $text, bool $ipv4Only = false): bool
    {
        $flag = $ipv4Only ? FILTER_FLAG_IPV4 : 0;
        return filter_var($text, FILTER_VALIDATE_IP, $flag) !== false;
    }

    /**
     * Checks if a string is a valid UUID (v4 format).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is a valid UUID; otherwise false.
     */
    public static function IsUuid(string $text): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * Checks if a string is valid Base64 encoded data.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is valid Base64; otherwise false.
     */
    public static function IsBase64(string $text): bool
    {
        if ($text === '') return false;

        // Check if it matches Base64 character set
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $text)) {
            return false;
        }

        // Try to decode and re-encode to verify
        $decoded = base64_decode($text, true);
        return $decoded !== false && base64_encode($decoded) === $text;
    }

    /**
     * Checks if a string is a valid URL slug (lowercase letters, numbers, hyphens).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is a valid slug; otherwise false.
     */
    public static function IsSlug(string $text): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $text) === 1;
    }

    /**
     * Checks if a string is safe to use as a filename (no path traversal or special chars).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string is a safe filename; otherwise false.
     */
    public static function IsSafeFilename(string $text): bool
    {
        // Reject empty strings, path separators, and dangerous characters
        if ($text === '' || str_contains($text, '..')) {
            return false;
        }

        // Check for path separators and null bytes
        if (preg_match('/[\/\\\\:\*\?"<>\|]/', $text) || str_contains($text, "\0")) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a string contains HTML tags.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains HTML tags; otherwise false.
     */
    public static function ContainsHtml(string $text): bool
    {
        return $text !== strip_tags($text);
    }

    /**
     * Checks if a string contains only characters from a specified set.
     *
     * @param string $text The input string.
     * @param string $allowedChars String containing all allowed characters.
     *
     * @return bool True if the string contains only allowed characters; otherwise false.
     */
    public static function ContainsOnly(string $text, string $allowedChars): bool
    {
        return strspn($text, $allowedChars) === strlen($text);
    }

    /**
     * Checks if a string contains at least one character from a specified set.
     *
     * @param string $text The input string.
     * @param string $chars String containing characters to search for.
     *
     * @return bool True if any character from chars is found; otherwise false.
     */
    public static function ContainsAny(string $text, string $chars): bool
    {
        return strcspn($text, $chars) !== strlen($text);
    }

    /**
     * Checks if a string contains any whitespace characters.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains whitespace; otherwise false.
     */
    public static function ContainsWhitespace(string $text): bool
    {
        return preg_match('/\s/', $text) === 1;
    }

    /**
     * Checks if a string contains at least one numeric digit.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains a digit; otherwise false.
     */
    public static function ContainsDigit(string $text): bool
    {
        return preg_match('/\d/', $text) === 1;
    }

    /**
     * Checks if a string contains at least one alphabetic character.
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains a letter; otherwise false.
     */
    public static function ContainsLetter(string $text): bool
    {
        return preg_match('/[a-zA-Z]/', $text) === 1;
    }

    /**
     * Checks if a string contains special characters (non-alphanumeric).
     *
     * @param string $text The input string.
     *
     * @return bool True if the string contains special characters; otherwise false.
     */
    public static function ContainsSpecialChar(string $text): bool
    {
        return preg_match('/[^a-zA-Z0-9]/', $text) === 1;
    }


}