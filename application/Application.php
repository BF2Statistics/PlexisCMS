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

use Application\Middleware\AuthenticationMiddleware;
use Application\Middleware\DatabaseMiddleware;
use Random\RandomException;
use System\Autoloader;
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;
use System\Database\DbConnection;
use System\Database\DbFactory;
use System\Events\Event;
use System\Events\EventManager;
use System\Http\Dispatcher;
use System\Http\Middleware\HttpsEnforcerMiddleware;
use System\Http\Request;
use System\Http\Response;
use System\Http\Session\Middleware\SessionMiddleware;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\ObjectDisposedException;
use System\Routing\RouteEvent;
use System\Routing\RouteNotFoundException;
use System\Routing\Router;
use System\Security\ContentSecurityPolicy;
use System\Security\Middleware\CSPMiddleware;
use System\Security\Middleware\CsrfValidationMiddleware;
use System\Version;

/**
 * Class Application
 *
 * Represents the core application logic and functionality.
 * This class serves as the main entry point for initializing and executing
 * the application. It manages configurations, handles HTTP requests,
 * database connections, content security policies, and various middleware setup.
 *
 * @package Application
 */
class Application
{
    /**
     * Indicates whether the application is currently running.
     * This ensures the application only executes once during its lifecycle.
     *
     * @var bool
     */
    private static bool $isRunning = false;

    /**
     * Stores the database configuration.
     *
     * @var ConfigBase
     */
    private static ConfigBase $Config;

    /**
     * Stores the database configuration.
     *
     * @var ConfigBase
     */
    private static ConfigBase $DbConfig;

    /**
     * Stores the current version of the application's database.
     *
     * @var string
     */
    private static string $appDbVersion = '0.0.0';

    /**
     * Stores the current version of the statistics database.
     *
     * @var string
     */
    private static string $statsDbVersion = '0.0.0';

    /**
     * The min expected stats database version for proper operation of the site
     * @var string
     */
    private static string $expectedStatsDbVersion = '3.2.0';

    /**
     * The content security policy instance.
     * Used to define and enforce CSP rules throughout the application.
     *
     * @var ContentSecurityPolicy
     */
    private static ContentSecurityPolicy $Csp;

    /**
     * Initializes and executes the core application logic.
     *
     * The `Run` method acts as the entry point for processing HTTP requests.
     * It initializes the necessary components such as middleware, database connections,
     * session management, and routing, then processes the request, and sends an HTTP response.
     *
     * @param Request $request The HTTP request to be handled by the application.
     *
     * @return void
     *
     * @throws FileNotFoundException If required files, such as configuration files, are missing.
     * @throws IOException If file operations (e.g., reading, writing) fail.
     * @throws ObjectDisposedException If required objects are disposed before being used.
     *
     * @throws Exception|Throwable For any unexpected exceptions that occur during execution.
     */
    public static function Run(Request $request): void
    {
        // Only allow the system to run once
        if (self::$isRunning) die;

        // Register that we are running
        self::$isRunning = true;

        // Register the GameQ namespaces
        Autoloader::RegisterNamespace('GameQ', Path::Combine(APP_DIR, 'framework', 'GameQ'));

        // Build our Content Security Policy, using the default 'system/config/csp.php' config (no parameters)
        self::$Csp = new ContentSecurityPolicy();

        // Load configs
        self::LoadConfigs();

        // Register module route filtering for disabled modules
        EventManager::Listen('router.reloadModuleRoutes.before', [self::class, 'OnModuleRoutesReloading'], 10);

        // Create a router instance using the base Plexis Core System\Routing package
        $router = Router::Instance();

        // Run through the Http Pipeline
        $response = new Dispatcher()
            ->process($request)
            ->using($router)
            ->through(
                new HttpsEnforcerMiddleware(),      // Force https if configured
                new DatabaseMiddleware(),           // Verify database connectivity or redirect to the installer / site offline
                new SessionMiddleware(),            // Initialize sessions immediately, using the default System SessionHandler
                new CsrfValidationMiddleware(),     // CSRF token validation for posting data
                new AuthenticationMiddleware(),     // Authenticate Users from a cookie
                new CSPMiddleware(self::$Csp, true),      // Set Content Security Policy
            )
            ->execute();

        // Send executed response to browser
        $response->send();
    }

    /**
     * Executes the specified route using the provided parameters within the application's HTTP pipeline.
     *
     * @param string $routeName The name of the route to execute.
     * @param array $params Optional. The parameters to pass to the route during execution. Defaults to an empty array.
     *
     * @return Response The response object generated after processing the route.
     *
     * @throws Exception If the method is called before the application has started running.
     */
    public static function RunInternal(string $routeName, array $params = []): Response
    {
        if (!self::$isRunning)
            throw new Exception("Application::RunInternal() called before Run()");

        // Create a router instance using the base System\Routing package
        $router = Router::Instance();

        // Run through the Http Pipeline
        return new Dispatcher()
            ->call($routeName, $params)
            ->using($router)
            ->execute();
    }

