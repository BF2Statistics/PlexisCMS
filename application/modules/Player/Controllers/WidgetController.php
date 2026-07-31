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

namespace Modules\Player\Controllers;

use Modules\Player\Models\PlayerModel;
use System\HtmlController;
use System\Http\Response;
use System\IO\FileNotFoundException;
use System\Routing\Route;

class WidgetController extends HtmlController
{
    protected ?PlayerModel $playerModel = null;

    /**
     * @throws \ReflectionException
     * @throws FileNotFoundException
     * @throws \Exception
     */
    #[Route('/player/list/top/{limit<\d+>}/score', 'top-players-score', ['GET'], isInternal: true)]
    public function getTopPlayersByScore(int $limit = 10): Response
    {
        $this->loadModel('PlayerModel');

        // Grab top players
        $topPlayers = $this->playerModel->getTopPlayersByScore($limit);
        $view = $this->loadView('top_players.widget');
        $view->assign('players', $topPlayers);

        return $this->respondWith($view);
    }
}