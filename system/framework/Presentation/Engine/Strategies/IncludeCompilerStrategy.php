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
 * Compiler strategy for {% include %} directives.
 * Compiles include directives for partial views.
 */
class IncludeCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Compile the include directive node into PHP code.
     *
     * Supports:
     * - {% include 'partial' %} -> renderInclude('partial', [], false)
     * - {% include 'partial' with { items: item.children } %} -> renderInclude('partial', ['items' => $item['children']], false)
     * - {% include 'partial' with { items: item.children } only %} -> renderInclude('partial', ['items' => $item['children']], true)
     *
     * @param DirectiveNode $node The directive node to compile
     *
     * @return string The compiled PHP code
     *
     * @throws CompilerException If the directive structure is invalid
     */
    public function compile(DirectiveNode $node): string
    {
        $this->validateDirectiveHasNodes($node, 'Include');

        // First node is the partial name (string or variable expression)
        $partialName = $this->extractFirstExpression($node, 'Include');

        // Separate parameter nodes from the 'only' keyword
        $paramNodes = [];
        $hasOnly = false;

        for ($i = 1; $i < $node->count(); $i++)
        {
            $currentNode = $node->getNodeAt($i);

            // Check if this is the 'only' keyword
            if ($currentNode->token->type === TokenType::Keyword &&
                strtolower($currentNode->token->value) === 'only')
            {
                $hasOnly = true;
                // Don't add 'only' to paramNodes, just set the flag
                continue;
            }

            // Add all other nodes as parameters
            $paramNodes[] = $currentNode;
        }

        // Compile parameters to PHP using the same logic as RunCompilerStrategy
        $compiledParams = $this->compileParamNodes($paramNodes);

        // Generate the renderInclude call
        $paramsArray = empty($compiledParams) ? '[]' : $compiledParams;
        $onlyFlag = $hasOnly ? 'true' : 'false';

        return sprintf(
            'echo $this->renderInclude(%s, %s, %s); ?>',
            $partialName,
            $paramsArray,
            $onlyFlag
        );
    }
}
