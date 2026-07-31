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
namespace System\Presentation;

use Exception;
use Random\RandomException;
use System\ArgumentException;
use System\Configuration\ConfigManager;
use System\Http\Request;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\ObjectDisposedException;
use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ViewRenderer;
use Throwable;

/**
 * Class View
 *
 * Handles the parsing, variable substitution, caching, and rendering of template views.
 * The `View` class is a core component of the presentation layer of Plexis CMS, responsible for managing dynamic
 * template rendering for web pages. Views can be created from either a file or a string and support features such
 * as precompiled templates, variable management, and template messages.
 *
 * ## Key Responsibilities:
 * - **Template Management**: Manages templates' contents.
 * - **Variable Substitution**: Dynamically replaces variables within templates with assigned values.
 * - **Include Directives**: Handles paths for relative template includes.
 * - **Caching**: Supports precompiled templates for increased rendering efficiency.
 * - **View Rendering**: Outputs dynamic content for the presentation layer.
 *
 * ## Features:
 * - Support for precompiled templates and caching to improve performance.
 * - Easy template variable management through getter and setter methods.
 * - Manage and include other templates via relative include paths.
 * - Exception handling for missing or inaccessible view files.
 *
 * ## Key Properties:
 * - **$variables**: Stores the variables assigned to the view for substitution.
 * - **$inserts**: Maintains any dynamically inserted content within the view.
 * - **$includePaths**: Holds relative paths for the 'include' directive used in templates.
 * - **$contents**: Stores the raw content of the view template.
 * - **$filePath**: Stores the file path of the template (if applicable).
 * - **$isPreCompiled**: Indicates whether the view has been precompiled for optimized rendering.
 * - **$cacheKey**: Holds a unique key that identifies cached data for efficient retrieval.
 * - **$messages**: A static array for storing template-level notifications (e.g., warnings or informational messages).
 *
 * ## Usage Examples:
 *
 * ### Example 1: Loading a View From a File
 * ```
 * try {
 *     $view = View::FromFile('/path/to/view.tpl');
 * } catch (\ViewNotFoundException $e) {
 *     echo "View file not found: " . $e->getMessage();
 * }
 * ```
 *
 * ### Example 2: Assigning Variables to Views
 * ```
 * $view = View::FromFile('/path/to/view.tpl');
 * $view->assign('user', 'Steven');
 * $view->assign('email', 'steven@example.com');
 * echo $view->render();
 * ```
 *
 * ### Variable Management:
 * - Assign variables using methods like `assign($key, $value)` or bulk-assign values with `assign($array)`.
 * - Retrieve assigned variables with methods like `getVariable($key)`.
 *
 * ## Features and Benefits:
 * - **Dynamic Templating**: Supports replacing placeholders with variables supplied at runtime.
 * - **Efficient Rendering**: Includes support for precompiled templates and cached views.
 * - **Error Handling**: Provides clear exceptions for missing or inaccessible view files.
 * - **Extendable Design**: Can be integrated with additional layout processing systems (e.g., `ViewRenderer`).
 *
 * ## Security and Best Practices:
 * - Ensure that user-supplied variables passed to views are sanitized to prevent XSS attacks.
 * - Validate file paths to prevent path traversal vulnerabilities when using `FromFile()`.
 * - Use caching for large or frequently-used templates to optimize performance in production environments.
 *
 * ## Exceptions:
 * The `View` class throws several exceptions to handle common error scenarios:
 * - `ViewNotFoundException`: Thrown if a specified view file does not exist.
 * - `IOException`: Thrown if there are file I/O issues while setting template content.
 * - `ObjectDisposedException`: Thrown if a file operation fails due to unexpected stream closure.
 *
 * @package System\Presentation
 * @author Steven Wilson
 * @license GNU GPL v3
 */
class View
{
    /**
     * Assigned template variables and values
     * @var ViewContextProvider
     */
    protected ViewContextProvider $context;

    /**
     * @var array
     */
    protected array $callbacks = array();

    /**
     * Name of the layout being used
     * @var string|null
     */
    public ?string $layoutName = null;

    /**
     * Path to the file, if we loaded from a file.
     * @var string
     */
    protected(set) string $filePath = '';

    /**
     * Array of global template messages
     * @var array[] ('level', 'message', isClosable)
     */
    protected static array $messages = array();

    /**
     * The Content-Security-Policy (CSP) nonce value.
     * @var string
     */
    protected static string $CspNonce = '';

    /**
     * The asset manager instance used to resolve assets in this view
     */
    protected(set) ?LayoutInterface $layout = null;

    /**
     * Creates a new instance of View using the specified view file.
     *
     * @param string $filePath The full path to the view file
     *
     * @return View
     *
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    public static function FromFile(string $filePath) : View
    {
        return new View($filePath);
    }

    /**
     * Sets the Content-Security-Policy (CSP) nonce value.
     *
     * @param string $nonce The nonce value to be used for CSP.
     *
     * @return void
     */
    public static function SetCspNonce(string $nonce) : void
    {
        self::$CspNonce = $nonce;
    }

