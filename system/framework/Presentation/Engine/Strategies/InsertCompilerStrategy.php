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
use System\Presentation\Engine\TokenType;
use System\Presentation\Engine\VariableNode;

/**
 * Compiler strategy for {% insert %} directives.
 * Compiles insert directives with optional arguments.
 */
class InsertCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Compile the insert directive node into PHP code.
     *
     * @param DirectiveNode $node The directive node to compile
     *
     * @return string The compiled PHP code
     *
     * @throws CompilerException If the directive structure is invalid
     */
    public function compile(DirectiveNode $node): string
    {
        $nodes = $node->getNodes();
        if (empty($nodes)) {
            throw new CompilerException("Insert directive missing callback name");
        }

        // First node is the callback name (string token)
        $identToken = $nodes[0]->token;
        if ($identToken->type !== TokenType::String) {
            throw new CompilerException("Insert directive callback name must be a string");
        }

        // Remove quotes from route name
        $identifier = trim($identToken->value, '\'"');

        // Remaining nodes are the parameters
        $paramNodes = array_slice($nodes, 1);

        // Compile parameters to PHP
        $paramsArray = $this->compileParamNodes($paramNodes);

        // Compile parameters using the same logic as IncludeCompilerStrategy
        if (!empty($paramNodes))
        {
            $compiledParams = $this->compileParamNodes($paramNodes);
            $paramsArray = empty($compiledParams) ? '[]' : $compiledParams;
            return sprintf('echo $this->renderInsert("%s", %s); ?>', $identifier, $paramsArray);
        }

        // No parameters - use original implementation (backward compatible)
        return sprintf('echo $this->renderInsert("%s"); ?>', $identifier);
    }
}
