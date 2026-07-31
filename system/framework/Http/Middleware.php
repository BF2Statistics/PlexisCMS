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
namespace System\Http;

use InvalidArgumentException;

/**
 * Attribute to define middleware for controllers or methods.
 *
 * This attribute allows associating a specific middleware class
 * (implementing `MiddlewareInterface`) and optionally parameters to be passed
 * to the middleware. It can target controllers or methods.
 *
 * @see MiddlewareInterface The interface the middleware class must implement.
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Middleware
{
    /**
     * The fully qualified class name of the middleware.
     *
     * The class must implement the `MiddlewareInterface`.
     *
     * @var class-string<MiddlewareInterface>
     */
    public string $middlewareClass;

    /**
     * Parameters to be passed to the middleware during its execution.
     *
     * @var array
     */
    public array $parameters;

    /**
     * Initializes a new instance of the Middleware attribute.
     *
     * @param class-string<MiddlewareInterface> $middlewareClass The middleware's fully qualified class name,
     *                                                           implementing `MiddlewareInterface`.
     * @param mixed ...$parameters Optional parameters to pass to the middleware.
     *
     * @throws InvalidArgumentException If the provided middleware class does not implement `MiddlewareInterface`.
     */
    public function __construct(string $middlewareClass, mixed ...$parameters)
    {
        if (!is_subclass_of($middlewareClass, MiddlewareInterface::class)) {
            throw new InvalidArgumentException(
                "The middleware class '{$middlewareClass}' must implement MiddlewareInterface."
            );
        }

        $this->middlewareClass = $middlewareClass;
        $this->parameters = $parameters;
    }
}