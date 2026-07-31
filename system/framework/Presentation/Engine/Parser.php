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
use InvalidArgumentException;
use System\Presentation\Engine\Directives\DirectiveHandlerRegistry;

/**
 * A class responsible for parsing tokens into a structure of nodes (AST), ensuring proper
 * nesting, structure, and directive validation. This class handles various token types,
 * including directives, variables, and plain text, and enforces a maximum stack size
 * to prevent excessive nesting.
 */
class Parser implements ParserInterface
{
    /**
     * Contains a list of keywords that cannot be used as variable names!
     */
    public static array $ReservedWords = [
        'is',
        'not',
        'and',
        'or',
        'in',
        'as',
        'true',
        'false',
        'null',
        'empty',
        'odd',
        'even',
        'defined',
        'xor',
        'app',
        'this'
    ];

    /**
     * @var int The max allowed stack size for directives
     */
    protected int $maxStackSize;

    /**
     * @var array Contains the stack of opening directives
     */
    protected array $stack = [];

    /**
     * @var NodeCollection Contains the list of nodes to return
     */
    protected NodeCollection $nodes;

    /**
     * @var DirectiveHandlerRegistry Registry for directive handlers
     */
    protected DirectiveHandlerRegistry $handlerRegistry;

    /**
     * @var VariableProcessor Handles variable processing
     */
    protected(set) VariableProcessor $variableProcessor;

    /**
     * @var ExpressionProcessor Handles expression processing
     */
    protected(set) ExpressionProcessor $expressionProcessor;

    /**
     * Initializes a new instance of the class with the specified maximum stack size.
     *
     * @param int $maxStackSize The maximum number of items the stack can hold. Must be a positive integer.
     *
     * @throws InvalidArgumentException If the maximum stack size is not a positive integer.
     */
    public function __construct(int $maxStackSize = 10)
    {
        if ($maxStackSize <= 0) {
            throw new InvalidArgumentException('Max stack size must be a positive integer.');
        }

        $this->maxStackSize = $maxStackSize;
        $this->nodes = new NodeCollection();
        $this->handlerRegistry = new DirectiveHandlerRegistry($this);
        $this->variableProcessor = new VariableProcessor($this);
        $this->expressionProcessor = new ExpressionProcessor($this);
    }

    /**
     * Parses an array of tokens into a collection of nodes.
     *
     * @param TokenStream $stream The tokens to parse, including directives and conditions.
     * @return NodeCollection Parsed collection of nodes.
     *
     * @throws ParsingException For invalid structure or unmatched blocks.
     * @throws Exception
     */
    public function parse(TokenStream $stream): NodeCollection
    {
        // Reset
        $this->stack = [];
        $this->nodes = new NodeCollection();

        while (!$stream->isEnd())
        {
            $token = $stream->current();

            // Ensure we aren't over our maximum stack size. This prevents overflow issues
            if (count($this->stack) >= $this->maxStackSize) {
                throw new ParsingException("Too many nested blocks at line {$token->line}");
            }

            // Since all process methods consume and add nodes, we should ONLY
            // expect these 3 types of nodes at the top level here
            switch ($token->type)
            {
                case TokenType::BlockStart:
                    $this->processDirective($stream, $this->nodes);
                    break;

                case TokenType::VariableStart:
                    $this->processVariableAndAddNodes($stream, $this->nodes);
                    break;

                case TokenType::ExpressionStart:
                    $this->processExpressionAndAddNodes($stream, $this->nodes);
                    break;

                case TokenType::Text:
                    $this->nodes->addNode($this->createNode($token));
                    $stream->next();
                    break;

                default:
                    throw new ParsingException("Unexpected token {$token->type->value} at line {$token->line}");
            }
        }

        // If our stack isn't empty, inform the user
        if (!empty($this->stack))
        {
            $errors = [];
            foreach ($this->stack as $token)
            {
                $errors[] = "Unclosed {$token->type->name} block starting on line {$token->line}";
            }
            throw new ParsingException(join("\n", $errors));
        }

        return $this->nodes;
    }

