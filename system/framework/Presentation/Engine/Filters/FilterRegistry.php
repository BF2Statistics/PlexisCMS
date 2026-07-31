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
namespace System\Presentation\Engine\Filters;

use System\Presentation\Engine\CompilerException;

/**
 * Registry for managing template filters.
 * Maps filter names (e.g., 'upper') to PHP function names (e.g., 'strtoupper').
 */
class FilterRegistry
{
    /**
     * @var array<string, string>
     */
    private array $filters = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register default filters
        $this->register('upper', 'strtoupper');
        $this->register('lower', 'strtolower');
        $this->register('capitalize', 'ucfirst');
        $this->register('length', 'mb_strlen');
        $this->register('escape', 'htmlspecialchars');
        $this->register('e', 'htmlspecialchars');
        $this->register('reverse', 'strrev');
        $this->register('count', 'count');
    }

    /**
     * Register a new filter.
     *
     * @param string $name The name of the filter (used in templates).
     * @param string $function The PHP function name to map to.
     */
    public function register(string $name, string $function): void
    {
        $this->filters[$name] = $function;
    }

    /**
     * Get the PHP function name for a filter.
     *
     * @param string $name The filter name.
     * @return string The PHP function name.
     * @throws CompilerException If the filter is not found.
     */
    public function get(string $name): string
    {
        if (!isset($this->filters[$name])) {
            throw new CompilerException("Unknown filter: {$name}");
        }
        return $this->filters[$name];
    }

    /**
     * Check if a filter exists.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }
}
