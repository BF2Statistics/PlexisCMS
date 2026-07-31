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
namespace System\Routing;

use System\Http\Dispatcher;
use System\Http\HttpMethod;
use System\Http\Request;

/**
 * Represents a Route used to define routing for specific URL paths, their associated names, HTTP methods,
 * and additional metadata such as AJAX-specific flags.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    /**
     * Default regular expression when none is defined in the parameter
     */
    public const string DEFAULT_REGEX = '[\w\-]+';

    /**
     * @var array $parameters Keeps the parameters cached with the associated regex
     */
    private array $parameters = [];

    /**
     * @var bool
     */
    private bool $parametersFetched = false;

    /**
     * Initializes a new instance of the class with the provided path, name, methods, and additional flags.
     *
     * @param string $path The route path.
     * @param string $name The name of the route, used for fast lookups by name. Defaults to an empty string.
     *  If not provided, the path will be used as the name.
     * @param array $methods The HTTP methods associated with the route. Defaults to []. If not provided, the route
     *  will be accessible via all HTTP methods.
     * @param bool $isAjax Indicates whether the route is restricted to AJAX requests. Defaults to false.
     * @param bool $isInternal Indicates whether the route is marked as internal. If true, this route
     *  cannot be called via the URL or {@see Dispatcher::process()} on the initial {@see Request}. Defaults to false.
     *
     * @return void
     */
    public function __construct(
        private string $path,
        private string $name = '',
        private array $methods = [],
        private bool $isAjax = false,
        private bool $isInternal = false
    ) {
        if (empty($this->name)) {
            $this->name = $this->path;
        }

        if (empty($this->methods))
        {
            $this->methods = HttpMethod::cases();
        }
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    /**
     * Checks the presence of parameters in the path of the route
     *
     * @return bool
     */
    public function hasParams(): bool
    {
        return count($this->fetchParams()) > 0;
    }

    /**
     * Retrieves in key of the array, the names of the parameters as well as the regular expression (if there is one)
     * in value
     *
     * @return array
     */
    public function fetchParams(): array
    {
        if (!$this->parametersFetched)
        {
            preg_match_all('/{([\w\-%]+)(?:<(.+?)>)?}/', $this->path, $params);
            $this->parameters = array_combine($params[1], $params[2]);
            $this->parametersFetched = true;
        }

        return $this->parameters;
    }
}