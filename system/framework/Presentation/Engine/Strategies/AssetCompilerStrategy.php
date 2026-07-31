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
 * Compiler strategy for {% asset %} directives.
 * Compiles asset directives for including CSS/JS files.
 */
class AssetCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Compile the asset directive node into PHP code.
     *
     * @param DirectiveNode $node The directive node to compile
     *
     * @return string The compiled PHP code
     *
     * @throws CompilerException If the directive structure is invalid
     */
    public function compile(DirectiveNode $node): string
    {
        $parameters = [];
        $currentParam = '';

        foreach ($node->getNodes() as $subNode)
        {
            if ($subNode instanceof VariableNode)
            {
                $currentParam .= $this->compileVariableExpression($subNode);
            }
            else
            {
                $token = $subNode->token;
                switch ($token->type)
                {
                    case TokenType::Concat:
                        $currentParam .= ' . ';
                        break;

                    case TokenType::String:
                        $currentParam .= $token->value;
                        break;

                    case TokenType::Number:
                        // If we already have a parameter, this number is a new parameter (priority)
                        if (!empty($currentParam)) {
                            $parameters[] = $currentParam;
                            $currentParam = $token->value;
                        } else {
                            $currentParam .= $token->value;
                        }
                        break;

                    case TokenType::Literal:
                        $currentParam .= $this->convertLiteral($token->value);
                        break;

                    case TokenType::BlockEnd:
                        // Finalize current parameter
                        if (!empty($currentParam)) {
                            $parameters[] = $currentParam;
                            $currentParam = ''; // ← ADD THIS to prevent double-add
                        }
                        break;

                    case TokenType::Comma:
                        // Comma marks the end of the current parameter
                        if (!empty($currentParam)) {
                            $parameters[] = $currentParam;
                            $currentParam = '';
                        }
                        break;

                    default:
                        throw new CompilerException("Unexpected token in asset directive: {$token->type->value}");
                }
            }
        }

        // Finalize if we didn't hit BlockEnd (only if currentParam is not empty)
        if (!empty($currentParam)) {
            $parameters[] = $currentParam;
        }

        // Build the PHP call with 1 or 2 parameters
        $paramString = implode(', ', $parameters);
        return '$this->includeAsset(' . $paramString . '); ?>';
    }
}
