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
use System\Presentation\Engine\TokenType;

/**
 * Registry for managing compiler strategies.
 * Maps TokenTypes to their corresponding strategy instances.
 */
class CompilerStrategyRegistry
{
    /**
     * @var array<string, AbstractCompilerStrategy>
     */
    private array $strategies = [];

    /**
     * Constructor
     *
     * @param Compiler $compiler The compiler instance to pass to strategies
     */
    public function __construct(Compiler $compiler)
    {
        // Register all compiler strategies
        $this->registerStrategy('insert', new InsertCompilerStrategy($compiler));
        $this->registerStrategy('include', new IncludeCompilerStrategy($compiler));
        $this->registerStrategy('extends', new ExtendsCompilerStrategy($compiler));
        $this->registerStrategy('asset', new AssetCompilerStrategy($compiler));
        $this->registerStrategy('if', new IfCompilerStrategy($compiler));
        $this->registerStrategy('foreach', new ForeachCompilerStrategy($compiler));
        $this->registerStrategy('for', new ForCompilerStrategy($compiler));
        $this->registerStrategy('run', new RunCompilerStrategy($compiler));
        $this->registerStrategy('set', new SetCompilerStrategy($compiler));
    }

    /**
     * Register a new compiler strategy.
     *
     * @param string $name The directive name
     * @param AbstractCompilerStrategy $strategy The strategy instance
     */
    public function registerStrategy(string $name, AbstractCompilerStrategy $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    /**
     * Get the strategy for a specific directive type.
     *
     * @param string $name The directive name
     *
     * @return AbstractCompilerStrategy The strategy for this directive
     *
     * @throws CompilerException If no strategy is registered for this type
     */
    public function getStrategy(string $name): AbstractCompilerStrategy
    {
        if (!isset($this->strategies[$name])) {
            throw new CompilerException("No strategy registered for directive type: {$name}");
        }

        return $this->strategies[$name];
    }

    /**
     * Check if a strategy is registered for a specific directive type.
     *
     * @param string $name The directive name
     *
     * @return bool True if a strategy is registered, false otherwise
     */
    public function hasStrategy(string $name): bool
    {
        return isset($this->strategies[$name]);
    }
}