    /**
     * Event callback. Hooks into the loading of module routes, allowing the process to be stopped for disabled modules.
     *
     * @param RouteEvent $event The event instance associated with the module route loading process.
     *
     * @return void
     */
    public static function OnModuleRoutesReloading(RouteEvent $event): void
    {
        // Dont load disabled module routes
        $disabledModules = self::$Config->get('disabled_modules', []);
        if (in_array($event->moduleName, $disabledModules)) {
            $event->stopPropagation();
        }
    }

    /**
     * Attempts to establish a connection to the statistics database. Connection to the stats
     * database is lazy loaded, as not all page loads will require a connection to it.
     *
     * This method ensures that the application has a valid connection to the
     * statistics database by either reusing an existing connection, if applicable,
     * or creating a new one.
     *
     * If the connection fails, the error is logged appropriately.
     *
     * @return DbConnection|false Returns true if the stats database connection is successfully established,
     *              otherwise returns false in case of a failure.
     */
    public static function TryStatsDatabaseConnection(): DbConnection|false
    {
        // Have we already connected?
        if (self::$statsDbVersion != '0.0.0')
            return DbFactory::GetConnection('stats');

        // Check to see if we are using the same config values
        $sameDatabase = (self::$DbConfig["web"]["database"] == self::$DbConfig["stats"]["database"]);
        $sameHost = (self::$DbConfig["web"]["host"] == self::$DbConfig["stats"]["host"]);
        $samePort = (self::$DbConfig["web"]["port"] == self::$DbConfig["stats"]["port"]);

        // Are we using the same database as web? If so, just copy the instance from web
        if ($sameDatabase && $sameHost && $samePort)
        {
            $connection = DbFactory::GetConnection('web');
            DbFactory::SetConnectionByName('stats', $connection);
            return $connection;
        }

        // Connect to the stats database
        try
        {
            // Create a connection using the MySQL connection builder
            $dbType = self::$DbConfig["stats"]["driver"];
            $builder = DbFactory::GetConnectionStringBuilder($dbType);
            $builder->host = self::$DbConfig["stats"]["host"];
            $builder->port = self::$DbConfig["stats"]["port"];
            $builder->user = self::$DbConfig["stats"]["username"];
            $builder->password = self::$DbConfig["stats"]["password"];
            $builder->database = self::$DbConfig["stats"]["database"];
            $connection = DbFactory::CreateConnection('stats', $builder);

            // Fetch database version
            $stmt = $connection->from('_version')->select('version')->orderByDesc('updateid')->limit(1)->execute();
            $result = $stmt->fetchColumn(0);

            self::$statsDbVersion = ($result === false) ? '0.0.0' : $result;
            return $connection;
        }
        catch (Exception $e)
        {
            System::Log()->logDebug("STATS Database connection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the current content security policy.
     *
     * This method provides access to the content security policy that has been set
     * within the application, ensuring it is correctly enforced throughout the system.
     *
     * @return ContentSecurityPolicy Returns the content security policy object.
     */
    public static function GetContentSecurityPolicy(): ContentSecurityPolicy
    {
        return self::$Csp;
    }
	
	/**
     * Loads the system and database configuration files
     *
     * This method loads the main system configuration and database configuration
     * files into memory. It also sets up the debug log with the appropriate
     * log level from the main system configuration.
     *
     * @return void
     *
     * @throws FileNotFoundException
     */
    protected static function LoadConfigs(): void
    {
        // Load CMS config
        self::$Config = ConfigManager::Load( APP_DIR . DS . "config" . DS . "config.php" );

        // Load Database config
        self::$DbConfig = ConfigManager::Load( SYSTEM_DIR . DS . "config" . DS . "database.php" );

        // Create debug log
        \System::Log()->logDebug("Application::LoadConfigs(): System Started and configs loaded");
    }

    /**
     * Retrieves the current CMS configuration.
     *
     * This method provides access to the CMS configuration values
     * stored within the application.
     *
     * @return ConfigBase Returns the CMS configuration as a ConfigBase object.
     */
    public static function Config(): ConfigBase
    {
        return self::$Config;
    }

    /**
     * Retrieves the current database configuration.
     *
     * This method provides access to the database configuration values
     * stored within the application. The returned configuration can include
     * details such as host, port, username, password, and database name
     * for various database connections defined in the application.
     *
     * @return ConfigBase Returns the database configuration as a ConfigBase object.
     */
	public static function DbConfig(): ConfigBase
	{
		return self::$DbConfig;
	}

    /**
     * Retrieves the version of the statistics database currently in use.
     *
     * This method provides the parsed version of the statistics database
     * connection, ensuring it is returned as a standardized version object.
     *
     * @return Version Returns a Version object representing the current stats database version.
     */
    public static function StatsDbVersion(): Version
    {
        return Version::Parse(self::$statsDbVersion);
    }

    /**
     * Retrieves the expected version of the statistics database.
     *
     * This method returns the version of the statistics database that the application
     * is configured to work with. The expected version is used to confirm compatibility
     * between the application and the database structure.
     *
     * @return Version Returns the expected version of the statistics database.
     */
    public static function ExpectedStatsDbVersion(): Version
    {
        return Version::Parse(self::$expectedStatsDbVersion);
    }

    public static function GenerateOfflineResponse(Request $request, string $string): Response
    {
        return new Response('The site is currently offline and will be back soon!');
    }
}