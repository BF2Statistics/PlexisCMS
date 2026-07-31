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

namespace System;

use System\Events\EventSubscriberInterface;

/**
 * Represents an abstract base class for a module that implements the EventSubscriberInterface.
 * This class defines the structure and common properties for modules, including metadata and
 * methods to retrieve controllers and version information.
 */
abstract class AbstractModule implements EventSubscriberInterface
{
    /**
     * The module name
     * @var string
     */
    public readonly string $name;

    /**
     * @var string
     */
    public readonly string $namespace;

    /**
     * The root path to the module
     * @var string
     */
    public readonly string $rootPath;

    /**
     * Module Constructor.
     *
     * @param string $name The name of the module
     */
    public function __construct(string $name)
    {
        // Set internal variables
        $this->name = $name;
        $this->namespace = "Modules\\" . ucfirst($name);
        $this->rootPath = APP_DIR . DS . "modules" . DS . $name;
    }

    /**
     * Called when the module is first installed.
     *  Use this to create DB tables, folders, or default settings.
     */
    abstract public function install(): void;

    /**
     * Called when the module is removed.
     * Use this to drop tables and cleanup.
     */
    abstract public function uninstall(): void;

    /**
     * Called when the detected version on disk is higher than the version in the DB.
     *
     * @param string $previousVersion The version currently installed (e.g., '1.0.0')
     */
    abstract public function upgrade(string $previousVersion): void;

    /**
     * Returns the fully qualified (namespaced) admin controller name for this module, or null if there is none.
     */
    abstract public static function GetAdminController(): ?string;

    /**
     * Returns an array of Frontend controller names for this module that handle routes.
     * Names should be fully qualified (namespaced). DO NOT include the module admin controller here.
     */
    abstract public static function GetRouteControllers(): array;

    /**
     * Retrieves the display name associated with the Module.
     *
     * @return string The display name.
     */
    abstract public static function GetDisplayName(): string;

    /**
     * Retrieves the description associated with the module.
     *
     * @return string The description of the Module.
     */
    abstract public static function GetDescription(): string;

    /**
     *
     */
    abstract public static function GetAuthor(): string;

    /**
     *
     */
    abstract public static function GetAuthorEmail(): string;

    /**
     *
     */
    abstract public static function GetAuthorUrl(): string;

    /**
     *
     */
    abstract public static function GetCopyright(): string;

    /**
     * Returns the version of this module
     */
    abstract public static function GetVersion(): Version;

    /**
     * @inheritDoc
     */
    abstract public static function GetSubscribedEvents(): array;
}