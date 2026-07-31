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

use System\Presentation\Engine\Compiler;
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\DirectiveNode;
use System\Presentation\Engine\TokenType;

/**
 * Handles the compilation of widget directives within a templating engine.
 * Converts widget expressions into executable PHP code, allowing dynamic rendering
 * of widgets based on specified route names and parameters.
 */
class RunCompilerStrategy extends AbstractCompilerStrategy
{

    /**
     * Constructor method for initializing the class with a compiler instance.
     *
     * @param Compiler $compiler The compiler instance used for processing.
     * @return void
     */
    public function __construct(Compiler $compiler)
    {
        parent::__construct($compiler);
    }

    /**
     * Compiles a directive node into a PHP code string that renders a widget.
     *
     * @param DirectiveNode $node The directive node to be compiled. The first node should contain
     *                             the route name as a string, followed by any parameters.
     *
     * @return string The compiled PHP code that executes the widget rendering.
     *
     * @throws CompilerException If the directive node is missing a route name or if the route
     *                           name is not a valid string token.
     */
    public function compile(DirectiveNode $node): string
    {
        $nodes = $node->getNodes();

        if (empty($nodes)) {
            throw new CompilerException("Widget directive missing route name");
        }

        // The first node is the route name (string token)
        $routeNameToken = $nodes[0]->token;
        if ($routeNameToken->type !== TokenType::String) {
            throw new CompilerException("Widget directive route name must be a string");
        }

        // Remove quotes from route name
        $routeName = trim($routeNameToken->value, '\'"');

        // Remaining nodes are the parameters
        $paramNodes = array_slice($nodes, 1);

        // Compile parameters to PHP
        $paramsArray = $this->compileParamNodes($paramNodes);

        return "echo \$this->renderWidget('{$routeName}', {$paramsArray}); ?>";
    }
}
