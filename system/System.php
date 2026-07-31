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
use System\Autoloader;
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;
use System\Diagnostics\ErrorHandler;
use System\Diagnostics\LogWriter;
use System\Events\EventManager;
use System\Events\EventSubscriberInterface;
use System\Http\Dispatcher;
use System\Http\JsonResponse;
use System\Http\Request;
use System\Http\Response;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\Routing\RouteNotFoundException;
use System\Routing\Router;
use System\Routing\RouterEvent;

/**
 * Class System
 *
 * The `System` class provides core functionality for initializing and executing the application lifecycle of Plexis CMS.
 * It manages key responsibilities such as registering autoloaders, handling configurations, initializing databases and sessions,
 * and processing HTTP requests. This class is designed to work as a singleton utility, ensuring the application runs only once
 * per request lifecycle.
 *
 * ## Key Responsibilities:
 * - **Application Initialization**: Registers autoloaders, error handlers, and initializes core components.
 * - **Configuration Management**: Loads and applies application and database configuration settings.
 * - **Logging**: Creates and manages debug logs for error reporting and diagnostic purposes.
 *
 * ## Features:
 * - Centralized execution method (`Run`) that orchestrates the full application lifecycle.
 * - Dynamic configuration loading for system initialization.
 * - Automatic HTTPS enforcement when enabled in configuration.
 * - Robust error handling and logging mechanisms.
 * - Safe execution with a singleton design pattern (`isRunning`).
 *
 * ## Example Usage:
 *
 * ### Running the Application:
 * ```
 * try {
 *     System::Run();
 * } catch (Exception $e) {
 *     echo "Failed to start the system: " . $e->getMessage();
 * }
 * ```
 *
 * ## Key Method:
 *
 * ### Run(): never
 * - Executes the core application lifecycle. This method performs the following steps:
 *   1. Registers the autoloader (`Autoloader::Register`).
 *   2. Registers the error handler (`ErrorHandler::Register`).
 *   3. Initializes a debug log for diagnostics.
 *   4. Loads application and database configurations (`LoadConfigs`).
 *   5. Sets the default timezone based on configurations.
 *   6. Enforces HTTPS redirection if configured (`force_https`).
 *   7. Handles the initial HTTP request (`Request::GetInitial`).
 *
 * - **Returns**: This method does not return, as it executes the application lifecycle and terminates with a `die` statement or completes the response.
 *
 * - **Throws**:
 *   - `FileNotFoundException`: Thrown when required files are missing during initialization.
 *   - `IOException`: Thrown when there are errors related to I/O operations.
 *   - `ReflectionException`: Thrown when reflection operations fail during class or method discovery.
 *
 * ## Features and Benefits:
 * - **Singleton Execution**: Ensures that the application lifecycle is executed only once, preventing conflicts or duplicate processing.
 * - **Robust Configuration Handling**: Dynamically loads and applies settings for seamless initialization.
 * - **Error Handling and Logs**: Captures application errors and logs diagnostic data for debugging.
 * - **HTTPS Enforcement**: Redirects requests to HTTPS based on configuration settings, enhancing security.
 *
 * ## Example Configuration:
 * Below is an example of configuration settings relevant to the `System` class:
 * ```
 * 'default_timezone' => 'UTC',
 * 'force_https' => true,
 * ```
 *
 * ## Exceptions:
 * - **FileNotFoundException**: Thrown when a required file, such as a configuration or dependency file, is not found.
 * - **IOException**: Thrown on I/O-related errors, such as file read/write permissions issues.
 * - **ReflectionException**: Thrown when a reflection operation, like class or method discovery, fails.
 *
 * ## Notes:
 * - The `Run()` method must be the first entry point of the application lifecycle in a Plexis CMS-based project.
 * - This method is not intended to be called multiple times during the same request lifecycle.
 *
 * @package System
 * @author
 * @license GNU GPL v3
 */
class System
{
    /**
     * Indicates whether the System is running
     * @var bool
     */
    private static bool $isRunning = false;

    /**
     * @var ConfigBase
     */
    private static ConfigBase $Config;

    /**
     * @var LogWriter
     */
    private static LogWriter $DebugLog;

    /**
     * Executes the core system logic and application lifecycle
     *
     * The `Run` method orchestrates the entire lifecycle of the application. It initializes
     * essential components including the autoloader, error handler, configuration manager,
     * and logging system, ensuring the application is prepared for handling HTTP requests
     * and responses. Additionally, it enforces security settings such as HTTPS redirection
     * (if enabled) and applies system configurations like the default timezone.
     *
     * This method is designed to execute only once during the lifespan of a request
     * to prevent redundant initialization and to ensure consistency and predictability
     * in the application's behavior. Once executed, the method terminates the script
     * execution after generating the final HTTP response or encountering a critical error.
     *
     * ## Key Steps Performed:
     * - Registering the autoloader to handle class and file loading dynamically.
     * - Setting up the error handler to centralize error and exception management.
     * - Loading and applying configuration settings for the system.
     * - Initializing a debugging and system log for observing runtime behavior.
     * - Defining the default timezone based on configuration settings.
     *
     * ## Return Value:
     * This method does not return any value. It effectively ends the script's execution
     * either by finalizing the HTTP response (`die` or `exit`), or by throwing an exception
     * in case of unrecoverable errors.
     *
     * ## Exceptions:
     * - **FileNotFoundException**: Raised if any required configuration or dependency files
     *   are missing during initialization.
     * - **IOException**: Triggered for errors occurring during file read or write operations.
     * - **ReflectionException**: Thrown when issues occur during runtime introspection
     *   (e.g., discovering or creating classes and methods).
     * - **Exception**: Represents other unexpected errors that may occur during execution.
     *
     * ## Usage Example:
     * ```
     * try {
     *     System::Run();
     * } catch (Exception $e) {
     *     echo "Error: " . $e->getMessage();
     * }
     * ```
     *
     * @return never
     *
     * @throws FileNotFoundException  If required configuration files are not found.
     * @throws IOException            If input/output operations fail during initialization.
     * @throws ReflectionException    If reflection errors occur during runtime.
     * @throws Exception|Throwable    For other unexpected errors.
     */

