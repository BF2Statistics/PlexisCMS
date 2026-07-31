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
 * Handles all variable-related parsing operations.
 * Responsible for collecting, validating, and processing variable tokens.
 */
class VariableProcessor
{
    /**
     * Reference to the Parser instance for creating nodes
     */
    private Parser $parser;

    /**
     * Constructor
     *
     * @param Parser $parser The parser instance
     */
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Processes variable-related tokens, validates their structure, and adds the corresponding nodes to the collection.
     *
     * @param TokenStream $stream The list of tokens to process.
     * @param INodeCollection $collection The node collection to which the processed nodes will be added.
     *
     * @return void
     *
     * @throws ParsingException
     * @throws Exception
     */
    public function processVariableAndAddNodes(TokenStream $stream, INodeCollection $collection): void
    {
        // Collect variable tokens, including optional parentheses
        $variableTokens = $this->collectVariableTokens($stream);

        // Validate the variable structure
        $this->validateVariableStructure($variableTokens);

        // Ensure $this is not used as a variable name
        $this->validateFirstIdentifier($variableTokens);

        // When this method is called from processTokensRecursivelyUntil(), the $collection is
        // already a sub VariableNode of the upper collection
        if ($collection instanceof VariableNode)
        {
            // Process recursively
            $this->processVariableNodeRecursively($variableTokens, $collection);
        }
        else
        {
            // Create a new VariableNode
            $mainToken = $variableTokens[0];
            $col = new VariableNode($mainToken, $this->extractFiltersFromToken($mainToken));

            // We must skip the initial variable node
            if ($mainToken->type == TokenType::VariableStart)
                array_shift($variableTokens);

            // Process recursively
            $this->processVariableNodeRecursively($variableTokens, $col);
            $collection->addNode($col);
        }
    }

    /**
     * Processes a list of variable tokens recursively, constructing nodes,
     * applying filters, and adding them to the specified node collection.
     *
     * @param array $variableTokens The list of tokens representing variables to be processed.
     * @param INodeCollection $collection The collection to which the generated nodes will be added.
     *
     * @return void
     */
    private function processVariableNodeRecursively(array $variableTokens, INodeCollection $collection): void
    {
        for ($i = 0; $i < count($variableTokens); $i++)
        {
            $token = $variableTokens[$i];
            if (is_array($token))
            {
                $mainToken = array_shift($token); // always do this!
                $subNode = new VariableNode($mainToken, $this->extractFiltersFromToken($mainToken));
                $this->processVariableNodeRecursively($token, $subNode);
                $collection->addNode($subNode);
            }
            else
            {
                $collection->addNode($this->parser->createNode($token));
            }
        }

        // add filters
        $collection->filters = $this->extractFiltersFromToken($collection->token);
    }

    /**
     * Collects tokens that define a variable structure from the provided tokens array.
     *
     * @param TokenStream $stream The array of tokens to analyze for variable structures.
     *
     * @return array The collected tokens that represent the variable.
     *
     * @throws ParsingException If an unexpected token is encountered or if the variable structure is invalid.
     */
    private function collectVariableTokens(TokenStream $stream): array
    {
        $variableTokens = [];
        static $allowedTypes = [
            TokenType::Identifier,
            TokenType::AccessOperator,
            TokenType::MethodOperator,
            TokenType::Number,
            TokenType::Operator,
            TokenType::String,
            TokenType::Comma,
            TokenType::OpenSquare,
            TokenType::CloseSquare,
            TokenType::OpenParen,
            TokenType::CloseParen,
            TokenType::Literal,
            TokenType::Concat
        ];

        // Add top level variable start
        $variableTokens[] = $stream->expect(TokenType::VariableStart);

        while (!$stream->isEnd())
        {
            $token = $stream->current();

            if ($token->type == TokenType::VariableStart)
            {
                $variableTokens[] = $this->collectVariableTokens($stream);
            }
            else if ($token->type == TokenType::VariableEnd)
            {
                // Add variable end
                $variableTokens[] = $token;
                $stream->next();
                break;
            }
            // Valid tokens for variable structures
            else if (in_array($token->type, $allowedTypes))
            {
                $variableTokens[] = $token;
                $stream->next();
            }
            // Invalid token in variable structure
            else
            {
                throw new ParsingException(
                    "Unexpected token '{$token->type->name}' in variable structure at line {$token->line}, column {$token->column}."
                );
            }
        }

        if (empty($variableTokens)) {
            $pos = $stream->getPosition();
            throw new ParsingException("Invalid variable structure found at index $pos.");
        }

        return $variableTokens;
    }

