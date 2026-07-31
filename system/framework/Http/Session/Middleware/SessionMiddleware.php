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
namespace System\Http\Session\Middleware;

use Exception;
use Random\RandomException;
use System\Configuration\ConfigManager;
use System\Diagnostics\LogWriter;
use System\Http\Cookie;
use System\Http\MiddlewareInterface;
use System\Http\Request;
use System\Http\Response;
use System\Http\SameSiteSetting;
use System\Http\Session\Containers\EncryptedContainer;
use System\Http\Session\SessionHandler;
use System\Http\Session\SessionInterface;
use System\IO\FileNotFoundException;
use System\IO\Path;
use System\Security\Cryptography\Aes;

/**
 * Class SessionMiddleware
 *
 * Middleware responsible for handling session initialization and management
 * within HTTP requests, ensuring secure session handling, user identity
 * initialization, and propagating the session through the request lifecycle.
 *
 * @package System\Http\Session\Middleware
 */
class SessionMiddleware implements MiddlewareInterface
{
    /**
     * @var SessionInterface|null The current session instance being used.
     */
    protected ?SessionInterface $session = null;

    /**
     * SessionMiddleware constructor.
     *
     * Initializes the session middleware, using a custom session interface if provided,
     * or loading the configured session handler as a fallback.
     *
     * @param SessionInterface|null $customInterface Optional custom session interface.
     *
     * @throws FileNotFoundException If the session configuration file is not found.
     */
    public function __construct(?SessionInterface $customInterface = null)
    {
        // Load the session handler
        $this->session = $customInterface ?? $this->loadSessionHandler();
    }

    /**
     * Processes the incoming HTTP request, initializes the session system,
     * propagates the session to the request object, and continues down the pipeline.
     *
     * @param Request $request The incoming HTTP request.
     * @param callable $next The next middleware or request handler to process.
     *
     * @return Response The resulting HTTP response.
     *
     * @throws Exception If an error occurs during session handling or initialization.
     */
    public function process(Request $request, callable $next): Response
    {
        // Load session cookie
        $sessionId = $this->loadSessionCookie($request);

        // Initialize session
        $this->session->start($sessionId);

        // Set session
        $request->session($this->session);

        // Proceed down the pipeline
        try
        {
            return $next($request);
        }
        finally
        {
            // Check for a regenerated session ID
            if ($this->session->isIdRegenerated())
            {
                $this->setSessionCookie($this->session->getId(), time() + 1800);
            }
        }
    }

    /**
     * Loads and initializes the session handler, based on the session configuration.
     *
     * @return SessionInterface The initialized session handler.
     *
     * @throws FileNotFoundException If the session configuration file is not found.
     */
    protected function loadSessionHandler(): SessionInterface
    {
        // Load the session configuration file
        $filePath = Path::Combine(SYSTEM_DIR, 'config', 'session.php');
        $config = ConfigManager::Load($filePath);

        // Initialize variables
        $driver = null;
        $ttl = $config['session_ttl'];

        // Determine the appropriate session driver based on configuration
        switch ($config['session_driver'])
        {
            case 'Database': // Database-driven session handler (not yet implemented)
                // @todo Implement this
                break;

            default:
                // Extract driver-specific configuration
                $driverName = $config['session_driver'];
                $keyPrefix = $config['key_prefix'];
                $config = $config['driver_config'][$driverName];
                $className = $config['class'];
                $driver = new $className($config, $keyPrefix);
                break;
        }

        return new SessionHandler($driver, new EncryptedContainer(), $ttl);
    }

    /**
     * Sets a session cookie with the provided data and expiration time.
     *
     * @param string $data The data to be stored in the session cookie.
     * @param int $expireTime The expiration time of the cookie as a UNIX timestamp.
     *
     * @return void
     *
     * @throws Exception If the cookie cannot be set due to an error.
     */
    protected function setSessionCookie(#[\SensitiveParameter] string $data, int $expireTime): void
    {
        $cookie = new Cookie('session', $data, $expireTime);
        $cookie->httpOnly = true;
        $cookie->secure = Request::IsSecure();
        $cookie->sameSite = SameSiteSetting::STRICT;
        $cookie->setStatic();
    }

    /**
     * Loads the session cookie from the request and initializes a user identity
     * using the database and session data.
     *
     * @param Request $request The request object containing the session cookie.
     * @return string The loaded session identifier.
     * @throws Exception
     */
    protected function loadSessionCookie(Request $request): string
    {
        // Use the database and session cookie to init a user identity
        $logWriter = LogWriter::Instance('debug');
        $sessionId = $request->cookie('session');
        if (empty($sessionId))
        {
            $logWriter->logDebug("[SessionMiddleware] No session cookie found. Generating new session ID.");
            $sessionId = $this->session->createSessionId();
        }

        // Always set update the cookie expire time for the end user. Sessions last 30 minutes after the last activity.
        $this->setSessionCookie($sessionId, time() + 1800);
        return $sessionId;
    }
}