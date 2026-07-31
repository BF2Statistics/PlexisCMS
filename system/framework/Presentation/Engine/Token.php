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
namespace System\Presentation\Engine;

class Token
{
    public TokenType $type; // The type of the token (e.g., VARIABLE, TEXT, IF, etc.)
    public string $value; // The actual value of the token (e.g., 'user.name')
    public int $line; // The line number where this token was found
    public int $column; // The column number where this token starts

    /**
     * Token constructor.
     *
     * @param TokenType $type The token type.
     * @param string $value The token value (if applicable).
     * @param int $line The line number where the token was found.
     * @param int $column The column number where the token starts.
     */
    public function __construct(TokenType $type, string $value, int $line, int $column )
    {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
        $this->column = $column;
    }
}

