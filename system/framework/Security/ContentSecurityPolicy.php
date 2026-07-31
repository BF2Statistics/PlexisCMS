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
namespace System\Security;

use Random\RandomException;
use System\IO\Path;

/**
 * Class ContentSecurityPolicy
 *
 * This class provides a programmatic way to create and manage Content Security Policies (CSP).
 * CSP helps to prevent a wide range of web vulnerabilities such as cross-site scripting (XSS).
 *
 * The class supports the definition of standard CSP directives and values,
 * dynamic generation of nonces for inline script and style security, and output in various formats.
 *
 * Implements the `ContentSecurityPolicyInterface`.
 *
 * @package System\Security
 */
class ContentSecurityPolicy implements ContentSecurityPolicyInterface
{
    /**
     * A list of all valid CSP directives.
     * These directives define the types of content that the browser is allowed to load.
     *
     * @var string[]
     */
    private const array VALID_DIRECTIVES = [
        'default-src', 'script-src', 'style-src', 'img-src', 'connect-src',
        'font-src', 'frame-src', 'object-src', 'media-src', 'worker-src',
        'sandbox', 'form-action', 'frame-ancestors', 'manifest-src',
        'navigate-to', 'report-uri', 'report-to', 'upgrade-insecure-requests',
        'block-all-mixed-content', 'base-uri', 'child-src', 'require-trusted-types-for',
        'trusted-types', 'script-src-attr', 'script-src-elem', 'style-src-attr', 'style-src-elem',
    ];

    /**
     * A list of safe standard CSP values.
     * These values can be used for the CSP directives, ensuring safety and compliance.
     *
     * @var string[]
     */
    private const array VALID_VALUES = [
        "'self'", "'unsafe-inline'", "'unsafe-eval'", 'none', '*', 'data:', 'https:',
        'http:', 'blob:', 'filesystem:', 'mediastream:',
    ];

    /**
     * The current CSP directives and their corresponding values.
     *
     * @var array<string, string> Associative array where keys are directive names and values are their parameters.
     */
    private array $directives = [];

    /**
     * The cryptographic nonce value generated for inline scripts and styles.
     * The nonce is included in CSP to allow secure inline scripts/styles without 'unsafe-inline'.
     *
     * @var string|null
     */
    private ?string $nonce = null;

    /**
     * ContentSecurityPolicy constructor.
     *
     * Loads and validates the CSP configuration from a specified file or a default config path.
     *
     * @param string|null $configPath The optional path to the configuration file.
     *
     * @throws \RuntimeException If the configuration file is not found or invalid.
     */
    public function __construct(?string $configPath = null)
    {
        $defaultConfigPath = $configPath ?? Path::Combine(SYSTEM_DIR, 'config', 'csp.php');
        if (file_exists($defaultConfigPath))
        {
            $config = include $defaultConfigPath;
            if (!is_array($config))
            {
                throw new \RuntimeException("Invalid configuration: Config file must return an array.");
            }

            try
            {
                foreach ($config as $directive => $values) {
                    $this->setDirective($directive, $values);
                }
            }
            catch (\InvalidArgumentException $e)
            {
                throw new \RuntimeException("Invalid configuration: " . $e->getMessage());
            }
        }
        else
        {
            throw new \RuntimeException("Configuration file not found at: $defaultConfigPath");
        }
    }

    /**
     * Validates a directive name.
     *
     * Ensures that the provided directive is valid (part of VALID_DIRECTIVES).
     *
     * @param string $directive The directive name to validate.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the directive is not valid.
     */
    private function validateDirective(string $directive): void
    {
        if (!in_array($directive, self::VALID_DIRECTIVES, true)) {
            throw new \InvalidArgumentException("Invalid CSP directive: $directive");
        }
    }

    /**
     * Validates CSP directive values.
     *
     * Ensures that the provided values conform to supported valid CSP values or patterns.
     *
     * @param string|array $values The value(s) to validate.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If any value is invalid.
     */
    private function validateValues(string|array $values): void
    {
        $valuesToValidate = is_array($values) ? $values : explode(' ', $values);
        foreach ($valuesToValidate as $value)
        {
            if (
                !in_array($value, self::VALID_VALUES, true) && // Check against valid standard values
                !preg_match('#^(https?|blob|data|filesystem|mediastream):#', $value) && // Check for valid schemes
                !preg_match('#^\'nonce-[\w\-+/=]+\'+$#', $value) // Allow 'nonce-*' values enclosed with single quotes
            ) {
                throw new \InvalidArgumentException("Invalid CSP value: $value");
            }
        }
    }

