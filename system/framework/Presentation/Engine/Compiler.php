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

use System\Presentation\Engine\Strategies\CompilerStrategyRegistry;
use System\Presentation\Engine\Filters\FilterRegistry;

/**
 * This class is responsible for compiling a custom template syntax into executable PHP code.
 * It processes a collection of template nodes, converting them into appropriate PHP constructs,
 * enabling dynamic rendering of templates using variables, loops, and conditions.
 */
class Compiler implements CompilerInterface
{
    /**
     * The directory in which compiled PHP files will be stored.
     */
    protected(set) string $compiledDir;

    /**
     * @var CompilerStrategyRegistry Registry for compiler strategies
     */
    protected CompilerStrategyRegistry $strategyRegistry;

    /**
     * @var FilterRegistry Registry for template filters
     */
    protected(set) FilterRegistry $filterRegistry;

    /**
     * Creates a new instance of the Compiler class.
     *
     * @param string $compiledDir The directory where compiled files will be stored.
     */
    public function __construct(string $compiledDir)
    {
        $this->compiledDir = $compiledDir;
        $this->strategyRegistry = new CompilerStrategyRegistry($this);
        $this->filterRegistry = new FilterRegistry();
    }

    /**
     * Compiles a NodeCollection into a PHP code string by processing each node
     * based on its type and generating the corresponding PHP syntax.
     *
     * @param NodeCollection $nodes A collection of nodes to be compiled into PHP code.
     *
     * @return string The resulting PHP code as a string.
     *
     * @throws \Exception If an unexpected node type is encountered during compilation.
     */
    public function compile(NodeCollection $nodes): string
    {
        $compiled = '';

        foreach ($nodes->getNodes() as $node)
        {
            $compiled .= $this->compileNode($node, false);
        }

        // Clean up excessive blank lines in the final output
        return $this->cleanupBlankLines($compiled);
    }

    /**
     * Compiles a single node into PHP code based on its type.
     *
     * @param Node $node The node to compile.
     *
     * @return string The resulting PHP code for the node.
     *
     * @throws CompilerException
     */
    public function compileNode(Node $node, bool $isPhpTagOpen): string
    {
        $token = $node->token;
        return match ($token->type)
        {
            TokenType::Text => $token->value,
            TokenType::VariableStart => $this->compileVariable($node),
            TokenType::ExpressionStart => $this->compileExpression($node),
            TokenType::Directive,
            TokenType::IfStart => $this->compileDirective($node, $isPhpTagOpen),
            default => throw new CompilerException("Unknown token type: {$token->type->value}"),
        };
    }

    /**
     * Compiles a VariableNode into a PHP code string by processing its expression
     * and generating the corresponding PHP syntax.
     *
     * @param VariableNode $node The variable node to be compiled into PHP code.
     *
     * @return string The generated PHP code as a string.
     *
     * @throws CompilerException If an unexpected or invalid token type is encountered,
     *                            or if the variable expression is incomplete.
     */
    public function compileVariable(VariableNode $node): string
    {
        $code = $this->compileVariableExpression($node); // Compile expression into PHP code
        return $this->writeEcho($code);
    }

    /**
     * Compiles an ExpressionNode into a PHP code string.
     * Handles null coalescing (??), ternary (?:), and Elvis (?:) operators.
     *
     * @param ExpressionNode $node The expression node to compile.
     *
     * @return string The generated PHP code as a string.
     *
     * @throws CompilerException
     */
    public function compileExpression(ExpressionNode $node): string
    {
        $code = $this->compileExpressionContent($node);
        return $this->writeEcho($code);
    }

