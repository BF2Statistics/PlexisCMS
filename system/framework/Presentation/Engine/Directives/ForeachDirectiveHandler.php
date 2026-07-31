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
 * Handler for {% foreach %} directives.
 * Processes foreach loop structures with various formats.
 */
class ForeachDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the foreach directive and add nodes to the collection.
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
        // Define our static formats
        static $regularFormatTokens = [
            TokenType::VariableStart,
            TokenType::Keyword,
            TokenType::VariableStart,
        ];
        static $regularFormatOptionalTokens = [
            TokenType::DoubleArrow,
            TokenType::VariableStart
        ];
        static $altFormatTokens = [
            TokenType::VariableStart,
            TokenType::Comma,
            TokenType::VariableStart,
            TokenType::Keyword,
            TokenType::VariableStart,
        ];

        // Peak ahead and determine the users format chosen by the user
        $regularFormat = true;
        $foundExpectedFormat = false;
        $expectedTokens = [];

        // Loop until we find the end block for the foreach directive
        $offset = 0;
        while (true)
        {
            $token = $stream->peek($offset);
            if (!$token || $token->type == TokenType::BlockEnd) {
                break;
            }

            if ($token->type == TokenType::Comma)
            {
                if (!$foundExpectedFormat)
                {
                    $expectedTokens = $altFormatTokens;
                    $regularFormat = false;
                    $foundExpectedFormat = true;
                }
            }
            else if ($regularFormat && $token->type == TokenType::DoubleArrow)
            {
                $expectedTokens = array_merge($expectedTokens, $regularFormatOptionalTokens);
            }
            else if (!$foundExpectedFormat && $token->type == TokenType::Keyword)
            {
                $expectedTokens = $regularFormatTokens;
                $foundExpectedFormat = true;
            }

            $offset++;
        }

        // Context tokens (simplified for now)
        $contextTokens = []; 
        $keywordToken = null;

        // Loop through each expected token
        for ($i = 0; $i < count($expectedTokens); $i++)
        {
            $token = $stream->current();

            if ($expectedTokens[$i] !== $token->type)
            {
                $v = $token->type->value;
                $l = $token->line;
                $c = $token->column;
                throw new ParsingException(
                    "Unexpected token type '{$v}' at line {$l}, column {$c}. Expected {$expectedTokens[$i]->value}.",
                    $contextTokens
                );
            }

            // Enforce proper keyword
            if ($token->type == TokenType::Keyword)
            {
                if ($i === 3 && $token->value !== 'in')
                {
                    throw new ParsingException(
                        "'" . $token->value . "' is not a valid keyword when using this 'foreach' format. Expected 'in' at line {$token->line}.",
                        $contextTokens
                    );
                }

                $keywordToken = $token;
            }
            else if ($token->type == TokenType::DoubleArrow && $keywordToken?->value !== 'as' ?? true)
            {
                throw new ParsingException(
                    "'" . $keywordToken->value . "' is not a valid keyword when using this 'foreach' format. Expected 'as' at line {$token->line}.",
                    $contextTokens
                );
            }

            // Collapse variables into the VariableNode
            if ($token->type == TokenType::VariableStart)
            {
                /** @var VariableNode $varToken */
                $varToken = $this->createNode($token);
                $this->processVariableAndAddNodes($stream, $varToken);
                $collection->addNode($varToken);
            }
            else
            {
                $stream->next();
                $collection->addNode($this->createNode($token));
            }
        }

        // Add block end to the nodes list
        $collection->addNode($this->createNode($stream->expect(TokenType::BlockEnd)));

        // Process the body of the foreach block recursively until {% endforeach %}
        $this->processTokensRecursivelyUntil(['endforeach'], $stream, $collection);
    }
}
