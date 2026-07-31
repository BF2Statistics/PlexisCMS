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

use System\ArgumentException;
use System\ModuleProvider;

/**
 * Defines the structure and behavior for layout management in the application.
 */
interface LayoutInterface
{
    /**
     * @var Breadcrumb
     */
    public Breadcrumb $breadcrumb {
        get;
        set;
    }

    /**
     * Sets the contents of the master view using the provided View object.
     *
     * @param View $View The View object to set as the master view.
     *
     * @return void
     */
    public function setContents(View $View): void;

    /**
     * Clears the contents buffer of the template
     */
    public function clearContents() : void;

    /**
     * Renders the views and optionally processes placeholders within a full layout.
     *
     * @return string The fully rendered content, including processed placeholders if a full template is being rendered
     *
     * @throws \Exception If an error occurs during the rendering process
     */
    public function render(): string;

    /**
     * Appends the header adding a css tag
     *
     * The priority parameter controls the order in which stylesheets are rendered in the HTML output.
     * Lower priority values are rendered first (earlier in the document), higher values are rendered last.
     *
     **Recommended Priority Ranges:**
     * - **1-10**: Core/critical stylesheets (e.g., CSS resets, normalize.css, core framework styles)
     * - **11-30**: Third-party library stylesheets (e.g., DataTables, Chart.js, Bootstrap)
     * - **31-49**: Theme and layout stylesheets (e.g., theme-specific styles, common.css)
     * - **50**: Default priority - Auto-loaded module stylesheets (automatically attached by loadModuleView())
     * - **51-75**: View-specific stylesheet overrides (e.g., page-specific customizations)
     * - **76-100**: Non-critical or deferred stylesheets (e.g., print styles, optional enhancements)
     *
     **Examples:**
     * ```
     * // Core stylesheet - loads first
     * $layout->attachStylesheet('public/css/normalize.css', 10);
     *
     * // Third-party library
     * $layout->attachStylesheet('public/css/libs/datatables.css', 25);
     *
     * // Theme stylesheet
     * $layout->attachStylesheet('public/themes/default/css/style.css', 40);
     *
     * // Auto-loaded (default priority)
     * $layout->attachStylesheet('public/themes/default/css/modules/home.css'); // priority = 50
     *
     * // Page-specific override
     * $layout->attachStylesheet('public/css/custom-page.css', 60);
     * ```
     *
     * @param string $location The http location of the file
     * @param int $priority The priority for loading this asset (lower = earlier). Defaults to 50.
     *   Subsequent calls to the same script with the same priority will be ignored, but if a lower priority number is specified,
     *   it will be used instead.
     *
     * @return void
     */
    public function attachStylesheet(string $location, int $priority = 50): void;

    /**
     * Appends the header adding a script tag for this view file
     *
     * The priority parameter controls the order in which scripts are rendered in the HTML output.
     * Lower priority values are rendered first (earlier in the document), higher values are rendered last.
     * This is critical for managing JavaScript dependencies (e.g., jQuery must load before plugins).
     *
     **Recommended Priority Ranges:**
     * - **1-10**: Core libraries that other scripts depend on (e.g., jQuery, Modernizr, Polyfills)
     * - **11-30**: Third-party plugins and libraries (e.g., DataTables, Chart.js, moment.js)
     * - **31-49**: Theme and layout scripts (e.g., common.js, standard.js, theme utilities)
     * - **50**: Default priority - Auto-loaded module scripts (automatically attached by loadModuleView())
     * - **51-75**: View-specific scripts and overrides (e.g., page-specific functionality)
     * - **76-100**: Analytics, tracking, and non-critical scripts (e.g., Google Analytics, chat widgets)
     *
     **Examples:**
     * ```
     * // Core library - must load first
     * $layout->attachScript('public/js/jquery-3.6.3.min.js', 5);
     *
     * // Plugin that depends on jQuery
     * $layout->attachScript('public/js/libs/datatables/jquery.dataTables.js', 20);
     *
     * // Theme script
     * $layout->attachScript('public/themes/default/js/common.js', 35);
     *
     * // Auto-loaded module script (default priority)
     * $layout->attachScript('public/js/modules/Dashboard/home.js'); // priority = 50
     *
     * // Page-specific script
     * $layout->attachScript('public/js/custom-page.js', 60);
     *
     * // Analytics (loads last)
     * $layout->attachScript('public/js/analytics.js', 90);
     * ```
     *
     * **Note:** The `{% asset %}` directive in templates also supports priority:
     * ```
     * {# Load jQuery with high priority #}
     * {% asset 'public/js/jquery.js', 5 %}
     *
     * {# Load DataTables plugin after jQuery #}
     * {% asset 'public/js/libs/datatables/jquery.dataTables.js', 20 %}
     * ```
     *
     * @param string $location The http location of the file. This can be a relative path or an absolute URL.
     * @param int $priority The priority for loading this asset (lower = earlier). Defaults to 50.
     *  Subsequent calls to the same script with the same priority will be ignored, but if a lower priority number is specified,
     *  it will be used instead.
     * @param string $type The script mime type, as it would be in the html script tag.
     *
     * @return void
     */
    public function attachScript(string $location, int $priority = 50, string $type = 'text/javascript'): void;

    /**
     * Loads a layout file for the specified layout name.
     *
     * @param string $layoutName The name of the layout to be loaded.
     * @return void
     *
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    public function loadLayout(string $layoutName): void;

    /**
     * Loads a module's view file and optionally attaches a JavaScript file if it exists.
     *
     * @param ModuleProvider $module The module object for which the view file needs to be loaded.
     * @param string $viewFileName The name of the view file to load (excluding the extension).
     * @param bool $autoLoadFiles Whether to automatically attach the module's JavaScript file and stylesheets if they exist.
     *
     * @return View The loaded view instance.
     *
     * @throws ViewNotFoundException If the specified file does not exist.
     */
    public function loadModuleView(ModuleProvider $module, string $viewFileName, $autoLoadFiles = false): View;

    /**
     * Retrieves the URL of the current theme
     *
     * @return string The URL of the theme
     */
    public function getThemeUrl() : string;

    /**
     * Reloads the theme by verifying the theme's existence, updating the theme URL,
     * and reloading the layout contents and template engine configuration.
     *
     * @return void
     *
     * @throws InvalidThemePathException If the theme's template.xml file is missing or the path is invalid.
     * @throws ViewNotFoundException If the specified {@see Layout::$layout} file does not exist.
     */
    public function reloadTheme(): void;
}