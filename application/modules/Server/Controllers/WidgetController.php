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

namespace Modules\Server\Controllers;
use System\ArgumentException;
use System\HtmlController;
use System\Http\Response;
use System\Presentation\ViewNotFoundException;
use System\Routing\Route;

class WidgetController extends HtmlController
{
    /**
     * Retrieves a list of featured game servers and renders them using a specified template view.
     *
     * @param int $limit The maximum number of servers to retrieve. Defaults to 3.
     * @return Response A response object containing the rendered view of featured servers.
     *
     * @throws ViewNotFoundException
     * @throws ArgumentException
     */
    #[Route('/server/list/featured/{limit<\d+>}', 'featured-servers', ['GET'], isInternal: true)]
    public function featuredServers(int $limit = 3): Response
    {
        // Placeholder until we have a proper database connection
        $servers = [
            0 => [
                'id' => 1,
                'is_online' => true,
                'is_full' => true,
                'name' => 'Full Server',
                'map_name' => 'Strike At Karkand',
                'map_size' => 64,
                'map_mode' => 'Conquest',
                'player_count' => 64,
                'address' => '127.0.0.1',
                'port' => 16765
            ],
            1 => [
                'id' => 1,
                'is_online' => true,
                'name' => 'Online Server',
                'is_full' => false,
                'map_name' => 'Strike At Karkand',
                'map_size' => 64,
                'map_mode' => 'Conquest',
                'player_count' => 32,
                'address' => '127.0.0.1',
                'port' => 16765
            ],
            2 => [
                'id' => 1,
                'is_online' => false,
                'is_full' => false,
                'name' => 'Offline Server',
                'map_name' => 'Strike At Karkand',
                'map_size' => 64,
                'map_mode' => 'Conquest',
                'player_count' => 0,
                'address' => '127.0.0.1',
                'port' => 16765
            ],
        ];

        $view = $this->loadView('featured.widget.tpl');
        $view->assign('servers', $servers);

        return $this->respondWith($view);
    }
}