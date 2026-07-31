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

/**
 * Interface ContentSecurityPolicyInterface
 *
 * Defines the contract for managing Content Security Policies (CSPs).
 */
interface ContentSecurityPolicyInterface
{
    /**
     * Sets a directive in the CSP.
     *
     * @param string $directive The directive name (e.g., 'default-src', 'script-src')
     * @param string|array $values The value(s) for the directive
     * @return self
     */
    public function setDirective(string $directive, string|array $values): self;

    /**
     * Appends values to an existing directive.
     *
     * @param string $directive The directive name
     * @param string|array $values The value(s) to append
     * @return self
     */
    public function addToDirective(string $directive, string|array $values): self;

    /**
     * Removes a directive or specific values from a directive.
     *
     * @param string $directive The directive name
     * @param string|array|null $values Optional: specific values to remove
     * @return self
     */
    public function removeDirective(string $directive, string|array|null $values = null): self;

    /**
     * Generates a cryptographically secure nonce and includes it in the CSP.
     *
     * @return string The generated nonce
     */
    public function generateNonce(): string;

    /**
     * Checks if a nonce exists in the current context.
     *
     * @return bool True if a nonce is present, false otherwise
     */
    public function hasNonce(): bool;

    /**
     * Builds the CSP as a string.
     *
     * @return string The CSP header value
     */
    public function build(): string;

    /**
     * Retrieves the policy as an associative array.
     *
     * @return array The directives and their values
     */
    public function toArray(): array;
}