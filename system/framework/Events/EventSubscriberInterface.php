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

namespace System\Events;

interface EventSubscriberInterface
{
    /**
     * Retrieves the list of subscribed events and their associated listeners.
     *
     * @return array<string, array> An associative array where keys are event names and values are corresponding listeners.
     *  Each value should be an array as so: [Listener Class, Method, Priority]
     */
    public static function GetSubscribedEvents(): array;
}