    /**
     * Extracts filters from the provided token value.
     *
     * Parses the token value to identify filters and their arguments.
     * Filters are separated by `|`, and arguments (if any) are enclosed in parentheses.
     *
     * @param Token $token The token containing the value to extract filters from.
     *
     * @return array An array of filters, where each filter is represented as an associative array
     *               with keys 'name' (filter name) and 'args' (filter arguments).
     */
    private function extractFiltersFromToken(Token $token): array
    {
        // Split the token at each `|` into parts
        $parts = explode('|', $token->value);

        // The first part is the variable name
        array_shift($parts);

        // Parse filters
        $filters = [];
        foreach ($parts as $part)
        {
            $part = trim($part);
            if (str_contains($part, '('))
            {
                // Filter with arguments, e.g., `filter(arg1, arg2)`
                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*?)\((.*?)\)$/', $part, $matches))
                {
                    $filters[] = [
                        'name' => $matches[1], // Filter name
                        'args' => array_map('trim', explode(',', $matches[2])), // Arguments (split by comma)
                    ];
                }
            }
            else
            {
                // Simple filter without arguments
                $filters[] = [
                    'name' => $part,
                    'args' => [],
                ];
            }
        }

        return $filters;
    }

    /**
     * Validates the structure of a variable or a tokenized expression.
     *
     * @param Token[] $tokens An array of tokens representing the variable structure or expression.
     *
     * @throws Exception If the structure is invalid, with a descriptive error message.
     */
    private function validateVariableStructure(array $tokens): string
    {
        $stack = []; // Stack for matching open/close symbols
        $previousToken = null; // Previous token for sequence validation
        $variableString = ''; // Reconstructed string for debugging
        $loopIndex = 0;

        foreach ($tokens as $token)
        {
            if (is_array($token))
            {
                $variableString .= $this->validateVariableStructure($token);
                continue;
            }

            $variableString .= $token->value;

            switch ($token->type)
            {
                case TokenType::VariableStart:
                    if ($loopIndex != 0)
                        throw new ParsingException("Unexpected {{ at line {$token->line}, column {$token->column}");
                    break;

                case TokenType::VariableEnd:
                    //if ($loopIndex < count($tokens) - 1)
                        //throw new ParsingException("Unexpected }} end at line {$token->line}, column {$token->column}");
                    break;

                case TokenType::Identifier:
                    // Valid token, no need to validate deeply here
                    break;

                case TokenType::AccessOperator:
                    // Dot must be followed by an identifier
                    if (empty($previousToken) || ($previousToken->type !== TokenType::Identifier && $previousToken->type !== TokenType::CloseSquare)) {
                        throw new ParsingException("Unexpected '.' operator at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::MethodOperator:
                    // Arrow must be followed by an identifier (method or property)
                    if (empty($previousToken) || ($previousToken->type !== TokenType::Identifier && $previousToken->type !== TokenType::CloseSquare)) {
                        throw new ParsingException("Unexpected '->' operator at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::OpenSquare:
                    // Push to stack to match closing bracket
                    if (empty($previousToken) || ($previousToken->type !== TokenType::Identifier && $previousToken->type !== TokenType::CloseSquare)) {
                        throw new ParsingException("Unexpected '[' at line {$token->line}, column {$token->column}");
                    }
                    $stack[] = TokenType::OpenSquare;
                    break;

                case TokenType::CloseSquare:
                    // Ensure a matching '[' exists in the stack
                    if (empty($stack) || array_pop($stack) !== TokenType::OpenSquare) {
                        throw new ParsingException("Unmatched ']' at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::OpenParen:
                    // Push to stack to match closing parenthesis
                    if (empty($previousToken) || $previousToken->type !== TokenType::Identifier) {
                        throw new ParsingException("Unexpected '(' at line {$token->line}, column {$token->column}");
                    }
                    if ($tokens[$loopIndex - 2]->type !== TokenType::MethodOperator) {
                        throw new ParsingException("Unexpected '(' at line {$token->line}, column {$token->column}");
                    }
                    $stack[] = TokenType::OpenParen;
                    break;

                case TokenType::CloseParen:
                    // Ensure a matching '(' exists in the stack
                    if (empty($stack) || array_pop($stack) !== TokenType::OpenParen) {
                        throw new ParsingException("Unmatched ')' at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::Number:
                    // Strings/numbers/literals are valid within method arguments or array indices
                    if (empty($previousToken) || ($previousToken->type !== TokenType::OpenSquare && $previousToken->type !== TokenType::OpenParen && $previousToken->type !== TokenType::Comma)) {
                        throw new ParsingException("Unexpected number '{$token->value}' at line {$token->line}, column {$token->column}");
                    }
                    break;
                case TokenType::String:
                case TokenType::Literal:
                    // Strings/numbers/literals are valid within method arguments or array indices
                    if (empty($previousToken) || ($previousToken->type !== TokenType::OpenParen && $previousToken->type !== TokenType::Comma)) {
                        throw new ParsingException("Unexpected literal '{$token->value}' at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::Comma:
                    // Commas are valid only within method arguments (inside parentheses)
                    if (empty($stack) || end($stack) !== TokenType::OpenParen) {
                        throw new ParsingException("Unexpected ',' outside of method arguments at line {$token->line}, column {$token->column}");
                    }
                    break;

                case TokenType::Concat:
                    // Concat must be preceded by something that evaluates to a value
                    $validPredecessors = [
                        TokenType::Identifier,
                        TokenType::String,
                        TokenType::Number,
                        TokenType::Literal,
                        TokenType::CloseParen,
                        TokenType::CloseSquare
                    ];
                    if (empty($previousToken) || !in_array($previousToken->type, $validPredecessors)) {
                        throw new ParsingException("Unexpected '~' operator at line {$token->line}, column {$token->column}");
                    }
                    break;

                default:
                    throw new ParsingException("Unexpected token type '{$token->type->value}' at line {$token->line}, column {$token->column}");
            }

            $previousToken = $token; // Update the previous token
            $loopIndex++;
        }

        // Ensure no unclosed brackets or parentheses remain
        if (!empty($stack)) {
            throw new ParsingException("Unclosed brackets or parentheses in variable: {$variableString}");
        }

        return $variableString;
    }

    /**
     * Validates that the first identifier in a variable expression is not 'this'.
     *
     * @param Token[] $tokens An array of tokens representing the variable structure.
     *
     * @throws ParsingException If the first identifier is 'this'.
     */
    private function validateFirstIdentifier(array $tokens): void
    {
        // Find the first Identifier token
        foreach ($tokens as $token)
        {
            if (is_array($token))
            {
                // Recursively check nested variable arrays
                $this->validateFirstIdentifier($token);
                continue;
            }

            if ($token->type === TokenType::Identifier)
            {
                $identifierValue = strtolower($token->value);

                // Only block 'this' - other reserved words are allowed in variable expressions
                if ($identifierValue === 'this')
                {
                    throw new ParsingException(
                        "Cannot access reserved variable 'this' at line {$token->line}, column {$token->column}. " .
                        "The 'this' variable is restricted for security reasons."
                    );
                }

                // Only validate the FIRST identifier, then stop
                return;
            }
        }
    }
}