    /**
     * Compiles the content of an ExpressionNode into a PHP expression string.
     *
     * @param ExpressionNode $node The expression node to compile.
     *
     * @return string The compiled PHP expression.
     *
     * @throws CompilerException
     */
    public function compileExpressionContent(ExpressionNode $node): string
    {
        $expression = '';
        $parts = $node->getNodes();

        foreach ($parts as $index => $part)
        {
            $token = $part->token;

            // Handle nested VariableNode (variables within expressions)
            if ($part instanceof VariableNode)
            {
                $expression .= $this->compileVariableExpression($part);
                continue;
            }

            // Handle other token types
            switch ($token->type)
            {
                case TokenType::ExpressionEnd:
                    // End of expression, skip
                    break;

                case TokenType::Question:
                    $expression .= ' ? ';
                    break;

                case TokenType::Colon:
                    $expression .= ' : ';
                    break;

                case TokenType::NullCoalesce:
                    $expression .= ' ?? ';
                    break;

                case TokenType::Operator:
                    $expression .= " {$token->value} ";
                    break;

                case TokenType::LogicalOperator:
                    $expression .= " {$token->value} ";
                    break;

                case TokenType::UnaryOperator:
                    $expression .= '!';
                    break;

                case TokenType::String:
                    $expression .= $token->value;
                    break;

                case TokenType::Number:
                    $expression .= $token->value;
                    break;

                case TokenType::Literal:
                    $expression .= $this->convertLiteral($token->value);
                    break;

                case TokenType::OpenParen:
                    $expression .= '(';
                    break;

                case TokenType::CloseParen:
                    $expression .= ')';
                    break;

                case TokenType::Identifier:
                    // Standalone identifier (shouldn't happen often in expressions)
                    $expression .= '$' . $token->value;
                    break;

                default:
                    throw new CompilerException(
                        "Unhandled token type in expression: {$token->type->value}"
                    );
            }
        }

        return $expression;
    }

    /**
     * Compiles a VariableNode into a PHP variable expression string, transforming
     * each segment of the node into its corresponding PHP syntax based on the
     * token types, such as properties, array keys, and method calls.
     *
     * Does NOT apply the semicolon after the expression!
     *
     * @param VariableNode $node The VariableNode to be compiled, containing parts and tokens
     *                           that define the structure of the variable expression.
     *
     * @return string The compiled PHP representation of the variable expression as a string.
     *
     * @throws CompilerException If an unexpected or invalid token type is encountered, or if the variable expression is incomplete.
     * @throws \Exception
     */
    public function compileVariableExpression(VariableNode $node): string
    {
        $expression = '';
        $parts = $node->getNodes();

        foreach ($parts as $index => $part)
        {
            $token = $part->token;

            switch ($token->type)
            {
                case TokenType::VariableStart:
                    $expression .= $this->compileVariableExpression($part);
                    break;
                case TokenType::VariableEnd:
                    break;
                case TokenType::Identifier:
                    // If this is the first part of the expression, initialize it
                    if ($expression === '$' || $expression === '')
                    {
                        $expression = ($expression === '$') ? $token->value : '$' . $token->value;
                        break;
                    }

                    $lastToken = $parts[$index - 1]->token;
                    if ($lastToken->type === TokenType::AccessOperator)
                    {
                        $expression .= "['{$token->value}']";
                    }
                    elseif ($lastToken->type === TokenType::MethodOperator)
                    {
                        $expression .= $token->value;
                    }
                    elseif ($lastToken->type === TokenType::VariableStart)
                    {
                        // If it follows a VariableStart, it's the beginning of the variable name
                        $expression .= $token->value;
                    }
                    else
                    {
                        throw new CompilerException("Unexpected identifier in variable expression: {$node->token->value}");
                    }
                    break;

                case TokenType::AccessOperator:
                    // Implied by following Identifier; no output needed
                    break;

                case TokenType::OpenSquare:
                    $expression .= '[';
                    break;

                case TokenType::CloseSquare:
                    $expression .= ']';
                    break;

                case TokenType::String:
                    // Add string key or value for array access
                    $expression .= $token->value;
                    break;

                case TokenType::Literal:
                    // Add literal values like true, false, or null
                    $expression .= $this->convertLiteral($token->value);
                    break;

                case TokenType::Number:
                    // Add numeric value for array indexes or numeric operations
                    $expression .= $token->value;
                    break;

                case TokenType::MethodOperator:
                    // Add method call (->method)
                    $expression .= '->';
                    break;

                case TokenType::OpenParen:
                    $expression .= '(';
                    break;

                case TokenType::CloseParen:
                    $expression .= ')';
                    break;

                case TokenType::Comma:
                    $expression .= ', ';
                    break;

                case TokenType::Concat:
                    $expression .= ' . ';
                    break;

                case TokenType::Question:
                    $expression .= ' ? ';
                    break;

                case TokenType::Colon:
                    $expression .= ' : ';
                    break;

                case TokenType::NullCoalesce:
                    $expression .= ' ?? ';
                    break;

                case TokenType::Operator:
                    $expression .= " {$token->value} ";
                    break;

                case TokenType::LogicalOperator:
                    $expression .= " {$token->value} ";
                    break;

                case TokenType::UnaryOperator:
                    $expression .= "!";
                    break;

                default:
                    throw new CompilerException("Unhandled token type in variable expression: {$token->type->value}");
            }
        }

        // Apply filters (if any) to the compiled expression
        if (!empty($node->filters))
        {
            $expression = $this->applyFilters($expression, $node->filters);
        }

        return $expression;
    }

