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
namespace System\Diagnostics;

use ErrorException;
use Exception;
use System\Events\EventManager;
use System\Http\RouteNotFoundEvent;
use System\Http\HttpCode;
use System\Http\HttpForbiddenEvent;
use System\Http\HttpForbiddenException;
use System\Http\JsonResponse;
use System\Http\Request;
use System\Http\Response;
use System\IO\Path;
use System\Routing\RouteNotFoundException;
use Throwable;

/**
 * ErrorHandler class to manage application-wide error and exception handling.
 */
class ErrorHandler
{
    /**
     * @var bool
     */
    protected static bool $HandlingErrors = false;

    /**
     * @var bool
     */
    protected static bool $HandlingExceptions = false;

    /**
     * Registers error and exception handling for the application.
     *
     * @param bool $handleErrors Determines whether the function should handle PHP errors.
     * @param bool $handleExceptions Determines whether the function should handle uncaught exceptions.
     *
     * @return void
     */
    public static function Register(bool $handleErrors = true, bool $handleExceptions = true): void
    {
        // Errors
        if ($handleErrors && !self::$HandlingErrors)
        {
            self::$HandlingErrors = true;
            set_error_handler([self::class, 'HandlePHPError']);
            error_reporting(E_ALL);
        }

        // Exceptions
        if ($handleExceptions && !self::$HandlingExceptions)
        {
            self::$HandlingExceptions = true;
            set_exception_handler([self::class, 'HandleThrowable']);
        }

        // Make sure to register output buffering!
        if (ob_get_level() == 0)
        {
            ini_set('output_buffering', 'On');
            ob_start();
        }
        else
        {
            ob_clean();
        }

        // Register for 404 and 403 events, very low priority (should fire absolutely last)
        EventManager::Listen('dispatch.forbidden', [self::class, 'OnHttpForbidden'], 99);
        EventManager::Listen('dispatch.route.notFound', [self::class, 'OnHttpNotFound'], 99);
    }

