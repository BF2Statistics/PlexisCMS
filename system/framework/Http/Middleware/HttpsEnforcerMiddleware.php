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
namespace System\Http\Middleware;

use Exception;
use System;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;

/**
 * Middleware for handling and enforcing HTTPS connections.
 *
 * This middleware ensures that incoming HTTP requests are redirected to their
 * HTTPS equivalents if the `force_https` configuration is enabled. If the incoming
 * request is already secure or the `force_https` option is disabled, it delegates
 * control to the next middleware or handler in the chain.
 */
class HttpsEnforcerMiddleware implements MiddlewareInterface
{

    /**
     * Processes the given request and determines if it should be redirected to an HTTPS URL.
     *
     * If the `force_https` configuration option is enabled and the request is not secure,
     * the method redirects the request to its HTTPS equivalent. Otherwise, it executes
     * the next middleware or handler in the processing chain.
     *
     * @param Request $request The incoming HTTP request to process.
     * @param callable $next The next middleware or handler to call in the chain.
     *
     * @return Response The resulting HTTP response, either a redirect to HTTPS or the response from the next handler.
     * @throws Exception
     */
    public function process(Request $request, callable $next): Response
    {
        // If the `force_https` configuration option is enabled, all non-HTTPS requests are redirected to the HTTPS equivalent URL
        if (System::Config()->get('force_https') && !Request::IsSecure())
        {
            System::Log()->logDebug("Redirecting to HTTPS");

            $response = new Response($request);
            $site_url = ltrim(Request::Domain(), '.') . $request->getPath();
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
            $response->redirect('https://' . $site_url, temporaryStatus: 302);

            return $response;
        }

        return $next($request);
    }
}