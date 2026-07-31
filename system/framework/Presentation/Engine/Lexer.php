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
 * The Lexer class is responsible for tokenizing a template string to generate a structured array of Token objects,
 * which represent different elements of the template, including variables, control structures, and plain text.
 *
 * This class handles various template constructs and ensures the input is broken down into manageable and
 * syntactically valid components. It includes methods for tokenizing variables, directives, and rendering plain text blocks.
 *
 * The lexer identifies different types of tokens from the template and categorizes them based on rules defined
 * by regular expressions and the template's syntax.
 */
class Lexer implements LexerInterface
{
    // Main regex used to tokenize the string
    private const string REGEX_TOKENIZE = '/
    # 1. Match Variable: {{ ... }}
    # Handles strings with escaped quotes inside, and allows "}" if not followed by "}"
    (\{\{\s*(?:(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')|[^}]|\}(?!\}))*?\s*\}\})

    # 2. Match Block: {% ... %}
    # Handles strings, and allows "%" if not followed by "}"
    | (\{%\s*(?:(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')|[^%]|\%(?!\}))*?\s*%\})

    # 3. Match Comment: {# ... #}
    | (\{\#\s*.*?\s*\#\})

    # 4. Match Plain Text (chunks of non-braces)
    | ([^{}]+)

    # 5. Catch-All (Safe fallback for stray "{" or "}" in text)
    | (.)
/xs';


    /**
     * Tokenize the input template string.
     *
     *  This method scans the provided template string and breaks it down into an array of structured `Token` objects.
     *  It supports the following template constructs:
     *
     *  ### Supported Constructs
     *  - **Variable expressions**: `{{ variableName }}`
     *    - Example: `{{ user.name }}` for arrays, `{{ user->name }}` for objects
     *    - Token type: `VARIABLE`
     *
     *  - **Control structures**:
     *    - **If statements**: `{% if condition %}`, `{% elseif condition %}`, `{% else %}`, `{% endif %}`
     *      - Example: `{% if user.isAdmin %}...{% endif %}`
     *      - Token types: `IF`, `ELSEIF`, `ELSE`, `ENDIF`
     *    - **For loops**: `{% for ... %}`, `{% endfor %}`
     *      - Example: `{% for i 0..10 %}...{% endfor %}`
     *      - Token types: `FOR`, `ENDFOR`
     *    - **Foreach loops**: `{% foreach ... %}`, `{% endforeach %}`
     *      - Example 1: `{% foreach user.friends as friend %}...{% endforeach %}`
     *          - Example 2: `{% foreach friend in user.friends %}...{% endforeach %}`
     *      - Token types: `FOREACH`, `ENDFOREACH`
     *  - **Includes**: `{% include 'viewName' %}`
     *    - Example: `{% include 'header.html' %}`
     *    - Token type: `INCLUDE`
     *  - **Comments**: `{# comment text #}`
     *    - Example: `{# This is a comment #}`
     *    - Comments are stripped during tokenization and do not appear in output
     *  - **Plain text**: Any content outside the template tags.
     *    - Example: `Hello, World!`
     *    - Token type: `TEXT`
     *
     *  ### Example Output
     *  Input template:
     *  ```html
     *  <h1>{{ user.name }}</h1>
     *  {# This is a comment #}
     *  {% if user.isAdmin %}
     *    <p>Welcome, Admin!</p>
     *  {% else %}
     *    <p>Welcome, Guest!</p>
     *  {% endif %}
     *  ```
     *  Output tokens:
     *  ```
     *  [
     *      new Token('TEXT', '<h1>', null, 0),
     *      new Token('VARIABLE', 'user.name', null, 4),
     *      new Token('TEXT', '</h1>', null, 17),
     *      new Token('IF', 'user.isAdmin', null, 23),
     *      new Token('TEXT', '<p>Welcome, Admin!</p>', null, 43),
     *      new Token('ELSE', null, null, 74),
     *      new Token('TEXT', '<p>Welcome, Guest!</p>', null, 82),
     *      new Token('ENDIF', null, null, 112),
     *  ]
     *  ```
     * @param string $template The raw template string (contains the template code).
     * @param bool $removePhpCode If true, remove all php code within the $template string
     *
     * @return TokenStream An array of Token objects (each representing a token).
     *
     * @throws Exception If the input contains invalid syntax.
     */
    public function tokenize(string $template, bool $removePhpCode = true): TokenStream
    {
        $tokens = [];
        $processedLength = 0;

        // Remove php code?
        if ($removePhpCode) {
            $template = preg_replace('/<\?(?:php|=)(.*?)\?>/s', '', $template);
        }

        // Extract verbatim blocks before any other processing
        [$template, $verbatimBlocks] = $this->extractVerbatimBlocks($template);

        // Remove standalone comments - PRESERVE line count by replacing with blank lines
        // This regex matches:
        // - Start of line (or after newline)
        // - Optional whitespace
        // - Comment {# ... #}
        // - Optional whitespace
        // - Newline (handles both \n and \r\n)
        $template = preg_replace_callback(
            '/^[ \t]*\{#.*?#\}[ \t]*(\r?\n)?/ms',
            function($matches)
            {
                // Count newlines in the entire comment block
                $newlineCount = substr_count($matches[0], "\n");
                return str_repeat("\n", $newlineCount);
            },
            $template
        );

        // Match all token patterns in the template
        preg_match_all(self::REGEX_TOKENIZE, $template, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches[0]) > 10000) { // Arbitrary upper limit for complex templates
            throw new LexerException("Template size too large to tokenize efficiently.");
        }

        foreach ($matches[0] as $match)
        {
            $content = $match[0];       // Matched raw content
            $offset = $match[1];
            $line = $this->getLineNumber($template, $offset);
            $column = $this->getColumnNumber($template, $offset);
            $processedLength += strlen($content);

            if ($processedLength > strlen($template))
            {
                throw new \LogicException("Lexer is infinitely processing the template.");
            }

            if (preg_match('/^\{\{\s*(.+?)\s*}}$/s', $content, $varMatch))
            {
                // Handle variables ({{ ... }})
                $tokens = array_merge($tokens, $this->tokenizeVariableExpression($varMatch[1], $line, $column));
            }
            elseif (preg_match('/^\{%\s*(.+?)\s*%}$/s', $content, $directiveMatch))
            {
                // Handle directives ({% ... %})
                $tokens = array_merge($tokens, $this->tokenizeDirective($directiveMatch[1], $line, $column));
            }
            elseif (preg_match('/^\{#\s*(.+?)\s*#}$/s', $content, $commentMatch))
            {
                // Skip comments entirely - they've already been removed by preprocessing
                // This case only handles inline comments that weren't removed
                continue;
            }
            else
            {
                // Handle plain text
                $tokens[] = $this->createToken(TokenType::Text, $content, $line, $column);
            }
        }
        // Restore verbatim blocks in the token stream
        $tokens = $this->restoreVerbatimBlocks($tokens, $verbatimBlocks);

        return new TokenStream($tokens);
    }

    /**
     * Helper method to create a Token object.
     *
     * @param TokenType $type The type of Token (e.g., TEXT, VARIABLE, IF_START, etc.).
     * @param string $value The value of the Token, if applicable (e.g., expressions or content).
     * @param int $line The line number where the Token is found (optional).
     * @param int $column The column position where the Token starts.
     * @return Token A new Token object.
     * @throws LexerException
     */
    private function createToken(TokenType $type, string $value, int $line, int $column): Token
    {
        static $tokenCount = 0;
        $tokenCount++;

        // Arbitrary threshold
        if ($tokenCount > 10000)
        {
            throw new LexerException("Too many tokens created, possible infinite loop at line $line");
        }

        return new Token($type, $value, $line, $column);
    }

    /**
     * Tokenize variable expressions (e.g., `{{ variableName }}`).
     * Handles identifiers, property access (`.` or `->`), indexing (`[...]`), and method calls (`(...)`).
     *
     * @param string $expression The variable expression (e.g., `user.name`, `user.permissions[6]`, `user1->func()`).
     * @param int $line The line of the variable.
     * @param int $column The column of the variable.
     * @return array<Token> Array of tokens for the variable.
     * @throws LexerException
     */
    private function tokenizeVariable(string $expression, int $line, int $column): array
    {
        $tokens = [];
        $stringRegex = '/^(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")$/';
        $matchAllRegex = '/
    (\'(?:\\\\.|[^\'\\\\])*\'        # Single-quoted string with escaped characters
    | "(?:\\\\.|[^"\\\\])*")         # Double-quoted string with escaped characters
    | (true|false|null)         # Literal values
    | ([a-zA-Z_][a-zA-Z0-9_]*)  # Identifiers (variables, keys, properties)
    | (\d+)                     # Numbers
    | (\.)                      # Dot operator
    | (->)                      # Arrow operator
    | ([\[\]])                  # Square brackets
    | ([()])                    # Parentheses
    | (,)                       # Comma
    | (~)                       # Concat
    /x';


        // Define local variables
        $stack = []; // To manage nested brackets/parentheses
        $currentVariableStarted = false;
        $subVariableStack = []; // Nested sub variables

        // Exclude filters from expression
        $expressionWithFilters = $expression;
        $expression = explode('|', $expression)[0];

        // Iterate over all matched tokens
        preg_match_all($matchAllRegex, $expression, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match)
        {
            $value = $match[0];
            $matchColumn = $column + $match[1];

            if ($value === '.')
            {
                // Handle dot (array access operator)
                $tokens[] = $this->createToken(TokenType::AccessOperator, '.', $line, $matchColumn);
            }
            elseif ($value === '[' || $value === '(')
            {
                // Push opening bracket to the stack
                $stack[] = $value;

                // Emit the open bracket/parenthesis token
                $tokens[] = $this->createToken(
                    $value === '[' ? TokenType::OpenSquare : TokenType::OpenParen,
                    $value,
                    $line,
                    $matchColumn
                );
            }
            elseif ($value === ']' || $value === ')')
            {
                // Decrement depth after matching the closing bracket
                $isSquare = ($value === ']');
                $expected = $isSquare ? '[' : '(';

                // If the depth equals the stack size, close the current sub-variable
                $depth = count($stack);
                if ($depth > 0 && count($subVariableStack) === $depth)
                {
                    $tokens[] = $this->createToken(TokenType::VariableEnd, '', $line, $matchColumn);
                    array_pop($subVariableStack); // Pop the sub-variable
                }

                // Validate matching bracket
                if (empty($stack) || array_pop($stack) !== $expected) {
                    throw new LexerException("Unmatched closing '{$value}' at line {$line}, column {$matchColumn}");
                }

                // Emit the closing bracket/parenthesis token
                $type = $isSquare ? TokenType::CloseSquare : TokenType::CloseParen;
                $tokens[] = $this->createToken($type, $value, $line, $matchColumn);
            }
            elseif ($value === ',')
            {
                // Commas always signify the end of the current sub-variable within the same depth
                $depth = count($stack);
                if ($depth > 0 && count($subVariableStack) === $depth)
                {
                    // Emit VariableEnd for the current sub-variable
                    $tokens[] = $this->createToken(TokenType::VariableEnd, '', $line, $matchColumn - 1);
                    array_pop($subVariableStack); // Remove the sub-variable from the stack
                }

                // Emit the comma token
                $tokens[] = $this->createToken(TokenType::Comma, ',', $line, $matchColumn);
            }
            elseif ($value === '~')
            {
                $tokens[] = $this->createToken(TokenType::Concat, '~', $line, $matchColumn);
            }
            elseif ($value === '->')
            {
                // Handle object method/property access
                $tokens[] = $this->createToken(TokenType::MethodOperator, '->', $line, $matchColumn);
            }
            else if (preg_match('/^(true|false|null)$/i', $value))
            {
                // Handle literal values
                $tokens[] = $this->createToken(TokenType::Literal, $value, $line, $matchColumn);
            }
            else if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value))
            {
                // Handle identifiers
                if (!$currentVariableStarted)
                {
                    // Emit VariableStart ONLY for the top-level identifier
                    $tokens[] = $this->createToken(TokenType::VariableStart, $expressionWithFilters, $line, $matchColumn);
                    $currentVariableStarted = true;
                }

                // If the stack size is less than the current depth, start a new sub-variable
                if (count($subVariableStack) < count($stack))
                {
                    $tokens[] = $this->createToken(TokenType::VariableStart, $value, $line, $matchColumn);
                    $subVariableStack[] = $value; // Add the sub-variable to the stack
                }

                $tokens[] = $this->createToken(TokenType::Identifier, $value, $line, $matchColumn);
            }
            elseif (is_numeric($value))
            {
                // Handle numeric tokens
                $tokens[] = $this->createToken(TokenType::Number, $value, $line, $matchColumn);
            }
            elseif (preg_match($stringRegex, $value))
            {
                // Handle string literals (e.g., 'name' or "name")
                $tokens[] = $this->createToken(TokenType::String, $value, $line, $matchColumn);
            }
            else
            {
                throw new LexerException("Unexpected token '{$value}' at line {$line}, column {$matchColumn}");
            }
        }

        // Ensure all brackets/parentheses are properly closed
        if (!empty($stack)) {
            throw new LexerException("Unmatched opening bracket/parenthesis in: {$expression}");
        }

        // Close the top-level variable
        if ($currentVariableStarted)
        {
            $tokens[] = $this->createToken(TokenType::VariableEnd, '', $line, $column);
        }

        return $tokens;
    }

    /**
     * Tokenizes the content inside {{ }} brackets, handling complex expressions.
     *
     * This is the high-level method for variable blocks, similar to how tokenizeDirective()
     * works for {% %} blocks. It delegates to tokenizeCondition() for complex expressions
     * that may contain operators, ternary operators, null coalescing, etc.
     *
     * @param string $expression The full expression inside {{ }}
     * @param int $line The line number
     * @param int $column The column number
     * @return array<Token> Array of tokens
     * @throws LexerException
     */
    private function tokenizeVariableExpression(string $expression, int $line, int $column): array
    {
        // Check if this is a simple variable (no operators, just variable access)
        // Pattern: identifier with only dots, arrows, brackets, parentheses
        $simpleVariablePattern = '/^[a-zA-Z_][a-zA-Z0-9_.|\->\[\]()\'",\s~]*$/';

        // Check for operators that indicate a complex expression
        $hasComplexOperators = preg_match('/(\?\?|\?|:|==|!=|<=|>=|<>|<=>|===|!==|<|>|\band\b|\bor\b|\bxor\b|\bnot\b|\bis\b)/i', $expression);

        if (!$hasComplexOperators && preg_match($simpleVariablePattern, $expression))
        {
            // Simple variable expression - use the original tokenizeVariable()
            return $this->tokenizeVariable($expression, $line, $column);
        }
        else
        {
            // Complex expression with operators
            // tokenizeExpression() will call tokenizeVariable() when it encounters identifiers
            return $this->tokenizeExpression($expression, $line, $column);
        }
    }

    /**
     * Reads a full variable chain starting at the beginning of $remaining.
     *
     * Example outputs:
     * - "app.user->isGuest('test')"
     * - "metadata->itemAt('name')"
     * - "user.roles[0]"
     *
     * @throws LexerException
     */
    private function consumeVariableExpression(string $remaining, int $line, int $column): string
    {
        $len = strlen($remaining);
        $i = 0;

        if ($len === 0 || !preg_match('/^[a-zA-Z_]/', $remaining[0])) {
            throw new LexerException("Expected variable expression at line {$line}, column {$column}");
        }

        $parenDepth = 0;
        $brackDepth = 0;
        $inString = false;
        $stringQuote = '';
        $escape = false;

        while ($i < $len)
        {
            $ch = $remaining[$i];

            if ($inString)
            {
                if ($escape) {
                    $escape = false;
                    $i++;
                    continue;
                }

                if ($ch === '\\') {
                    $escape = true;
                    $i++;
                    continue;
                }

                if ($ch === $stringQuote) {
                    $inString = false;
                    $stringQuote = '';
                    $i++;
                    continue;
                }

                $i++;
                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                $inString = true;
                $stringQuote = $ch;
                $i++;
                continue;
            }

            if ($ch === '(') {
                $parenDepth++;
                $i++;
                continue;
            }

            if ($ch === ')') {
                if ($parenDepth === 0) {
                    break;
                }
                $parenDepth--;
                $i++;
                continue;
            }

            if ($ch === '[') {
                $brackDepth++;
                $i++;
                continue;
            }

            if ($ch === ']') {
                if ($brackDepth === 0) {
                    break;
                }
                $brackDepth--;
                $i++;
                continue;
            }

            // At depth 0, stop when the variable chain ends.
            if ($parenDepth === 0 && $brackDepth === 0)
            {
                if (ctype_space($ch)) {
                    break;
                }

                // Allow method/property operator "->" inside variable expressions.
                // Without this, we would consume the '-' and then stop at the '>' (treated as a comparison operator).
                if ($ch === '-' && ($i + 1) < $len && $remaining[$i + 1] === '>') {
                    $i += 2;
                    continue;
                }

                // Allow nullsafe operator "?->" inside variable expressions (PHP 8.0+).
                // Without this, we would stop at '?' thinking it's a ternary operator.
                if ($ch === '?' && ($i + 2) < $len && $remaining[$i + 1] === '-' && $remaining[$i + 2] === '>') {
                    $i += 3;
                    continue;
                }

                // Stop at pipe only if it's followed by another pipe (logical OR: ||)
                if ($ch === '|' && ($i + 1) < $len && $remaining[$i + 1] === '|') {
                    break;
                }

                // These characters start other condition-level tokens/operators.
                if (str_contains('=<>!&;,:?', $ch)) {
                    break;
                }

                // Range operator is a condition token; stop before it.
                if ($ch === '.' && ($i + 1) < $len && $remaining[$i + 1] === '.') {
                    break;
                }
            }

            $i++;
        }

        if ($inString) {
            $preview = substr($remaining, 0, 60);
            throw new LexerException("Unterminated string in variable expression at line {$line}, column {$column}: {$preview}");
        }

        if ($parenDepth !== 0 || $brackDepth !== 0) {
            throw new LexerException("Unmatched parentheses/brackets in variable expression at line {$line}, column {$column}");
        }

        return substr($remaining, 0, $i);
    }

    /**
     * Tokenizes a directive into an array of tokens based on its type and content.
     *
     * @param string $directive The directive string to tokenize.
     * @param int $line The line number where the directive appears.
     * @param int $column The column number where the directive starts.
     *
     * @return array An array of tokens representing the structured breakdown of the directive.
     *
     * @throws LexerException
     * @throws Exception
     */
    private function tokenizeDirective(string $directive, int $line, int $column): array
    {
        $tokens = [];
        $trimmedDirective = trim($directive);
        $tokens[] = $this->createToken(TokenType::BlockStart, '', $line, $column);
        if (str_starts_with($trimmedDirective, 'if'))
        {
            $trimmedDirective = trim(substr($trimmedDirective, 2));
            $tokens[] = $this->createToken(TokenType::IfStart, 'if', $line, $column);
            array_push($tokens, ...$this->tokenizeCondition($trimmedDirective, $line, $column));
        }
        elseif (str_starts_with($trimmedDirective, 'else') && (strpos($trimmedDirective, 'if') === 4 || strpos($trimmedDirective, 'if') === 5))
        {
            $ifPos = strpos($trimmedDirective, 'if');
            $trimmedDirective = trim(substr($trimmedDirective, $ifPos + 2)); // +2 to skip "if"
            $tokens[] = $this->createToken(TokenType::ElseIf, 'elseif', $line, $column);
            array_push($tokens, ...$this->tokenizeCondition($trimmedDirective, $line, $column));
        }
        elseif ($trimmedDirective == 'else')
        {
            $tokens[] = new Token(TokenType::Else, 'else', $line, $column);
        }
        elseif ($trimmedDirective == 'endif')
        {
            $tokens[] = new Token(TokenType::IfEnd, 'endif', $line, $column);
        }
        else
        {
            $parts = preg_split('/\s+/', $trimmedDirective, 2);
            $directiveName = $parts[0];
            $directiveArgs = $parts[1] ?? '';
            $tokens[] = $this->createToken(TokenType::Directive, $directiveName, $line, $column);
            if (!empty($directiveArgs)) {
                array_push($tokens, ...$this->tokenizeCondition($directiveArgs, $line, $column));
            }
        }

        $tokens[] = new Token(TokenType::BlockEnd, '', $line, $column);
        return $tokens;
    }

    /**
     * Tokenizes a condition expression into its individual tokens.
     *
     * This method scans a string containing a condition and breaks it down into smaller pieces such as:
     * - Identifiers (e.g., variable names or object properties)
     * - Logical Operators (e.g., `and`, `or`, `is`, `&&` etc.)
     * - Operators (e.g., `==`, `!=`, `<=`, etc.)
     * - Keywords (`true`, `false`, `null`)
     * - Numbers (e.g., integers or floats)
     * - Strings (e.g., quoted text)
     *
     * @param string $expression The raw condition string to tokenize.
     * @param int $line The line number where the condition appears (for error reporting).
     * @return Token[] An array of Token objects representing the condition.
     * @throws Exception If the condition contains invalid syntax.
     */
    private function tokenizeCondition(string $expression, int $line, int $column): array
    {
        $tokens = [];
        $position = 0;

        $regexPatterns = [
            'String' => '/^(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")/', // Matches strings with valid escape sequences
            'Literal' => '/^(true|false|null|empty|odd|even|defined)\b/i', // Matches "true", "false", "null" and empty
            'Keyword' => '/^(as|in|with|only)\b/i', // Matches "as", "in" "with" and "only
            'LogicalOperator' => '/^(and|or|xor|\|\||&&|&)\b/i', // Matches logical operators
            'Operator' => '/^(is\s+not\b|is(?=\s)|==|!=|<=?|>=?|!==|===|<>|<=>|[+\-*\/%]|!)/i', // Matches comparison and special operators
            'UnaryOperator' => '/^(not)\b/i',  // AFTER Operator
            'RangeOperator' => '/^\.\./', // Matches the range operator (..)
            'NullCoalesce' => '/^\?\?/',          // Add this (MUST come before Question)
            'Question' => '/^\?/',
            // Identifier start; the full variable chain is consumed by `consumeVariableExpression()`.
            'Identifier' => '/^[a-zA-Z_][a-zA-Z0-9_]*/',
            'Number' => '/^\d+(\.\d+)?/', // Matches integers or floats
            'Increment' => '/^\+\+/', // Matches increment operator (++)
            'MethodOperator' => '/^->/', // Matches method operator (->)
            'OpenParenthesis' => '/^\(/', // Matches open parenthesis
            'CloseParenthesis' => '/^\)/', // Matches close parenthesis
            'DoubleArrow' => '/^=>/',
            'SetOperator' => '/^=(?!=)/',  // matches = not followed by =
            'Colon' => '/^:/',
            'Semicolon' => '/^;/',
            'OpenSquare' => '/^\[/',
            'CloseSquare' => '/^\]/',
            'OpenBrace' => '/^\{/',
            'CloseBrace' => '/^\}/',
            'Comma' => '/^,/', // Matches commas
            'Concat' => '/^~/',
            'Whitespace' => '/^\s+/', // Matches whitespace (to skip over it)
        ];

        // Parse the condition expression one character at a time
        while ($position < strlen($expression))
        {
            $remaining = substr($expression, $position);
            $matched = false;
            $value = '';

            foreach ($regexPatterns as $type => $pattern)
            {
                if (preg_match($pattern, $remaining, $matches))
                {
                    $value = $matches[0];
                    $length = strlen($value);

                    // For identifiers, consume the entire variable chain (including method calls + string args)
                    if ($type === 'Identifier') {
                        $value = $this->consumeVariableExpression($remaining, $line, $column + $position);
                        $length = strlen($value);
                    }

                    // Skip whitespace tokens (not needed in the output)
                    if ($type !== 'Whitespace')
                    {
                        $tokenType = match ($type) {
                            'Literal' => TokenType::Literal, // Handle true, false, null
                            'Keyword' => TokenType::Keyword,
                            'Identifier' => TokenType::Identifier,
                            'LogicalOperator' => TokenType::LogicalOperator,
                            'Operator' => TokenType::Operator,
                            'Number' => TokenType::Number,
                            'String' => TokenType::String,
                            'UnaryOperator' => TokenType::UnaryOperator,
                            'RangeOperator' => TokenType::RangeOperator,
                            'NullCoalesce' => TokenType::NullCoalesce,
                            'Question' => TokenType::Question,
                            'Increment' => TokenType::Increment,
                            'MethodOperator' => TokenType::MethodOperator,
                            'OpenParenthesis' => TokenType::OpenParen,
                            'CloseParenthesis' => TokenType::CloseParen,
                            'DoubleArrow' => TokenType::DoubleArrow,
                            'SetOperator' => TokenType::SetOperator,
                            'Colon' => TokenType::Colon,
                            'Semicolon' => TokenType::Semicolon,
                            'OpenSquare' => TokenType::OpenSquare,
                            'CloseSquare' => TokenType::CloseSquare,
                            'OpenBrace' => TokenType::OpenBrace,
                            'CloseBrace' => TokenType::CloseBrace,
                            'Comma' => TokenType::Comma,
                            'Concat' => TokenType::Concat,
                            'Whitespace' => TokenType::Space,
                            default => throw new LexerException("Unexpected token type: $type"),
                        };

                        if ($tokenType == TokenType::Identifier)
                        {
                            array_push($tokens, ...$this->tokenizeVariable($value, $line, $column + $position));
                        }
                        else
                        {
                            $tokens[] = $this->createToken($tokenType, $value, $line, $column + $position);
                        }
                    }

                    $position += $length;
                    $matched = true;
                    break;
                }
            }

            // If no match, we need to throw an exception
            if (!$matched)
            {
                $column += $position;
                $data = [
                    'tokens' => $tokens,
                    'expression' => $expression,
                    'remaining_string' => $remaining,
                    'line' => $line,
                    'position' => $position,
                    'column' => $column,
                ];

                // Additional check: Validate potential malformed strings
                $details = "at line $line, at column $column, position $position: ". substr($remaining, 0, 20);
                if ($remaining[0] === '"' || $remaining[0] === "'") {
                    throw new LexerException("Malformed string ". $details, $data);
                }

                // If no pattern matches, throw an exception for invalid syntax
                throw new LexerException("Invalid syntax in condition ". $details, $data);
            }
        }

        return $tokens;
    }

    /**
     * Tokenizes an expression inside {{ }} brackets.
     *
     * This method handles complex expressions with operators, ternary operators,
     * null coalescing, etc. It's similar to tokenizeCondition() but specifically
     * designed for variable expression contexts.
     *
     * @param string $expression The expression string to tokenize
     * @param int $line The line number where the expression appears
     * @param int $column The column number where the expression starts
     * @return Token[] An array of Token objects representing the expression
     * @throws LexerException If the expression contains invalid syntax
     */
    private function tokenizeExpression(string $expression, int $line, int $column): array
    {
        $tokens = [];
        $trimmedDirective = trim($expression);
        $tokens[] = $this->createToken(TokenType::ExpressionStart, $expression, $line, $column);
        $position = 0;

        $regexPatterns = [
            'String' => '/^(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")/',
            'Literal' => '/^(true|false|null)\b/i',
            'LogicalOperator' => '/^(and|or|xor|\|\||&&)\b/i',
            'Operator' => '/^(is\s+not\b|is(?=\s)|==|!=|<=?|>=?|!==|===|<>|<=>|[+\-*\/%]|!)/i',
            'UnaryOperator' => '/^(not|!)\b/i',
            'NullCoalesce' => '/^\?\?/',          // MUST come before Question
            'Question' => '/^\?/',                // Ternary operator
            'Identifier' => '/^[a-zA-Z_][a-zA-Z0-9_]*/',
            'Number' => '/^\d+(\.\d+)?/',
            'MethodOperator' => '/^->/',
            'OpenParenthesis' => '/^\(/',
            'CloseParenthesis' => '/^\)/',
            'Colon' => '/^:/',
            'OpenSquare' => '/^\[/',
            'CloseSquare' => '/^\]/',
            'Comma' => '/^,/',
            'Concat' => '/^~/',
            'Whitespace' => '/^\s+/',
        ];

        while ($position < strlen($expression))
        {
            $remaining = substr($expression, $position);
            $matched = false;

            foreach ($regexPatterns as $type => $pattern)
            {
                if (preg_match($pattern, $remaining, $matches))
                {
                    $value = $matches[0];
                    $length = strlen($value);

                    // For identifiers, consume the entire variable chain
                    if ($type === 'Identifier') {
                        $value = $this->consumeVariableExpression($remaining, $line, $column + $position);
                        $length = strlen($value);
                    }

                    // Skip whitespace tokens
                    if ($type !== 'Whitespace')
                    {
                        $tokenType = match ($type) {
                            'Literal' => TokenType::Literal,
                            'Identifier' => TokenType::Identifier,
                            'LogicalOperator' => TokenType::LogicalOperator,
                            'Operator' => TokenType::Operator,
                            'Number' => TokenType::Number,
                            'String' => TokenType::String,
                            'UnaryOperator' => TokenType::UnaryOperator,
                            'NullCoalesce' => TokenType::NullCoalesce,
                            'Question' => TokenType::Question,
                            'MethodOperator' => TokenType::MethodOperator,
                            'OpenParenthesis' => TokenType::OpenParen,
                            'CloseParenthesis' => TokenType::CloseParen,
                            'Colon' => TokenType::Colon,
                            'OpenSquare' => TokenType::OpenSquare,
                            'CloseSquare' => TokenType::CloseSquare,
                            'Comma' => TokenType::Comma,
                            'Concat' => TokenType::Concat,
                            default => throw new LexerException("Unexpected token type: $type"),
                        };

                        if ($tokenType == TokenType::Identifier)
                        {
                            // Tokenize the variable chain
                            array_push($tokens, ...$this->tokenizeVariable($value, $line, $column + $position));
                        }
                        else
                        {
                            $tokens[] = $this->createToken($tokenType, $value, $line, $column + $position);
                        }
                    }

                    $position += $length;
                    $matched = true;
                    break;
                }
            }

            if (!$matched)
            {
                $column += $position;
                $details = "at line $line, column $column: " . substr($remaining, 0, 20);
                throw new LexerException("Invalid syntax in expression " . $details);
            }
        }

        $tokens[] = new Token(TokenType::ExpressionEnd, '', $line, $column + $position);
        return $tokens;
    }

    /**
     * Calculates the line number in a given string based on a specified position.
     *
     * @param string $input The input string to evaluate.
     * @param int $position The position within the string to determine the line number.
     *
     * @return int The line number corresponding to the given position in the string.
     */
    private function getLineNumber(string $input, int $position): int
    {
        return substr_count($input, "\n", 0, $position) + 1;
    }

    /**
     * Determines the column number in a given string based on a specified position.
     *
     * @param string $input The input string to analyze.
     * @param int $position The position within the string to determine the column number.
     *
     * @return int The column number corresponding to the given position in the string.
     */
    private function getColumnNumber(string $input, int $position): int
    {
        $lastNewlinePos = strrpos(substr($input, 0, $position), "\n");
        return $lastNewlinePos === false ? $position + 1 : $position - $lastNewlinePos;
    }

    /**
     * Extracts verbatim blocks from the template and replaces them with placeholders.
     * Returns an array with the modified template and the extracted verbatim content.
     *
     * @param string $template The template string to process
     * @return array{0: string, 1: array<string, string>} Modified template and verbatim blocks map
     *
     * @throws LexerException If verbatim blocks are not properly closed
     */
    private function extractVerbatimBlocks(string $template): array
    {
        $verbatimBlocks = [];
        $counter = 0;

        // Pattern to match {% verbatim %}...{% endverbatim %} blocks
        $pattern = '/\{%\s*verbatim\s*%\}(.*?)\{%\s*endverbatim\s*%\}/s';

        $modifiedTemplate = preg_replace_callback($pattern,
            function($matches) use (&$verbatimBlocks, &$counter)
            {
                $placeholder = "___VERBATIM_BLOCK_{$counter}___";
                $content = $matches[1];
                $verbatimBlocks[$placeholder] = $content;

                // Count newlines in the original block
                $newlineCount = substr_count($matches[0], "\n");

                // Replace it with placeholder + enough newlines to preserve line count for error reporting purposes
                $replacement = $placeholder . str_repeat("\n", $newlineCount);

                $counter++;
                return $replacement;
            }, $template
        );

        // Check for unclosed verbatim blocks
        if (preg_match('/\{%\s*verbatim\s*%\}/', $modifiedTemplate)) {
            throw new LexerException("Unclosed {% verbatim %} block found in template");
        }

        if (preg_match('/\{%\s*endverbatim\s*%\}/', $modifiedTemplate)) {
            throw new LexerException("Found {% endverbatim %} without matching {% verbatim %} in template");
        }

        return [$modifiedTemplate, $verbatimBlocks];
    }

    /**
     * Restores verbatim blocks in the token stream by replacing placeholder text tokens
     * with the original verbatim content.
     *
     * @param Token[] $tokens The array of tokens to process
     * @param array<string, string> $verbatimBlocks Map of placeholders to verbatim content
     * @return Token[] The modified token array with verbatim content restored
     */
    private function restoreVerbatimBlocks(array $tokens, array $verbatimBlocks): array
    {
        if (empty($verbatimBlocks)) {
            return $tokens;
        }

        foreach ($tokens as $index => $token)
        {
            if ($token->type === TokenType::Text)
            {
                // Check if this text token contains any verbatim placeholders
                foreach ($verbatimBlocks as $placeholder => $content)
                {
                    if (str_contains($token->value, $placeholder))
                    {
                        // Replace the placeholder with the actual verbatim content
                        $tokens[$index] = new Token(
                            TokenType::Text,
                            str_replace($placeholder, $content, $token->value),
                            $token->line,
                            $token->column
                        );
                    }
                }
            }
        }

        return $tokens;
    }
}