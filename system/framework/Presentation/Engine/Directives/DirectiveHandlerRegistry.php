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
namespace System\Presentation\Engine\Directives;

use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ParsingException;
use System\Presentation\Engine\TokenType;

/**
 * Registry for managing directive handlers.
 * Maps TokenTypes to their corresponding handler instances.
 */
class DirectiveHandlerRegistry
{
    /**
     * @var array<string, AbstractDirectiveHandler>
     */
    private array $handlers = [];

    /**
     * Constructor
     *
     * @param Parser $parser The parser instance to pass to handlers
     */
    public function __construct(Parser $parser)
    {
        // Register all directive handlers
        $this->registerHandler('insert', new InsertDirectiveHandler($parser));
        $this->registerHandler('include', new IncludeDirectiveHandler($parser));
        $this->registerHandler('extends', new ExtendsDirectiveHandler($parser));
        $this->registerHandler('asset', new AssetDirectiveHandler($parser));
        $this->registerHandler('if', new IfDirectiveHandler($parser));
        $this->registerHandler('foreach', new ForeachDirectiveHandler($parser));
        $this->registerHandler('for', new ForDirectiveHandler($parser));
        $this->registerHandler('run', new RunDirectiveHandler($parser));
        $this->registerHandler('set', new SetDirectiveHandler($parser));
    }

    /**
     * Register a new directive handler.
     *
     * @param string $name The directive name (e.g., "foreach", "custom")
     * @param AbstractDirectiveHandler $handler The handler instance
     */
    public function registerHandler(string $name, AbstractDirectiveHandler $handler): void
    {
        $this->handlers[$name] = $handler;
    }

    /**
     * Get the handler for a specific directive type.
     *
     * @param string $name The directive name
     *
     * @return AbstractDirectiveHandler The handler for this directive
     *
     * @throws ParsingException If no handler is registered for this type
     */
    public function getHandler(string $name): AbstractDirectiveHandler
    {
        if (!isset($this->handlers[$name])) {
            throw new ParsingException("No handler registered for directive type: {$name}");
        }

        return $this->handlers[$name];
    }

    /**
     * Check if a handler is registered for a specific directive type.
     *
     * @param string $name The directive name
     *
     * @return bool True if a handler is registered, false otherwise
     */
    public function hasHandler(string $name): bool
    {
        return isset($this->handlers[$name]);
    }
}
