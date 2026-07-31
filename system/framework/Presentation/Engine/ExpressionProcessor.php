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

use Exception;

/**
 * Processes complex expressions within template tags.
 *
 * This class handles the parsing and validation of expressions found within `{{ ... }}` blocks,
 * including support for ternary operators, null coalescing, logical operators, and method calls.
 */
class ExpressionProcessor
{
    /**
     * @var Parser The main parser instance used to create nodes.
     */
    private Parser $parser;

    /**
     * @var VariableProcessor The processor used for handling variable-specific tokens.
     */
    private VariableProcessor $variableProcessor;

    /**
     * ExpressionProcessor constructor.
     *
     * @param Parser $parser The main parser instance.
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
        $this->variableProcessor = $parser->variableProcessor;
    }

    /**
     * Processes an expression from the token stream and adds the resulting ExpressionNode to the collection.
     *
     * @param TokenStream $stream The stream of tokens to consume.
     * @param INodeCollection $collection The collection to add the final ExpressionNode to.
     *
     * @return void
     * @throws ParsingException If an unexpected token is encountered or the expression structure is invalid.
     */
    public function processExpressionAndAddNodes(TokenStream $stream, INodeCollection $collection): void
    {
        // Expect ExpressionStart
        $startToken = $stream->expect(TokenType::ExpressionStart);

        // Create the ExpressionNode
        $expressionNode = new ExpressionNode($startToken);

        // Process tokens until ExpressionEnd
        while (!$stream->isEnd())
        {
            $token = $stream->current();

            if ($token->type === TokenType::ExpressionEnd)
            {
                $stream->next(); // Consume ExpressionEnd
                break;
            }

            // Delegate variable handling to VariableProcessor
            if ($token->type === TokenType::VariableStart)
            {
                $this->variableProcessor->processVariableAndAddNodes($stream, $expressionNode);
            }
            // Handle expression-specific tokens (operators, literals, etc.)
            else if ($this->isValidExpressionToken($token))
            {
                $expressionNode->addNode($this->parser->createNode($token));
                $stream->next();
            }
            else
            {
                throw new ParsingException(
                    "Unexpected token '{$token->type->name}' in expression at line {$token->line}, column {$token->column}."
                );
            }
        }

        // Validate the expression structure
        $this->validateExpressionStructure($expressionNode);
        $collection->addNode($expressionNode);
    }

    /**
     * Checks if a given token is valid within an expression context.
     *
     * @param Token $token The token to validate.
     * @return bool True if the token type is allowed in an expression, false otherwise.
     */
    private function isValidExpressionToken(Token $token): bool
    {
        static $validTypes = [
            TokenType::Question,       // Ternary ? and Elvis ?:
            TokenType::NullCoalesce,   // Null coalescing ??
            TokenType::Colon,          // Ternary : and Elvis ?:
            TokenType::Operator,       // Comparison operators (==, !=, <, >, etc.)
            TokenType::LogicalOperator,// and, or, &&, ||
            TokenType::UnaryOperator,  // not, !
            TokenType::String,
            TokenType::Number,
            TokenType::Literal,        // true, false, null
            TokenType::OpenParen,
            TokenType::CloseParen,
        ];

        return in_array($token->type, $validTypes);
    }

    /**
     * Validates the structural integrity of an ExpressionNode.
     *
     * Ensures that:
     * - Ternary operators are correctly paired (? and :).
     * - Null coalescing operators have appropriate context.
     * - The expression is not empty.
     *
     * @param ExpressionNode $node The node to validate.
     * @return void
     * @throws ParsingException If the structure is invalid.
     */
    private function validateExpressionStructure(ExpressionNode $node): void
    {
        $nodes = $node->getNodes();
        $questionCount = 0;
        $colonCount = 0;
        $hasNullCoalesce = false;
        $isElvisOperator = false;

        for ($i = 0; $i < count($nodes); $i++)
        {
            $subNode = $nodes[$i];

            switch ($subNode->token->type)
            {
                case TokenType::Question:
                    $questionCount++;
                    // Check for Elvis operator (?:) - Question immediately followed by Colon
                    if (isset($nodes[$i + 1]) && $nodes[$i + 1]->token->type === TokenType::Colon)
                    {
                        $isElvisOperator = true;
                    }
                    break;

                case TokenType::Colon:
                    $colonCount++;
                    break;

                case TokenType::NullCoalesce:
                    $hasNullCoalesce = true;
                    break;
            }
        }

        // Validate ternary operator pairing (each ? needs a :)
        if ($questionCount !== $colonCount)
        {
            throw new ParsingException(
                "Mismatched ternary operator: found {$questionCount} '?' and {$colonCount} ':' at line {$node->token->line}."
            );
        }

        // Validate null coalescing has a right-hand value
        if ($hasNullCoalesce)
        {
            $this->validateNullCoalesceStructure($nodes);
        }

        if ($node->isEmpty())
        {
            throw new ParsingException("Empty expression at line {$node->token->line}.");
        }
    }

    /**
     * Validates that null coalescing operator has proper left and right operands.
     *
     * @param Node[] $nodes The sub-nodes within the expression to check.
     * @return void
     * @throws ParsingException If operands are missing for '??'.
     */
    private function validateNullCoalesceStructure(array $nodes): void
    {
        $foundNullCoalesce = false;
        $hasLeftOperand = false;
        $hasRightOperand = false;

        foreach ($nodes as $node)
        {
            if ($node->token->type === TokenType::NullCoalesce)
            {
                if (!$hasLeftOperand)
                {
                    throw new ParsingException(
                        "Null coalescing operator '??' missing left operand at line {$node->token->line}."
                    );
                }
                $foundNullCoalesce = true;
                $hasRightOperand = false; // Reset for right side
            }
            else if ($this->isValueToken($node->token) || $node instanceof VariableNode)
            {
                if (!$foundNullCoalesce)
                {
                    $hasLeftOperand = true;
                }
                else
                {
                    $hasRightOperand = true;
                }
            }
        }

        if ($foundNullCoalesce && !$hasRightOperand)
        {
            throw new ParsingException(
                "Null coalescing operator '??' missing right operand at line {$nodes[0]->token->line}."
            );
        }
    }

    /**
     * Determines if a token represents a constant value or an identifier.
     *
     * @param Token $token The token to check.
     * @return bool True if it's a string, number, literal, or identifier.
     */
    private function isValueToken(Token $token): bool
    {
        return in_array($token->type, [
            TokenType::String,
            TokenType::Number,
            TokenType::Literal,
            TokenType::Identifier,
        ]);
    }
}