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
namespace System\Presentation\Engine\Strategies;

use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;

/**
 * Compiler strategy for {% extends %} directives.
 * Compiles extends directives for layout inheritance.
 */
class ExtendsCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Compile the extends directive node into PHP code.
     *
     * @param DirectiveNode $node The directive node to compile
     *
     * @return string The compiled PHP code
     *
     * @throws CompilerException If the directive structure is invalid
     */
    public function compile(DirectiveNode $node): string
    {
        $expression = $this->extractFirstExpression($node, 'Extends');
        return sprintf('$this->setLayout(%s); ?>', $expression);
    }
}
