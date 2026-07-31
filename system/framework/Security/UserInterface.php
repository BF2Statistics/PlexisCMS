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
 * Represents a user identity in the system.
 *
 * Provides methods to retrieve user-specific information and
 * to verify permissions associated with the user. This interface
 * acts as a blueprint for defining user-related functionalities.
 */
interface UserInterface
{
    /**
     * This method is used to return whether the user have a specific permission
     *
     * @param string $permission The name of the operation we are checking
     *   permissions for
     * @return bool
     */
    public function isGranted(string $permission): bool;

    /**
     * Returns whether this user identity is a guest
     *
     * @return bool
     */
    public function isGuest(): bool;

    /**
     * Returns the username of this user identity
     *
     * @return string
     */
    public function getUsername(): string;

    /**
     * Retrieves the unique identifier of the user.
     *
     * @return int The user's unique identifier.
     */
    public function getUserId(): int;
}