    /**
     * Adds or updates a directive in the CSP configuration.
     *
     * Replaces the values for the specified directive with the provided values.
     *
     * @param string $directive The name of the directive.
     * @param string|array $values One or more values for the directive.
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the directive or its values are invalid.
     */
    public function setDirective(string $directive, string|array $values): self
    {
        $this->validateDirective($directive);
        $this->validateValues($values);

        // Ensure the directive exists and normalize the values as a space-separated string
        $this->directives[$directive] = is_array($values) ? implode(' ', $values) : $values;

        return $this;
    }

    /**
     * Appends values to an existing directive.
     *
     * Adds new value(s) to the values list of the specified directive.
     * If the directive does not exist, it is created.
     *
     * @param string $directive The directive name.
     * @param string|array $values The value(s) to append.
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the directive or new values are invalid.
     */
    public function addToDirective(string $directive, string|array $values): self
    {
        $this->validateDirective($directive);
        $this->validateValues($values);

        $valuesToAdd = is_array($values) ? implode(' ', $values) : $values;

        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = $valuesToAdd;
        } else {
            $this->directives[$directive] .= ' ' . $valuesToAdd;
        }

        return $this;
    }

    /**
     * Removes a directive or specific values from a directive.
     *
     * If values are provided, only those values are removed from the directive.
     * If no values are provided, the entire directive is removed.
     *
     * @param string $directive The directive name.
     * @param string|array|null $values Optional: Specific value(s) to remove.
     *
     * @return self
     */
    public function removeDirective(string $directive, string|array|null $values = null): self
    {
        if (is_null($values))
        {
            // Remove the entire directive
            unset($this->directives[$directive]);
        }
        else
        {
            // Remove specific values from the directive
            $valuesToRemove = is_array($values) ? $values : [$values];
            $currentValues = explode(' ', $this->directives[$directive]);

            $this->directives[$directive] = implode(
                ' ',
                array_diff($currentValues, $valuesToRemove)
            );

            if (trim($this->directives[$directive]) === '') {
                unset($this->directives[$directive]);
            }
        }

        return $this;
    }

    /**
     * Generates a cryptographically secure nonce for inline scripts and styles.
     *
     * Nonce is added to 'script-src' and 'style-src' directives if 'unsafe-inline' is absent.
     *
     * @return string The generated nonce value.
     *
     * @throws RandomException If secure random bytes cannot be generated.
     */
    public function generateNonce(): string
    {
        if ($this->nonce === null)
        {
            // Generate a base64-encoded random 16-byte nonce
            $this->nonce = base64_encode(random_bytes(16));

            // Add nonce to 'script-src' if 'unsafe-inline' is not present
            if (!isset($this->directives['script-src']) || !str_contains($this->directives['script-src'], "'unsafe-inline'"))
            {
                $this->addToDirective('script-src', "'nonce-{$this->nonce}'");
            }

            // Add nonce to 'style-src' if 'unsafe-inline' is not present
            if (!isset($this->directives['style-src']) || !str_contains($this->directives['style-src'], "'unsafe-inline'"))
            {
                $this->addToDirective('style-src', "'nonce-{$this->nonce}'");
            }
        }

        return $this->nonce;
    }

    /**
     * Builds the complete Content Security Policy as a header string.
     *
     * Combines all defined directives and values into a single CSP header string.
     *
     * @return string The constructed CSP header value.
     */
    public function build(): string
    {
        $policy = '';

        foreach ($this->directives as $directive => $values) {
            $policy .= $directive . ' ' . (is_array($values) ? implode(' ', $values) : $values) . '; ';
        }

        return trim($policy);
    }

    /**
     * Converts the CSP directives into an associative array.
     *
     * Provides a representation of the directives and their values.
     *
     * @return array<string, string> An associative array of CSP directives and values.
     */
    public function toArray(): array
    {
        return $this->directives;
    }

    /**
     * @inheritDoc
     */
    public function hasNonce(): bool
    {
        return !empty($this->nonce);
    }
}