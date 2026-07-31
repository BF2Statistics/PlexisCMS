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
namespace System;

use System\Http\Request;
use System\Http\Response;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\Presentation\LayoutInterface;
use System\Presentation\InvalidThemePathException;
use System\Presentation\Layout;
use System\Presentation\Template;
use System\Presentation\View;
use System\Presentation\ViewNotFoundException;

/**
 *  Class HtmlController
 *
 *  An abstract base class designed to facilitate the rendering of HTML-based responses.
 *  The `HtmlController` extends the core `BaseController` and provides built-in functionality
 *  for handling templates, views, and preparing HTTP responses in HTML format.
 *
 *  This class is part of the Plexis CMS framework and simplifies the creation of controllers
 *  that respond with HTML or view-rendered content.
 *
 *  ## Key Responsibilities:
 *  - **Template Handling**: Manages the layout and templates used to render views.
 *  - **View Loading**: Provides mechanisms to dynamically load views and their associated assets (e.g., JavaScript).
 *  - **HTML Response**: Prepares and returns `Response` objects that include view-rendered content.
 *
 *  ## Features:
 *  - Automatically loads the specified theme's layout during initialization.
 *  - Supports dynamic view and script loading for modular templates.
 *  - Handles fallback mechanisms for views, first checking template-level views,
 *    then module-level views.
 *  - Provides detailed error handling for missing views through exceptions.
 *
 *  Example:
 *  ```
 *  class ExampleController extends HtmlController
 *  {
 *      public function index()
 *      {
 *          // Create a view
 *          $view = $this->loadView('example');
 *
 *          // Set content and return the response
 *          return $this->respondWith($view);
 *      }
 *  }
 *  ```
 *
 * @package System
 * @extends BaseController
 * @abstract
 * @subpackage Core
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */
abstract class HtmlController extends BaseController
{
    /**
     * The template object
     *
     * @var ?LayoutInterface
     */
    protected static ?LayoutInterface $layout = null;

    /**
     * The layout class name to use for the template.
     *
     * @var string
     */
    protected string $layoutClass = Layout::class;

    /**
     * The response object
     *
     * @var Response
     */
    protected Response $response;

    /**
     * Constructor method for initializing the class.
     *
     * @param ModuleProvider $provider The module instance associated with this request.
     * @param Request $request The request object containing HTTP request details.
     * @param bool|null $loadTemplate Optional parameter to determine if the layout template should be loaded.
     *  If not specified, the template will be loaded unless the request is an internal sub-request.
     */
    public function __construct(ModuleProvider $provider, Request $request, ?bool $loadTemplate = null)
    {
        parent::__construct($provider, $request);
        $this->response = new Response($request);

        // Load the layout unless we are an internal sub request, OR loadTemplate is not defined
        if ($loadTemplate === true || !$request->isInternal())
            $this->loadLayout(\Application::Config()->get('theme'));
    }

    /**
     * Prepares the response based on the request type and input data.
     *
     * @param View $view The View object to be used for the response.
     *
     * @return Response
     */
    public function respondWith(View $view): Response
    {
        if (empty(self::$layout) || $this->request->isInternal())
            $this->response->body($view);
        else
            self::$layout->setContents($view);

        return $this->response;
    }

    /**
     * Loads a view file for the child controller (See detailed description)
     *
     * The first path searched is the current template's module/views
     * folder. If the template does not contain a view for the current module,
     * then the modules view folder will be checked... If a view file cannot
     * be located on either of those paths, a ViewNotFoundException will be thrown
     * unless the variable $silence is set to true, in which case a false will be returned.
     *
     * @param string $name The view filename to load (no extension)
     *
     * @return View Returns false if the view file cannot be located,
     *   (and $silence is set to true), a Library\View object otherwise
     *
     * @throws ViewNotFoundException If neither the module nor the template contain a view of $name
     */
    protected function loadView(string $name) : View
    {
        // Are we using a Layout?
        if (!empty(self::$layout))
        {
            return self::$layout->loadModuleView($this->moduleProvider, $name);
        }

        $viewPath = Template::ResolveModuleViewPath($this->moduleProvider, $name);
        return View::FromFile($viewPath);
    }

    /**
     * Sets the theme path for the template class to pull views, layouts,
     * and partials from.
     *
     * @param string $layoutName The layout to use.
     *
     * @throws ViewNotFoundException
     */
    protected function loadLayout(string $layoutName = "default"): void
    {
        if (self::$layout !== null)
        {
            self::$layout->loadLayout($layoutName);
        }
        else
        {
            // Load the layout
            self::$layout = new $this->layoutClass($layoutName);
        }

        // Set the template as the response body for now
        $this->response->body(self::$layout);
    }

    /**
     * Sets the theme for the template system.
     *
     * @param string $name The name of the theme to be applied.
     * @return void
     */
    protected function setTheme(string $name): void
    {
        Template::SetTheme($name);
    }

    /**
     * Retrieves the current theme name from the template system.
     *
     * @return string The name of the currently set theme.
     */
    protected function getTheme(): string
    {
        return Template::GetTheme();
    }

    /**
     * Retrieves the current layout instance.
     *
     * @return LayoutInterface|null The current layout instance if set, or null if no layout is defined.
     */
    public static function GetLayout(): ?LayoutInterface
    {
        return self::$layout;
    }
}