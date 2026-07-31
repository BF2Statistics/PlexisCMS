<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Admin\Controllers;

use System\HtmlController;
use System\Http\Middleware;
use System\Http\Response;
use System\Routing\Route;
use System\Security\Middleware\RequiredPermissionMiddleware;

#[Middleware(RequiredPermissionMiddleware::class, 'admin_access')]
final class FrontController extends HtmlController
{
    #[Route('/admin', 'admin-index', ['GET'])]
    public function dashboard(): Response
    {
        return $this->respondWith('dashboard');
    }
}