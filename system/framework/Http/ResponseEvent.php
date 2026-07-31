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

namespace System\Http;

use System\Events\Event;

/**
 * Event payload used when a {@see Response} is about to be sent.
 *
 * Carries the current {@see Response} instance and the response body contents so
 * listeners can inspect and/or modify the output before it is emitted.
 */
class ResponseEvent extends Event
{
    /**
     * The response being sent.
     */
    protected(set) Response $response;

    /**
     * The request that triggered the response.
     */
    public Request $request
        {
            get => $this->response->request;
        }

    /**
     * The response body contents that will be output.
     *
     * Event listeners may modify this value to change the final output.
     *
     * @var string
     */
    public string $contents;

    /**
     * Creates a new response send event.
     *
     * @param Response $response The response being sent.
     * @param string   $contents The response body contents.
     */
    public function __construct(Response $response, string $contents)
    {
        $this->response = $response;
        $this->contents = $contents;
    }

    /**
     * Sets the response body contents.
     *
     * @param string $contents The response body contents to output.
     *
     * @return void
     */
    public function setContents(string $contents)
    {
        $this->contents = $contents;
    }
}