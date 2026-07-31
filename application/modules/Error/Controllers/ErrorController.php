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
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Error\Controllers;

use System\Diagnostics\ErrorHandler;
use System\HtmlController;
use System\Http\Response;
use System\Routing\Route;

class ErrorController extends HtmlController
{
    /**
     *
     * @throws \Exception
     */
    //#[Route('/show-error', 'show-error', isInternal: true)]
    public function showError(\Throwable $throwable): Response
    {
        // Set status code
        $this->response->statusCode(500);

        // Clear out the current output buffer
        while (ob_get_level() > 0) ob_end_clean();

        // Load the partial view
        $view = $this->loadView('error.tpl');
        $view->assign('items', $navItems);

        return $this->respondWith($view);
    }

    //#[Route('/error/404', 'show-404')]
    public function show404(mixed $throwable): never
    {
        /*
        if ($throwable instanceof \Throwable)
        {
            $code = 404;
            $page = ErrorHandler::RenderDefaultErrorPage($throwable, 'Page Not Found', null, $code);
            $this->response->statusCode($code);
            $this->response->body($page);
            $this->response->send(false);
        }
        */
    }

    //#[Route('/error/403', 'show-403')]
    public function show403(): Response
    {

    }

    //#[Route('/error/offline', 'show-offline', isInternal: true)]
    public function showOffline(): Response
    {

    }
}