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

interface MiddlewareInterface
{
    /**
     * Processes the request and calls the next middleware.
     *
     * @param Request $request The current HTTP request.
     * @param callable $next The next middleware or final handler.
     * @return Response The processed HTTP response.
     */
    public function process(Request $request, callable $next): Response;
}