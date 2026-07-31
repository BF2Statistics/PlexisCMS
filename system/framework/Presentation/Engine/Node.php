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

/**
 * Represents a node structure with a type, value, and optional filters.
 */
class Node
{
    /**
     * @var Token
     */
    public readonly Token $token;

    /**
     * @var TokenType
     */
    public TokenType $type {
        get => $this->token->type;
    }

    /**
     * @var int
     */
    public int $line {
        get => $this->token->line;
    }

    /**
     * Initialize a node with a type, value, and optional filters.
     *
     * @param Token $token The Token
     */
    public function __construct(Token $token)
    {
        $this->token = $token;
    }
}

