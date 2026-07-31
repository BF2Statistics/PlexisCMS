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
 * Represents an HTTP User-Agent and exposes parsed details
 * such as browser, version, platform/OS, device, engine, and flags.
 */
class UserAgent
{
    /** @var string */
    protected string $raw;

    /** @var string */
    protected string $browser = 'Unknown';

    /** @var string */
    protected string $version = '';

    /** @var string */
    protected string $platform = 'Unknown';

    /** @var string */
    protected string $device = 'Unknown';

    /** @var bool */
    protected bool $isMobile = false;

    /** @var bool */
    protected bool $isBot = false;

    /** @var string */
    protected string $engine = 'Unknown';

    /**
     * Create a UserAgent instance from the current request globals.
     *
     * @return static
     */
    public static function FromGlobals(): static
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return new static($ua);
    }

    /**
     * @param string $userAgent The raw user agent string
     */
    public function __construct(string $userAgent)
    {
        $this->raw = $userAgent;
        $this->parse();
    }

    /**
     * Returns the raw User-Agent string.
     */
    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * Returns the browser name (e.g., Chrome, Firefox, Safari).
     */
    public function getBrowser(): string
    {
        return $this->browser;
    }

    /**
     * Returns the browser version, when available.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns the platform/OS (e.g., Windows, macOS, Linux, Android, iOS).
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * Returns the device label (e.g., iPhone, iPad, Android, Desktop).
     */
    public function getDevice(): string
    {
        return $this->device;
    }

    /**
     * True if the UA appears to be a mobile device.
     */
    public function isMobile(): bool
    {
        return $this->isMobile;
    }

    /**
     * True if the UA appears to be a bot/crawler.
     */
    public function isBot(): bool
    {
        return $this->isBot;
    }

    /**
     * Returns the rendering engine (e.g., Blink, Gecko, WebKit, Trident).
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * Parse the raw UA into structured properties.
     */
    protected function parse(): void
    {
        $ua = $this->raw;
        if ($ua === '') {
            return;
        }

        $lower = strtolower($ua);

        // Basic bot detection
        $botTokens = [
            'googlebot','bingbot','slurp','duckduckbot','baiduspider','yandexbot','sogou','exabot',
            'facebot','ia_archiver','semrush','mj12bot','ahrefsbot','seznam','uptimebot','curl','wget'
        ];
        foreach ($botTokens as $t) {
            if (str_contains($lower, $t)) {
                $this->isBot = true;
                break;
            }
        }

        // Platform
        if (str_contains($ua, 'Windows NT')) {
            $this->platform = 'Windows';
        }
        elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X')) {
            $this->platform = 'macOS';
        }
        elseif (str_contains($ua, 'iPhone')) {
            $this->platform = 'iOS';
        }
        elseif (str_contains($ua, 'iPad')) {
            $this->platform = 'iPadOS';
        }
        elseif (str_contains($ua, 'Android')) {
            $this->platform = 'Android';
        }
        elseif (str_contains($ua, 'Linux')) {
            $this->platform = 'Linux';
        }

        // Device + Mobile flag
        if (str_contains($ua, 'iPhone')) {
            $this->device = 'iPhone';
            $this->isMobile = true;
        }
        elseif (str_contains($ua, 'iPad')) {
            $this->device = 'iPad';
        }
        elseif (str_contains($ua, 'Android')) {
            $this->device = (str_contains($ua, 'Mobile')) ? 'Android Phone' : 'Android Tablet';
            $this->isMobile = str_contains($ua, 'Mobile');
        }
        elseif (str_contains($ua, 'Windows Phone')) {
            $this->device = 'Windows Phone';
            $this->isMobile = true;
        }
        else {
            $this->device = 'Desktop';
        }

        // Engine + Browser + Version
        // Order matters for Chromium-based variants
        if (preg_match('/EdgA?\/([\d.]+)/', $ua, $m)) {
            $this->browser = 'Microsoft Edge';
            $this->version = $m[1];
            $this->engine = 'Blink';
        }
        elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m)) {
            $this->browser = 'Opera';
            $this->version = $m[1];
            $this->engine = 'Blink';
        }
        elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) {
            $this->browser = 'Chrome';
            $this->version = $m[1];
            $this->engine = 'Blink';
        }
        // Detect Safari after Chrome to avoid false positives
        elseif (preg_match('/Version\/([\d.]+).*Safari\//', $ua, $m)) {
            $this->browser = 'Safari';
            $this->version = $m[1];
            $this->engine = 'WebKit';
        }
        elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
            $this->browser = 'Firefox';
            $this->version = $m[1];
            $this->engine = 'Gecko';
        }
        elseif (preg_match('/MSIE\s([\d.]+)/', $ua, $m)) {
            $this->browser = 'Internet Explorer';
            $this->version = $m[1];
            $this->engine = 'Trident';
        }
        elseif (preg_match('/Trident\/.*rv:([\d.]+)/', $ua, $m)) {
            $this->browser = 'Internet Explorer';
            $this->version = $m[1];
            $this->engine = 'Trident';
        }
        elseif (preg_match('/curl\/([\d.]+)/', $lower, $m)) {
            $this->browser = 'curl';
            $this->version = $m[1];
            $this->engine = 'N/A';
        }
        elseif (preg_match('/wget\/([\d.]+)/', $lower, $m)) {
            $this->browser = 'wget';
            $this->version = $m[1];
            $this->engine = 'N/A';
        }

        // If engine still unknown, try to infer
        if ($this->engine === 'Unknown') {
            if (str_contains($ua, 'AppleWebKit')) {
                $this->engine = str_contains($ua, 'Chrome') || str_contains($ua, 'OPR') || str_contains($ua, 'Edg')
                    ? 'Blink' : 'WebKit';
            }
            elseif (str_contains($ua, 'Gecko/') || str_contains($ua, 'Firefox')) {
                $this->engine = 'Gecko';
            }
            elseif (str_contains($ua, 'Trident') || str_contains($ua, 'MSIE')) {
                $this->engine = 'Trident';
            }
        }

        // Additional mobile hint
        if (!$this->isMobile && (str_contains($ua, 'Mobile') || str_contains($ua, 'Mobi/'))) {
            $this->isMobile = true;
        }
    }
}