    /**
     * Constructor method that initializes the object, optionally setting the content from a file.
     *
     * @param string|null $filePath Path to the file from which content is to be set. Defaults to null.
     *
     * @return void
     *
     * @throws Exception
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    protected function __construct(?string $filePath = null)
    {
        // Set the default variables
        $this->clearVariables();

        // Load file
        if (!empty($filePath)) {
            $this->setContentsFromFile($filePath);
        }
    }

    /**
     * Sets the content of the view from the specified file, optionally utilizing a cached version for precompiled templates.
     *
     * @param string $filePath The path to the view file to load content from.
     *
     * @return void
     *
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    public function setContentsFromFile(string $filePath) : void
    {
        $filePath = Path::Normalize($filePath);
        if (!file_exists($filePath))
            throw new ViewNotFoundException('Could not find view file "' . $filePath . '".');

        // Determine the cached file location
        $this->filePath = $filePath;
    }

    /**
     * Sets a value to a specified variable or associates multiple variables with their values.
     *
     * @param string|array $name The name of the variable to set, or an associative array of key-value pairs to set multiple variables.
     * @param mixed|null $value The value to assign to the variable. If $name is an array, this parameter is ignored.
     *
     * @return $this Returns the current instance for method chaining.
     *
     * @throws ArgumentException If the variable name is reserved or contains invalid characters
     */
    public function assign(string|array $name, mixed $value = null): self
    {
        if (is_array($name))
        {
            foreach ($name as $key => $v)
            {
                $this->setVariable(trim($key), $v);
            }
        }
        else
        {
            $this->setVariable(trim($name), $value);
        }

        return $this;
    }

    /**
     * Binds a callback to a specified name for future usage.
     *
     * @param string $name The name to associate with the callback.
     * @param callable $callback The callable to be bound.
     *
     * @return self The current instance for method chaining.
     */
    public function bind(string $name, callable $callback) : self
    {
        $this->callbacks[trim($name)] = $callback;
        return $this;
    }

    /**
     * This method clears all the set variables for this view
     *
     * @return void
     *
     * @throws Exception
     */
    public function clearVariables(): void
    {
        $this->context = new ViewContextProvider();
        $this->context->set('app', $this->prepareAppContext());
    }

    /**
     * Retrieves the current list of variables.
     *
     * @return ViewContextProvider The context of variables currently stored.
     */
    public function getContext(): ViewContextProvider
    {
        return $this->context;
    }

    /**
     * Sets the asset manager instance.
     *
     * @param LayoutInterface $layout The asset manager to be assigned.
     *
     * @return void
     */
    public function setLayout(LayoutInterface $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Adds a message to be displayed in the Global Messages container of the layout
     *
     * @param string $type The html class type ie: "error", "info", "warning" etc
     * @param string $message The string message to display to the client
     * @param bool $isClosable Indicates whether the client is able to close this message
     *
     * @return void
     */
    public function displayMessage(string $type, string $message, bool $isClosable = true) : void
    {
        self::$messages[] = array('type' => $type, 'message' => $message, 'is_closable' => $isClosable);
    }

    /**
     * Renders the output based on the provided template, variables, and configuration.
     * If precompiled templates are available, it renders from those; otherwise, it compiles
     * the template dynamically, caches the result, and renders it.
     *
     * @return string The rendered content as a string.
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ObjectDisposedException
     * @throws RandomException
     * @throws Exception
     * @throws Throwable
     */
    public function render(): string
    {
        $cacheFile = Template::Compile($this);
        $renderer = new ViewRenderer($this, $this->callbacks);
        return $renderer->renderCacheFile($cacheFile);
    }

    /**
     * Prepares the application context by initializing and setting up variables
     * related to the application state, session, and security.
     *
     * @return array
     *
     * @throws Exception
     */
    protected function prepareAppContext(): array
    {
        $session = Request::Global()->session();
        return [
            'base_url' => Request::BaseUrl(),
            'config' => \Application::Config()->fetchAll(),
            'messages' => &self::$messages, // Reference assignment ensures synchronization
            'csp_nonce' => self::$CspNonce,
            'csrf_token' => $session?->csrfToken ?? '',
            'session' => $session?->getAll() ?? [],
            'user' => $session?->getUser() ?? [],
        ];
    }

    /**
     * Sets a variable with a specified name and value, ensuring the name is valid
     * and not a reserved word.
     *
     * @param string $name The name of the variable to set
     * @param mixed $value The value to assign to the variable
     *
     * @return void
     *
     * @throws ArgumentException If the variable name is reserved or contains invalid characters
     */
    private function setVariable(string $name, mixed $value): void
    {
        if (in_array($name, Parser::$ReservedWords))
            throw new ArgumentException("Variable name '$name' is reserved.");

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name))
            throw new ArgumentException("Variable name '$name' contains invalid characters.");

        $this->context->set($name, $value);
    }
}