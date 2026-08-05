<?php declare(strict_types=1);
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

namespace System\Routing;

use System\Events\StoppableEvent;

class RouteEvent extends StoppableEvent
{
    public string $moduleName;

    /**
     * @param string $moduleName
     */
    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }
}