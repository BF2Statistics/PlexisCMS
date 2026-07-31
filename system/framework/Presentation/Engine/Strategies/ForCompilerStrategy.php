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

use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;
use System\Presentation\Engine\TokenType;

/**
 * The ForCompilerStrategy class is designed to compile "for" loop directives
 * into executable PHP code. It interprets the nodes of a directive structure
 * and generates the appropriate PHP "for" loop syntax based on the format and
 * content of the input nodes.
 */
class ForCompilerStrategy extends AbstractCompilerStrategy
{
    /**
     * @inheritDoc
     */
    public function compile(DirectiveNode $node): string
    {
        $nodes = $node->getNodes();
        $nodeCount = count($nodes);

        if ($nodeCount < 4) {
            throw new CompilerException("Invalid for loop structure.");
        }

        // --- 1. Compile Header ---
        $loopVar = $nodes[0]->token->value;
        $secondNode = $nodes[1];
        $headerCode = "";
        $startIndex = 0;
        if ($secondNode->token->type === TokenType::Number)
        {
            // Range format: for i 0..10
            $startValue = $nodes[1]->token->value;
            $endValue = $nodes[3]->token->value;
            $op = ($startValue > $endValue) ? ">=" : "<=";
            $inc = ($startValue > $endValue) ? "--" : "++";
            $headerCode = "for (\${$loopVar} = {$startValue}; \${$loopVar} {$op} {$endValue}; \${$loopVar}{$inc}):";
            $startIndex = 5;
        }
        elseif ($secondNode->token->type === TokenType::SetOperator)
        {
            // Extended format: for i = 0, i < 10, i++
            $initValue = $nodes[2]->token->value;
            $conditionVar = $nodes[4]->token->value;
            $conditionOp = $nodes[5]->token->value;
            $conditionValue = $nodes[6]->token->value;
            $incrementVar = $nodes[8]->token->value;
            $headerCode = "for (\${$loopVar} = {$initValue}; \${$conditionVar} {$conditionOp} {$conditionValue}; \${$incrementVar}++):";
            $startIndex = 11;
        }
        else
        {
            throw new CompilerException("Invalid for loop format.");
        }

        // --- 2. Compile Body ---
        $bodyCode = "";
        $inBlock = false;
        for ($i = $startIndex; $i < $nodeCount; $i++)
        {
            $part = $nodes[$i];
            if ($part instanceof DirectiveNode) {
                if ($part->token->value === 'endfor') {
                    $bodyCode .= "endfor;";
                    continue;
                }
                $bodyCode .= $this->compiler->compileNode($part, $inBlock);
                $inBlock = false;
                continue;
            }
            switch ($part->token->type) {
                case TokenType::VariableStart:
                    $bodyCode .= ($inBlock) ? $this->compileVariableExpression($part) : $this->compiler->compileVariable($part);
                    break;
                case TokenType::ExpressionStart:
                    $bodyCode .= ($inBlock) ? $this->compiler->compileExpressionContent($part) : $this->compiler->compileExpression($part);
                    break;
                case TokenType::ExpressionEnd:
                    // End marker for expressions, no output needed
                    break;
                case TokenType::BlockStart:
                    $bodyCode .= "<?php ";
                    $inBlock = true;
                    break;
                case TokenType::BlockEnd:
                    $bodyCode .= " ?>";
                    $inBlock = false;
                    break;
                case TokenType::Text:
                    $bodyCode .= $part->token->value;
                    break;
            }
        }
        return "<?php {$headerCode} ?>{$bodyCode}";
    }
}
