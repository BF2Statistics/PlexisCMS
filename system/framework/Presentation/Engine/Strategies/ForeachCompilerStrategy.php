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
namespace System\Presentation\Engine\Strategies;

use System\Collections\ArrayList;
use System\IO\Directory;
use System\IO\File;
use System\IO\Path;
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;
use System\Presentation\Engine\TokenType;

/**
 * Class ForeachCompilerStrategy
 *
 * Represents a compiler strategy for processing "foreach" directives in a template.
 * This class is responsible for handling the compilation of nested and non-nested
 * foreach loops including support for `else` blocks in the context of the template engine.
 *
 * It processes directive nodes, building corresponding compiled PHP code to handle
 * the iteration logic based on provided iterable variables, keys, and values. The compilation
 * result includes optimized usage of external PHP script files for complex loop structures.
 *
 * The class manages various internal constructs such as loop stacks, else stacks, and
 * variable parsing to construct the resulting code. Certain key behaviors include:
 * - Parsing of directive nodes to identify loop bounds, key-value pairs, and other components.
 * - Handling nested loop constructs and ensuring proper closures within the compiled output.
 * - Generating and caching PHP files for loop structures and leveraging `opcache_compile_file`
 *   for improved performance when available.
 *
 * Methods:
 * - `compile(DirectiveNode $node)`: Compiles a given directive node into a string of PHP code.
 * - `buildRendererForeachCall(bool $isNested, string $iterable, string $key, ?string $value, string $fileName)`:
 *   Generates the PHP code responsible for invoking the foreach loop renderer for template rendering.
 */
class ForeachCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * Tracks the nesting depth of foreach loops.
     */
    private int $foreachDepth = 0;

    /**
     * Compiles a given DirectiveNode into a rendered PHP expression.
     *
     * This method processes the structure of a given DirectiveNode, resolving
     * loops, handling nested structures, and generating appropriate code for
     * variable expressions, conditional statements, and other directives.
     * The compiled output is cached locally as a file for optimized rendering
     * and can be precompiled using opcode caching when supported.
     *
     * @param DirectiveNode $node The directive node representing a loop or conditional structure to be compiled.
     *
     * @return string The compiled PHP code as a string, ready for rendering or inclusion.
     *
     * @throws CompilerException If an unhandled token type is encountered during compilation.
     * @throws \Exception
     */
    public function compile(DirectiveNode $node): string
    {
        $this->foreachDepth++;

        $loopStack = new ArrayList();
        $elseStack = new ArrayList();
        $outerStack = new ArrayList();
        $currentStack = $loopStack;
        $inBlock = true;
        $foreachClosed = false;
        $keyword = "";
        $usedKeyValue = false;
        $expressionVariables = [];
        $iterableVariable = "";
        $keyVariable = "";
        $valueVariable = "null";

        $nodes = $node->getNodes();
        for ($i = 0; $i < count($nodes); $i++)
        {
            $part = $nodes[$i];
            if ($part instanceof DirectiveNode)
            {
                // Handle endforeach
                if ($part->token->value === 'endforeach')
                {
                    $currentStack = $loopStack;
                    if ($loopStack->count() > 0) $loopStack->removeAt($loopStack->count() - 1);
                    if ($elseStack->count() > 0) $elseStack->removeAt($elseStack->count() - 1);
                    $this->foreachDepth--;
                    continue;
                }

                $loopStack->add($this->compiler->compileNode($part, $inBlock));
                $inBlock = false;
                continue;
            }
            $token = $part->token;
            switch ($token->type)
            {
                case TokenType::Else:
                    $currentStack = $elseStack;
                    $currentStack->add("<?php else: ?>");
                    break;
                case TokenType::VariableStart:
                    if (!$foreachClosed) {
                        $expressionVariables[] = $this->compileVariableExpression($part);
                    } else {
                        $currentStack->add(($inBlock) ? $this->compileVariableExpression($part) : $this->compiler->compileVariable($part));
                    }
                    break;
                case TokenType::ExpressionStart:
                    $currentStack->add(($inBlock) ? $this->compiler->compileExpressionContent($part) : $this->compiler->compileExpression($part));
                    break;
                case TokenType::ExpressionEnd:
                    // End marker for expressions, no output needed
                    break;
                case TokenType::BlockStart:
                    $currentStack->add("<?php ");
                    $inBlock = true;
                    break;
                case TokenType::BlockEnd:
                    if (!$foreachClosed)
                    {
                        if ($keyword == "in")
                        {
                            $thirdVar = $expressionVariables[2] ?? "";
                            if ($usedKeyValue) {
                                $iterableVariable = $thirdVar;
                                $keyVariable = "'" . ltrim($expressionVariables[0], '$') ."'";
                                $valueVariable = "'" . ltrim($expressionVariables[1], '$') ."'";
                            }
                            else
                            {
                                $iterableVariable = $expressionVariables[1];
                                $keyVariable = "'" . ltrim($expressionVariables[0], '$') ."'";
                            }
                        }
                        else
                        {
                            $iterableVariable = $expressionVariables[0];
                            $keyVariable = "'" . ltrim($expressionVariables[1], '$') ."'";
                            if (count($expressionVariables) > 2)
                                $valueVariable = "'" . ltrim($expressionVariables[2], '$') ."'";
                        }
                        $foreachClosed = true;
                    }
                    $inBlock = false;
                    break;
                case TokenType::DoubleArrow:
                    $usedKeyValue = true;
                    break;
                case TokenType::Keyword:
                    $keyword = $token->value;
                    break;
                case TokenType::LogicalOperator:
                case TokenType::Operator:
                    $currentStack->add(" {$token->value} ");
                    break;
                case TokenType::Literal:
                    $currentStack->add($this->convertLiteral($token->value));
                    break;
                case TokenType::Comma:
                    if (!$foreachClosed) { $usedKeyValue = true; break; }
                    $currentStack->add(", ");
                    break;
                case TokenType::Text:
                case TokenType::String:
                case TokenType::Number:
                    $currentStack->add($token->value);
                    break;
                default:
                    throw new CompilerException("Unhandled token type: {$token->type->value}", $node->getNodes());
            }
        }
        $loopContents = implode("", $loopStack->toArray());
        $key = substr(md5($loopContents), 0, 12);
        $fileName = "foreach_". $key . ".phtml";
        $directory = Path::Combine($this->compiler->getCompiledDir(), "loops");
        if (!Directory::Exists($directory)) Directory::CreateDirectory($directory);

        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        $expression = $this->buildRendererForeachCall($iterableVariable, $keyVariable, $valueVariable, $fileName);
        if ($elseStack->count() > 0)
        {
            $outerStack->add("if (!empty({$iterableVariable})): ?><?php ");
            $outerStack->add($expression);
            foreach ($elseStack as $node) $outerStack->add($node);
            $outerStack->add("<?php endif; ?>");
            $expression = implode("", $outerStack->toArray());
        }

        File::WriteAllText($path, $loopContents);
        if (function_exists("opcache_compile_file"))
            opcache_compile_file($path);

        return $expression;
    }

    /**
     * Builds the PHP code for invoking a rendered foreach loop.
     *
     * This method generates the PHP code to handle the rendering of a foreach
     * loop, considering whether it is nested, the iterable data structure, and
     * associated key-value variable references. It includes handling of optional
     * values and outputs a string ready for execution in PHP templates.
     *
     * @param string $iterable The variable name or expression representing the iterable data.
     * @param string $key The variable name for the loop key.
     * @param string|null $value The optional variable name for the loop value.
     * @param string $fileName The name of the file where the rendered loop logic is applied.
     *
     * @return string The PHP code as a string to execute the rendered foreach loop.
     */
    private function buildRendererForeachCall(string $iterable, string $key, ?string $value, string $fileName): string
    {
        return sprintf('echo $this->renderForeachLoop(%s, %s, %s, "%s"); ?>',
            $iterable, trim($key), trim($value), $fileName
        );
    }
}
