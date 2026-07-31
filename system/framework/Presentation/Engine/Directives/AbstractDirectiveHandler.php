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
use System\Presentation\Engine\Node;
use System\Presentation\Engine\NodeCollection;
use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ParsingException;
use System\Presentation\Engine\Token;
use System\Presentation\Engine\TokenStream;
use System\Presentation\Engine\TokenType;
use System\Presentation\Engine\VariableNode;

/**
 * Abstract base class for all directive handlers.
 * Provides common functionality and defines the interface for processing directives.
 */
abstract class AbstractDirectiveHandler
{
    /**
     * Reference to the Parser instance
     */
    protected Parser $parser;

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
     * Process the directive and add nodes to the collection.
     * This is the main method that each handler must implement.
     *
     * @param TokenStream $stream The stream of tokens to process
     * @param INodeCollection $collection The collection to add nodes to
     *
     * @return void
     *
     * @throws ParsingException If the directive structure is invalid
     */
    abstract public function handle(TokenStream $stream, INodeCollection $collection): void;

    /**
     * Create a node from a token using the parser's factory method.
     *
     * @param Token $token
     *
     * @return Node
     */
    protected function createNode(Token $token): Node
    {
        return $this->parser->createNode($token);
    }

    /**
     * Process a variable token and add nodes to the collection.
     *
     * @param TokenStream $stream
     * @param VariableNode|INodeCollection $target
     *
     * @return void
     *
     * @throws ParsingException
     */
    protected function processVariableAndAddNodes(TokenStream $stream, VariableNode|INodeCollection $target): void
    {
        $this->parser->processVariableAndAddNodes($stream, $target);
    }

    /**
     * Validate and pop a directive from the parser's stack.
     *
     * @param TokenType $expectedType
     * @param Token $token
     *
     * @return void
     *
     * @throws ParsingException
     */
    protected function validateAndPopStack(TokenType $expectedType, Token $token): void
    {
        $this->parser->validateAndPopStack($expectedType, $token);
    }

