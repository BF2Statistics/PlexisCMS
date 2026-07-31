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

use Exception;
use System;
use System\Cache\CacheInterface;
use System\Cache\CacheService;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;
use System\Http\JsonResponse;

/**
 * Simple per-IP throttle middleware using a sliding window counter.
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    /**
     * The maximum number of requests allowed within the time window.
     */
    protected int $rateLimit;

    /**
     * The size of the time window in seconds.
     */
    protected int $timeWindow;

    /**
     * Cache instance used to track requests per IP.
     */
    protected CacheInterface $cache;

    /**
     * Creates a new throttle middleware.
     *
     * @param int $rateLimit  Max requests allowed in window.
     * @param int $timeWindow Window length in seconds.
     *
     * @return void
     * @throws Exception
     */
    public function __construct(int $rateLimit, int $timeWindow)
    {
        $this->rateLimit = $rateLimit;
        $this->timeWindow = $timeWindow;
        $this->cache = CacheService::Default();
    }

    /**
     * Processes the incoming HTTP request and applies rate-limiting logic.
     *
     * If the rate limit is exceeded for the client (identified e.g., by IP),
     * a 429 (Too Many Requests) response is returned. Otherwise, the request
     * is forwarded to the next middleware in the pipeline.
     *
     * @param Request $request The incoming HTTP request.
     * @param callable $next The next middleware or handler to invoke.
     *
     * @return Response The final HTTP response.
     *
     * @throws Exception If there are issues with the caching mechanism or request processing.
     */
    public function process(Request $request, callable $next): Response
    {
        // Identify the client (e.g., by IP)
        $clientIdentifier = 'Throttle:' . Request::ClientIp();

        // Retrieve the request count and timestamp from the cache
        if (!$this->cache->has($clientIdentifier))
        {
            $this->cache->set($clientIdentifier, [
                'count' => 0,
                'timestamp' => time(),
            ], $this->timeWindow);
        }

        // Retrieve data from the cache
        $requestData = $this->cache->get($clientIdentifier);
        if ($requestData === null) {
            throw new Exception('Unable to retrieve request data from cache.');
        }

        // Setup variables
        $currentTime = time();
        $requestCount = (int) $requestData['count'];
        $firstRequestTime = (int) $requestData['timestamp'];

        // Check if the time window has expired
        $timeLeft = ($firstRequestTime + $this->timeWindow) - $currentTime;
        if ($currentTime - $firstRequestTime > $this->timeWindow)
        {
            // Reset the window
            $requestCount = 0;
            $firstRequestTime = $currentTime;

            // Recalculate $timeLeft for the new window
            $timeLeft = $this->timeWindow;
        }

        // Increment the request count
        $requestCount++;

        // Check if the rate limit has been exceeded
        if ($requestCount > $this->rateLimit)
        {
            // Log
            System::Log()->logSecurity('Rate limit exceeded for client IP ' . Request::ClientIp());

            // Create a response
            if ($request->isAjax())
            {
                $response = new JsonResponse($request);
                $response->statusCode(429); // Too many requests
                $response->append([
                    'success' => false,
                    'error' => 'Too Many Requests',
                    'message' => 'You have exceeded the rate limit. Please try again later.',
                    'retry_after' => $timeLeft,
                ]);
            }
            else
            {
                $response = new Response($request);
                $response->statusCode(429); // Too many requests
                $response->body('Too many requests');
                $this->setHeaders($response, $requestCount, $firstRequestTime);
            }

            return $response;
        }

        // Update the cache
        $this->cache->set($clientIdentifier, [
            'count' => $requestCount,
            'timestamp' => $firstRequestTime,
        ], max(1, $timeLeft));

        // Pass the request to the next middleware/handler
        $response = $next($request);

        // Update headers
        $this->setHeaders($response, $requestCount, $firstRequestTime);

        // Return the final response
        return $response;
    }

    /**
     * Sets rate-limiting headers on the provided response object.
     *
     * Adds headers such as:
     * - `X-RateLimit-Limit`: The maximum number of requests allowed.
     * - `X-RateLimit-Remaining`: The remaining number of requests available in the current window.
     * - `X-RateLimit-Reset`: The UNIX timestamp when the current window resets.
     * - `Retry-After`: The number of seconds the client must wait before retrying.
     *
     * @param Response $response The response object to add headers to.
     * @param int $requestCount The number of requests made by the client in the current window.
     * @param int $firstRequestTime The timestamp of the first request in the current time window.
     *
     * @return void
     */
    protected function setHeaders(Response $response, int $requestCount, int $firstRequestTime): void
    {
        $response->headers([
            'X-RateLimit-Limit' => $this->rateLimit,
            'X-RateLimit-Remaining' => max(0, $this->rateLimit - $requestCount),
            'X-RateLimit-Reset' => $firstRequestTime + $this->timeWindow,
            'Retry-After' => ($firstRequestTime + $this->timeWindow) - time()
        ]);
    }
}