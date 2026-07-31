<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace Modules\Dashboard\Controllers;
use Exception;
use Modules\Dashboard\Models\HomeModel;
use System\HtmlController;
use System\Http\Response;
use System\Routing\Route;

/**
 * Home Module Main Controller
 *
 * @package Modules
 */
class FrontController extends HtmlController
{
    /**
     * @protocol    GET
     * @request     /
     * @output      html
     *
     * @throws Exception
     */
    #[Route('/', 'home-page', ['GET'])]
    public function getIndex(): Response
    {
        // Load contents view
        $view = $this->loadView('home');

        // Set breadcrumbs
        self::$layout->breadcrumb->set([
            'Dashboard' => '#',
        ]);

        // Attach view
        return $this->respondWith($view);
    }
}