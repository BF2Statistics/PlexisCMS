<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace Modules\Navigation\Controllers;
use Modules\Navigation\Models\NavTreeBuilder;
use System\ArgumentException;
use System\Http\Request;
use System\Http\Response;
use System\IO\FileNotFoundException;
use System\Presentation\ViewNotFoundException;
use System\Routing\Route;

/**
 * Manages the rendering and functionality for the site navigation widget.
 * This controller handles the generation of navigation trees and rendering
 * corresponding views for specific routes.
 *
 * PHP 8.4.x or newer is required.
 */
class WidgetController extends \System\HtmlController
{
    /**
     * @var NavTreeBuilder
     */
    protected NavTreeBuilder $navTreeBuilder;

    /**
     * @throws ArgumentException
     * @throws ViewNotFoundException
     * @throws \ReflectionException
     * @throws FileNotFoundException
     * @throws \Exception
     */
    #[Route('/navigation/site', 'site-nav.widget', ['GET'], isInternal: true)]
    public function buildSiteNav(): Response
    {
        // Load the model. It handles the tree building, as well as caching
        $this->loadModel('NavTreeBuilder');

        // Build or fetch the tree. We use the initial request to determine the current URI pathing
        $initialRequest = Request::Global();
        $navItems = $this->navTreeBuilder->getTree($initialRequest, 'bf2web_site_navigation');

        // Load the partial view
        $view = $this->loadView('navigation.widget.tpl');
        $view->assign('items', $navItems);

        return $this->respondWith($view);
    }
}