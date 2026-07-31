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

use Exception;
use ReflectionException;
use System\Collections\Dictionary;
use System\Diagnostics\Debug;
use System\Http\Session\SessionInterface;
use System\Net\IPAddressInterface;
use System\Net\IPAddress;
use System\Routing\RouteNotFoundException;
use System\Routing\Router;
use System\Routing\RouterInterface;
use System\Routing\RoutingDirective;

/**
 * Represents an HTTP request within the platform and provides tools for handling
 * various aspects of incoming web requests, such as processing HTTP methods,
 * managing GET/POST parameters, cookies, and determining Ajax requests.
 * The class also manages static properties for information like base URLs, domains,
 * protocols, and client IP addresses.
 *
 * ## Key Responsibilities:
 * - **HTTP Request Management**: Handles key attributes of an HTTP request, such as the URI,
 *   HTTP method (GET, POST, DELETE, PUT), and request-specific metadata.
 * - **Parameter Parsing**: Provides easy access to parsed GET, POST, and cookie data in the form
 *   of `Dictionary` objects for structured handling.
 * - **Domain and URL Information**: Manages static properties for the protocol, base URL, domain,
 *   and web root, allowing for easy access to global request-related information.
 * - **Ajax Request Detection**: Includes functionality for determining whether the request was
 *   initiated via an Ajax call.
 *
 * ## Features:
 * - Automatically normalizes and stores request URIs.
 * - Supports processing and storage of various HTTP methods (GET, POST, DELETE, PUT).
 * - Structured data storage for GET, POST, and cookie parameters via `Dictionary` objects.
 * - Static properties for global request information, including protocol (`http` or `https`),
 *   client IP, domain, and base URL.
 * - Automatically detects if a request is an Ajax (`XMLHttpRequest`) request.
 *
 * ## Usage:
 * The `Request` class is designed for use in handling incoming HTTP requests. It normalizes request
 * data and provides convenient access to HTTP parameters and metadata. Example usage:
 *
 * ```
 * $request = new Request('/example-route', 'POST', ['param1' => 'value1'], ['query' => 'test'], ['cookie' => 'value']);
 * echo $request->getMethod(); // Outputs: 'POST'
 * echo $request->getUri();    // Outputs: '/example-route'
 * if ($request->isAjax()) {
 *     echo 'This is an Ajax request!';
 * }
 * ```
 *
 * ## Key Properties:
 * - **$uri**: The normalized request URI, always starting with a forward slash (`/`).
 * - **$method**: The HTTP method for the request (e.g., GET, POST).
 * - **$postData**: A structured `Dictionary` of POST data.
 * - **$queryString**: A structured `Dictionary` of GET data.
 * - **$cookieData**: A structured `Dictionary` of cookie data.
 * - **$isAjax**: A boolean indicating whether the request is an Ajax request.
 * - **$clientIp** (static): The remote IP address associated with the request.
 * - **$protocol** (static): The request protocol (e.g., `http` or `https`).
 * - **$baseurl** (static): The base URL of the site (domain plus root path).
 * - **$domain** (static): The domain name without trailing paths (e.g., `example.com`).
 * - **$webroot** (static): The web root path after the domain name.
 *
 * ## Methods:
 * ### __construct(string $uri, string $method = self::GET, array $postData = [], array $queryString = [], array $cookieData = [])
 * - Initializes a new `Request` object with the provided URI, HTTP method, POST data,
 *   GET query string parameters, and cookie data.
 * - Automatically normalizes the URI and stores the data in structured dictionaries.
 * - Detects if the request is an Ajax request and sets corresponding properties.
 *
 * ## Security Notes:
 * - Ensure that incoming data from GET, POST, and cookies is adequately sanitized to avoid
 *   security vulnerabilities, such as SQL injection or cross-site scripting (XSS).
 * - Use HTTPS whenever possible to secure sensitive data transmitted via HTTP parameters.
 *
 * @package System\Http
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */
class Request
{
    /**
     * An array of parent request objects
     * @var array
     */
    protected static array $requests = array();

    /**
     * The remote IP address connected to this request
     * @var ?IPAddressInterface
     */
    protected static ?IPAddressInterface $clientIp;

    /**
     * Current protocol
     * @var string
     */
    protected static string $protocol = 'http';