    /**
     * Converts a literal value (true, false, null) into its PHP equivalent.
     *
     * @param string $value The literal value.
     * @return string The PHP equivalent.
     * @throws CompilerException
     */
    public function convertLiteral(string $value): string
    {
        return match (strtolower($value))
        {
            'true' => 'true',
            'false' => 'false',
            'null' => 'null',
            default => throw new CompilerException("Unknown literal value: {$value}"),
        };
    }

    /**
     * Compiles a directive node into PHP code (e.g., if, foreach, else, else if).
     *
     * @param DirectiveNode $node The directive node.
     * @return string The compiled directive PHP code.
     * @throws CompilerException
     * @throws \Exception
     */
    private function compileDirective(DirectiveNode $node, bool $tagOpen = false): string
    {
        $token = $node->token;
        $code = (!$tagOpen) ? '<?php ' : '';

        // Use strategy registry for directive compilation
        $key = $token->value;
        if ($this->strategyRegistry->hasStrategy($key))
        {
            $strategy = $this->strategyRegistry->getStrategy($key);
            $compiled = $strategy->compile($node);

            // Special handling for if directive (needs condition wrapping)
            if ($token->type === TokenType::IfStart) {
                $code .= "if ({$compiled}";
            } else {
                $code .= $compiled;
            }
        }
        else
        {
            throw new CompilerException("Unknown directive: {$token->value}");
        }

        return $code;
    }

    /**
     * Registers a new filter in the filter registry with a specified name and function.
     *
     * @param string $name The name of the filter to be added.
     * @param string $function The PHP function name to map to.
     *
     * @return void
     */
    public function addFilter(string $name, string $function): void
    {
        $this->filterRegistry->register($name, $function);
    }

    /**
     * Applies a series of filters to a given variable by dynamically constructing
     * and modifying an expression based on filter functions and their arguments.
     *
     * @param string $variable The name of the variable to which the filters are applied.
     * @param array $filters An array of filters, where each filter includes a 'name' key
     *                       for the filter function and optionally an 'args' key for arguments.
     *
     * @return string The resulting expression after all filters have been applied.
     *
     * @throws CompilerException If a filter function cannot be resolved.
     */
    private function applyFilters(string $variable, array $filters): string
    {
        $expression = $variable;
        foreach ($filters as $filter)
        {
            $function = $this->resolveFilterFunction($filter['name']);
            if (!empty($filter['args']))
            {
                $oldExpression = $variable;
                $expression = "{$function}(". implode(', ', $filter['args']) . ");
                $expression = str_replace('', $oldExpression, $expression)";
            }
            else
            {
                $expression = "{$function}({$variable})";
            }
        }

        return $expression;
    }

    /**
     * Resolve filter name to a corresponding PHP function.
     *
     * @param string $filter The filter name.
     *
     * @return string The corresponding PHP function.
     *
     * @throws CompilerException If a filter function cannot be resolved.
     */
    private function resolveFilterFunction(string $filter): string
    {
        return $this->filterRegistry->get($filter);
    }

    /**
     * Wraps any PHP code in an echo instruction.
     *
     * @param string $code The code to output.
     * @return string PHP echo statement.
     */
    private function writeEcho(string $code): string
    {
        return "<?= {$code} ?>";
    }

    /**
     * Get the directory where compiled templates are stored.
     */
    public function getCompiledDir(): string
    {
        return $this->compiledDir;
    }

    /**
     * Removes excessive blank lines from compiled output while preserving intentional spacing.
     * Reduces multiple consecutive blank lines to a single blank line.
     *
     * @param string $compiled The compiled PHP code
     * @return string The cleaned compiled code
     */
    private function cleanupBlankLines(string $compiled): string
    {
        // Replace 3 or more consecutive newlines with just 2 newlines (one blank line)
        // This preserves intentional paragraph spacing while removing excessive blanks
        return preg_replace('/\n{3,}/', "\n\n", $compiled);
    }
}