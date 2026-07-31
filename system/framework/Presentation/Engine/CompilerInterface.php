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

interface CompilerInterface
{
    /**
     * Compiles a `NodeCollection` into executable PHP code.
     *
     * @throws \Exception
     */
    public function compile(NodeCollection $nodes): string;

    /**
     * Compiles a single node.
     *
     * @throws CompilerException
     */
    public function compileNode(Node $node, bool $isPhpTagOpen): string;

    /**
     * Compiles a variable node into an echoable PHP snippet.
     *
     * @throws CompilerException
     */
    public function compileVariable(VariableNode $node): string;

    /**
     * Compiles a variable node into a PHP expression (no trailing semicolon).
     *
     * @throws CompilerException
     * @throws \Exception
     */
    public function compileVariableExpression(VariableNode $node): string;

    /**
     * Converts template literals (`true`, `false`, `null`) to PHP equivalents.
     *
     * @throws CompilerException
     */
    public function convertLiteral(string $value): string;

    /**
     * Registers a template filter.
     */
    public function addFilter(string $name, string $function): void;

    /**
     * Returns the directory where compiled templates are stored.
     */
    public function getCompiledDir(): string;
}