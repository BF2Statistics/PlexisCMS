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

use System\Presentation\View;

/**
 * Handles JSON responses by encoding data, managing headers, and sending responses to the client.
 */
class JsonResponse extends Response
{
    /**
     * Content Mime Type
     * @var string
     */
    protected string $contentType = 'application/json';

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * Sets the body content by encoding the provided data as JSON.
     *
     * @param array $data The data to be encoded and set as the body content
     */
    public function append(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Sends all the response headers, cookies, and current buffered contents
     * to the client. After this method is called, any output will most likely
     * cause a content length error for our client.
     *
     * @param bool $clearOutputBuffer Clear the output buffer?
     *
     * @return void
     * @throws \Exception
     */
    public function send(bool $clearOutputBuffer = true): void
    {
        // Send headers
        $this->sendHeaders();

        // Encode JSON data
        $contents = json_encode($this->data);

        // Fire event, to allow modification of the output
        $event = new ResponseEvent($this, $contents);
        \System\Events\EventManager::Dispatch('response.send.before', $event);

        // Output the body contents
        echo $event->contents;

        if ($clearOutputBuffer)
            ob_flush();

        self::$ResponseSent = true;
    }

    /**
     * Gets or sets the body content of the response. If a value is provided,
     * it sets the body content and returns the current instance. If no value
     * is provided, it retrieves the current body content.
     *
     * @param string|View|null $contents The body content to set. If null, the current body is retrieved.
     *
     * @return string|View|JsonResponse Returns the current body content as a string when retrieving,
     * or the current instance when setting.
     */
    #[\Deprecated('For json responses, use the append() method instead.')]
    public function body(string|View|null $contents = null): string|View|static
    {
        if ($contents === null)
            return json_encode($this->data);

        return $this;
    }

    /**
     * Resets all set headers, cookies, and body
     */
    public function reset(): void
    {
        $this->data = [];
        $this->body = '';
        $this->headers = array();
        $this->cookies = array();
        $this->statusCode = 200;
        $this->contentType = "application/json";
        $this->charset = "UTF-8";
    }
}