<?php
declare(strict_types=1);
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

use System\ArgumentException;
use System\Diagnostics\Debug;
use System\Diagnostics\Stopwatch;
use System\Http\Request;
use System\IO\File;
use System\IO\Path;
use System\ModuleProvider;
use System\Presentation\Engine\Compiler;
use System\Presentation\Engine\CompilerException;
use System\Presentation\Engine\Lexer;
use System\Presentation\Engine\LexerException;
use System\Presentation\Engine\Parser;
use System\Presentation\Engine\ParsingException;

/**
 * Class Layout
 *
 * The `Layout` class is responsible for managing the presentation layer's layout configuration,
 * including themes, stylesheets, scripts, JavaScript variables, and page structure rendering.
 * This class extends the `View` class, adding functionality for handling global layout elements
 * such as headers, footers, view content management, and theme support within Plexis CMS.
 *
 * ## Key Responsibilities:
 * - **Theme Management**: Handles theme loading, paths, and configuration.
 * - **Global Assets Management**: Manages stylesheets, scripts, and JavaScript variables.
 * - **View Handling**: Handles multiple view rendering within the layout's content area.
 * - **Breadcrumbs**: Manages page navigation breadcrumbs for better user experience.
 * - **Session Integration**: Optionally includes session handling as part of the layout.
 *
 * ## Features:
 * - Load and manage layouts from a theme with support for custom headers, footers, and dynamic elements.
 * - Attach stylesheets and scripts to the page dynamically.
 * - Include global JavaScript variables for client-side scripting support.
 * - Render multiple views within the content area of the layout.
 * - Automatic breadcrumb management for navigation hierarchies.
 *
 * ## Usage Examples:
 *
 * ### Example 1: Creating a Layout
 * ```
 * try {
 *     $layout = new Layout('default', 'home');
 *     $layout->attachStylesheet('/css/style.css');
 *     $layout->attachScript('/js/script.js');
 *     $layout->setContents(View::FromFile('/path/to/view.tpl'));
 *     echo $layout->render();
 * } catch (\InvalidThemePathException $e) {
 *     echo "Theme path error: " . $e->getMessage();
 * }
 * ```
 *
 * ### Example 2: Adding JavaScript Variables and Scripts
 * ```
 * $layout = new Layout('default', 'dashboard');
 * $layout->setJavascriptVar('baseUrl', '/my-app');
 * $layout->attachScript('/scripts/main.js');
 * echo $layout->render();
 * ```
 *
 * ### Example 3: Managing Breadcrumbs
 * ```
 * $layout = new Layout('default');
 * $layout->breadcrumb->add('Home', '/home');
 * $layout->breadcrumb->add('Dashboard', '/dashboard');
 * echo $layout->breadcrumb->render(); // Outputs breadcrumb markup
 * ```
 *
 * ## Key Properties:
 *
 * - **$templateName**: Name of the theme to be used.
 * - **$layoutName**: Name of the layout file to use within the theme.
 * - **$themeUrl**: Complete HTTP path to the theme's root directory.
 * - **$themeConfig**: XML object containing theme configuration (e.g., settings defined in `template.xml`).
 * - **$includeSession**: Flag to enable or disable session handling integration.
 * - **$headers**: Array of strings to be injected into the layout's `<head>` tag.
 * - **$views**: Array of `View` objects to be rendered in the content area of the layout.
 * - **$pageTitle**: The title of the page to be displayed in the browser or header.
 * - **$breadcrumb**: Manages navigation breadcrumbs for the page.
 * - **$stylesheets**: Static array containing all attached stylesheets.
 * - **$scripts**: Static array containing all attached scripts with metadata.
 * - **$jsVariables**: Static array of JavaScript variables (`$name => $value`) to include globally.
 *
 * ## Features and Benefits:
 * - Centralized layout and theme management, ensuring consistency across the application’s pages.
 * - Flexibility to dynamically load and switch between themes and layouts.
 * - Easy management of global assets such as stylesheets and scripts.
 * - Support for breadcrumbs and navigation paths to improve user experience.
 * - Integrates seamlessly with the `View` class for managing template rendering.
 *
 * ## Exceptions:
 * The `Layout` class provides robust error handling to manage issues with themes, layouts, and files:
 * - **InvalidThemePathException**: Thrown when the specified theme path is invalid.
 * - **IOException**: Raised when file I/O operations fail.
 * - **ViewNotFoundException**: Raised when the specified view file is not found.
 * - **FileNotFoundException**: Thrown when any necessary file is missing.
 *
 * ## Example Folder Structure:
 * The following folder structure ensures proper usage of themes and layouts:
 * ```
 * /application/templates/
 * ├── default/
 * │   ├── template.xml
 * │   ├── layouts/
 * │   │   ├── default.tpl
 * │   │   ├── special-page.tpl
 * ```
 *
 * @package System\Presentation
 * @extends View
 * @author
 * @license GNU GPL v3
 */
