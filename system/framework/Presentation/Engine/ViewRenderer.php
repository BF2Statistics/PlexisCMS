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
namespace System\Presentation\Engine;

use System\IO\Path;
use System\Presentation\Template;
use System\Presentation\View;

/**
 * Class ViewRenderer
 *
 * The `ViewRenderer` class is responsible for rendering compiled PHP-based templates from template files.
 * It facilitates the inclusion of templates, supports dynamic context data injection, and allows customization
 * through callback directives for inserting additional content during rendering.
 *
 * ## Key Responsibilities:
 * - **Template Rendering**: Renders templates from PHP files or strings while injecting a provided context.
 * - **Dynamic Context Management**: Allows adding and managing context variables accessible within templates.
 * - **Includes and Callbacks**: Supports including other templates and defining callback-based inserts for dynamic data.
 *
 * ## Features:
 * - Render templates from PHP files (`renderCacheFile`).
 * - Inject dynamic variables into templates through context arrays.
 * - Enable custom rendering directives using callable insert callbacks.
 * - Supports nested includes and loops.
 *
 * ## Example Usage:
 *
 * ### Example 1: Rendering a PHP Template File
 * ```
 * // Initialize the renderer with a global context and include paths
 * $renderer = new TemplateRenderer($view);
 *
 * // Render a PHP file with the provided context
 * echo $renderer->renderCacheFile('/path/to/template.phtml');
 * ```
 *
 * ### Example 2: Using Insert Callbacks
 * ```
 * $callbacks = [
 *     'content' => function ($arg) {
 *         return $arg === 'dynamic' ? 'Dynamic Content' : 'Static Content';
 *     }
 * ];
 *
 * $renderer = new ViewRenderer([], [], $callbacks);
 * echo $callbacks['content']('dynamic'); // Outputs: Dynamic Content
 * ```
 *
 * ## Key Properties:
 *
 * - **$globalContext** *(array)*:
 *   The global array of context variables to be injected into rendered templates.
 *
 * - **$insertCallbacks** *(array)*:
 *   Associative array of custom callbacks (e.g., `'content' => callable`) that handle specific directives during rendering.
 *
 * ## Features and Benefits:
 * - **Dynamic Rendering**: Allows the seamless integration of variables and customization in templates.
 * - **Flexible Design**: Facilitates inclusion of templates and use of callable directives for advanced rendering logic.
 * - **Context Isolation**: Ensures templates operate on isolated scopes to avoid variable conflicts.
 *
 * ## Limitations:
 * - Templates rely on PHP code evaluation, so performance may depend on the complexity of templates being rendered. Use caching when possible.
 *
 * @package System\Presentation\Engine
 * @license GNU GPL v3
 * @author Steven Wilson
 */
class ViewRenderer
{
    protected array $globalContext = [];

    /**
     * A map of compiled view files to their cache paths.
     * @var array<string, string>
     */
    protected array $compiledViews = [];

    /**
     * Tracks the current iteration context for nested loops.
     * @var array<string, mixed>
     */
    protected array $currentIterationContext = [];

    /**
     * A map of custom directives and their corresponding callback functions.
     * @var array<string, callable>
     */
    protected array $insertCallbacks = [];

    /**
     * Reference to the View instance that created this renderer.
     * @var View
     */
    protected View $view;

    /**
     * Initializes a new instance of the class with the provided context.
     *
     * @param View $view
     * @param array $insertCallbacks A key-value pair where:
     *                              - Key is the directive name.
     *                              - Value is a callable that accepts optional arguments and returns the replacement content.
     *                              Example: 'content' => fn($arg) => $arg ? 'Dynamic Content' : 'Static Content'
     *
     */
    public function __construct(View $view, array $insertCallbacks = [])
    {
        $this->view = $view;
        $this->insertCallbacks = $insertCallbacks;
    }

    /**
     * Renders the content of a PHP file by including it within a provided context.
     *
     * @param string $cacheFile The path to the compiled template PHP file to be rendered.
     *
     * @return string The output generated from including the PHP file, as a string.
     * @throws \Throwable
     */
    public function renderCacheFile(string $cacheFile): string
    {
        // Collapse the context array into a single array
        $this->globalContext = $this->view->getContext()->toArray();
        return $this->renderFileWithinContext($cacheFile, $this->globalContext);
    }

