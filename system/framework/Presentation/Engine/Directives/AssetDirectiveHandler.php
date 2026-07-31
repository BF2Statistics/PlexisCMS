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
use System\Presentation\Engine\VariableNode;

/**
 * Handler for {% asset %} directives.
 * Processes asset directives for including CSS/JS files.
 */
class AssetDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the asset directive and add nodes to the collection.
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
        // Define the list of tokens that are allowed at the top level of an asset directive.
        static $allowedTopLevelTypes = [
            TokenType::VariableStart,
            TokenType::Concat,
            TokenType::String,
            TokenType::Number,
            TokenType::Comma,
            TokenType::Literal,
            TokenType::BlockEnd
        ];

        $parameterCount = 0; // Track which parameter we're on (0 = path, 1 = priority)

        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // 1. Check if the token is in our "allowed" list
            if (!in_array($token->type, $allowedTopLevelTypes)) {
                throw new ParsingException(
                    "Unexpected token '{$token->type->value}' in asset directive at line {$token->line}."
                );
            }

            // 2. Handle BlockEnd
            if ($token->type === TokenType::BlockEnd)
            {
                $this->validateAndPopStack(TokenType::Directive, $token, 'asset');
                $collection->addNode($this->createNode($token));
                $stream->next();
                break;
            }
            // 3. Handle Variable Expressions (for path parameter only)
            elseif ($token->type === TokenType::VariableStart)
            {
                if ($parameterCount > 0) {
                    throw new ParsingException(
                        "Priority parameter must be a number, not a variable expression at line {$token->line}."
                    );
                }

                /** @var VariableNode $varNode */
                $varNode = $this->createNode($token);
                $this->processVariableAndAddNodes($stream, $varNode);
                $collection->addNode($varNode);
                $parameterCount++;
            }
            // 4. Handle Numbers (could be priority parameter)
            elseif ($token->type === TokenType::Number)
            {
                // If this is the second parameter, it's the priority
                if ($parameterCount === 1) {
                    // Mark this as a priority parameter by adding a special marker node
                    $collection->addNode($this->createNode($token));
                    $stream->next();
                    $parameterCount++;
                } else {
                    // First parameter can also be a number (though unusual for asset paths)
                    $collection->addNode($this->createNode($token));
                    $stream->next();
                    $parameterCount++;
                }
            }
            // 5. Handle comma
            elseif ($token->type === TokenType::Comma)
            {
                // Comma separates parameters - just consume it
                $stream->next();
                // Don't increment parameterCount - the next token will do that
            }
            // 6. Handle Concat, Strings, and Literals
            else
            {
                $collection->addNode($this->createNode($token));
                $stream->next();

                // Only increment parameter count for non-concat tokens
                if ($token->type !== TokenType::Concat) {
                    $parameterCount++;
                }
            }
        }
    }
}
