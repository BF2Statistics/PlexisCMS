<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace System\Security\Middleware;

use Application\Security\UserIdentity;
use System\Http\HttpForbiddenException;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;

/**
 * Middleware to ensure that a user has the required permissions before processing a request.
 *
 * This middleware checks whether the current user has the necessary permissions.
 * If the user does not have the required permissions, a forbidden response is generated.
 * If the user is authorized, it proceeds with the next middleware or final handler.
 *
 * @package App\Middleware
 */
class RequiredPermissionMiddleware implements MiddlewareInterface
{
    /**
     * @var array The list of permissions required to access the resource.
     */
    protected array $permissions;

    /**
     * Constructor for RequiredPermissionMiddleware.
     *
     * @param string ...$permissions The list of permissions required to access the resource.
     */
    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * Processes the request and ensures that the user has the required permissions
     * before calling the next middleware or final handler.
     *
     * @param Request $request The current HTTP request.
     * @param callable $next The next middleware or final handler in the pipeline.
     *
     * @return Response The processed HTTP response if the user has the required permissions.
     * @throws HttpForbiddenException thrown if the user does not have the required permissions.
     */
    public function process(Request $request, callable $next): Response
    {
        // Let's insure the user is allowed here!
        $user = $request->session()?->getUser();
        if (!($user instanceof UserIdentity))
        {
            Forbidden:
            {
                throw new HttpForbiddenException($request, 'You are not allowed to access this page.');
            }
        }

        // Ensure user has each required permission
        foreach ($this->permissions as $permission)
        {
            if (!$user->isGranted($permission))
            {
                goto Forbidden;
            }
        }

        // Continue down the pipeline
        return $next($request);
    }
}