    /**
     * Renders the content of a PHP file by including it within a provided context.
     *
     * @param string $cacheFile The path to the PHP file to be included and rendered.
     * @param array $context An associative array of variables to be extracted into the local scope for use within the PHP file.
     *
     * @return string The output generated from including the PHP file, as a string.
     */
    protected function renderFileWithinContext(string $cacheFile, array $context): string
    {
        $__previousContext = $this->currentIterationContext;
        $this->currentIterationContext = $context;

        //  Open the Buffer
        ob_start();

        try
        {
            extract($context, EXTR_SKIP);

            // Try to render
            include $cacheFile;

            // Success? Grab the content and close buffer.
            return ob_get_clean();
        }
        catch (\Throwable $e)
        {
            // Clean the buffer for the error page
            ob_end_clean();
            throw $e;
        }
        finally
        {
            $this->currentIterationContext = $__previousContext;
        }
    }

    /**
     * Renders a foreach loop using precompiled view data.
     *
     * @param iterable $iterable The iterable variable to be looped through.
     * @param string $key The variable name for the key in the loop.
     * @param string|null $value The variable name for the value in the loop, or null if not applicable.
     * @param string $fileName The file name of the precompiled view.
     *
     * @return string The rendered output of the precompiled loop.
     * @throws \Throwable
     */
    protected function renderForeachLoop(iterable $iterable, string $key, ?string $value, string $fileName): string
    {
        $path = Path::Combine(Template::GetCompileDir(), 'loops', $fileName);

        // Use currentIterationContext which should contain the parent scope including $app
        $iterationContext = $this->currentIterationContext;
        return $this->renderPrecompiledLoop($iterable, $key, $value, $path, $iterationContext);
    }

    /**
     * Renders an included partial view by searching through configured include paths.
     * Uses View::FromFile()->render() to handle compilation and caching automatically.
     * Automatically appends .tpl extension if not provided.
     *
     * @param string $partialName The name of the partial view (with or without extension).
     * @param array|null $context An optional associative array of variables to be extracted into the local scope for use within the partial view.
     * @param bool $only If true, only the {@see $context} will be used to render the partial, without access to the current global context.
     *
     * @return string The rendered partial content.
     *
     * @throws \Exception If the partial file is not found in any include path.
     * @throws \Throwable
     */
    protected function renderInclude(string $partialName, ?array $context = null, bool $only = false): string
    {
        // Strip quotes if present (single or double)
        $partialName = trim($partialName, " \t\n\r\0\x0B\"'");
        if ($partialName === '') {
            throw new \InvalidArgumentException('Partial name cannot be empty.');
        }

        // Remove .tpl extension if provided
        if (str_ends_with(strtolower($partialName), '.tpl')) {
            $partialName = substr($partialName, 0, -4);
        }
        $partialName = Path::Normalize($partialName);

        // Ensure we have a context to work with
        $context = $context ?? [];
        if (!$only)
        {
            $context = (empty($context)) ? $this->currentIterationContext : array_merge($this->currentIterationContext, $context);
        }

        // Per-request memoization by include name (avoid repeated resolve/compile)
        if (isset($this->compiledViews[$partialName]))
        {
            return $this->renderFileWithinContext($this->compiledViews[$partialName], $context);
        }

        // Resolve the real template file path (theme override -> app fallback)
        $partialPath = Template::ResolveViewPath($partialName);

        // Build a View for the partial (so it has a context/layout like normal rendering)
        $partialView = View::FromFile($partialPath);

        // Compile via centralized Template pipeline
        $cacheFile = Template::Compile($partialView);

        // Cache for this request
        $this->compiledViews[$partialName] = $cacheFile;

        // Render inside the current renderer context (important for loop scoping)
        return $this->renderFileWithinContext($cacheFile, $context);
    }

    /**
     * Sets the layout to be used for rendering this view.
     * The layout file will be searched in the application/templates/{templateName}/layouts/ directory.
     * Automatically appends .tpl extension if not provided.
     *
     * @param string $layoutName The name of the layout (with or without extension).
     *
     * @return void
     *
     * @throws \Exception If the layout file is not found.
     */
    protected function setLayout(string $layoutName): void
    {
        // Strip quotes if present (single or double)
        $layoutName = trim($layoutName, '\'"');

        // Add .tpl extension if no extension is provided
        if (pathinfo($layoutName, PATHINFO_EXTENSION) === '') {
            $layoutName .= '.tpl';
        }

        // Set the layout property on the View instance
        $this->view->layoutName = $layoutName;
    }

