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

use Random\RandomException;
use System\Events\EventManager;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\ObjectDisposedException;
use System\Presentation\View;

/**
 * Represents an HTTP response within the platform, designed to encapsulate
 *  server-side response handling, including headers, cookies, status codes,
 *  protocol, and body content. This class provides a complete abstraction for
 *  crafting and sending web responses in adherence to HTTP standards.
 *
 *  ## Key Responsibilities:
 *  - **Protocol Management**: Supports HTTP/1.0 and HTTP/1.1 protocols.
 *  - **Status Codes**: Implements standard HTTP codes (e.g., 200, 404, 500) with
 *    their respective contextual descriptions.
 *  - **Headers and Cookies**: Manages HTTP headers and cookies for the response.
 *  - **Content and Encoding**: Defines response body content, MIME type, and
 *    character encoding.
 *  - **Output Management**: Handles response rendering and ensures headers/content
 *    are sent at the appropriate time.
 *
 *  ## Features:
 *  - Dynamically set and retrieve HTTP status codes.
 *  - Add, modify, and send HTTP headers and cookies.
 *  - Customize the response body or content type (e.g., text, HTML, JSON, etc.).
 *  - Predefined list of all HTTP status codes with their corresponding descriptions.
 *  - Verifies whether the response output has already been sent.
 *
 *  ## Usage:
 *  The `Response` class is initialized with a `Request` object, ensuring
 *  alignment with the corresponding HTTP request. It can be used to:
 *  - Construct an HTTP response programmatically.
 *  - Define headers, cookies, and statuses dynamically.
 *  - Send content to the client browser in a secure and flexible manner.
 *
 *  ## Example:
 *  ```
 *  $request = new Request();
 *  $response = new Response($request);
 *  $response->setStatusCode(200)
 *           ->setHeader('Content-Type', 'application/json')
 *           ->setBody(json_encode(['message' => 'Success']))
 *           ->send();
 *  ```
 *
 *  ## Security Notes:
 *  - Ensure the appropriate content type and encoding are set to prevent security
 *    risks, such as Content Type Sniffing.
 *  - Manage session cookies with the `HttpOnly` and `Secure` flags to mitigate
 *    cross-site scripting (XSS) and hijacking risks.
 *
 * @package System\Http
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */
class Response
{
	/**
	 * HTTP protocol 1.0
	 */
	const string HTTP_10 = 'HTTP/1.0';

	/**
	 * HTTP protocol 1.1
	 */
	const string HTTP_11 = 'HTTP/1.1';

	/**
	 * WebResponse Protocol (HTTP/1.0 | 1.1)
	 * @var string
	 */
	protected string $protocol = self::HTTP_11;

	/**
	 * The request object for this response
	 * @var Request
	 */
	public readonly Request $request;

	/**
	 * Status code to be returned in the response
	 * @var int
	 */
	protected int $statusCode = 200;

	/**
	 * Content encoding
	 * @var string
	 */
	protected string $charset = 'UTF-8';

	/**
	 * Content Mime Type
	 * @var string
	 */
	protected string $contentType = 'text/html';

	/**
	 * Array of headers to be sent with the response
	 * @var string[]
	 */
	protected array $headers = array();

	/**
	 * Array of cookies to be sent with the response
	 * @var Cookie[]
	 */
	protected array $cookies = array();

	/**
	 * The response body (contents)
	 * @var string|View
	 */
	protected string|View $body;

	/**
	 * Used to determine if output / headers have been sent to the client browser already
	 * @var bool
	 */
    protected static bool $ResponseSent = false;

    /**
     * Resets all set headers, cookies, and body
     */
    public function reset(): void
    {
        $this->body = '';
        $this->headers = array();
        $this->cookies = array();
        $this->statusCode = 200;
        $this->contentType = "text/html";
        $this->charset = "UTF-8";
    }

    /**
     * Checks whether the response has already been sent to the client.
     *
     * @return bool Returns true if the response has been sent, otherwise false.
     */
    public static function isResponseSent(): bool
    {
        return self::$ResponseSent;
    }

    /**
     * Initializes the object with a specified web request instance.
     *
     * @param Request $Request The web request instance to initialize with.
     *
     * @return void
     */
	public function __construct(Request $Request)
	{
		$this->request = $Request;
        $this->body = '';
	}

    /**
     * Ensure the code is supported!
     *
     * @param int|null $code The HTTP status code to set, or null to retrieve the current code.
     *
     * @return bool|int|static Returns false if the status code cannot be set, the current status code as an integer if retrieved,
     *                          or $this for method chaining when setting the code.
     */
    public function statusCode(?int $code = null): bool|int|static
    {
        if ($code === null)
            return $this->statusCode;

        // Can't set a different status code if this is a hard redirect
        if (isset($this->headers["Location"]))
            return false;

        // Ensure the code is supported!
        if (HttpCode::tryFrom($code) === null)
            throw new \InvalidArgumentException("Invalid HTTP status code: {$code}");

        $this->statusCode = $code;
        return $this;
    }