    /**
     * the site's base url (the root of the website)
     * @var ?string
     */
    protected static ?string $baseurl = null;

    /**
     * Http domain name (no trailing paths after the .com)
     * @var ?string
     */
    protected static ?string $domain = null;

    /**
     * The web root is the trailing path after the domain name.
     * The base url is the Domain name, plus the webroot
     * @var ?string
     */
    protected static ?string $webroot = null;

    /**
     * The properly formatted URI for this request. Always begins with a forward slash.
     * @var string
     */
    protected string $uri;

    /**
     * Directive used to determine the routing behavior for a request
     * @var ?RoutingDirective
     */
    protected ?RoutingDirective $routingDirective = null;

    /**
     * The HTTP method for this request
     * @var HttpMethod
     */
    protected HttpMethod $method;

    /**
     * The $_SERVER data for this request
     * @var Dictionary
     */
    protected Dictionary $serverData;

    /**
     * The POST data for this request
     * @var Dictionary
     */
    protected Dictionary $postData;

    /**
     * The GET data array for this request
     * @var Dictionary
     */
    protected Dictionary $queryString;

    /**
     * The cookies to be used in this request
     * @var Dictionary
     */
    protected Dictionary $cookieData;

    /**
     * Indicates if this is an Ajax request
     * @var bool
     */
    protected bool $isAjax;

    /**
     * The request position
     * @var int
     */
    protected int $requestId;

    /**
     * @var SessionInterface|null
     */
    protected ?SessionInterface $session = null;

