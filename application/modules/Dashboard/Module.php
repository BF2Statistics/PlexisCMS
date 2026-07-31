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

namespace Modules\Dashboard;

use Modules\Dashboard\Controllers\FrontController;
use System\AbstractModule;
use System\Version;

class Module extends AbstractModule
{
    /**
     * @inheritDoc
     */
    public function install(): void
    {
        // Not needed for this module.
    }

    /**
     * @inheritDoc
     */
    public function uninstall(): void
    {
        // Not needed for this module.
    }

    /**
     * @inheritDoc
     */
    public function upgrade(string $previousVersion): void
    {
        // Not needed for this module.
    }

    /**
     * @inheritDoc
     */
    public static function GetSubscribedEvents(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function GetAdminController(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public static function GetRouteControllers(): array
    {
        return [
            FrontController::class
        ];
    }

    /**
     * @inheritDoc
     */
    public static function GetVersion(): Version
    {
        return Version::Parse('1.0.0');
    }

    public static function GetDisplayName(): string
    {
        return 'Plexis Home Page';
    }

    public static function GetDescription(): string
    {
        return '';
    }

    public static function GetAuthor(): string
    {
        return 'Plexis Dev Team';
    }

    public static function GetAuthorEmail(): string
    {
        return '';
    }

    public static function GetAuthorUrl(): string
    {
        return '';
    }

    public static function GetCopyright(): string
    {
        return '';
    }
}