    /**
     * Gets or sets the body content of the response. If a value is provided,
     * it sets the body content and returns the current instance. If no value
     * is provided, it retrieves the current body content.
     *
     * @param string|View|null $contents The body content to set. If null, the current body is retrieved.
     *
     * @return string|View|static Returns the current body content as a string when retrieving,
     * or the current instance when setting.
     */
	public function body(string|View|null $contents = null): string|View|static
    {
		// Are we setting or retrieving?
		if ($contents === null)
        {
            return $this->body;
        }

		$this->body = $contents;
		return $this;
	}

    /**
     * Sets a header for the response. If the header is "Content-Type", it parses
     * and stores the content type and charset separately. For all other headers, it stores them directly.
     *
     * @param string $name The name of the header, underscores will be replaced with dashes.
     * @param string $value The value of the header. For "Content-Type", it may include a charset.
     *
     * @return void
     */
	public function setHeader(string $name, string $value): void
    {
		$key = str_replace('_', '-', $name);
		if ($key == 'Content-Type')
		{
			if (preg_match('/^(.*);\w*charset\w*=\w*(.*)/', $value, $matches))
			{
				$this->contentType = $matches[1];
				$this->charset = $matches[2];
			}
			else
				$this->contentType = $value;
		}
		else
			$this->headers[$key] = $value;
	}

    /**
     * Retrieves the current headers or sets multiple headers at once.
     *
     * @param array|null $headers An associative array of headers to set, where keys are header names
     *                             and values are the corresponding header values. If null, retrieves
     *                             the currently set headers.
     *
     * @return static|array If `$headers` is null, returns the current headers as an associative array.
     *                       Otherwise, returns the current instance.
     */
    public function headers(?array $headers = null): static|array
    {
        if ($headers === null)
            return $this->headers;

        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }

