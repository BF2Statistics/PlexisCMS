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
 * Compiler strategy for {% set %} directives.
 * Compiles variable assignment directives into PHP code.
 */
class SetCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Compile the set directive node into PHP code.
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

        if (count($nodes) < 3)
        {
            throw new CompilerException("Set directive requires at least a variable name and value");
        }

        // First node should be the variable name (VariableNode)
        $varNameNode = $nodes[0];
        if (!($varNameNode instanceof VariableNode))
        {
            throw new CompilerException("Set directive must start with a variable name");
        }

        // Extract the identifier from the VariableNode
        $identifierNode = $varNameNode->getNodeAt(0);
        if ($identifierNode->token->type !== TokenType::Identifier)
        {
            throw new CompilerException("Set directive variable name must be an identifier");
        }

        $variableName = $identifierNode->token->value;

        // Second node should be the assignment operator (=)
        $operatorNode = $nodes[1];
        if ($operatorNode->token->type !== TokenType::SetOperator)
        {
            throw new CompilerException("Set directive requires '=' operator");
        }

        // Get all nodes after the = operator
        $valueNodes = array_slice($nodes, 2);

        // Compile the value expression
        $valueExpression = $this->compileValueExpression($valueNodes);

        // Generate PHP code that:
        // 1. Assigns the value to a local variable
        // 2. Updates the current iteration context so nested scopes can access it
        return sprintf(
            '$%s = %s; $this->currentIterationContext[\'%s\'] = $%s; ?>',
            $variableName,
            trim($valueExpression),
            $variableName,
            $variableName
        );
    }

    /**
     * Compiles the value expression for a set directive.
     * Handles special operators like "is defined", "is empty", etc.
     *
     * @param array $nodes The nodes representing the value expression
     * @return string The compiled PHP expression
     * @throws CompilerException
     */
    private function compileValueExpression(array $nodes): string
    {
        static $altOperators = ['is', 'is not'];
        static $altLiterals = ['empty', 'odd', 'even', 'defined'];

        $expression = '';
        $i = 0;
        $count = count($nodes);

        while ($i < $count)
        {
            $part = $nodes[$i];

            // Check for Dictionary (OpenBrace)
            if ($part->token->type === TokenType::OpenBrace)
            {
                // Use compileParamNodes for dictionary syntax
                return trim($this->compileParamNodes($nodes), "=");
            }

            if ($part instanceof VariableNode)
            {
                // Check if next token is "is" operator with special literal
                if (isset($nodes[$i + 1]) && isset($nodes[$i + 2]))
                {
                    $nextToken = $nodes[$i + 1]->token;
                    $literalToken = $nodes[$i + 2]->token;

                    if ($nextToken->type === TokenType::Operator &&
                        in_array(strtolower($nextToken->value), $altOperators) &&
                        $literalToken->type === TokenType::Literal &&
                        in_array($literalToken->value, $altLiterals))
                    {
                        // Compile the variable
                        $varExpression = $this->compileVariableExpression($part);
                        $operator = strtolower($nextToken->value);
                        $isNot = str_ends_with($operator, "not");

                        // Convert to PHP function
                        $expression .= match ($literalToken->value) {
                            "empty" => ($isNot) ? "!empty({$varExpression})" : "empty({$varExpression})",
                            "odd" => ($isNot) ? "{$varExpression} % 2 == 0" : "{$varExpression} % 2 != 0",
                            "even" => ($isNot) ? "{$varExpression} % 2 != 0" : "{$varExpression} % 2 == 0",
                            "defined" => ($isNot) ? "!isset({$varExpression})" : "isset({$varExpression})",
                        };

                        // Skip the operator and literal tokens
                        $i += 3;
                        continue;
                    }
                }

                // Normal variable compilation
                $expression .= $this->compileVariableExpression($part);
            }
            else
            {
                $token = $part->token;
                switch ($token->type)
                {
                    case TokenType::String:
                    case TokenType::Number:
                        $expression .= $token->value;
                        break;

                    case TokenType::Literal:
                        // Only convert basic literals (true, false, null)
                        // Special literals (defined, empty, odd, even) are handled above
                        if (in_array(strtolower($token->value), ['true', 'false', 'null'])) {
                            $expression .= $this->convertLiteral($token->value);
                        } else {
                            throw new CompilerException(
                                "Literal '{$token->value}' must be used with 'is' operator (e.g., 'variable is {$token->value}')"
                            );
                        }
                        break;

                    case TokenType::Operator:
                        // Skip "is" and "is not" operators when they're part of special literal patterns
                        // (they're handled in the VariableNode section above)
                        if (!in_array(strtolower($token->value), $altOperators)) {
                            $expression .= " {$token->value} ";
                        }
                        break;

                    case TokenType::LogicalOperator:
                        $expression .= " {$token->value} ";
                        break;

                    case TokenType::UnaryOperator:
                        $expression .= '!';
                        break;

                    case TokenType::Question:
                        $expression .= ' ? ';
                        break;

                    case TokenType::Colon:
                        $expression .= ' : ';
                        break;

                    case TokenType::NullCoalesce:
                        $expression .= ' ?? ';
                        break;

                    case TokenType::OpenParen:
                        $expression .= '(';
                        break;

                    case TokenType::CloseParen:
                        $expression .= ')';
                        break;

                    case TokenType::OpenBrace:
                    case TokenType::OpenSquare:
                        $expression .= '[';
                        break;

                    case TokenType::CloseBrace:
                    case TokenType::CloseSquare:
                        $expression .= ']';
                        break;

                    case TokenType::Comma:
                        $expression .= ', ';
                        break;

                    case TokenType::DoubleArrow:
                        $expression .= ' => ';
                        break;

                    case TokenType::Concat:
                        $expression .= ' . ';
                        break;

                    case TokenType::Identifier:
                        // Standalone identifier (convert to variable)
                        $expression .= '$' . $token->value;
                        break;

                    default:
                        throw new CompilerException("Unhandled token type in set directive: {$token->type->value}");
                }
            }

            $i++;
        }

        return $expression;
    }
}