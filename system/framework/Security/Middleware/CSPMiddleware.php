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
namespace System\Security\Middleware;

use Random\RandomException;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;
use System\IO\Path;
use System\Presentation\View;
use System\Security\ContentSecurityPolicyInterface;

/**
 * Middleware for applying Content Security Policy (CSP) to HTTP responses.
 *
 * This middleware dynamically generates a Content-Security-Policy header based on
 * directives specified in a configuration file or defined programmatically, and applies
 * the header to outgoing HTTP responses to enhance security by controlling the resources
 * that the browser is allowed to load.
 */
class CSPMiddleware implements MiddlewareInterface
{
    /**
     * @var ContentSecurityPolicyInterface
     */
    protected ContentSecurityPolicyInterface $policy;

    /**
     * @var string|null
     */
    protected ?string $nonce = null;

    /**
     * @var bool
     */
    protected bool $enableNonce;

    /**
     * Constructor for initializing the class with a Content Security Policy and nonce option.
     *
     * @param ContentSecurityPolicyInterface $csp The content security policy instance.
     * @param bool $enableNonce Optional. Whether to enable nonce generation. Default is true.
     *
     * @return void
     */
    public function __construct(ContentSecurityPolicyInterface $csp, bool $enableNonce = true)
    {
        $this->enableNonce = $enableNonce;
        $this->policy = $csp;
    }

    /**
     * Process method for applying CSP via headers
     *
     * @param Request $request
     * @param callable $next
     *
     * @return Response
     * @throws RandomException
     */
    public function process(Request $request, callable $next): Response
    {
        if ($request->isAjax()) {
            return $next($request);
        }

        // Generate a nonce for this request
        if ($this->enableNonce)
        {
            $this->nonce = $this->policy->generateNonce();

            // Tell the view class
            View::SetCspNonce($this->nonce);

            if ($request->session() !== null)
            {
                $request->session()->set('csp_nonce', $this->nonce);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        // Generate the CSP header
        $cspHeader = $this->policy->build();

        // Set the Content-Security-Policy header
        $response->setHeader('Content-Security-Policy', $cspHeader);

        return $response;
    }
}