        return $this;
    }

    /**
     * Checks whether a specific header has been set on the response.
     *
     * @param string $name The header name to check.
     *
     * @return bool True if the header exists, false otherwise.
     */
    public function hasHeader(string $name): bool
    {
        $key = str_replace('_', '-', $name);
        return isset($this->headers[$key]);
    }

    /**
     * Removes a specific header from the response.
     *
     * @param string $name The name of the header to remove.
     *
     * @return void
     */
    public function removeHeader(string $name): void
    {
        $key = str_replace('_', '-', $name);
        unset($this->headers[$key]);
    }

    /**
     * Sets a cookie in the response cookies collection.
     *
     * @param Cookie $Cookie The cookie object to be added.
     *
     * @return void
     */
	public function setCookie(Cookie $Cookie): void
    {
		$this->cookies[$Cookie->getName()] = $Cookie;
	}

    /**
     * Gets or sets the content type for the current instance.
     *
     * @param string|null $val The content type value to set. If null, the method retrieves the current content type.
     *
     * @return string|static Returns the current content type if no value is provided.
     *                       Returns the current instance when a value is set.
     */
	public function contentType(?string $val = null) : string|static
	{
		// Are we setting or retrieving?
		if ($val === null)
			return $this->contentType;

		$this->contentType = $val;
		return $this;
	}

    /**
     * Gets or sets the encoding charset for the current instance.
     *
     * If a value is provided, the encoding charset will be set to the given value.
     * If no value is provided, the current encoding charset will be returned.
     *
     * @param string|null $val The encoding charset to set, or null to retrieve the current charset.
     *
     * @return string|static Returns the current encoding charset when retrieving,
     * or the current instance for method chaining when setting.
     */
	public function encoding(?string $val = null) : string|static
	{
		// Are we setting or retrieving?
		if ($val === null)
			return $this->charset;

		$this->charset = $val;
		return $this;
	}

    /**
     * Sends all the response headers, cookies, and current buffered contents
     * to the client. After this method is called, any output will most likely
     * cause a content length error for our client.
     *
     * @param bool $clearOutputBuffer Clear the output buffer?
     *
     * @return void
     *
     * @event ResponseEvent response.send.before Called before the response is sent to the client.
     *
     * @throws \System\IO\FileNotFoundException
     * @throws \System\IO\IOException
     * @throws \System\ObjectDisposedException
     * @throws RandomException|\Throwable
     */
	public function send(bool $clearOutputBuffer = true): void
    {
		// Send headers
		$this->sendHeaders();

		// Output the body contents
        if ($this->body instanceof View) {
            $contents = $this->body->render();
        }
        else {
            $contents = $this->body;
        }

        // Fire event, to allow modification of the output (language filters, etc.)
        $event = new ResponseEvent($this, $contents);
        EventManager::Dispatch('response.send.before', $event);

        // Output to the browser
        echo $event->contents;

		if ($clearOutputBuffer)
			ob_flush();

        self::$ResponseSent = true;
	}

    /**
     * Captures and returns the body content. Optionally sends the response headers.
     *
     * @param bool $sendHeaders Whether to send the response headers.
     *
     * @return string The captured body content.
     *
     * @throws RandomException
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ObjectDisposedException
     * @throws \Throwable
     */
	public function capture(bool $sendHeaders = false): string
    {
		if ($sendHeaders)
			$this->sendHeaders();

        self::$ResponseSent = true;
		return ($this->body instanceof View) ? $this->body->render() : $this->body;
	}

    /**
     * Redirects the client to a specified URL with the option to wait and define
     * whether the redirect is permanent or temporary. If the given location is
     * a relative path, it will be appended to the site's base URL.
     *
     * @param string $location The URL or path to redirect to.
     * @param int $waitTime Number of seconds to wait before redirecting (default is 0).
     * @param bool $permanent Indicates if the redirect should be permanent (301).
     * @param int|null $temporaryStatus Allows selection between 302 and 307 for temporary redirects.
     *                                  Pass null to use the default behavior (307).
     *
     * @return Response The current instance for method chaining.
     * @throws \Exception
     */
    public function redirect(string $location, int $waitTime = 0, bool $permanent = false, ?int $temporaryStatus = null): static
    {
        // If we have a relative path, append the site URL
        $location = trim($location);
        if (!preg_match('@^((ftp|http(s)?)://|www\.)@i', $location)) {
            $location = Request::BaseUrl() . '/' . ltrim($location, '/');
        }

        // Set redirect status code
        if ($permanent) {
            $this->statusCode = 301; // Permanent redirect
        } else {
            // Determine temporary status code (302 or 307)
            $this->statusCode = $temporaryStatus ?? 307; // Default to 307 if not specified
        }

        // Reset all set data, and process the redirect immediately
        if ($waitTime == 0) {
            $this->headers['Location'] = $location;
            $this->body = '';
        } else {
            $this->headers['Refresh'] = $waitTime . ';url=' . $location;
        }

        return $this;
    }

    /**
     * Determines if the response is a redirect by checking for the presence
     * of specific redirect-related headers such as 'Location' or 'Refresh'.
     *
     * @return bool True if the response includes redirect headers, false otherwise.
     */
	public function isRedirect(): bool
    {
		return (isset($this->headers['Location']) || isset($this->headers['Refresh']));
	}

    /**
     * Clears any redirect headers (Location and Refresh) from the response.
     *
     * @return void
     */
	public function clearRedirect(): void
    {
        unset($this->headers['Location']);
        unset($this->headers['Refresh']);
	}

	/**
	 * Removes all current headers that are set
	 *
	 * @return void
	 */
	public function clearHeaders(): void
    {
		$this->headers = array();
	}

	/**
	 * Removes all current cookies that are modified
	 *
	 * @return void
	 */
	public function clearCookies(): void
    {
		$this->cookies = array();
	}


    /**
     * Sends a single HTTP header to the client. If the headers have already
     * been sent to the client, this function will do nothing and return false.
     *
     * @param string $name The name of the header to send.
     * @param mixed $value The value of the header. If null, only the name is sent.
     *
     * @return bool True if the header was successfully sent, false if headers were already sent.
     */
	protected function sendHeader(string $name, mixed $value = null): bool
    {
		// Make sure the headers haven't been sent!
		if (!headers_sent())
		{
			if (is_null($value))
				header($name);
			else
				header("{$name}: {$value}");

			return true;
		}

		return false;
	}

    /**
     * Handles the transmission of HTTP response headers to the client,
     * including the status line, cookies, content type, and custom headers.
     * Ensures that all headers are sent as per the specified protocol and configuration.
     *
     * @return void
     * @throws \Exception
     */
	protected function sendHeaders(): void
    {
        // Send status
        $message = HttpCode::from($this->statusCode)->message();
        $this->sendHeader("{$this->protocol} {$this->statusCode} {$message}");

        // Manually send cookies
        foreach ($this->cookies as $cookie) {
            $this->sendHeader("Set-Cookie: " . $cookie->getHeaderValue());
        }

        // Manually send cookies
        foreach (Cookie::GetAllStaticCookies() as $cookie) {
            $this->sendHeader("Set-Cookie: " . $cookie->getHeaderValue());
        }

        // Send Content Type
		if (str_starts_with($this->contentType, 'text/'))
			$this->setHeader('Content-Type', $this->contentType ."; charset=". $this->charset);
		elseif ($this->contentType === 'application/json')
			$this->setHeader('Content-Type', $this->contentType ."; charset=". $this->charset);
		else
			$this->setHeader('Content-Type', $this->contentType);

		// Send the rest of the headers
		foreach ($this->headers as $key => $value) {
            $this->sendHeader($key, $value);
        }
	}
}