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

/**
 * Represents a navigation structure capable of holding multiple navigation items.
 */
class Navigation
{
    /**
     * @var NavigationItem[]
     */
    protected $items = [];

    /**
     * Appends a Navigation Item to the current navigation set.
     *
     * @param NavigationItem $item
     */
    public function append(NavigationItem $item)
    {
        $this->items[] = $item;
    }
}