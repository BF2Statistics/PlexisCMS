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

namespace System\Diagnostics;

/**
 * A utility class that provides methods for syntax highlighting PHP code and generating
 * contextual code snippets for displaying errors with line highlighting.
 */
class ErrorHighlighter
{
    /**
     * Maps specific PHP tokenizer constants to CSS classes.
     * PHP 8.4+ environment guaranteed.
     */
    private const TOKEN_MAP = [
        T_VARIABLE                  => 'var',
        T_CONSTANT_ENCAPSED_STRING  => 'str',
        T_ENCAPSED_AND_WHITESPACE   => 'str',
        T_STRING                    => 'func', // Often function names or classes
        T_COMMENT                   => 'comment',
        T_DOC_COMMENT               => 'comment',
        T_INLINE_HTML               => 'str',
        // PHP 8.0+ Namespaced names
        T_NAME_QUALIFIED            => 'func',
        T_NAME_FULLY_QUALIFIED      => 'func',
        T_NAME_RELATIVE             => 'func',
    ];

    /**
     * Generates the HTML for the Code Preview section.
     *
     * @param string $filePath The full path to the file causing the error.
     * @param int $errorLine The line number where the error occurred.
     * @param int $padding How many lines to show before and after.
     * @return array Returns ['lines' => HTML_STRING, 'numbers' => HTML_STRING]
     */
    public static function GetSnippet(string $filePath, int $errorLine, int $padding = 5): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['lines' => 'File not found or unreadable.', 'numbers' => ''];
        }

        $code = file_get_contents($filePath);
        $highlightedFull = self::HighlightSource($code);

        // Split into lines
        $lines = explode('<br />', $highlightedFull);
        $totalLines = count($lines);

        // Calculate range
        $startLine = max(1, $errorLine - $padding);
        $endLine   = min($totalLines, $errorLine + $padding);

        // Slice the array to get the specific window
        // array_slice offset is 0-based, so subtract 1 from startLine
        $length = $endLine - $startLine + 1;
        $slicedLines = array_slice($lines, $startLine - 1, $length);

        // Build the HTML Output
        $codeHtml = '';
        $numHtml = '';

        foreach ($slicedLines as $index => $lineContent)
        {
            $currentLineNum = $startLine + $index;
            $isActive = ($currentLineNum === $errorLine);

            // 1. Line Numbers Column
            $numClass = $isActive ? 'active-line-num' : '';
            $numHtml .= "<div class=\"{$numClass}\">{$currentLineNum}</div>";

            // 2. Code Column
            // If active, wrap in the highlight div
            if ($isActive) {
                $codeHtml .= "<div class=\"highlight-line\">{$lineContent}</div>";
            } else {
                // Ensure empty lines have height
                $content = empty($lineContent) ? '&nbsp;' : $lineContent;
                $codeHtml .= "<div>{$content}</div>";
            }
        }

        return [
            'lines' => $codeHtml,
            'numbers' => $numHtml
        ];
    }

    /**
     * Tokenizes PHP code and wraps it in template-specific spans.
     */
    public static function HighlightSource(string $source): string
    {
        $tokens = token_get_all($source);
        $output = '';

        foreach ($tokens as $token)
        {
            if (is_string($token))
            {
                // Simple 1-char tokens like ;, {, }, (, )
                $output .= htmlspecialchars($token);
            }
            else
            {
                list($id, $text) = $token;

                // Map token ID to our CSS class
                $cssClass = self::TOKEN_MAP[$id] ?? null;

                // Fallback for keywords not explicitly mapped above
                if (!$cssClass && self::IsKeyword($id)) {
                    $cssClass = 'kwd';
                }

                $escapedText = htmlspecialchars($text);

                if ($cssClass)
                {
                    // Split content by newlines to wrap each line individually in the span.
                    // This ensures multi-line tokens (like comments) maintain styling across line breaks.
                    $lines = preg_split('/\R/', $escapedText);
                    $wrappedLines = array_map(fn($line) => "<span class=\"{$cssClass}\">{$line}</span>", $lines);

                    // Rejoin with newlines (which will be converted to <br /> below)
                    $output .= implode("\n", $wrappedLines);
                }
                else
                {
                    $output .= $escapedText;
                }
            }
        }

        // Normalize newlines to <br /> for easy splitting
        return str_replace(["\r\n", "\r", "\n"], '<br />', $output);
    }

    /**
     * Checks if a token ID is a PHP keyword.
     * Contains standard PHP 8.4 keywords.
     */
    public static function IsKeyword($token_id): bool
    {
        static $keywords;

        if ($keywords === null)
        {
            $keywords = [
                // Standard Control Structures & Keywords
                T_ABSTRACT, T_ARRAY, T_AS, T_BREAK, T_CALLABLE, T_CASE,
                T_CATCH, T_CLASS, T_CLONE, T_CONST, T_CONTINUE, T_DECLARE,
                T_DEFAULT, T_DO, T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY,
                T_ENDDECLARE, T_ENDFOR, T_ENDFOREACH, T_ENDIF, T_ENDSWITCH,
                T_ENDWHILE, T_EVAL, T_EXIT, T_EXTENDS, T_FINAL, T_FINALLY,
                T_FOR, T_FOREACH, T_FUNCTION, T_GLOBAL, T_GOTO,
                T_HALT_COMPILER, T_IF, T_IMPLEMENTS, T_INCLUDE,
                T_INCLUDE_ONCE, T_INSTANCEOF, T_INSTEADOF, T_INTERFACE,
                T_ISSET, T_LIST, T_NAMESPACE, T_NEW, T_PRINT, T_PRIVATE,
                T_PROTECTED, T_PUBLIC, T_REQUIRE, T_REQUIRE_ONCE, T_RETURN,
                T_STATIC, T_SWITCH, T_THROW, T_TRAIT, T_TRY, T_UNSET,
                T_USE, T_VAR, T_WHILE, T_YIELD, T_YIELD_FROM,

                // PHP 8+ Additions (Hardcoded for PHP 8.4+)
                T_MATCH,        // PHP 8.0
                T_FN,           // PHP 7.4/8.0
                T_READONLY,     // PHP 8.1
                T_ENUM          // PHP 8.1
            ];
        }

        return in_array($token_id, $keywords, true);
    }
}