    /**
     * Process directive arguments (used by insert, include, etc.).
     *
     * @param TokenStream $stream
     * @param INodeCollection $collection
     * @param string $directiveName
     *
     * @return void
     *
     * @throws ParsingException
     */
    protected function processDirectiveArguments(TokenStream $stream, INodeCollection $collection, string $directiveName): void
    {
        // Expect opening parenthesis
        $token = $stream->expect(TokenType::OpenParen);
        $collection->addNode($this->createNode($token));

        // Process arguments until we hit closing parenthesis
        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // End of arguments
            if ($token->type === TokenType::CloseParen)
            {
                $collection->addNode($this->createNode($token));
                $stream->next();
                break;
            }
            // Handle variable arguments
            elseif ($token->type === TokenType::VariableStart)
            {
                /** @var VariableNode $varNode */
                $varNode = $this->createNode($token);
                $this->processVariableAndAddNodes($stream, $varNode);
                $collection->addNode($varNode);
            }
            // Handle literal arguments (strings, numbers, literals like true/false/null)
            elseif (in_array($token->type, [TokenType::String, TokenType::Number, TokenType::Literal]))
            {
                $collection->addNode($this->createNode($token));
                $stream->next();
            }
            // Handle comma separators
            elseif ($token->type === TokenType::Comma)
            {
                $collection->addNode($this->createNode($token));
                $stream->next();
            }
            else
            {
                throw new ParsingException(
                    "Unexpected token '{$token->type->value}' in {$directiveName} arguments at line {$token->line}."
                );
            }
        }
    }

    /**
     * Parses a sequence of tokens into parameter nodes and adds them to the specified node collection.
     * This method handles complex parameter expressions including nested structures (arrays, objects, function calls).
     *
     * Example: { key: value, key2: value2 } or [ key: value, key2: value2 ]
     *
     * @param TokenStream $stream The stream of tokens to be parsed.
     * @param INodeCollection $collection The collection where parsed parameter nodes should be stored.
     *
     * @return void
     */
    protected function parseKeyValuePairsAsNodes(TokenStream $stream, INodeCollection $collection): void
    {
        while ($stream->current()->type !== TokenType::BlockEnd)
        {
            // Collect parameter tokens until comma or BlockEnd
            $paramTokens = [];
            $depth = 0;

            while (true)
            {
                $token = $stream->current();

                // Track nesting depth
                if ($token->type === TokenType::OpenParen ||
                    $token->type === TokenType::OpenSquare ||
                    $token->type === TokenType::OpenBrace)
                {
                    $depth++;
                }
                if ($token->type === TokenType::CloseParen ||
                    $token->type === TokenType::CloseSquare ||
                    $token->type === TokenType::CloseBrace)
                {
                    $depth--;
                }

                // At depth 0, comma or BlockEnd ends the current parameter
                if ($depth === 0 && ($token->type === TokenType::Comma || $token->type === TokenType::BlockEnd))
                {
                    if ($token->type === TokenType::Comma)
                    {
                        $stream->next(); // consume comma
                    }
                    break;
                }

                $paramTokens[] = $token;
                $stream->next();
            }

            if (!empty($paramTokens))
            {
                // Add each token as a node to build the AST
                foreach ($paramTokens as $paramToken)
                {
                    $collection->addNode($this->createNode($paramToken));
                }
            }

            if ($stream->current()->type === TokenType::BlockEnd)
            {
                break;
            }
        }
    }

    /**
     * Processes tokens recursively until one of the specified end token names is found.
     * Consumes the TokenType::BlockEnd.
     *
     * @param array $endNames Array of directive names (strings) that indicate where to stop parsing.
     * @param TokenStream $stream The array of tokens to process
     * @param INodeCollection $collection The collection to add nodes to
     *
     * @return void
     *
     * @throws ParsingException If an expected end directive is not found.
     * @throws \Exception
     */
    protected function processTokensRecursivelyUntil(array $endNames, TokenStream $stream, INodeCollection $collection): void
    {
        $stack = $this->parser->getStack();
        $index = count($stack) - 1;
        $inAnIf = ($index >= 0 && $stack[$index]->type == TokenType::IfStart);
        $hasElse = false; // Track whether ELSE has already been processed
        $previousPos = -1;

        if (empty($endNames)) {
            throw new \InvalidArgumentException("endNames cannot be empty.");
        }

        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // Prevent infinite loops
            if ($previousPos === $stream->getPosition())
            {
                throw new ParsingException("Infinite loop detected while processing tokens on line {$token->line}");
            }
            $previousPos = $stream->getPosition();

            $stack = $this->parser->getStack();
            if (count($stack) >= $this->parser->getMaxStackSize())
            {
                throw new ParsingException("Too many nested blocks at line {$token->line}");
            }

            // Stop processing when encountering an end token name
            // We check the value of the token
            if (in_array($token->value, $endNames))
            {
                $openingTokenType = match ($token->type) {
                    TokenType::IfEnd => TokenType::IfStart,
                    default => TokenType::Directive
                };

                // Validate and pop using the token type.
                // Note: We don't check expectedValue here because we don't know what opened the block
                // without looking at the stack, but validateAndPopStack will verify the type match.
                // For Directive type, we could check if the popped value matches what we expect from this end tag,
                // but the end tag (e.g. endforeach) doesn't say "I close foreach". The logic is implicit.
                // However, the Parser stack contains the Opening Value.
                // If we assume standard naming (foreach -> endforeach), we could validate.
                // But custom directives might have weird closing tags.
                // So we stick to Type validation for now.

                $this->parser->validateAndPopStack($openingTokenType, $token);
                $collection->addNode($this->createNode($token));

                // Collect the closing block too
                $stream->next();
                $collection->addNode($this->createNode($stream->expect(TokenType::BlockEnd)));
                return;
            }

            switch ($token->type)
            {
                // Directive Starts
                case TokenType::BlockStart:
                    $next = $stream->peek();
                    // If next token is a flow control token (Else, ElseIf), OR one of our End Directives!
                    // We need to check if $next matches $endNames
                    if ($next && (
                        in_array($next->type, [TokenType::Else, TokenType::ElseIf, TokenType::IfEnd]) ||
                        in_array($next->value, $endNames)
                    )) {
                         $collection->addNode($this->createNode($token));
                         $stream->next();
                    } else {
                        // Otherwise it's a new nested directive
                        $this->parser->processDirective($stream, $collection);
                    }
                    break;

                case TokenType::ElseIf:
                    if (!$inAnIf) {
                        throw new ParsingException("ELSEIF cannot appear outside of an IF at line {$token->line}");
                    }
                    else if ($hasElse) {
                        throw new ParsingException("ELSEIF cannot appear after ELSE at line {$token->line}");
                    }

                    // Add ElseIf node
                    $collection->addNode($this->createNode($token));
                    $stream->next();

                    // Parse Condition
                    $conditionCollection = new NodeCollection();
                     while (!$stream->isEnd()) {
                        $t = $stream->current();
                        if ($t->type === TokenType::BlockEnd) break;

                         if ($t->type === TokenType::VariableStart) {
                            $this->processVariableAndAddNodes($stream, $conditionCollection);
                            continue;
                        }

                        $conditionCollection->addNode($this->createNode($t));
                        $stream->next();
                    }

                    if ($conditionCollection->isEmpty()) {
                         throw new ParsingException("Empty condition in elseif statement at line {$token->line}.");
                    }

                    $this->validateConditionStructure($conditionCollection);

                    // Add condition nodes to collection
                    foreach ($conditionCollection->getNodes() as $node) {
                        $collection->addNode($node);
                    }
                    // BlockEnd will be handled by next iteration (default case)
                    break;

                case TokenType::Else:
                    if ($hasElse) {
                        throw new ParsingException("Multiple ELSE statements found at line {$token->line}");
                    }
                    $hasElse = true;

                    $collection->addNode($this->createNode($token));
                    $stream->next();
                    break;

                case TokenType::VariableStart:
                    $this->processVariableAndAddNodes($stream, $collection);
                    break;

                case TokenType::ExpressionStart:
                    $this->parser->processExpressionAndAddNodes($stream, $collection);
                    break;

                default:
                    // Handle other token types (e.g., variables, identifiers, etc.)
                    $collection->addNode($this->createNode($token));
                    $stream->next();
                    break;
            }
        }

        // If we reached here, it means the expected end token was not found
        throw new ParsingException("Expected one of [" . implode(', ', $endNames) . "] but reached the end of tokens.");
    }

    /**
     * Validates the structure of a condition (e.g. if, elseif) within a collection of nodes.
     * Ensures that the condition follows proper syntax rules, including matching parentheses,
     * appropriate sequences of tokens, and valid ending token types.
     *
     * @param INodeCollection $nodes The collection of nodes representing the condition to validate.
     *
     * @return void
     *
     * @throws ParsingException If the condition structure is invalid.
     */
    protected function validateConditionStructure(INodeCollection $nodes): void
    {
        // Does not include variable types
        static $allowedTypes = [
            TokenType::VariableStart,
            TokenType::Literal,
            TokenType::Number,
            TokenType::String,
            TokenType::Operator,
            TokenType::LogicalOperator,
            TokenType::UnaryOperator,
            TokenType::OpenParen,
            TokenType::CloseParen,
        ];

        $previousTokenType = null;
        $openParensCount = 0;
        $nodeCount = $nodes->count();

        for ($index = 0; $index < $nodeCount; $index++)
        {
            $node = $nodes->getNodeAt($index);

            // break on close block
            if ($node->type === TokenType::BlockEnd)
            {
                break;
            }

            // Provide specific error for assignment operator in conditions
            if ($node->type === TokenType::SetOperator)
            {
                throw new ParsingException(
                    "Assignment operator '=' cannot be used in conditions at line {$node->line}. Did you mean '==' for comparison?"
                );
            }

            // Check that all tokens are of an allowed type
            if (!in_array($node->type, $allowedTypes, true))
            {
                throw new ParsingException("Unexpected token type '{$node->type->value}' in condition statement at line {$node->line}.");
            }

            // Validate specific token sequences
            switch ($node->type)
            {
                case TokenType::OpenParen:
                    $openParensCount++;
                    break;

                case TokenType::CloseParen:
                    if ($openParensCount <= 0) {
                        throw new ParsingException("Unmatched closing parenthesis in condition statement at line {$node->line}.");
                    }
                    $openParensCount--;
                    break;

                case TokenType::Operator:
                case TokenType::LogicalOperator:
                    // Cannot have two operators/logical operators in a row
                    // UNLESS the previous was a UnaryOperator (e.g., "not" before a comparison)
                    if (
                        ($previousTokenType === TokenType::Operator ||
                            $previousTokenType === TokenType::LogicalOperator) &&
                        $previousTokenType !== TokenType::UnaryOperator
                    ) {
                        throw new ParsingException("Cannot have two operators/logical operators in a row at line {$node->line}.");
                    }
                    break;

                case TokenType::UnaryOperator:
                    // 'not' can appear:
                    // - At the start of a condition
                    // - After a logical operator (and, or)
                    // - After an opening parenthesis
                    if (
                        $previousTokenType !== null &&
                        $previousTokenType !== TokenType::LogicalOperator &&
                        $previousTokenType !== TokenType::OpenParen
                    ) {
                        throw new ParsingException(
                            "Unary operator 'not' must appear at the start, after a logical operator, or after '(' at line {$node->line}."
                        );
                    }
                    break;

                case TokenType::VariableStart:
                case TokenType::Literal:
                case TokenType::Number:
                case TokenType::String:
                    // Cannot have two literals, variables, or identifiers in a row
                    // UNLESS the previous was a UnaryOperator or OpenParen
                    if (
                        ($previousTokenType === TokenType::VariableStart ||
                            $previousTokenType === TokenType::Literal ||
                            $previousTokenType === TokenType::Number ||
                            $previousTokenType === TokenType::String) &&
                        $previousTokenType !== TokenType::UnaryOperator &&
                        $previousTokenType !== TokenType::OpenParen
                    ) {
                        throw new ParsingException("Cannot have two literals, variables, or identifiers in a row at line {$node->line}.");
                    }
                    break;
            }

            // Update the previous token type
            $previousTokenType = $node->type;
        }

        // Ensure all opened parentheses are closed
        if ($openParensCount !== 0) {
            throw new ParsingException("Unmatched opening parenthesis in condition statement at line {$previousTokenType->line}.");
        }

        // Conditions must end with a valid token type like Variable, Identifier, Literal, Number, or String
        if (!in_array($previousTokenType, [TokenType::VariableStart, TokenType::Literal, TokenType::Number, TokenType::String, TokenType::CloseParen, TokenType::CloseSquare]))
        {
            throw new ParsingException("Unexpected end token type '{$previousTokenType->value}' in condition statement at line {$previousTokenType->line}.");
        }
    }
}