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

use System\Http\Request;

/**
 * Handles the management and generation of breadcrumb navigation for an application.
 */
class Breadcrumb
{
    /**
     * Holds breadcrumb navigation links and labels.
     */
    protected array $breadcrumbs = array();

    /**
     * Initializes the application by appending a default route for "Home"
     * with the base URL of the request.
     *
     * @return void
     * @throws \Exception
     */
    public function __construct()
    {
        $this->append("Dashboard", Request::BaseUrl());
    }

    /**
     * Appends a new breadcrumb to the list with the provided text and hyperlink.
     *
     * @param string $text The display text for the breadcrumb.
     * @param string $href The hyperlink associated with the breadcrumb.
     * @return void
     */
    public function append(string $text, string $href): void
    {
        $this->breadcrumbs[] = array(
            'text' => $text,
            'href' => $href
        );
    }

    /**
     * Sets the breadcrumbs for the application by replacing the current breadcrumbs with the provided array.
     * Each breadcrumb is built from the given name and link pairs.
     *
     * @param array $crumbs An associative array where the key represents the breadcrumb name,
     *                      and the value is the corresponding hyperlink.
     * @return void
     */
    public function set(array $crumbs): void
    {
        $this->breadcrumbs = array();
        foreach ($crumbs as $name => $link)
        {
            $this->breadcrumbs[] = array(
                'text' => $name,
                'href' => $link
            );
        }
    }

    /**
     * Generates a breadcrumb navigation as a list of HTML `<li>` elements, optionally with a CSS class and a divider.
     *
     * @param string|null $cssClass An optional CSS class to apply to each `<li>` element. Defaults to null.
     * @param string $divider An optional string to use as a divider between list items. Defaults to an empty string.
     * @return string A string containing the generated HTML structure for the breadcrumb navigation.
     */
    public function generateAsList(?string $cssClass = null, string $divider = ""): string
    {
        $string = null;
        foreach ($this->breadcrumbs as $b)
        {
            $class = ($cssClass != null) ? " class={$cssClass}" : '';
            $string .= "<li{$class}><a href=\"{$b['href']}\">{$b['text']}</a></li>" . $divider;
        }

        return rtrim($string, $divider);
    }

    /**
     * Retrieves a list of breadcrumbs.
     *
     * @return array Returns an array containing the breadcrumbs.
     */
    public function getList(): array
    {
        return $this->breadcrumbs;
    }
}