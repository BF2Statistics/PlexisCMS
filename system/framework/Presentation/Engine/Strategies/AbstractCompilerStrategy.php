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

use System\Presentation\Engine\Compiler;
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;
use System\Presentation\Engine\TokenType;
use System\Presentation\Engine\VariableNode;

/**
 * Abstract base class for all compiler strategies.
 * Provides common functionality and defines the interface for compiling directives.
 */
abstract class AbstractCompilerStrategy
{
    /**
     * Reference to the Compiler instance
     */
    protected Compiler $compiler;

    /**
     * Constructor
     *
     * @param Compiler $compiler The compiler instance
     */
    public function __construct(Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Compile the directive node into PHP code.
     * This is the main method that each strategy must implement.
     *
     * @param DirectiveNode $node The directive node to compile
     *
     * @return string The compiled PHP code
     *
     * @throws CompilerException If the directive structure is invalid
     */
    abstract public function compile(DirectiveNode $node): string;

    /**
     * Compile a variable expression.
     *
     * @param VariableNode $node
     * @return string
     * @throws CompilerException
     */
    protected function compileVariableExpression(VariableNode $node): string
    {
        return $this->compiler->compileVariableExpression($node);
    }

    /**
     * Convert a literal value to PHP.
     *
     * @param string $value
     * @return string
     */
    protected function convertLiteral(string $value): string
    {
        return match (strtolower($value))
        {
            'true' => 'true',
            'false' => 'false',
            'null' => 'null',
            default => throw new CompilerException("Unknown literal value: {$value}"),
        };
    }

    /**
     * Validate that a directive has nodes.
     *
     * @param DirectiveNode $node
     * @param string $directiveName
     * @return void
     * @throws CompilerException
     */
    protected function validateDirectiveHasNodes(DirectiveNode $node, string $directiveName): void
    {
        if ($node->count() === 0) {
            throw new CompilerException("{$directiveName} directive requires arguments.");
        }
    }

    /**
     * Extract and compile the first expression from a directive node.
     *
     * @param DirectiveNode $node
     * @param string $directiveName
     * @return string
     * @throws CompilerException
     */
    protected function extractFirstExpression(DirectiveNode $node, string $directiveName): string
    {
        $this->validateDirectiveHasNodes($node, $directiveName);
        
        $firstNode = $node->getNodeAt(0);

        // Handle static string
        if ($firstNode->token->type === TokenType::String)
        {
            return $firstNode->token->value;
        }
        // Handle dynamic variable expression
        elseif ($firstNode instanceof VariableNode)
        {
            return $this->compileVariableExpression($firstNode);
        }
        else
        {
            throw new CompilerException("{$directiveName} directive expects a string or variable expression.");
        }
    }

    /**
     * Compile directive arguments into a PHP array expression.
     *
     * @param DirectiveNode $node
     * @param int $startIndex
     * @return array
     * @throws CompilerException
     */
    protected function compileArgumentExpressions(DirectiveNode $node, int $startIndex): array
    {
        $argExpressions = [];

        for ($i = $startIndex; $i < $node->count(); $i++)
        {
            $argNode = $node->getNodeAt($i);

            if ($argNode->token->type === TokenType::CloseParen) {
                break;
            }
            elseif ($argNode->token->type === TokenType::Comma) {
                continue;
            }
            elseif ($argNode instanceof VariableNode) {
                $argExpressions[] = $this->compileVariableExpression($argNode);
            }
            elseif ($argNode->token->type === TokenType::String) {
                $argExpressions[] = $argNode->token->value;
            }
            elseif ($argNode->token->type === TokenType::Number) {
                $argExpressions[] = $argNode->token->value;
            }
            elseif ($argNode->token->type === TokenType::Literal) {
                $argExpressions[] = $this->convertLiteral($argNode->token->value);
            }
        }

        return $argExpressions;
    }

    /**
     * Compiles an array of parameter nodes into a string representation.
     * This method uses the same logic as RunCompilerStrategy to ensure consistency.
     *
     * @param array $nodes An array of parameter nodes, where each node contains a token
     *                     representing a segment of the parsed expression.
     *
     * @return string The compiled string representation of the parameter nodes.
     */
    protected function compileParamNodes(array $nodes): string
    {
        if (empty($nodes)) {
            return '[]';
        }

        $expression = '';
        $nextIsKey = false;           // Track if next identifier is an array literal key
        $inVariable = false;          // Track if we're inside a variable reference
        $isFirstIdentifier = false;   // Track if next identifier is the first in a variable chain
        $needsClosingBracket = false; // Track if we need to close a bracket before next access

        foreach ($nodes as $node) {
            $token = $node->token;

            switch ($token->type) {
                case TokenType::OpenBrace:
                    $expression .= '[';
                    $nextIsKey = true;  // First item after { is a key
                    break;

                case TokenType::CloseBrace:
                    $expression .= ']';
                    $nextIsKey = false;
                    break;

                case TokenType::Colon:
                    $expression .= ' => ';
                    $nextIsKey = false;  // After colon comes a value
                    break;

                case TokenType::Comma:
                    $expression .= ', ';
                    $nextIsKey = true;  // After comma comes next key
                    break;

                case TokenType::VariableStart:
                    // Entering a variable reference chain
                    $inVariable = true;
                    $isFirstIdentifier = true;
                    $needsClosingBracket = false;
                    break;

                case TokenType::VariableEnd:
                    // Exiting a variable reference chain
                    if ($needsClosingBracket) {
                        $expression .= ']';
                        $needsClosingBracket = false;
                    }
                    $inVariable = false;
                    $isFirstIdentifier = false;
                    break;

                case TokenType::Identifier:
                    if ($nextIsKey) {
                        // It's an array literal key name, quote it
                        $expression .= "'{$token->value}'";
                    } elseif ($inVariable && $isFirstIdentifier) {
                        // First identifier in a variable chain gets $ prefix
                        $expression .= '$' . $token->value;
                        $isFirstIdentifier = false;
                        $needsClosingBracket = false; // First identifier doesn't need closing bracket
                    } elseif ($inVariable) {
                        // Subsequent identifiers in variable chain are array keys
                        // AccessOperator already added [' so we just add value']
                        $expression .= "{$token->value}']";
                        $needsClosingBracket = false; // We just closed it
                    } else {
                        // Standalone identifier (shouldn't happen in well-formed input)
                        $expression .= '$' . $token->value;
                    }
                    break;

                case TokenType::Number:
                    if ($inVariable && !$isFirstIdentifier) {
                        // Number in variable chain (after AccessOperator) - numeric array index
                        // AccessOperator already added [' but we need numeric index without quotes
                        // So we need to remove the trailing quote and add number with ]
                        $expression = rtrim($expression, "'");  // Remove the trailing quote from AccessOperator
                        $expression .= "{$token->value}]";
                        $needsClosingBracket = false; // We just closed it
                    } else {
                        // Number outside variable chain or as first element
                        $expression .= $token->value;
                    }
                    break;

                case TokenType::AccessOperator:
                    // Convert dot notation to PHP array access
                    $expression .= "['";
                    $needsClosingBracket = true;
                    break;

                case TokenType::String:
                    $expression .= $token->value;
                    break;

                case TokenType::Literal:
                    $expression .= strtolower($token->value);
                    break;

                case TokenType::OpenSquare:
                    $expression .= '[';
                    break;

                case TokenType::CloseSquare:
                    $expression .= ']';
                    break;

                case TokenType::OpenParen:
                    $expression .= '(';
                    break;

                case TokenType::CloseParen:
                    $expression .= ')';
                    break;

                case TokenType::MethodOperator:
                    $expression .= '->';
                    break;

                default:
                    // For any other token, just append its value
                    $expression .= $token->value;
                    break;
            }
        }

        return $expression;
    }
}
