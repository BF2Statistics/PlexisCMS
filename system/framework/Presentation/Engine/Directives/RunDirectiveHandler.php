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
use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ParsingException;
use System\Presentation\Engine\TokenStream;
use System\Presentation\Engine\TokenType;

/**
 * Handles the 'run' directive parsing and processing within a token stream.
 * This directive defines a route name as a string and optionally accepts parameters
 * specified after the 'with' keyword. The directive ensures correctness by requiring
 * proper syntax and structure, and it self-closes.
 */
class RunDirectiveHandler extends AbstractDirectiveHandler
{
    protected Parser $parser;

    public function __construct(Parser $parser)
    {
        parent::__construct($parser);
    }

    /**
     * Handles the parsing and processing of the 'run' directive in the token stream.
     * Expects the next token after the 'run' directive to be a route name as a string.
     * Optionally, parses additional parameters following the 'with' keyword.
     * Ensures the directive block is properly closed and validates its structure.
     *
     * @param TokenStream $stream The token stream to parse and process the directive from.
     * @param INodeCollection $collection The collection to which parsed nodes will be added.
     * @return void
     * @throws ParsingException If the expected module route name or required tokens are not found.
     */
    public function handle(TokenStream $stream, INodeCollection $collection): void
    {
        // At this point, 'run' directive token has been consumed
        // Next token should be the widget name as a STRING (in quotes)
        $token = $stream->current();
        if ($token->type !== TokenType::String) {
            throw new ParsingException(
                "Expected widget route name (string in quotes) after 'run' directive at line {$token->line}"
            );
        }

        // Add the route name as a node (first child)
        $collection->addNode($this->createNode($token));
        $stream->next();

        // Check if 'with' keyword is present (optional)
        $token = $stream->current();

        if ($token->type === TokenType::Keyword && strtolower($token->value) === 'with') {
            $stream->next(); // consume 'with'

            // Parse parameters and add them as child nodes
            $this->parseKeyValuePairsAsNodes($stream, $collection);
        }

        // Expect BlockEnd
        $stream->expect(TokenType::BlockEnd);

        // Pop from stack (run is self-closing)
        $this->parser->validateAndPopStack(TokenType::Directive, $token, 'run');
    }
}