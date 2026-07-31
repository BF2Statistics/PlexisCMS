<?php
declare(strict_types=1);
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

interface ParserInterface
{
    /**
     * Parses a `TokenStream` into a `NodeCollection` (AST).
     *
     * @throws ParsingException
     * @throws \Exception
     */
    public function parse(TokenStream $stream): NodeCollection;

    /**
     * Creates a node instance appropriate for the token type.
     */
    public function createNode(Token $token): Node;

    /**
     * Processes a directive block and adds it to the provided collection.
     *
     * @throws ParsingException
     */
    public function processDirective(TokenStream $stream, INodeCollection $collection): void;

    /**
     * Processes variable-related tokens and adds nodes to the provided collection.
     *
     * @throws ParsingException
     * @throws \Exception
     */
    public function processVariableAndAddNodes(TokenStream $stream, INodeCollection $collection): void;

    /**
     * Validates and pops the last opening directive from the stack.
     *
     * @throws ParsingException
     */
    public function validateAndPopStack(TokenType $expectedType, Token $token, ?string $expectedValue = null): void;

    /**
     * Returns the current internal directive stack (for handlers).
     */
    public function getStack(): array;

    /**
     * Returns the configured maximum stack size.
     */
    public function getMaxStackSize(): int;
}