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
 * Represents a single item in a navigation menu.
 */
class NavigationItem
{
    /**
     * @var string Gets or Sets the title for this navigation group
     */
    public string $title = '';

    /**
     * @var string Gets or Sets the text for this navigation group
     */
    public string $text = '';

    /**
     * @var string Gets or Sets the navigation href
     */
    public string $href = '#';

    /**
     * @var bool Indicates whether this navigation menu is open or closed.
     */
    public bool $isMenuOpen = false;

    /**
     * @var bool Indicates whether this navigation menu item is highlighted as current
     */
    public bool $isCurrent = false;

    /**
     * @var string Gets or Sets the icon for this navigation menu
     */
    public string $class = '';

    /**
     * @var NavigationItem[] Contains the submenu links
     */
    public array $children = [];

    /**
     * Constructor for initializing the object properties.
     *
     * @param string $text The main text content.
     * @param string|null $title An optional title. Defaults to the value of $text if not provided.
     *
     * @return void
     */
    public function __construct(string $text, ?string $title = null)
    {
        $this->text = $text;
        $this->title = $title ?? $text;
    }

    /**
     * Appends a sub link in this navigation group
     */
    public function addChild(NavigationItem $child): void
    {
        $this->children[] = $child;
    }
}