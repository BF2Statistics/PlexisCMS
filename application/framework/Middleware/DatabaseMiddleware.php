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

use Exception;
use System;
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;
use System\Database\DbFactory;
use System\Http\MiddlewareInterface;
use System\Http\JsonResponse;
use System\Http\Request;
use System\Http\Response;
use System\IO\FileNotFoundException;
use System\IO\Path;

/**
 * Middleware to manage and ensure database connectivity for the application.
 *
 * The `DatabaseMiddleware` class is responsible for verifying the database connection
 * and initializing it if possible. If the database is unavailable or the connection fails,
 * appropriate responses will be generated based on the context of the request (e.g., redirecting
 * to an installer or returning an error response for AJAX calls). It also logs errors when the
 * database connection fails for debugging purposes.
 *
 * @package Application\Middleware
 */
class DatabaseMiddleware implements MiddlewareInterface
{
    /**
     * @var ConfigBase $dbConfig Holds the database configuration object loaded from the configuration file.
     */
    protected ConfigBase $dbConfig;

    /**
     * Constructor.
     *
     * Loads the database configuration file required to establish the database connection.
     * Throws an exception if the configuration file is not found.
     *
     * @throws FileNotFoundException Thrown if the database configuration file is missing.
     */
    public function __construct()
    {
        // Load Plexis Database Config
        $this->dbConfig = \Application::DbConfig();
    }

    /**
     * Processes the incoming HTTP request.
     *
     * This method verifies the database connection. If the connection is available,
     * the request is passed to the next middleware or handler. If the connection fails,
     * different handling is applied based on the request type (e.g., AJAX or normal).
     * It may redirect the user to an installer or display an error message.
     *
     * @param Request $request The current HTTP request being processed.
     * @param callable $next A callback to invoke the next middleware in the pipeline.
     *
     * @return Response The processed response, either from the application or an error/redirect.
     *
     * @throws Exception Thrown if there is an unexpected error during the process.
     */
    public function process(Request $request, callable $next): Response
    {
        // Test database connection
        if ($this->loadDatabase())
        {
            // Continue down the pipeline
            return $next($request);
        }
		
		// test if the installer is locked
		$lockFile = Path::Combine(APP_DIR, 'config', 'install.lock');
		if (file_exists($lockFile))
		{
			// Show site is offline
			return \Application::GenerateOfflineResponse($request, "Unable to connect to the application database.");
		}

		// Are we already requesting the installer?
		if (str_starts_with($request->getPath(), "/install"))
		{
			// Continue down the pipeline
			return $next($request);
		}

        // Handle Ajax differently
        if ($request->isAjax())
        {
            $response = new JsonResponse($request);
            $response->statusCode(503);
            $response->append([
                'success' => false,
                'message' => 'Service unavailable: Database connection failed.',
                'error' => 'Database connection failed.'
            ]);
        }
        else
        {
            $response = new Response($request);
            $response->redirect('/install', temporaryStatus: 302);
        }

        return $response;
    }

    /**
     * Attempts to establish a database connection and load the database version.
     *
     * This method initializes a database connection using the configuration parameters
     * and queries the database to retrieve its version. If successful, it defines
     * the `DB_VERSION` constant based on the retrieved version. If the operation fails
     * (e.g., connection issues or query errors), it logs the error and returns `false`.
     *
     * @return bool Returns `true` if the connection is successful and the database
     *              version is retrieved, otherwise returns `false`.
     */
    protected function loadDatabase(): bool
    {
        // Connect to the stats database
        try
        {
            // Create connection using the MySQL connection builder
            $dbType = $this->dbConfig["web"]["driver"];
            $builder = DbFactory::GetConnectionStringBuilder($dbType);
            $builder->host = $this->dbConfig["web"]["host"];
            $builder->port = $this->dbConfig["web"]["port"];
            $builder->user = $this->dbConfig["web"]["username"];
            $builder->password = $this->dbConfig["web"]["password"];
            $builder->database = $this->dbConfig["web"]["database"];
            $connection = DbFactory::CreateConnection('web', $builder);

            // Fetch database version
            $stmt = $connection->from('bf2web_version')->select('version')->orderByDesc('updateid')->limit(1)->execute();
            $result = $stmt->fetchColumn(0);

            return ($result === false) ? '0.0.0' : $result;
        }
        catch (Exception $e)
        {
            System::Log()->logDebug("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
}