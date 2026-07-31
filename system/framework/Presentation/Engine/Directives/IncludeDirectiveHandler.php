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
 * Handler for {% include %} directives.
 * Processes include directives for partial views with optional parameter passing.
 */
class IncludeDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the include directive and add nodes to the collection.
     * Expects a template name (string or variable) followed by optional 'with' keyword and parameters,
     * and an optional 'only' keyword.
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
        // At this point, 'include' directive token has been consumed
        // Next token should be the template name as a STRING (in quotes) or a VARIABLE
        $token = $stream->current();

        if ($token->type === TokenType::String)
        {
            // Static template name
            $collection->addNode($this->createNode($token));
            $stream->next();
        }
        elseif ($token->type === TokenType::VariableStart)
        {
            // Dynamic template name (variable expression)
            $this->processVariableAndAddNodes($stream, $collection);
        }
        else
        {
            throw new ParsingException(
                "Expected template name (string in quotes or variable) after 'include' directive at line {$token->line}"
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

        // Check if 'only' keyword is present (optional)
        $token = $stream->current();

        if ($token->type === TokenType::Keyword && strtolower($token->value) === 'only')
        {
            // Add the 'only' keyword as a node so the compiler can detect it
            $collection->addNode($this->createNode($token));
            $stream->next(); // consume 'only'
        }

        // Expect BlockEnd
        $stream->expect(TokenType::BlockEnd);

        // Pop from stack (include is self-closing)
        $this->parser->validateAndPopStack(TokenType::Directive, $token, 'include');
    }
}
