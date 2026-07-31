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
namespace System\Http;

/**
 * Provides methods for handling and generating URLs, including the ability to convert URI query strings
 * into full, valid URLs through configurable options.
 */
class Url
{
    /**
     * Converts a URI query string to a full URL
     *
     * @param string $uri The URI string path
     *
     * @return string
     * @throws \Exception
     */
    public static function Create(string $uri): string
    {
        // If uri is empty, return base url
        $uri = trim($uri, '/');
        if (empty($uri))
            return Request::BaseUrl();

        // if this is a legit url, just return it
        if (preg_match('@^((mailto|ftp|http(s)?)://|www\.)@i', $uri))
            return $uri;

        // Fetch config, and parse the URI
        $Config = \System::Config();
        if ($Config->get("enable_query_strings", false))
        {
            // convert the paths to query vars
            $parts = explode('/', $uri);
            $uri = "?m=" . $parts[0];

            // Append controller and action
            if (isset($parts[1]))
                $uri .= "&c=" . $parts[1];
            if (isset($parts[2]))
                $uri .= "&a=" . $parts[2];
            if (isset($parts[3]))
                $uri .= "&params=" . implode('/', array_slice($parts, 3));
        }
        elseif (!isset($_SERVER['MOD_REWRITE']) || $_SERVER['MOD_REWRITE'] !== 'On')
            // No mod_rewrite, so prepend URI with query string
            $uri = "?uri=" . $uri;

        // Return properly formatted URL
        return Request::BaseUrl() . '/' . $uri;
    }
}