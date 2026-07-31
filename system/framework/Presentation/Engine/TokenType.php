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

/**
 * Represents the set of token types used by the lexer for parsing templates or scripts.
 * Each token type corresponds to a specific syntactic or semantic structure.
 *
 * This enumeration provides a comprehensive list of identifiers to help categorize and process
 * tokens efficiently during the parsing process.
 *
 * The token types include basic control flow structures, literals, identifiers, punctuation,
 * operators, and other syntax-related elements.
 */
enum TokenType: string
{
    case BlockStart   = 'BLOCK_START';
    case BlockEnd     = 'BLOCK_END';

    case VariableStart = 'VARIABLE_START';
    case VariableEnd   = 'VARIABLE_END';

    case ExpressionStart = 'EXPRESSION_START';
    case ExpressionEnd   = 'EXPRESSION_END';

    // ==== Directives ==== //
    case Directive    = 'DIRECTIVE';
    case IfStart      = 'IF_START';
    case IfEnd        = 'IF_END';
    case Else         = 'ELSE';
    case ElseIf       = 'ELSE_IF';

    case Text         = 'TEXT';

    /**
     * true, false or null
     */
    case Literal      = 'LITERAL';

    /**
     * The name of a variable or method
     */
    case Identifier   = 'IDENTIFIER';
    case Number       = 'NUMBER';
    case String       = 'STRING';
    case Comma        = 'COMMA';
    case Colon      = 'COLON';
    case Semicolon    = 'SEMICOLON';
    case Pipe         = 'PIPE';
    case Question      = 'QUESTION';           // For ternary operator ?
    case NullCoalesce  = 'NULL_COALESCE';      // For null coalescing ??

    /**
     *  =>
     */
    case DoubleArrow        = 'DOUBLE_ARROW';
    case OpenParen    = 'OPEN_PAREN';
    case CloseParen   = 'CLOSE_PAREN';
    case OpenSquare   = 'OPEN_SQUARE';
    case CloseSquare  = 'CLOSE_SQUARE';
    case OpenBrace = 'OPEN_BRACE';
    case CloseBrace = 'CLOSE_BRACE';

    /**
     *  ->
     */
    case MethodOperator        = 'METHOD_OPERATOR';

    /**
     * a dot
     */
    case AccessOperator          = 'ACCESS_OPERATOR';

    /**
     * '..'
     */
    case RangeOperator = 'RANGE_OPERATOR';
    /**
     * Generic operator (+, -, ==, <, etc.)
     */
    case Operator     = 'OPERATOR';

    /**
     * Assignment operator (=)
     */
    case SetOperator  = 'SET_OPERATOR';

    /**
     * !
     */
    case UnaryOperator = 'UNARY_OPERATOR';
    /**
     * &&, ||, and, or, xor, is, is not
     */
    case LogicalOperator = 'LOGICAL_OPERATOR';
    case Increment    = 'INCREMENT';
    case Decrement    = 'DECREMENT';
    case Keyword      = 'KEYWORD';
    case Concat        = 'CONCAT';

    case Space        = 'SPACE';
}