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

namespace Application\Middleware;

use System\ArgumentException;
use System\Http\Request;
use System\Http\Response;

class MaintenanceMiddleware
{
    private array $allowedIPs = ['127.0.0.1', '192.168.1.1'];

    /**
     * @throws ArgumentException
     */
    public function process(callable $next, Request $request)
    {
        $isInMaintenance = true; // Set via a config or environment variable

        if ($isInMaintenance && !in_array(Request::ClientIp(), $this->allowedIPs))
        {
            $response = new Response('The site is under maintenance');
            $response->statusCode(503);
            return $response;
        }

        return $next($request);
    }

}