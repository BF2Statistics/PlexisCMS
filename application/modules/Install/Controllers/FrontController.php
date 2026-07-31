<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace Modules\Install\Controllers;

use DateTime;
use DateTimeZone;
use System;
use System\Configuration\ConfigManager;
use System\Database\DbFactory;
use System\HtmlController;
use System\Http\Response;
use System\IO\Path;
use System\Routing\Route;

/**
 * Represents the installation controller responsible for managing the installation process
 * of the application. It extends the HtmlController class to leverage rendering capabilities.
 */
class FrontController extends HtmlController
{
    /**
     * Handles the installer index page by preparing necessary data and rendering the template.
     *
     * Populates a list of time zones categorized by region, retrieves database driver support,
     * assigns configurations and assets to the template, then returns the generated response.
     *
     * @return Response Returns the response object after preparing the installer page.
     *
     * @throws \System\ArgumentException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws \System\IO\DirectoryNotFoundException
     * @throws \System\IO\FileNotFoundException
     * @throws \System\IO\IOException
     * @throws \System\ObjectDisposedException
     * @throws \System\Security\SecurityException
     * @throws \System\Presentation\ViewNotFoundException
     */
    #[Route('/install', 'installer', ['GET'])]
    public function getIndex(): Response
    {
        // First we need to check if the installer.lock file exists
        if (file_exists(Path::Combine(APP_DIR, "config", "installer.lock")))
        {
            $this->response->redirect('/');
            return $this->response;
        }

        // Define our regions
        $regions = array(
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC
        );

        $timezones = array();
        foreach ($regions as $name => $mask)
        {
            $zones = DateTimeZone::listIdentifiers($mask);
            foreach($zones as $timezone)
            {
                // Get the time there right now
                $time = new DateTime('now', new DateTimeZone($timezone));

                // Us dumb Americans can't handle military time
                $ampm = $time->format('H') > 12 ? ' ('. $time->format('g:i a'). ')' : '';

                // Remove region name and add a sample time
                $timezones[$name][$timezone] = substr($timezone, strlen($name) + 1) . ' - ' . $time->format('H:i') . $ampm;
            }
        }

        // Adjust template..
        self::$layout->loadLayout('installer');

        // Get all drivers for the dropdown list
        $items = DbFactory::GetSupportedDrivers();

        // Load database config
        $dbFilePath = Path::Combine(SYSTEM_DIR, "config", "database.php");
        $config = ConfigManager::Load($dbFilePath);

        // Set variables
        self::$layout->assign('timezones', $timezones);
        self::$layout->assign('db_drivers', $items);
        self::$layout->assign(array_merge(System::Config()->fetchAll(), \Application::Config()->fetchAll()));
        self::$layout->assign('database', $config->fetchAll());
        self::$layout->assign('countries', System\Globalization\CountryIsoCodes::COUNTRIES);

        // Finally, return our response
        return $this->response;
    }
}