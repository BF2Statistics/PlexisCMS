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
 * Handler for {% insert %} directives.
 * Processes insert directives with optional arguments.
 */
class InsertDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the insert directive and add nodes to the collection.
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
        // At this point, 'insert' directive token has been consumed
        // Next token should be the template name as a STRING (in quotes) or a VARIABLE
        $token = $stream->current();

        if ($token->type === TokenType::String)
        {
            // Static template name
            $collection->addNode($this->createNode($token));
            $stream->next();
        }
        else
        {
            throw new ParsingException(
                "Expected callback name (string in quotes) after 'insert' directive at line {$token->line}"
            );
        }

        // Check if 'with' keyword is present (optional)
        $token = $stream->current();
        if ($token->type === TokenType::Keyword && strtolower($token->value) === 'with')
        {
            $stream->next(); // consume 'with'

            // Parse parameters and add them as child nodes
            $this->parseKeyValuePairsAsNodes($stream, $collection);
        }

        // Expect BlockEnd
        $stream->expect(TokenType::BlockEnd);

        $this->validateAndPopStack(TokenType::Directive, $token, 'insert');
    }
}