class Layout extends View implements LayoutInterface
{
    /**
     * The complete http path to the theme root
     * @var string
     */
    protected string $themeUrl
    {
        get {
            return 'public/themes/'. Template::GetTheme();
        }
    }

    /**
     * Theme xml config object
     * @var \SimpleXMLElement
     */
    protected $themeConfig;

    /**
     * A flag indicating whether to include session handling in the script.
     * @var bool
     */
    public bool $includeSession = true;

    /**
     * An array of lines to be injected into the layout head tags
     * @var string[]
     */
    protected array $headers = array();

    /**
     * Array of views for the contents area
     * @var ?View
     */
    protected ?View $masterView = null;

    /**
     * The title of the page
     * @var string
     */
    protected string $pageTitle;

    /**
     * @var Breadcrumb
     */
    public Breadcrumb $breadcrumb;

    /**
     * An array of attached style sheets
     * @var array[]
     */
    protected array $stylesheets = array();

    /**
     * An array of attached scripts
     * @var array[]
     */
    protected array $scripts = array();

    /**
     * An array of javascript Variables
     * @var array $name => $value
     */
    protected array $jsVariables = array();

    /**
     * Constructor to initialize the theme, layout, and various settings for the view
     *
     * @param string $layoutName The name of the layout file to use, defaults to "default"
     *
     * @throws InvalidThemePathException If the theme path is invalid or does not contain a theme.xml file
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    public function __construct(string $layoutName = "default")
    {
        // Add .tpl extension if no extension is provided
        if (pathinfo($layoutName, PATHINFO_EXTENSION) === '') {
            $layoutName .= '.tpl';
        }

        // Load theme paths etc.
        $this->layoutName = $layoutName;
        $this->setLayout($this);

        // Set page title
        $this->pageTitle = \Application::Config()->get("site_title", "Plexis CMS");

        // Create a breadcrumb instance
        $this->breadcrumb = new Breadcrumb();

        // Make sure the layout file exists
        parent::__construct();

        // Make sure the path exists!
        $this->reloadTheme();
    }

    /**
     * @inheritDoc
     */
    public function reloadTheme(): void
    {
        $themeName = Template::GetTheme();
        $templatePath = Path::Combine(APP_DIR, 'templates', $themeName);
        if (!file_exists(Path::Combine($templatePath, 'template.xml')))
            throw new InvalidThemePathException('Invalid template path "' . $templatePath . '". Missing template.xml file.');

        // Set filepath
        $this->setContentsFromFile(Path::Combine($templatePath, 'layouts', $this->layoutName));

        // Update the template engine
        Template::SetCompileDir(
            Path::Combine(APP_DIR, 'templates', $themeName, "compiled")
        );
    }

    /**
     * @inheritDoc
     */
    public function attachStylesheet(string $location, int $priority = 50) : void
    {
        foreach ($this->stylesheets as &$css)
        {
            if ($css['location'] === $location)
            {
                // Use the "highest" priority (the lowest numerical value)
                if ($priority < $css['priority']) {
                    $css['priority'] = $priority;
                }
                return;
            }
        }

        $this->stylesheets[] = [
            'location' => $location,
            'priority' => $priority
        ];
    }

    /**
     * @inheritDoc
     */
    public function attachScript(string $location, int $priority = 50, string $type = 'text/javascript'): void
    {
        foreach ($this->scripts as &$script)
        {
            if ($script['location'] === $location)
            {
                // Update to the higher priority if the new request is more urgent
                if ($priority < $script['priority']) {
                    $script['priority'] = $priority;
                }
                return;
            }
        }

        $this->scripts[] = [
            'location' => $location,
            'type' => $type,
            'priority' => $priority
        ];
    }

