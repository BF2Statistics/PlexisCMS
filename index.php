<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

/**
 * Define ROOT and system paths. This ensures all paths are consistent across the application.
 */
const CODE_VERSION = '0.1.0';
const CODE_VERSION_DATE = '2025-4-14';
const DS = DIRECTORY_SEPARATOR;
const ROOT = __DIR__;
const SYSTEM_DIR = ROOT . DS . 'system';
const APP_DIR = ROOT . DS . 'application';

define('TIME_START', microtime(true));

// Make sure we are running php version 8.2.2 or newer!!!!
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80402)
    die("PHP version 8.4.2 or newer required to run the this application. Your version: " . PHP_VERSION);

// Make sure we have PDO loaded
if (!defined('PDO::ATTR_DRIVER_NAME'))
    die("PDO extension is not loaded! This version of the ASP requires PHP's PDO extension. Please enable it and try again.");

// Ensure mod rewrite is enabled
if (!isset($_SERVER['HTTP_MOD_REWRITE']) || $_SERVER['HTTP_MOD_REWRITE'] != "On" )
    die("Apache Module mod_rewrite is required! Please enable it and try again.");

// Set Error Reporting. Set display_errors to false, because we will handle our own Error displays
error_reporting(E_ALL);
ini_set("log_errors", "1");
ini_set("error_log", SYSTEM_DIR . DS . 'logs' . DS . 'php_errors.log');
ini_set("display_errors", "0");

// Require the necessary scripts to launch the system
require SYSTEM_DIR . DS . 'framework' . DS . 'Autoloader.php';
require SYSTEM_DIR . DS . 'System.php';

// Load the controller, which in turn loads the current task
System::Run();