    /**
     * Renders the output for a registered insert callback by its name.
     * Supports passing arguments to the callback.
     *
     * @param string $name The name of the insert callback to be executed.
     * @param array $arguments Optional arguments to pass to the callback.
     *
     * @return string The rendered output of the specified insert callback.
     *
     * @throws \Exception If the insert callback with the given name is not found.
     */
    protected function renderInsert(string $name, array $arguments = []): string
    {
        if (isset($this->insertCallbacks[$name]) && is_callable($this->insertCallbacks[$name]))
        {
            // Call the callback with the provided arguments
            // If any keys of args are strings, those elements will be passed to callback as named arguments, with the name given by the key.
            $result = call_user_func_array($this->insertCallbacks[$name], $arguments);
            return $this->removePhpCode($result);
        }

        throw new \Exception("Insert callback not found or is not callable: {$name}");
    }

    /**
     * Includes a specified asset by determining its type and attaching it to the layout.
     *
     * @param string $path The file path of the asset to be included. The file extension determines the type of asset.
     * @param int $priority The priority for loading this asset (lower = earlier). Defaults to 50.
     *
     * @return void This method does not return a value.
     */
    protected function includeAsset(string $path, int $priority = 50): void
    {
        // If we dont have a layout, we cant attach assets
        if (empty($this->view->layout))
        {
            \System::Log()->logDebug("Cannot attach asset to layout: No layout is set.");
            return;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'js') {
            $this->view->layout->attachScript($path, $priority);
        } elseif ($extension === 'css') {
            $this->view->layout->attachStylesheet($path, $priority);
        }
    }

    /**
     * Renders a precompiled loop by iterating through the provided context data and dynamically
     * generating variables for each iteration. The method processes the loop using a precompiled
     * template file and returns the concatenated output.
     *
     * @param iterable $iterable The iterable structure to loop through.
     * @param string $keyVarName The variable name to represent the loop key in the template.
     * @param string|null $valueVarName The variable name to represent the loop value in the template. Can be null if only the key is needed.
     * @param string $filePath The file path of the precompiled template to be used during rendering.
     * @param array $iterationContext The associative array of context data to be passed into the template during each loop iteration.
     *
     * @return string Returns the rendered output of the compiled loop, generated by processing each iteration of the provided context.
     * @throws \Throwable
     */
    private function renderPrecompiledLoop(
        iterable $iterable,
        string $keyVarName,
        ?string $valueVarName,
        string $filePath,
        array $iterationContext
    ): string
    {
        $compiled = '';
        $total = count($iterable);
        $index = 0;

        foreach ($iterable as $key => $value)
        {
            // Extract the parent loop variable if it exists
            $parentLoop = $iterationContext['loop'] ?? null;

            // Generate dynamic loop variables
            $loop = [
                'index' => $index + 1,
                'index0' => $index,
                'first' => $index === 0,
                'last' => ($index === $total - 1),
                'count' => $total,
                'key' => $key,
                'parent' => $parentLoop,
            ];

            $merge = ['loop' => $loop];
            if (!empty($valueVarName))
            {
                $merge["{$keyVarName}"] = $key;
                $merge["{$valueVarName}"] = $value;
            }
            else
            {
                $merge["{$keyVarName}"] = $value;
            }

            $currentIterationContext = array_merge($iterationContext, $merge);
            $compiled .= $this->renderFileWithinContext($filePath, $currentIterationContext);
            $index++;
        }

        return $compiled;
    }

    /**
     * Render a widget by dispatching to its route
     *
     * @param string $routeName The route name that identifies the widget
     * @param array $params Parameters to pass to the widget
     * @return string The rendered widget HTML
     */
    protected function renderWidget(string $routeName, array $params = []): string
    {
        try
        {
            $response = \Application::RunInternal($routeName, $params);

            // Widgets run their own context and rendering, so here we merge the current context into the widget context
            /*
            $view = $response->body();
            if ($view instanceof View)
            {
                $cxt = new ViewContextProvider();
                $cxt->merge($this->currentIterationContext);
                $view->getContext()->setParent($cxt);
            }
            */

            return $response->capture(false);
        }
        catch (\Exception $e)
        {
            \System::LogThrowable($e);

            // Log error and return empty string or error message
            \System::Log()->logDebug("Widget rendering error: " . $e->getMessage());
            return "<!-- Widget Error: {$e->getMessage()} -->";
        }
    }

    /**
     * Removes all PHP code from the given input string.
     *
     * @param string $input The input string potentially containing PHP code.
     *
     * @return string The input string with PHP tags removed.
     */
    private function removePhpCode(string $input): string
    {
        return preg_replace('/<\?(?:php|=)?.*?\?>/is', '', $input);
    }
}