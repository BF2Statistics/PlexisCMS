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
use System\Presentation\Engine\VariableNode;

/**
 * Handler for {% set %} directives.
 * Processes variable assignment directives.
 */
class SetDirectiveHandler extends AbstractDirectiveHandler
{
    /**
     * Process the set directive and add nodes to the collection.
     *
     * Syntax: {% set variableName = expression %}
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
        // Expect: VariableStart (variable name wrapped by tokenizeCondition)
        $token = $stream->current();

        if ($token->type !== TokenType::VariableStart)
        {
            throw new ParsingException(
                "Expected variable name after 'set' directive at line {$token->line} but found '{$token->value}' instead."
            );
        }

        // Process the variable to extract the identifier
        // This will consume VariableStart, Identifier, and VariableEnd
        $this->processVariableAndAddNodes($stream, $collection);

        // Validate that the variable name is not a reserved word
        $varNameNode = $collection->getNodeAt(0);
        if ($varNameNode instanceof VariableNode)
        {
            $identifierNode = $varNameNode->getNodeAt(0);
            if ($identifierNode->token->type === TokenType::Identifier)
            {
                $variableName = strtolower($identifierNode->token->value);

                // Check against reserved words
                if (in_array($variableName, array_map('strtolower', Parser::$ReservedWords)))
                {
                    throw new ParsingException(
                        "Cannot use reserved word '{$identifierNode->token->value}' as variable name in set directive at line {$identifierNode->token->line}."
                    );
                }
            }
        }

        // Expect: SetOperator (=)
        $token = $stream->current();
        if ($token->type !== TokenType::SetOperator)
        {
            throw new ParsingException(
                "Expected '=' after variable name in set directive at line {$token->line} but found '{$token->value}' instead."
            );
        }

        // Add the assignment operator
        $collection->addNode($this->createNode($token));
        $stream->next();

        // Process the value expression until BlockEnd
        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // If the value is a dictionary, parse it as key-value pairs
            if ($token->type === TokenType::OpenBrace)
            {
                $this->parseKeyValuePairsAsNodes($stream, $collection);
                continue;
            }

            if ($token->type === TokenType::BlockEnd)
            {
                break;
            }

            // Handle variables in the expression
            if ($token->type === TokenType::VariableStart)
            {
                $this->processVariableAndAddNodes($stream, $collection);
            }
            else
            {
                // Add all other tokens (strings, numbers, operators, etc.)
                $collection->addNode($this->createNode($token));
                $stream->next();
            }
        }

        // Validate that we have at least a variable name, =, and a value
        if ($collection->count() < 3)
        {
            throw new ParsingException(
                "Incomplete set directive at line {$token->line}. Expected: {% set variable = value %}"
            );
        }

        // Expect BlockEnd
        $stream->expect(TokenType::BlockEnd);

        $this->validateAndPopStack(TokenType::Directive, $token, 'set');
    }
}