    /**
     * Adds the specified token's type and line to the stack.
     *
     * @param Token $token The token containing the type and line to be added to the stack.
     *
     * @return void
     */
    protected function addToStack(Token $token): void
    {
        $this->stack[] = $token;
    }

    /**
     * Creates a new node from the given token and adds it to the nodes list.
     *
     * @param Token $token The token containing the type and value to construct the node.
     *
     * @return Node
     */
    public function createNode(Token $token): Node
    {
        return match ($token->type)
        {
            TokenType::VariableStart => new VariableNode($token),
            TokenType::ExpressionStart => new ExpressionNode($token),
            TokenType::IfStart,
            TokenType::Directive => new DirectiveNode($token),
            default => new Node($token)
        };
    }

    /**
     * Processes a directive from the given set of tokens, managing nested directives
     * and validating their structure.
     *
     * TokenType::BlockStart initiates a call to this method
     *
     * @param TokenStream $stream An array of tokens representing the parsed directives.
     *
     * @throws ParsingException If the directive is invalid or the nesting exceeds the allowed limit.
     */
    public function processDirective(TokenStream $stream, INodeCollection $collection): void
    {
        // Expect BlockStart
        $stream->expect(TokenType::BlockStart);

        // Get directive type
        $token = $stream->current();
        $stream->next(); // Consume directive token

        $this->addToStack($token);

        /* @var DirectiveNode $directiveNode */
        $directiveNode = $this->createNode($token);

        if (count($this->stack) >= $this->maxStackSize) {
            throw new ParsingException("Too many nested blocks at line {$token->line}");
        }

        // Delegate to handler
        $handlerKey = $token->value;
        if ($this->handlerRegistry->hasHandler($handlerKey))
        {
            $handler = $this->handlerRegistry->getHandler($handlerKey);
            $handler->handle($stream, $directiveNode);
        }
        else
        {
            throw new ParsingException("No handler registered for directive '{$token->value}' at line {$token->line}");
        }

        $collection->addNode($directiveNode);
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
        $this->variableProcessor->processVariableAndAddNodes($stream, $collection);
    }

    /**
     * Processes an expression from the token stream and adds the resulting nodes to the collection.
     *
     * @param TokenStream $stream The stream of tokens to process the expression from.
     * @param INodeCollection $collection The collection where the processed nodes will be added.
     *
     * @return void
     *
     * @throws ParsingException
     */
    public function processExpressionAndAddNodes(TokenStream $stream, INodeCollection $collection): void
    {
        $this->expressionProcessor->processExpressionAndAddNodes($stream, $collection);
    }

    /**
     * Validates and removes a directive from the stack.
     *
     * @param TokenType $expectedType The expected directive type.
     * @param Token $token The current token (for error reporting).
     * @param string|null $expectedValue The expected directive value (optional).
     *
     * @throws ParsingException If the stack is empty or doesn't match the expected type.
     */
    public function validateAndPopStack(TokenType $expectedType, Token $token, ?string $expectedValue = null): void
    {
        $last = array_pop($this->stack);
        if (!$last || $last->type !== $expectedType) {
             $startLine = $last ? $last->line : 'unknown';
             throw new ParsingException("Unmatched closing block at line {$token->line}. Expected block started at line {$startLine} to close.");
        }

        if ($expectedValue !== null && $last->value !== $expectedValue) {
             throw new ParsingException("Unmatched closing block '{$expectedValue}' at line {$token->line}. Found '{$last->value}' started at line {$last->line}.");
        }
    }

    /**
     * Get the parser's stack.
     *
     * @return array
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * Get the maximum stack size.
     *
     * @return int
     */
    public function getMaxStackSize(): int
    {
        return $this->maxStackSize;
    }
}