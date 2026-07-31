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
namespace System\Database;
use System\Database\Drivers\DatabaseDriver;
use System\IO\File;

/**
 * A universal class that parses SQL files into an array of statements
 * and supports MySQL, PostgreSQL, and MS SQL.
 *
 * Comments are stripped and statements are split into individual commands.
 *
 * @package System\Database
 */
class SqlFileParser
{
    /**
     * @var int Internal SQL string position
     */
    private int $position;

    /**
     * @var int The length of the internal $sql string
     */
    private int $length;

    /**
     * @var string The internal SQL string
     */
    private string $sql;

    /**
     * @var DatabaseDriver The database driver instance.
     */
    private DatabaseDriver $driver;

    /**
     * SqlFileParser constructor.
     *
     * @param string $filePath       The file path to the SQL file.
     * @param DatabaseDriver $driver The database driver instance.
     */
    public function __construct(string $filePath, DatabaseDriver $driver)
    {
        $this->driver = $driver;

        // Get SQL statements
        $contents = File::ReadAllText($filePath);

        // Prepare query for comment extraction!
        $this->length = strlen($contents);
        $this->sql = $contents;

        // Remove comments
        $this->sql = $this->removeComments();
        $this->length = strlen($this->sql);
    }

    /**
     * Fetches all SQL statements as an array. Each array element is a single SQL statement.
     *
     * @return string[]
     */
    public function getStatements(): array
    {
        $sqlList = [];
        $query = "";
        $delimiter = ';';
        $this->position = 0;

        while (!$this->eof()) {
            // Do not parse quoted strings
            if ($this->peek() === "'" || $this->peek() === "\"") {
                $term = $this->peek();
                $query .= $this->take();

                // Skip until we hit our quote term
                while ($this->peek() !== $term && !$this->eof()) {
                    if ($this->peek() === "\\" && $this->peek(1) === $term) {
                        $query .= $this->take();
                    }

                    $query .= $this->take();
                }

                $query .= $this->take();
                continue;
            }

            // Handle DELIMITER changes (MySQL-specific)
            if ($this->driver->getDriverDisplayName() == 'MySQL' && $this->takeIfNext('delimiter', true)) {
                $this->takeWhiteSpace();
                $delimiter = '';
                while (!$this->eof() && !$this->nextIsWhiteSpace()) {
                    $delimiter .= $this->take();
                }
                $this->takeWhiteSpace();
                continue;
            }

            // Check for end of statement
            if ($this->takeIfNext($delimiter)) {
                $sqlList[] = trim($query);
                $query = "";
                $this->takeWhiteSpace();
                continue;
            }

            $query .= $this->take();
        }

        if (strlen($query) > 0) {
            $sqlList[] = trim($query);
        }

        return $sqlList;
    }


    /**
     * Removes comments from the SQL code.
     *
     * @return string
     */
    protected function removeComments(): string
    {
        $this->position = 0;
        $clean = "";

        while (!$this->eof()) {
            // Handle single-line comments (-- for MySQL/PostgreSQL, --[space] for MSSQL)
            if ($this->takeIfNext('--')) {
                $this->takeWhile(fn($c) => $c !== "\n");
                continue;
            }

            // Handle multi-line comments (/* */ for all databases)
            if ($this->takeIfNext('/*')) {
                $this->takeUntil('*/');
                continue;
            }

            $clean .= $this->take();
        }

        return $clean;
    }

    /**
     * Helpers for SQL parsing
     */
    private function takeWhile(callable $condition): void
    {
        while (!$this->eof() && $condition($this->peek())) {
            $this->take();
        }
    }

    /**
     * Reads characters from the input until the specified end sequence is encountered.
     *
     * @param string $end The string sequence to stop reading at.
     * @return void
     */
    private function takeUntil(string $end): void
    {
        while (!$this->eof() && !$this->takeIfNext($end)) {
            $this->take();
        }
    }

    /**
     * Determines if the end of the input has been reached.
     *
     * @return bool
     */
    private function eof(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Retrieves a character from the specified position relative to the current position.
     *
     * @param int $offset The offset from the current position. Defaults to 0.
     * @return string The character at the specified position or an empty string if out of bounds.
     */
    private function peek(int $offset = 0): string
    {
        return $this->position + $offset < $this->length ? $this->sql[$this->position + $offset] : '';
    }

    /**
     * Checks if the next portion of the SQL string matches the given string and, if so, advances the position.
     *
     * @param string $string The string to check for at the current position.
     * @param bool $caseInsensitive Whether the comparison should be case-insensitive.
     * @return bool Returns true if the given string matches the next portion of the SQL string; otherwise, false.
     */
    private function takeIfNext(string $string, bool $caseInsensitive = false): bool
    {
        $length = strlen($string);
        $chunk = substr($this->sql, $this->position, $length);

        if (($caseInsensitive && strcasecmp($chunk, $string) === 0) || $chunk === $string) {
            $this->position += $length;
            return true;
        }

        return false;
    }

    /**
     * Checks if the next character in the input is a whitespace character.
     *
     * @return bool
     */
    private function nextIsWhiteSpace(): bool
    {
        return preg_match('/^\s/', $this->peek()) === 1;
    }

    /**
     * Consumes and skips over consecutive whitespace characters from the input.
     *
     * @return void
     */
    private function takeWhiteSpace(): void
    {
        while (!$this->eof() && $this->nextIsWhiteSpace()) {
            $this->take();
        }
    }

    /**
     * Retrieves the current character from the SQL code at the current position
     * and advances the position counter. Returns an empty string if the end of
     * the string is reached.
     *
     * @return string The current character or an empty string if at the end of the string.
     */
    private function take(): string
    {
        return $this->position < $this->length ? $this->sql[$this->position++] : '';
    }
}