    /**
     * Initializes a new instance of the object for handling HTTP requests.
     *
     * @param string $uri The request URI. Any extra slashes will be normalized.
     * @param HttpMethod $method The HTTP method (e.g., GET, POST).
     * @param array $postData An associative array of POST data, if any.
     * @param array $queryString An associative array of GET query string parameters, if any.
     * @param array $cookieData An associative array of cookie data, if any.
     *
     * @return void
     * @throws Exception
     */
    public function __construct(string $uri, HttpMethod $method = HttpMethod::GET, array $postData = [], array $queryString = [], array $cookieData = [], array $serverData = [])
    {
        // Set request position, and add this request to the list
        $this->requestId = count(self::$requests);
        self::$requests[] = $this;

        // Set method and URI, and response
        $this->uri = '/' . trim(preg_replace('~(/{2,})~', '/', $uri), '/');
        $this->method($method);

        // Init default POST, GET and Cookie data
        $this->postData = new Dictionary(false, $postData);
        $this->queryString = new Dictionary(false, $queryString);
        $this->cookieData = new Dictionary(false, $cookieData);
        $this->serverData = new Dictionary(false, $serverData);

        // Set if we are an Ajax request
        $this->isAjax = (
            isset($serverData['HTTP_X_REQUESTED_WITH'])
            && strtolower($serverData['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );

        // Set statics if this is the Initial Request
        if (empty(self::$domain))
        {
            // Remove port and standardize domain (strip www if present and remove port if any)
            $domain = \System::Config()->get('site_domain');
            $host = strtolower(rtrim( $domain ?? $_SERVER['HTTP_HOST'], '/'));
            $cleanedDomain = preg_replace('/^www\.|:\d+$/', '', trim($host));

            // Enforce domain validation
            if (filter_var($cleanedDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                throw new Exception("Invalid domain detected: $cleanedDomain");
            }

            // Set the domain and webroot
            self::$domain = '.' . $cleanedDomain; // Leading dot for cross-subdomain cookies
            self::$webroot = \System::Config()->get('site_webroot') ?? dirname($_SERVER['PHP_SELF']);
            self::$webroot = rtrim(self::$webroot, '/') ?: '/';

            // Build the base URL
            self::$baseurl = \System::Config()->get('site_url');
            if (empty(self::$baseurl))
            {
                // Log
                \System::Log()->logDebug("site_url not set in config. Falling back to dynamically detected values.");

                // Detect the protocol
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                self::$protocol = $secure ? 'https' : 'http';

                $site_url = ltrim(self::$domain, '.') . '/' . trim(self::$webroot, '/');
                self::$baseurl = str_replace('\\', '', self::$protocol . '://' . $site_url);
            }
            else
            {
                // Validate site_url (must include protocol)
                self::$protocol = parse_url(self::$baseurl, PHP_URL_SCHEME);
                if (empty(self::$protocol))
                {
                    throw new Exception("The provided site_url in the config is missing a protocol (http or https).");
                }
            }
        }
    }

    /**
     * Indicates whether this request is an HMVC request, or the
     *   main request from the browser
     *
     * @return bool
     */
    public function isInternal(): bool
    {
        return ($this->requestId > 0);
    }

    /**
     * Sets or fetches whether this request be treated as an ajax request
     *
     * @param bool|null $setAs If set, indicates whether this request be treated as
     *   an Ajax request
     *
     * @return $this|bool
     */
    public function isAjax(?bool $setAs = null): bool|static
    {
        if ($setAs === null)
            return $this->isAjax;
        else
            $this->isAjax = $setAs;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->uri;
    }

    /**
     * Returns the parent request, or false if this object is the parent request.
     *
     * @return bool|Request
     */
    public function getParent(): Request|bool
    {
        return ($this->requestId == 0) ? false : self::$requests[$this->requestId - 1];
    }

    /**
     * Returns an array of child requests
     *
     * @return Request[]
     */
    public function getChildren(): array
    {
        return array_slice(self::$requests, $this->requestId + 1);
    }

    /**
     * Sets the routing directive for the current instance.
     *
     * This method assigns the provided RoutingDirective object to the instance,
     * allowing it to modify or influence routing behavior accordingly.
     *
     * @param RoutingDirective $result The routing directive to be set.
     *
     * @return void
     */
    public function setRoutingDirective(RoutingDirective $result): void
    {
        $this->routingDirective = $result;
    }

    /**
     * Retrieves the current routing directive.
     *
     * This method returns the routing directive associated with the instance,
     * which determines the routing behavior or configuration.
     *
     * @return ?RoutingDirective The current routing directive.
     */
    public function getRoutingDirective(): ?RoutingDirective
    {
        return $this->routingDirective;
    }

    /**
     * Sets or fetches the current HTTP method for this request
     *
     * @param null|HttpMethod $setAs The http method of this request
     *  (See class constants GET, POST, PUT, DELETE)
     *
     * @return HttpMethod|static If a method is being set, returns this object,
     *   otherwise returns the current set method.@
     */
    public function method(?HttpMethod $setAs = null): HttpMethod|static
    {
        // If we are fetching the current value
        if ($setAs === null)
            return $this->method;

        // Set the new value :)
        $this->method = $setAs;
        return $this;
    }

    /**
     * Sets of fetches POST variables for this request object
     *
     * @param string|string[]|null $name The name of the post item, or an array
     *   of key => value to set. If fetching a non-existent item value, null
     *   will be returned.
     * @param string $value The value of $name.
     *
     * @return mixed
     */
    public function post(array|string|null $name = null, mixed $value = null): mixed
    {
        // If name is null, return data array
        if ($name === null)
            return $this->postData;
        // If name is an array, set each $name as a key/value pair
        elseif (is_array($name))
            foreach ($name as $key => $v)
                $this->postData[$key] = $v;
        // If name is valid, and value is null, return the value of name
        elseif ($value === null)
            return ($this->postData->containsKey($name)) ? $this->postData[$name] : null;
        // Otherwise, set the value of name
        else
            $this->postData[$name] = $value;

        return $this;
    }

    /**
     * Sets of fetches QueryString (GET) variables for this request object
     *
     * @param string|string[]|null $name The name of the query item, or an array
     *   of key => value to set. If fetching a non-existent item value, null
     *   will be returned.
     * @param string $value The value of $name
     *
     * @return $this|Dictionary|mixed
     */
    public function get(array|string|null $name = null, mixed $value = null): mixed
    {
        // If name is null, return data array
        if ($name === null)
            return $this->queryString;
        // If name is an array, set each $name as a key/value pair
        elseif (is_array($name))
            foreach ($name as $key => $v)
                $this->queryString[$key] = $v;
        // If name is valid, and value is null, return the value of name
        elseif ($value === null)
            return ($this->queryString->containsKey($name)) ? $this->queryString[$name] : null;
        // Otherwise, set the value of name
        else
            $this->queryString[$name] = $value;

        return $this;
    }

    /**
     * Sets of fetches $_SERVER variables for this request object
     *
     * @param string|string[]|null $name The name of the server item, or an array
     *   of key => value to set. If fetching a non-existent item value, null
     *   will be returned.
     * @param string $value The value of $name.
     *
     * @return mixed
     */
    public function server(array|string|null $name = null, mixed $value = null): mixed
    {
        // If name is null, return data array
        if ($name === null)
            return $this->serverData;
        // If name is an array, set each $name as a key/value pair
        elseif (is_array($name))
            foreach ($name as $key => $v)
                $this->serverData[$key] = $v;
        // If name is valid, and value is null, return the value of name
        elseif ($value === null)
            return ($this->serverData->containsKey($name)) ? $this->serverData[$name] : null;
        // Otherwise, set the value of name
        else
            $this->serverData[$name] = $value;

        return $this;
    }

    /**
     * Sets or fetches Cookie variables for this request object.
     *
     * @param string|string[]|null $name The name of the cookie item, or an array
     *   of key => value pairs to set. If fetching a non-existent item, null
     *   will be returned.
     * @param mixed $value The value of $name.
     *
     * @return mixed|\static
     */
    public function cookie(array|string|null $name = null, mixed $value = null): mixed
    {
        // If name is null, return data array
        if ($name === null)
            return $this->cookieData;

        // If name is an array, set each $name as a key/value pair
        elseif (is_array($name))
            foreach ($name as $key => $v)
                $this->cookieData[$key] = $v;

        // If name is valid, and value is null, return the value of name
        elseif ($value === null)
            return ($this->cookieData->containsKey($name)) ? $this->cookieData[$name] : null;

        // Otherwise, set the value of name
        else
            $this->cookieData[$name] = $value;

        return $this;
    }

    /**
     * Retrieves or sets the current session instance.
     *
     * This method either returns the current session instance if no argument is provided
     * or sets the session instance to the given value.
     *
     * @param SessionInterface|null $setAs The session instance to set, or null to retrieve the current session.
     *
     * @return SessionInterface|static|null The current session instance or null if none is set.
     */
    public function session(?SessionInterface $setAs = null): SessionInterface|static|null
    {
        if ($setAs === null)
            return $this->session;

        $this->session = $setAs;
        return $this;
    }

    /**
     * Determines if this specific request was made over HTTPS,
     * checking both standard and proxy headers.
     *
     * @return bool
     */
    public function isSecureRequest(): bool
    {
        if ($this->serverData->containsKey('HTTPS') && $this->serverData['HTTPS'] !== 'off') {
            return true;
        }
        if ($this->serverData->containsKey('HTTP_X_FORWARDED_PROTO')
            && strtolower($this->serverData['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if ($this->serverData->containsKey('SERVER_PORT') && (int)$this->serverData['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }

    /**
     * Returns the Content-Type of the incoming request.
     *
     * @return string|null The content type, or null if not set.
     */
    public function getContentType(): ?string
    {
        $contentType = $this->serverData->containsKey('CONTENT_TYPE')
            ? $this->serverData['CONTENT_TYPE']
            : ($this->serverData->containsKey('HTTP_CONTENT_TYPE')
                ? $this->serverData['HTTP_CONTENT_TYPE']
                : null);

        if ($contentType !== null) {
            // Strip charset and boundary parameters
            $parts = explode(';', $contentType);
            return trim($parts[0]);
        }

        return null;
    }

    /**
     * Determines if the client expects a JSON response based on the Accept header.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->serverData->containsKey('HTTP_ACCEPT')
            ? $this->serverData['HTTP_ACCEPT']
            : '';

        return str_contains($accept, 'application/json') || str_contains($accept, '*/*');
    }

    /**
     * Extracts the Bearer token from the Authorization header, if present.
     *
     * @return string|null The bearer token, or null if not present.
     */
    public function bearerToken(): ?string
    {
        $header = $this->serverData->containsKey('HTTP_AUTHORIZATION')
            ? $this->serverData['HTTP_AUTHORIZATION']
            : ($this->serverData->containsKey('REDIRECT_HTTP_AUTHORIZATION')
                ? $this->serverData['REDIRECT_HTTP_AUTHORIZATION']
                : null);

        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Checks if the current request matches the given HTTP method.
     *
     * @param HttpMethod $method The HTTP method to check against.
     *
     * @return bool True if the request method matches, false otherwise.
     */
    public function isMethod(HttpMethod $method): bool
    {
        return $this->method === $method;
    }

    // Static Methods //

    /**
     * Returns the base URL to the site, including the webroot directory
     *
     * @example Example return: http://example.com/site/root
     * @return string
     * @throws Exception
     */
    public static function BaseUrl(): string
    {
        // Load the initial request to get domain name
        if (empty(self::$baseurl))
            self::Global();

        return self::$baseurl;
    }

    /**
     * Returns the site domain name, without any sub paths
     *
     * @example Example return: Http://example.com
     * @return string
     * @throws Exception
     */
    public static function Domain(): string
    {
        // Load the initial request to get domain name
        if (empty(self::$domain))
            self::Global();

        return self::$domain;
    }

    /**
     * Determines if the current protocol being used is secure (HTTPS).
     *
     * @return bool True if the protocol is HTTPS, otherwise false.
     * @throws Exception
     */
    public static function IsSecure(): bool
    {
        // Load the initial request to get domain name
        if (empty(self::$domain))
            self::Global();

        return self::$protocol == 'https';
    }

    /**
     * Retrieves the webroot path of the application.
     *
     * @return string The webroot path.
     */
    public static function GetWebroot(): string
    {
        return self::$webroot;
    }

    /**
     * Retrieves the referer of the current HTTP request.
     *
     * This method checks for the referer in the HTTP headers, prioritizing
     * 'HTTP_X_FORWARDED_HOST' if available, and falling back to 'HTTP_REFERER'.
     *
     * @return string|null The referer URL or null if not available.
     */
    public static function Referer(): ?string
    {
        $ref = null;
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']))
            $ref = $_SERVER['HTTP_X_FORWARDED_HOST'];
        elseif (isset($_SERVER['HTTP_REFERER']))
            $ref = $_SERVER['HTTP_REFERER'];

        return $ref;
    }

    /**
     * Returns the Initial (Main) Request object.
     *
     * If the initial WebRequest has not been created, it will be
     * created when this method is called automatically.
     *
     * @return Request
     *
     * @throws Exception
     */
    public static function Global(): Request
    {
        if (isset(self::$requests[0]))
            return self::$requests[0];

        // Create a new Web request
        $uri = $_GET['uri'] ?? '';
        $request = new Request($uri, HttpMethod::GET, $_POST, $_GET, $_COOKIE, $_SERVER);

        // Set method
        try
        {
            if (isset($_SERVER['REQUEST_METHOD']))
                $request->method(HttpMethod::from($_SERVER['REQUEST_METHOD']));
            elseif (false !== ($env = getenv('REQUEST_METHOD')))
                $request->method(HttpMethod::from($env));
            else
                $request->method((!empty($_POST) ? HttpMethod::POST : HttpMethod::GET));
        }
        catch (\InvalidArgumentException $e)
        {
        }

        return $request;
    }

    /**
     * Retrieves the client's IP address from the request headers. The method checks
     * various known IP-related headers to determine the client's IP address, ensuring
     * the value is valid and not from a private range. If a valid IP cannot be determined,
     * null will be returned.
     *
     * @return ?IPAddressInterface The parsed client IP address, or null if a valid IP cannot be determined.
     *
     * @throws \System\ArgumentException
     */
    public static function ClientIp(): ?IPAddressInterface
    {
        // Return it if we already determined the IP
        if (empty(self::$clientIp))
        {
            // List of know IP headers
            $ipHeaders = [
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'HTTP_VIA',
                'REMOTE_ADDR'
            ];

            foreach ($ipHeaders as $header)
            {
                // HTTP_X_FORWARDED_FOR can be an array og IPs!
                if ($header === 'HTTP_X_FORWARDED_FOR' && isset($_SERVER[$header]))
                {
                    $ips = explode(",", $_SERVER[$header]);
                    foreach ($ips as $ip)
                    {
                        $ip = trim($ip);
                        if (IPAddress::Validate($ip, true))
                        {
                            self::$clientIp = IPAddress::Parse($ip);
                            return self::$clientIp;
                        }
                    }
                }

                // Ensure we validate, we don't want private ranges here
                if (!empty($_SERVER[$header]) && IPAddress::Validate($_SERVER[$header], true))
                {
                    self::$clientIp = IPAddress::Parse($_SERVER[$header]);
                    return self::$clientIp;
                }
            }

            // If we are here, we cannot get a valid IP address from this connection
            self::$clientIp = null;
        }

        return self::$clientIp;
    }
}