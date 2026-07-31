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
namespace System\Presentation\Engine\Directives;

use System\Presentation\Engine\INodeCollection;
use System\Presentation\Engine\ParsingException;
use System\Presentation\Engine\TokenStream;
use System\Presentation\Engine\TokenType;

/**
 * Handler for {% for %} directives.
 * Processes for loop structures with range and extended formats.
 */
class ForDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the for directive and add nodes to the collection.
     *
     * @param TokenStream $stream The array of tokens to process
     * @param INodeCollection $collection The collection to add nodes to
     *
     * @return void
     *
     * @throws ParsingException If the directive structure is invalid
     */
    public function handle(TokenStream $stream, INodeCollection $collection): void
    {
        // --- Validate the loop variable ---
        // Loop variable is an identifier, but Lexer wraps it in VariableStart/End.
        $loopVariable = $this->expectIdentifier($stream);
        $collection->addNode($this->createNode($loopVariable));

        // Peek at the next token for Format determination
        $nextToken = $stream->current();

        if ($nextToken && $nextToken->type === TokenType::Number)
        {
            // --- Format 1: `for i 0..10` ---
            $this->processForRangeFormat($stream, $collection);
        }
        elseif ($nextToken && $nextToken->type === TokenType::Operator && $nextToken->value === '=')
        {
            // --- Format 2: `for i = 0, i < 10, i++` ---
            $this->processExtendedForExpressionFormat($stream, $collection);
        }
        else
        {
            throw new ParsingException(
                "Expected range (0..10) or expression (i = 0, i < 10, i++) in 'for' directive at line {$nextToken?->line}."
            );
        }

        // Ensure we are at a closing block now!
        if ($stream->current()->type !== TokenType::BlockEnd) {
            throw new ParsingException("Missing %} at the end of 'for' directive at line {$stream->current()->line}.");
        }

        // Add block end to the nodes list
        $collection->addNode($this->createNode($stream->current()));
        $stream->next();

        // Process the body of the for block recursively until {% endfor %}
        $this->processTokensRecursivelyUntil(['endfor'], $stream, $collection);
    }

    /**
     * Expects an identifier, handling potential VariableStart/End wrappers.
     *
     * @param TokenStream $stream
     * @return \System\Presentation\Engine\Token
     */
    private function expectIdentifier(TokenStream $stream): \System\Presentation\Engine\Token
    {
        if ($stream->current() && $stream->current()->type === TokenType::VariableStart) {
            $stream->next(); // Consume VariableStart
            $token = $stream->expect(TokenType::Identifier);
            $stream->expect(TokenType::VariableEnd); // Consume VariableEnd
            return $token;
        }
        return $stream->expect(TokenType::Identifier);
    }

    /**
     * Process range format: for i 0..10
     */
    private function processForRangeFormat(TokenStream $stream, INodeCollection $collection): void
    {
        // Expect a number
        $startValue = $stream->expect(TokenType::Number);
        $collection->addNode($this->createNode($startValue));

        // Expect the range operator
        $rangeOperator = $stream->expect(TokenType::RangeOperator);
        $collection->addNode($this->createNode($rangeOperator));

        // Expect an end value
        $endValue = $stream->expect(TokenType::Number);
        $collection->addNode($this->createNode($endValue));
    }

    /**
     * Process extended format: for i = 0, i < 10, i++
     */
    private function processExtendedForExpressionFormat(TokenStream $stream, INodeCollection $collection): void
    {
        // --- Initial Assignment (e.g., i = 0) ---
        $assignmentOperator = $stream->current();
        if (!$assignmentOperator || $assignmentOperator->type !== TokenType::Operator || $assignmentOperator->value !== '=') {
            throw new ParsingException("Expected '=' for variable initialization at line {$assignmentOperator?->line}.");
        }
        $collection->addNode($this->createNode($assignmentOperator));
        $stream->next();

        // Expect a start value
        $startValue = $stream->expect(TokenType::Number);
        $collection->addNode($this->createNode($startValue));

        // Expect a comma
        $comma1 = $stream->expect(TokenType::Comma);
        $collection->addNode($this->createNode($comma1));

        // --- Loop Condition (e.g., i < 10) ---
        $loopVariable = $this->expectIdentifier($stream);
        $collection->addNode($this->createNode($loopVariable));

        // Expect a comparison operator
        $comparisonOperator = $stream->expect(TokenType::Operator);
        $collection->addNode($this->createNode($comparisonOperator));

        // Expect a condition value
        $conditionValue = $stream->expect(TokenType::Number);
        $collection->addNode($this->createNode($conditionValue));

        // Expect a comma
        $comma2 = $stream->expect(TokenType::Comma);
        $collection->addNode($this->createNode($comma2));

        // --- Update Expression (e.g., i++) ---
        $updateVariable = $this->expectIdentifier($stream);
        $collection->addNode($this->createNode($updateVariable));

        // Expect increment operator
        $incrementOperator = $stream->expect(TokenType::Increment);
        $collection->addNode($this->createNode($incrementOperator));
    }
}