    /**
     * Unregisters the error and exception handlers set by the application.
     *
     * @return void
     */
    public static function UnRegister(): void
    {
        self::$HandlingErrors = false;
        self::$HandlingExceptions = false;
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Same method as TriggerError, except this method is called by php internally
     *
     * NOTE: Not all errors in PHP 8 are Throwable still.
     *
     * @param int $lvl Error level. the error levels share the php constants error levels
     * @param string $message The error message
     * @param string $file The filename in which the error was triggered from
     * @param int $line The line number in which the error was triggered from
     *
     * @return bool
     * @throws Throwable
     */
    public static function HandlePHPError(int $lvl, string $message, string $file, int $line): bool
    {
        // If the error_reporting level is 0, then this is a suppressed error ("@" preceding)
        if (!(error_reporting() & $lvl))
            return false;

        // Convert non-throwable errors to ErrorException
        $throwable = new \ErrorException($message, 0, $lvl, $file, $line);

        // Display error
        self::HandleThrowable($throwable);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handles an exception by displaying the error screen.
     *
     * @param Throwable $throwable The Exception object to handle. Provides details such as the exception code, message, file, and line.
     *
     * @return never
     *
     * @event UnhandledExceptionEvent system.error Dispatched before the default system level error page is rendered for display
     * @throws Throwable
     */
    public static function HandleThrowable(Throwable $throwable): never
    {
        // Clear out the current output buffer
        while (ob_get_level() > 0) ob_end_clean();

        // Initial status code
        $statusCode = 500;

        // Grab the request safely, in case the error originated from here
        try
        {
            $request = Request::Global();
        }
        catch (Throwable $e)
        {
            self::LogThrowable($e);
            $request = null;
        }

        // Create a headline message
        $headline = "A PHP Error has occurred";

        // Refine the headline message if this is a throwable
        if (!($throwable instanceof ErrorException))
        {
            $className = get_class($throwable);
            $plural = in_array(substr($className, 0, 1), ['A', 'E', 'I', 'O', 'U']);
            $headline = ($plural) ? "An {$className} has occurred" : "A {$className} has occurred";
        }

        // If ajax, then we create a JSON encoded error
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        if ($isAjax)
        {
            // Determine status code. If a 403 or 404 is not handled, then we need to handle it here
            if ($throwable instanceof RouteNotFoundException) {
                $statusCode = ($request && strtolower($request->getPath()) === '/error/403') ? 403 : 404;
            }
            else if ($throwable instanceof HttpForbiddenException) {
                $statusCode = 403;
            }

            $data = array(
                'success' => false,
                'message' => "A PHP {$headline} was thrown during this request",
                'error' => true,
                'errorData' => array(
                    'message' => $throwable->getMessage(),
                    'file' => $throwable->getFile(),
                    'line' => $throwable->getFile()
                )
            );
            $page = json_encode($data);
        }
        else
        {
            try
            {
                // Allow the Application a change to handle this Exception
                $event = new UnhandledExceptionEvent($throwable, $request, $statusCode);
                EventManager::Dispatch('system.error', $event);

                // Did the application provide a response?
                if ($event->isPropagationStopped() && $event->hasResponse())
                {
                    $event->response->send(false);
                    self::LogThrowable($throwable);
                    die();
                }
            }
            catch (\Throwable $e)
            {
                // User's handler blew up — log it and fall through to default rendering
                self::LogThrowable($e);
            }

            // Render the error page
            $page = self::RenderDefaultErrorPage($throwable, $headline, $request, $statusCode);
        }

        // Send the error page to the client
        if ($request === null)
        {
            $text = HttpCode::from($statusCode)->message();
            header("HTTP/1.1 {$statusCode} {$statusCode} {$text}", true);
            self::LogThrowable($throwable);
            die($page);
        }

        try
        {
            $response = ($isAjax) ? new JsonResponse($request) : new Response($request);
            $response->body($page);
            $response->statusCode($statusCode);
            $response->send(false);
        }
        catch (Exception $e)
        {
            echo $page;
        }
        finally
        {
            self::LogThrowable($throwable);
        }

        // Kill the script
        die();
    }

    /**
     * Handles HTTP 403 Forbidden errors by generating an appropriate response.
     *
     * @param HttpForbiddenEvent $event The event containing the details of the forbidden request.
     *
     * @return Response The generated HTTP response, either in JSON format for AJAX requests or an HTML page for regular requests.
     */
    public static function OnHttpForbidden(HttpForbiddenEvent $event): Response
    {
        // Output the error page based on if the request is ajax or not
        if ($event->request->isAjax())
        {
            $response = new JsonResponse($event->request);
            $response->append([
                'success' => false,
                'message' => $event->message,
                'error' => false
            ]);
        }
        else
        {
            $path = Path::Combine(SYSTEM_DIR, 'views', 'error_403.phtml');
            $message = $event->message;

            // Start output buffering to capture the template
            ob_start();
            include $path;
            $page = ob_get_clean();

            $response = new Response($event->request);
            $response->body($page);
        }

        // Set the correct status code
        $response->statusCode(403);

        // Tell the EventManager that we've handled the event and return the response
        $event->stopPropagation();
        return $response;
    }

    /**
     * Handles the HTTP 404 Not Found event.
     *
     * @param RouteNotFoundEvent $event The event triggered when a 404 error occurs.
     *
     * @return void
     * @throws Exception
     */
    public static function OnHttpNotFound(RouteNotFoundEvent $event): void
    {
        // Make sure we have a request object. Sometimes the event is triggered before the request is created,
        // Such as when an internal route is executed by name instead of by URI
        $request = ($event->request instanceof Request) ? $event->request : new Request($event->request);

        // Output the error page based on if the request is ajax or not
        if ($request->isAjax())
        {
            $response = new JsonResponse($request);
            $response->append([
                'success' => false,
                'message' => "Page not found",
                'error' => false
            ]);
        }
        else
        {
            $path = Path::Combine(SYSTEM_DIR, 'views', 'error_404.phtml');

            // Start output buffering to capture the template
            ob_start();
            include $path;
            $page = ob_get_clean();

            $response = new Response($request);
            $response->body($page);
        }

        // Return the response
        $response->statusCode(404);
        $event->setResponse($response);
    }

    /**
     * Renders the default error page based on the provided exception and context.
     *
     * @param Throwable $exception The exception that triggered the error page.
     * @param string $headline The headline to display on the error page.
     * @param Request $request The HTTP request object used to provide context.
     * @param int &$code A reference to the HTTP status code, which can be modified based on the error type.
     *
     * @return string The rendered error page content as an HTML string.
     */
    public static function RenderDefaultErrorPage(Throwable $exception, string $headline, Request $request, int &$code): string
    {
        try
        {
            // Always assign a message
            $message = $exception->getMessage();
            $preview = '';

            // Load error.phtml template, unless its a RouteNotFound error, then load error_404.phtml template
            $path = Path::Combine(SYSTEM_DIR, 'views', 'error.phtml');
            if ($exception instanceof RouteNotFoundException)
            {
                // Override in case the Application doesn't define a handler for 403 errors
                if (strtolower($request->getPath()) === '/error/403')
                {
                    $code = 403;
                    $path = Path::Combine(SYSTEM_DIR, 'views', 'error_403.phtml');
                    $message = 'You do not have permission to access this resource.';
                }
                else
                {
                    $code = 404;
                    $path = Path::Combine(SYSTEM_DIR, 'views', 'error_404.phtml');
                }
            }
            else if ($exception instanceof HttpForbiddenException)
            {
                $code = 403;
                $path = Path::Combine(SYSTEM_DIR, 'views', 'error_403.phtml');
                $message = 'You do not have permission to access this resource.';
            }
            else
            {
                // Generate code preview using ErrorHighlighter
                $preview = ErrorHighlighter::GetSnippet($exception->getFile(), $exception->getLine(), 5);
            }

            // Start output buffering to capture the template
            ob_start();
            include $path;
            $page = ob_get_clean();

            // Log the error
            self::LogThrowable($exception);

            return $page;
        }
        catch (Exception $e)
        {
            // If even the default template fails, return plain text
            self::LogThrowable($e);
            return "<h1>Critical Error</h1><p>{$exception->getMessage()}</p><p>File: {$exception->getFile()} Line: {$exception->getLine()}</p>";
        }
    }

    /**
     * Logs a detailed and recursive exception to the asp_debug.log file
     *
     * @param Exception $e
     */
    public static function LogThrowable(Throwable $e): void
    {
        $log = LogWriter::Instance('debug');
        if ($log instanceof LogWriter)
        {
            $log->logError('A Handled Throwable event was logged');
            $log->writeLine("\tThrowable Type: " . get_class($e));
            $log->writeLine("\tMessage: " . $e->getMessage());
            $log->writeLine("\tCode: " . $e->getCode());
            $log->writeLine("\tFile: " . $e->getFile());
            $log->writeLine("\tLine: " . $e->getLine());
            $log->writeLine("\tStack Trace: ");

            $i = 0;
            $trace = self::BuildStackTrace($e->getTrace(), false);
            foreach ($trace as $level)
            {
                $log->writeLine("\t\t[{$i}] => [");
                $log->writeLine("\t\t\t\"{$level['file']}\" @ line {$level['line']}");
                $log->writeLine("\t\t\t{$level['func']}({$level['args']})");
                $log->writeLine("\t\t]");
                $i++;
            }

            if ($ex = $e->getPrevious())
            {
                $i = 0;
                $log->writeLine("\tInner Exceptions: ");
                do
                {
                    // Stop at 10 nested levels! * Rare bug where $e->getPrevious() was returning TRUE
                    if ($i > 10 || !($ex instanceof \Throwable)) break;
                    $log->writeLine(
                        sprintf("\t\t[%d] => %s [%s] (%d) : %s",
                            $i++,
                            $ex->getMessage(),
                            $ex->getFile(),
                            $ex->getLine(),
                            get_class($ex)
                        )
                    );
                } while ($ex = $e->getPrevious());
            }
        }
        else
        {
            error_log("Unable to log Throwable to file: " . $e->getMessage());
        }
    }

    /**
     * Formalizes a stack trace array
     *
     * @param array $stack The stack trace
     *
     * @param bool $htmlEntities
     *
     * @return array
     */
    private static function BuildStackTrace(array $stack, bool $htmlEntities = true): array
    {
        $return = [];

        foreach ($stack as $level)
        {
            // File
            $file = '(unknown file)';
            if (isset($level['file']))
            {
                $file = str_replace([ROOT, DS], ['', '/'], $level['file']);
                if ($htmlEntities)
                    $file = htmlspecialchars($file);
            }

            // Check info
            $function = $level['function'] ?? '(unknown function)';
            if (isset($level['class']) and strlen($level['class']) > 0)
            {
                // Ignore the flow of this class
                if (trim('\\', $level['class']) == 'System\Diagnostics\ErrorHandler')
                    continue;

                // Build function string
                $type = (isset($level['type'])) ? $level['type'] : '::';
                $function = $level['class']. $type .$function;
            }

            // Arguments
            $args = array();
            if (isset($level['args']))
            {
                foreach ($level['args'] as $arg)
                {
                    $args[] = self::DescribeVar($arg);
                }
            }

            // Append return
            $return[] = [
                'file' => $file,
                'line' => $level['line'] ?? 0,
                'func' => $function,
                'args' => implode(', ', $args)
            ];
        }

        return $return;
    }

    /**
     * Return a description string of a variable
     *
     * @var mixed $var the var
     * @var int $depth Recursion depth
     *
     * @return ?string the description
     */
    protected static function DescribeVar(mixed $var, int $depth = 0): ?string
    {
        if (is_array($var))
        {
            // Limit depth to prevent infinite recursion
            if ($depth > 4) return 'array('.count($var).')';

            $count = count($var);
            if ($count === 0) return '[]';

            // Build the string, limiting to 25 items
            $out = [];
            $i = 0;
            foreach ($var as $key => $val)
            {
                if ($i >= 25)
                {
                    $out[] = '...';
                    break;
                }

                $keyStr = is_string($key) ? "'{$key}'" : $key;
                $out[] = $keyStr . ' => ' . self::DescribeVar($val, $depth + 1);
                $i++;
            }

            return '[' . implode(', ', $out) . ']';
        }
        elseif (is_object($var))
        {
            $id = method_exists($var, 'getId') ? $var->getId() : (property_exists($var, 'id') ? $var->id : '');
            return get_class($var).'('.$id.')';
        }
        elseif (is_bool($var))
        {
            return ($var ? 'true' : 'false');
        }
        elseif (is_string($var))
        {
            // Truncate long strings for display
            if (strlen($var) > 128) $var = substr($var, 0, 125) . '...';
            return '\''.$var.'\'';
        }
        else
        {
            return (string)$var ?? 'null';
        }
    }
}