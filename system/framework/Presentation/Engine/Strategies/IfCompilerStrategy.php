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
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;
use System\Presentation\Engine\TokenType;

/**
 * Class IfCompilerStrategy
 *
 * This class is responsible for compiling conditional directive nodes into
 * executable strings. It handles various token types representing conditional
 * branches, literals, variables, and operators, and translates them into
 * PHP code or other syntactically correct output.
 */
class IfCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * @inheritDoc
     */
    public function compile(DirectiveNode $node): string
    {
        $parts = $node->getNodes();
        $nodeCount = $node->count();
        $index = 0;

        $expression = $this->compileExpression($parts, $index);
        $inBlock = false;
        $index++;

        while ($index < $nodeCount)
        {
            $node = $parts[$index];

            if ($node instanceof DirectiveNode) {
                $expression .= $this->compiler->compileNode($node, $inBlock);
                $inBlock = false;
                $index++;
                continue;
            }
            switch ($node->token->type) {
                case TokenType::IfEnd:
                    $expression .= "endif;";
                    break;
                case TokenType::Else:
                    $expression .= "else:";
                    break;
                case TokenType::ElseIf:
                    $index++;
                    $expression .= "elseif (" . $this->compileExpression($parts, $index);
                    $inBlock = false;  // compileExpression() closed the PHP block
                    break;
                case TokenType::VariableStart:
                    $expression .= ($inBlock) ? $this->compileVariableExpression($node) : $this->compiler->compileVariable($node);
                    break;
                case TokenType::ExpressionStart:
                    $expression .= ($inBlock) ? $this->compiler->compileExpressionContent($node) : $this->compiler->compileExpression($node);
                    break;
                case TokenType::ExpressionEnd:
                    // End marker for expressions, no output needed
                    break;
                case TokenType::BlockStart:
                    $expression .= "<?php ";
                    $inBlock = true;
                    break;
                case TokenType::BlockEnd:
                    $expression .= " ?>";
                    $inBlock = false;
                    break;
                case TokenType::Text:
                case TokenType::String:
                case TokenType::Number:
                    $expression .= $node->token->value;
                    break;
                case TokenType::Literal:
                    $expression .= $this->convertLiteral($node->token->value);
                    break;
                case TokenType::Keyword:
                case TokenType::LogicalOperator:
                case TokenType::Operator:
                    $expression .= " {$node->token->value} ";
                    break;
                case TokenType::UnaryOperator:
                    $expression .= "!";
                    break;
                case TokenType::Question: $expression .= " ? "; break;
                case TokenType::Colon: $expression .= " : "; break;
                case TokenType::NullCoalesce: $expression .= " ?? "; break;
                case TokenType::OpenParen: $expression .= "("; break;
                case TokenType::CloseParen: $expression .= ")"; break;
                case TokenType::Comma: $expression .= ", "; break;
                default:
                    throw new CompilerException("Unhandled token: {$node->token->type->value}");
            }
            $index++;
        }
        return $expression;
    }

    /**
     * Compiles an expression from a sequence of nodes, starting at a specified index
     * and generating a string representation of the expression.
     *
     * @param array $nodes An array of nodes representing the parts of the expression.
     * @param int &$startIndex Reference to the starting index of the nodes array where
     *                         the compilation begins. This value will be incremented
     *                         during processing to reflect the position of the next unprocessed node.
     *
     * @return string The compiled expression as a string.
     *
     * @throws CompilerException If the expression contains unhandled or invalid tokens,
     * @throws \Exception
     *                           or if the expression ends unexpectedly.
     */
    private function compileExpression(array $nodes, int &$startIndex): string
    {
        static $altOperators = ['is', 'is not'];
        static $altLiterals = ['empty', 'odd', 'even', 'defined'];
        static $altOperatorMap = ['is' => '==', 'is not' => '!='];

        $nodeCount = count($nodes);
        $lastVariableIndex = 0;
        $stack = new ArrayList();
        while ($startIndex < $nodeCount)
        {
            $node = $nodes[$startIndex];
            switch ($node->token->type)
            {
                case TokenType::VariableStart:
                    $lastVariableIndex = $stack->add($this->compileVariableExpression($node));
                    break;
                case TokenType::BlockEnd:
                    $stack->add("): ?>");
                    return implode("", $stack->toArray());
                case TokenType::Operator:
                    $operator = strtolower($node->token->value);
                    if (!in_array($operator, $altOperators)) {
                        $stack->add(" {$node->token->value} ");
                        break;
                    }
                    if (str_starts_with($operator, "is"))
                    {
                        $next = $nodes[$startIndex + 1];
                        if (in_array($next->token->value, $altLiterals) && $next->token->type === TokenType::Literal)
                        {
                            $var = $stack->removeAt($lastVariableIndex);
                            $isNot = str_ends_with($operator, "not");
                            $newVal = match ($next->token->value) {
                                "empty" => ($isNot) ? "!empty({$var})" : "empty({$var})",
                                "odd" => ($isNot) ? "{$var} % 2 == 0" : "{$var} % 2 != 0",
                                "even" => ($isNot) ? "{$var} % 2 != 0" : "{$var} % 2 == 0",
                                "defined" => ($isNot) ? "!isset({$var})" : "isset({$var})",
                            };
                            $stack->add($newVal);
                            $startIndex++;
                        }
                        else
                        {
                            $stack->add(" {$altOperatorMap[$operator]} ");
                        }
                    }
                    break;
                case TokenType::Literal:
                    $stack->add($this->convertLiteral($node->token->value));
                    break;
                case TokenType::UnaryOperator:
                    $stack->add("!");
                    break;
                case TokenType::Keyword:
                case TokenType::LogicalOperator:
                    $stack->add(" {$node->token->value} ");
                    break;
                case TokenType::String:
                case TokenType::Number:
                    $stack->add($node->token->value);
                    break;
                case TokenType::OpenParen: $stack->add("("); break;
                case TokenType::CloseParen: $stack->add(")"); break;
                case TokenType::Comma: $stack->add(", "); break;
                case TokenType::Question: $stack->add(" ? "); break;
                case TokenType::Colon: $stack->add(" : "); break;
                case TokenType::NullCoalesce: $stack->add(" ?? "); break;
                default:
                    throw new CompilerException("Unhandled token in expression: {$node->token->type->value}");
            }
            $startIndex++;
        }
        throw new CompilerException("Unexpected end of expression");
    }
}
