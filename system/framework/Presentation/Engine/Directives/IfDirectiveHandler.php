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
 * Handler for {% if %} directives.
 * Processes if/elseif/else/endif conditional structures.
 */
class IfDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the if directive and add nodes to the collection.
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
        $startToken = $stream->current(); // First token of condition

        if ($stream->current()->type == TokenType::IfStart)
        {
            throw new ParsingException("Cannot have a directive in an if condition at line {$startToken->line}.");
        }

        // Iterate through tokens until TokenType::BlockEnd is encountered
        while (!$stream->isEnd())
        {
            $token = $stream->current();

            if ($token->type === TokenType::VariableStart)
            {
                $this->processVariableAndAddNodes($stream, $collection);
                continue;
            }

            // Stop at BlockEnd
            if ($token->type === TokenType::BlockEnd) {
                break;
            }

            // Collect the token as part of the condition
            $newNode = $this->createNode($token);
            $collection->addNode($newNode);
            $stream->next();
        }

        // Check if the condition tokens were valid and not empty
        if ($collection->isEmpty())
        {
            throw new ParsingException(
                "Empty condition in if statement at line {$startToken->line}."
            );
        }

        // Validate structure of the condition tokens
        $this->validateConditionStructure($collection);

        // Ensure we stopped at a BlockEnd
        if ($stream->current()->type !== TokenType::BlockEnd)
        {
            throw new ParsingException(
                "Missing BLOCK_END at the end of if condition at line {$startToken->line}."
            );
        }

        // Move past the BlockEnd
        $collection->addNode($this->createNode($stream->current()));
        $stream->next();

        // Process the body of the if block recursively until {% endif %}
        $this->processTokensRecursivelyUntil(['endif'], $stream, $collection);
    }
}
