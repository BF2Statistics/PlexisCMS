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
 * Represents a Cookie entity with its properties and operations.
 */
class Cookie
{
    /**
     * The name of the cookie
     * @var string
     */
    protected string $name;

    /**
     * The value of the cookie
     * @var string
     */
    protected string $value = '';

    /**
     * The Unix timestamp in which this cookie expires
     * @var false|int
     */
    protected false|int $expires;

    /**
     * The domain path the cookie is valid for
     * @var string
     */
    protected string $path;

    /**
     * The domain name for this cookie
     * @var ?string
     */
    protected ?string $domain = null;

    /**
     * Whether the cookie is HTTPS-only
     * @var bool
     */
    public bool $secure = false;

    /**
     * Whether the cookie is accessible only via HTTP (not JavaScript)
     * @var bool
     */
    public bool $httpOnly = false;

    /**
     * The SameSite directive for this cookie (Lax, Strict, or None).
     * @var ?SameSiteSetting
     */
    public ?SameSiteSetting $sameSite = null;

    /**
     * @var array
     */
    protected static array $staticCookies = [];

    /**
     * Constructor for initializing a cookie object with specified properties.
     *
     * @param string $name The name of the cookie
     * @param mixed $value The value of the cookie
     * @param int $expires The expiration timestamp for the cookie, default is 30 days from the current time
     * @param string $path The path on the server where the cookie will be available, default is '/'
     *
     * @throws \Exception
     */
    public function __construct(string $name, mixed $value, int $expires = 0, string $path = '/')
    {
        $this->name = $name;
        $this->value = $value ?? '';
        $this->expires = ($expires <= 0) ? time() + (30 * 24 * 60 * 60) : $expires;
        $this->setPath($path);

        // Default domain
        $this->domain = Request::Domain();
    }

    /**
     * Returns the name of this cookie
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Returns the value of this cookie
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the Unix timestamp in which this cookie will expire
     *
     * @return int
     */
    public function getExpireTime() : int
    {
        return $this->expires;
    }

    /**
     * Returns the path, for whom can use this cookie
     *
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * Returns the domain name for the cookie
     *
     * @return string
     */
    public function getDomain() : string
    {
        return $this->domain;
    }

    /**
     * Sets the domain for the cookie.
     *
     * @param string $domain The domain to be set for the cookie.
     *
     * @throws \InvalidArgumentException If the provided domain is invalid.
     */
    public function setDomain(string $domain) : void
    {
        if (!$this->isValidDomain($domain)) {
            throw new \InvalidArgumentException("Invalid domain provided for the cookie: $domain");
        }

        $this->domain = $domain;
    }

    /**
     * Sets the path for this object.
     *
     * @param string $path The path to set. It must start with a '/' character.
     *
     * @throws \InvalidArgumentException If the path does not start with a '/' character.
     */
    public function setPath(string $path) : void
    {
        if ($path[0] !== '/') {
            throw new \InvalidArgumentException("Invalid path: Path must start with a '/' character.");
        }

        $this->path = $path;
    }

    /**
     * Determines whether this cookie has expired.
     *
     * @return bool True if the cookie's expiration time is in the past.
     */
    public function isExpired(): bool
    {
        return $this->expires > 0 && $this->expires < time();
    }

    /**
     * Constructs and returns the full header string representation of the cookie.
     *
     * @return string The complete cookie header used for HTTP response, including name, value, and attributes.
     * @throws \Exception
     */
    public function getHeaderValue(): string
    {
        // Ensure value is a string
        $cookieValue = (string)($this->value ?? '');

        // Start with the cookie name (URL-encoded) and value
        // Use rawurlencode for the value to be RFC 6265 compliant
        // Do NOT combine quoting (addslashes) with URL-encoding
        $header = rawurlencode($this->name) . '=' . rawurlencode($cookieValue);

        // Add expiration time, if set
        if ($this->expires > 0) {
            $header .= '; Expires=' . gmdate('D, d M Y H:i:s T', $this->expires);
        }

        if (!empty($this->domain) && $this->isValidDomain($this->domain))
        {
            if (!$this->isDomainMatch($this->domain, Request::Domain())) {
                \System::Log()->logWarning("Cookie domain {$this->domain} does not match request domain");
            }
            else {
                $header .= "; Domain={$this->domain}";
            }
        }

        // Add path
        $header .= '; Path=' . $this->path;

        if ($this->secure) {
            $header .= "; Secure";
        }

        if ($this->httpOnly) {
            $header .= "; HttpOnly";
        }

        if ($this->sameSite !== null) {
            $header .= "; SameSite={$this->sameSite->value}";
        }

        return $header;
    }

    /**
     * Sets the current instance as a static cookie associated with its name.
     *
     * This cookie will be set in the final response header set to the client,
     * and does not need to be added to the final {@link Response}
     */
    public function setStatic(): void
    {
        self::$staticCookies[$this->name] = $this;
    }

    /**
     * Deletes a cookie
     *
     * The response object must be set in order for the cookie to be deleted
     *
     * @param string $name The name of the cookie
     */
    public static function Delete(string $name) : void
    {
        unset($_COOKIE[$name]);
        $Cookie = new Cookie($name, '', time() - 3600);
        self::$staticCookies[$name] = $Cookie;
    }

    /**
     * Retrieves all static cookies.
     *
     * @return array An array containing all static cookies.
     */
    public static function GetAllStaticCookies() : array
    {
        return self::$staticCookies;
    }

    /**
     * Validates whether a given string is a valid domain for cookie usage.
     *
     * @param string $domain The domain string to validate.
     *
     * @return bool True if the domain is valid, otherwise false.
     */
    private function isValidDomain(string $domain): bool
    {
        if (empty($domain)) {
            return false;
        }

        // Strip leading dot for validation (leading dot is valid for cookie domains)
        $domainToValidate = ltrim($domain, '.');

        // Check if it's an IP (invalid for cookies)
        if (filter_var($domainToValidate, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Check if it's a valid domain name
        return (bool)filter_var($domainToValidate, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    /**
     * Determines if a given cookie domain matches the request host.
     *
     * @param string $cookieDomain The domain specified by the cookie.
     * @param string $requestHost The host of the current request.
     *
     * @return bool True if the cookie domain matches the request host, otherwise false.
     */
    private function isDomainMatch(string $cookieDomain, string $requestHost): bool
    {
        $cookieDomain = strtolower(ltrim($cookieDomain, '.'));
        $requestHost = strtolower(ltrim($requestHost, '.'));

        // Exact match
        if ($requestHost === $cookieDomain) {
            return true;
        }

        // Subdomain match
        if (str_ends_with($requestHost, '.' . $cookieDomain)) {
            return true;
        }

        return false;
    }

    /**
     * @return int|string The value of this cookie
     */
    public function __toString()
    {
        return $this->value ?? '';
    }
}