    /**
     * Sets a JavaScript variable that can be used globally in the view JavaScript file.
     *
     * @param string $name the name of the variable
     * @param mixed $value the value of the variable. Arrays will be converted to JSON format.
     * @param bool $quoteString indicates whether string values are to be quoted.
     */
    public function setJavascriptVar(string $name, mixed $value, bool $quoteString = true) : void
    {
        if (is_array($value))
            $value = json_encode($value, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        else if (!is_numeric($value) && $quoteString)
            $value = '"'. trim($value, "\"'\t\n") .'"';

        $this->jsVariables[$name] = $value;
    }

    /**
     * Renders the views and optionally processes placeholders within a full layout.
     *
     * @return string The fully rendered content, including processed placeholders if a full template is being rendered
     *
     * @throws \Exception If an error occurs during the rendering process
     * @throws \Throwable
     */
    public function render(): string
    {
        // Hard set because this is a reserved word
        $this->context->set('app', $this->prepareAppContext());

        // Convert all of our views into compiled HTML
        $buffer = '';
        if ($this->masterView !== null)
        {
            $this->masterView->getContext()->setParent($this->context);
            $this->masterView->setLayout($this);
            $buffer = $this->masterView->render();

            // Did the view specify a layout?
            if ($this->masterView->layoutName !== null)
            {
                $this->loadLayout($this->masterView->layoutName);
            }
        }

        // Lazy-load placeholders.
        $this->callbacks = array_merge([
            'head'        => fn() => $this->buildHeader(),
            'contents'    => fn() => $buffer,
            'stylesheets' => fn() => '/?__|stylesheets|__?/',
            'scripts'     => fn() => '/?__|scripts|__?/',
            'jsvars'      => fn() => $this->buildJavascript(),
            'breadcrumb'   => fn() => $this->breadcrumb->generateAsList(),
            'elapsed_time' => fn() => $this->getElapsedTime(), // Optional example.
        ], $this->callbacks);

        // Render the final layout
        $finalRender =  parent::render();

        // Post-rendering injection of stylesheets and scripts
        // We do this because of Widgets and template load order.
        // Widgets are loaded in the Layout run AFTER the <head> tag is built, so we need placeholders
        // for any assets that the widget may have attached.
        return str_replace(
            ['/?__|scripts|__?/', '/?__|stylesheets|__?/'],
            [$this->renderScripts(), $this->renderStylesheets()],
            $finalRender
        );
    }

    /**
     * @inheritDoc
     */
    public function setContents(View $View) : void
    {
        $this->masterView = $View;
    }

    /**
     * Appends to the page title
     *
     * @param string $title The title of the page
     *
     * @return void
     */
    public function appendPageTitle(string $title) : void
    {
        if (!empty($this->pageTitle))
            $this->pageTitle .= " :: " . $title;
        else
            $this->pageTitle = $title;
    }

    /**
     * @inheritDoc
     */
    public function loadLayout(string $layoutName): void
    {
        // Add .tpl extension if no extension is provided
        if (pathinfo($layoutName, PATHINFO_EXTENSION) === '') {
            $layoutName .= '.tpl';
        }

        // Make sure the layout file exists
        $themeName = Template::GetTheme();
        $this->layoutName = $layoutName;
        $filePath = Path::Combine(APP_DIR, 'templates', $themeName, 'layouts', $layoutName);
        parent::setContentsFromFile($filePath);
    }

    /**
     * @inheritDoc
     */
    public function loadModuleView(ModuleProvider $module, string $viewFileName, $autoLoadFiles = false): View
    {
        // 1. Determine the View file path
        // Check if the template overrides the module view
        $viewPath = Template::ResolveModuleViewPath($module, $viewFileName);

        // View::FromFile will throw an exception if neither path existed. Set parent context (this is for widgets)
        $view = View::FromFile($viewPath);
        $view->getContext()->setParent($this->context);
        $view->setLayout($this); // Asset management for widgets (css and js)

        // Auto load stylesheets and scripts
        if ($autoLoadFiles)
        {
            // 1. Attach the module logic javascript "public/js/modules/<name>/<viewName>.js" if it exists
            $jsPath = Path::Combine(ROOT, 'public', 'js', 'modules', $module->name, $viewFileName . '.js');
            if (file_exists($jsPath)) {
                $this->attachScript('public/js/modules/' . $module->name . '/' . $viewFileName . '.js');
            }

            // 2. Attach the theme visual JavaScript "public/themes/<name>/js/modules/<modName>/<viewName>.js" if it exists
            $themeJsPath = Path::Combine(ROOT, $this->themeUrl, 'js', 'modules', $module->name, $viewFileName . '.js');
            if (file_exists($themeJsPath)) {
                $this->attachScript($this->themeUrl . '/js/modules/' . $module->name . '/' . $viewFileName . '.js');
            }

            // 3. Attach the stylesheet for the view "public/themes/<name>/css/modules/<modName>/<viewName>.css" if it exists
            $themeCssPath = Path::Combine(ROOT, $this->themeUrl, 'css', 'modules', $module->name, $viewFileName . '.css');
            if (file_exists($themeCssPath)) {
                $this->attachStylesheet($this->themeUrl . '/css/modules/' . $module->name . '/' . $viewFileName . '.css');
            }
        }

        // Return the View instance
        return $view;
    }

    /**
     * @inheritDoc
     */
    public function clearContents() : void
    {
        $this->masterView = null;
    }

    /**
     * @inheritDoc
     */
    public function getThemeUrl() : string
    {
        return $this->themeUrl;
    }

    /**
     * @inheritDoc
     */
    protected function prepareAppContext(): array
    {
        $config = \Application::Config();
        $session = Request::Global()->session();
        $app = [
            'base_url' => Request::BaseUrl(),
            'config' => $config->fetchAll(),
            'theme_url' => $this->themeUrl,
            'page_title' => $this->pageTitle,
            'site_title' => $config->get("site_title", ""),
            'messages' => &self::$messages,
            'csp_nonce' => self::$CspNonce,
            'csrf_token' => $session->get('csrf_token'),
        ];

        if ($this->includeSession) {
            $app['session'] = $session->getAll() ?? [];
            $app['user'] = $session?->getUser() ?? [];
        }

        return $app;
    }

    /**
     * Builds the plexis header
     *
     * @return string The rendered header data
     * @throws \Exception
     */
    protected function buildHeader() : string
    {
        $base = Request::BaseUrl();
        $Config = \Application::Config();
        $session = Request::Global()->session();

        // Build Basic Headers
        $headers = [
            '<!-- Basic Headings -->',
            '<title>' . $this->pageTitle . '</title>',
            '<base href="'. $base .'/">',
            '<meta data-theme-url="' . $this->themeUrl . '/"/>',
            '<meta name="keywords" content="' . $Config['keywords'] . '"/>',
            '<meta name="description" content="' . $Config['description'] . '"/>',
            '<meta name="generator" content="Plexis"/>',
            '<meta name="csrf-token" content="'. $session->get('csrf_token') .'">',
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
        ];

        // Merge user added headers
        if (!empty($this->headers))
        {
            $headers[] = ''; // Add Whitespace
            $headers[] = '<!-- Controller Added -->';
            $headers = array_merge($headers, $this->headers);
        }

        return implode("\n    ", $headers);
    }

    /**
     * Builds the JavaScript variables and scripts for the layout.
     */
    private function buildJavascript(): string
    {
        // Convert our JavaScript variables into a string
        if (!empty($this->jsVariables))
        {
            $string = "";
            foreach ($this->jsVariables as $key => $val)
            {
                // Format the var based on type
                $val = (is_numeric($val)) ? $val : '"' . $val . '"';
                $string .= "        var " . $key . " = " . $val . ";\n";
            }

            // Add
            $headers = [];
            $headers[] = "<script type=\"text/javascript\">\n" . rtrim($string) . "\n    </script>";
            $headers[] = ''; // Add whitespace

            return implode("\n    ", $headers);
        }

        return "";
    }

    /**
     * Renders the attached stylesheets into HTML <link> tags.
     *
     * @return string Generated HTML for stylesheets.
     */
    private function renderStylesheets(): string
    {
        // Sort by priority (ascending)
        usort($this->stylesheets, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $buffer = '';
        $first = true;
        foreach ($this->stylesheets as $css)
        {
            if (!$first) $buffer .= "\t";
            $buffer .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$css['location']}\" media=\"screen\" />" . PHP_EOL;
            $first = false;
        }
        return $buffer;
    }

    /**
     * Renders the attached scripts into HTML <script> tags.
     *
     * @return string Generated HTML for scripts.
     */
    private function renderScripts(): string
    {
        // Sort by priority (ascending)
        usort($this->scripts, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $buffer = '';
        $first = true;
        foreach ($this->scripts as $script)
        {
            if (!$first) $buffer .= "\t";
            $buffer .= "<script type=\"{$script['type']}\" src=\"{$script['location']}\"></script>" . PHP_EOL;
            $first = false;
        }
        return $buffer;
    }

    /**
     * Returns the elapsed time for a benchmark timer.
     *
     * @return string Elapsed time as a formatted string.
     *
     * @throws ArgumentException
     */
    private function getElapsedTime(): string
    {
        $sw = Stopwatch::StartNew(TIME_START);
        return $sw->elapsedTime(5) . " seconds";
    }
}