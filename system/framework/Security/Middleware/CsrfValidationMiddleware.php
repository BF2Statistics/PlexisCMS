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

use RuntimeException;
use System;
use System\Http\HttpMethod;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;

/**
 * Middleware for validating CSRF tokens in HTTP requests.
 *
 * Helps protect against Cross-Site Request Forgery attacks by ensuring
 * that sensitive HTTP methods (POST, PUT, DELETE) include a valid CSRF token.
 */
class CsrfValidationMiddleware implements MiddlewareInterface
{
    /**
     * The key used to store the CSRF token in the session.
     */
    private const string CSRF_TOKEN_SESSION_KEY = 'csrf_token';

    /**
     * Processes the request and validates the CSRF token.
     *
     * @param Request $request The current HTTP request.
     * @param callable $next The next middleware or final handler.
     *
     * @return Response The processed HTTP response.
     * @throws \Exception
     */
    public function process(Request $request, callable $next): Response
    {
        // Check if the request method requires CSRF validation
        if (!$this->validateCsrfToken($request))
        {
            return System::GenerateForbiddenResponse($request, 'CSRF token missing or invalid.');
        }

        // Call the next middleware or handler
        return $next($request);
    }

    /**
     * Validates the CSRF token for POST, PUT and DELETE requests to protect against Cross-Site Request Forgery attacks.
     *
     *  Ensures that the submitted token exists and matches the token stored in the session.
     *  Terminates the request if the token is missing or invalid.
     *
     * @param Request $request The incoming HTTP request object containing the method and parameters.
     *
     * @return bool
     */
    public function validateCsrfToken(Request $request): bool
    {
        // We need a session to store the CSRF token
        if ($request->session() === null) {
            throw new RuntimeException("Session not initialized");
        }

        // Check if enabled
        if (!System::Config()->get('csrf_enable', true)) {
            return true;
        }

        // Only validate on valid http methods
        if (in_array($request->method(), [HttpMethod::POST, HttpMethod::PUT, HttpMethod::DELETE]))
        {
            // Fetch submitted token
            $submittedToken = $request->post('csrf_token') ?? $request->server('HTTP_X_CSRF_TOKEN');

            // Check if token matches and exists in the session
            if (empty($submittedToken))
            {
                // Check if token exists
                if (empty($clientToken)) {
                    return false;
                }
            }

            // If we have an invalid token, lets check the old csrf token and validate that
            $session = $request->session();
            if ($submittedToken !== $session->get(self::CSRF_TOKEN_SESSION_KEY))
            {
                System::Log()->logWarning(
                    sprintf("CSRF protection failed: Invalid token for %s %s",
                        $request->method()->value,
                        $request->getPath()
                    )
                );
                return false;
            }
        }

        return true;
    }
}