    public static function Run(): never
    {
        // Only allow the system to run once
        if (self::$isRunning)
            throw new RuntimeException("The System class can only be executed once during the request lifecycle.");

        // Register that we are running
        self::$isRunning = true;

        // Register AutoLoader nad Modules namespace
        Autoloader::Register();

        // Then register error handler
        ErrorHandler::Register(true, true);

        // Load listening events
        self::LoadEventListeners();

        // Create the required log writer for the ASP
        try {
            // Create ASP log file instance
            self::$DebugLog = LogWriter::Create(Path::Combine(SYSTEM_DIR, "logs", "site_debug.log"), "debug");
        }
        catch (Exception $e) {
            // Use temporary file
            self::$DebugLog = LogWriter::Create('php://memory', "debug");
        }

        // Load configs
        self::LoadConfigs();

        // Load initial request
        $request = Request::Global();

        // Pass off to the application
        try
        {
            require APP_DIR . DS . 'Application.php';

            // Run the application
            Application::Run($request);
        }
        catch (Exception $e)
        {
            self::$DebugLog->logError("Error in Application.php: " . $e->getMessage());
            ErrorHandler::HandleThrowable($e);
        }
        finally
        {
            // Cleanup
            self::Cleanup();
        }
    }

    /**
     * Loads and initializes event bootstrappers from the cached events configuration file.
     * It iterates through the configured event listeners and registers them with the EventManager.
     * Supports overrides from config/manifest.php.
     *
     * @return void
     */
    private static function LoadEventListeners(bool $initialAttempt = true): void
    {
        $cacheFile = SYSTEM_DIR . DS . 'cache' . DS . 'events.cache.php';
        $configFile = SYSTEM_DIR . DS . 'config' . DS . 'events.php';

        // Load cached events
        if (!file_exists($cacheFile))
        {
            // Call this method again if routes are reloaded
            if ($initialAttempt)
            {
                EventManager::Listen('router.reloadRoutes.after', function (RouterEvent $event) {
                    self::LoadEventListeners(false);
                });
            }
            return;
        }

        $bootstrappers = include $cacheFile;
        if (!is_array($bootstrappers))
        {
            // Call this method again if routes are reloaded
            if ($initialAttempt)
            {
                EventManager::Listen('router.reloadRoutes.after', function (RouterEvent $event) {
                    self::LoadEventListeners(false);
                });
            }
            return;
        }

        // Check for manual overrides in config/events.php
        if (file_exists($configFile))
        {
            $overrides = include $configFile;
            if (is_array($overrides))
            {
                // Merge event overrides (append listeners, don't replace)
                foreach ($overrides as $eventName => $listeners)
                {
                    if (!isset($bootstrappers[$eventName])) {
                        $bootstrappers[$eventName] = [];
                    }
                    $bootstrappers[$eventName] = array_merge($bootstrappers[$eventName], $listeners);
                }
            }
        }

        // Register all listeners
        foreach ($bootstrappers as $eventName => $listeners)
        {
            foreach ($listeners as $listener)
            {
                EventManager::Listen($eventName, [$listener['class'], $listener['method']], $listener['priority']);
            }
        }
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
        // Load plexis config
        self::$Config = ConfigManager::Load( SYSTEM_DIR . DS . "config" . DS . "config.php" );

        // Set site default timezone
        date_default_timezone_set(self::$Config->get('default_timezone'));

        // Create debug log
        self::$DebugLog->setLogLevel(self::$Config->get("debug_lvl"));
        self::$DebugLog->logDebug("System::LoadConfigs(): System Started and configs loaded");
    }

    /**
     * Returns the main system configuration config file instance
     *
     * @return ConfigBase
     */
    public static function Config(): ConfigBase
    {
        return self::$Config;
    }

    /**
     * Retrieves the current debug logging instance.
     *
     * This method provides access to the application's debug log handler, allowing
     * for logging of errors, debugging information, or any other messages.
     *
     * @return LogWriter Returns the debug logging instance currently in use.
     */
    public static function Log() : LogWriter
    {
        return self::$DebugLog;
    }

    /**
     * Executes cleanup operations
     *
     * This method performs necessary cleanup tasks such as logging debug
     * information, including the page load time. It ensures that final
     * processing steps are completed before the script finishes execution.
     *
     * @return never
     */
    protected static function Cleanup(): never
    {
        // All cleanup code to be executed here
        $time = "Page Loaded in ". round(microtime(true) - TIME_START, 5) . " seconds";
        if (!empty(self::$DebugLog))
            self::$DebugLog->logDebug($time);

        die;
    }

    /**
     * Logs a detailed and recursive exception to the site_debug.log file
     *
     * @param Exception $e
     */
    public static function LogThrowable(Throwable $e): void
    {
        ErrorHandler::LogThrowable($e);
    }
}