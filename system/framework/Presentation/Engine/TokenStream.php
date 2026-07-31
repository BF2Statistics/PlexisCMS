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
 * Represents a stream of tokens that allows sequential access to tokens and provides utility
 * methods for token manipulation and traversal.
 */
class TokenStream
{
    /**
     * @var Token[]
     */
    private array $tokens;

    /**
     * @var int The current position in the stream.
     */
    private int $position = 0;

    /**
     * @var int The total number of tokens in the stream.
     */
    private int $count;

    /**
     * Constructor method to initialize the object with a set of tokens.
     *
     * @param array $tokens An array of tokens to be stored in the object.
     *
     * @return void
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->count = count($tokens);
    }

    /**
     * Get the current token.
     */
    public function current(): ?Token
    {
        return $this->tokens[$this->position] ?? null;
    }

    /**
     * Get the current token and move the pointer forward.
     */
    public function next(): ?Token
    {
        $token = $this->current();
        $this->position++;
        return $token;
    }

    /**
     * Look ahead (or behind) without moving the pointer.
     * peek(1) gets the next token.
     */
    public function peek(int $offset = 1): ?Token
    {
        return $this->tokens[$this->position + $offset] ?? null;
    }

    /**
     * Move the pointer forward by n steps.
     */
    public function skip(int $steps = 1): void
    {
        $this->position += $steps;
    }

    /**
     * Check if we have reached the end of the stream.
     */
    public function isEnd(): bool
    {
        return $this->position >= $this->count;
    }

    /**
     * Get the current position index.
     */
    public function getPosition(): int
    {
        return $this->position;
    }
    
    /**
     * Set the position manually (useful for lookahead rewinds if needed)
     */
    public function seek(int $position): void 
    {
        $this->position = $position;
    }

    /**
     * Expect a specific token type or throw an exception.
     * Consumes the token if successful.
     * @throws ParsingException
     */
    public function expect(TokenType $type): Token
    {
        $token = $this->current();
        if (!$token || $token->type !== $type) {
            $found = $token ? $token->type->value : 'End of Stream';
            throw new ParsingException("Expected token {$type->value}, found {$found} on line {$token->line}, column {$token->column}.");
        }
        $this->next();
        return $token;
    }
}
