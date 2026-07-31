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
 * Handler for {% extends %} directives.
 * Processes extends directives for layout inheritance.
 */
class ExtendsDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the extends directive and add nodes to the collection.
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
        // Define expected token sequence: String, BlockEnd
        static $allowedTypes = [
            TokenType::String,
            TokenType::VariableStart,
            TokenType::BlockEnd
        ];

        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // Check if token is allowed
            if (!in_array($token->type, $allowedTypes)) {
                throw new ParsingException(
                    "Unexpected token '{$token->type->value}' in extends directive at line {$token->line}. Expected String or BlockEnd."
                );
            }

            // Handle BlockEnd
            if ($token->type === TokenType::BlockEnd)
            {
                $this->validateAndPopStack(TokenType::Directive, $token, 'extends');
                $collection->addNode($this->createNode($token));
                $stream->next();
                break;
            }
            // Handle String (the layout name)
            elseif ($token->type === TokenType::String)
            {
                $collection->addNode($this->createNode($token));
                $stream->next();
            }
            // Handle Variable Expression (dynamic layout names)
            elseif ($token->type === TokenType::VariableStart)
            {
                /** @var VariableNode $varNode */
                $varNode = $this->createNode($token);
                $this->processVariableAndAddNodes($stream, $varNode);
                $collection->addNode($varNode);
            }
        }
    }
}
