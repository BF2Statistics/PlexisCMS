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
namespace Modules\Install\Controllers;

use Application\Security\Authentication;
use Application\Security\UserIdentity;
use Modules\Install\Models\ConnectionTester;
use Modules\Install\Models\SchemaSetup;
use ReflectionException;
use System;
use System\Configuration\ConfigManager;
use System\Database\DbFactory;
use System\Http\JsonResponse;
use System\IO\File;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\JsonController;
use System\Routing\Route;

/**
 * Handles AJAX-based installation processes for the Plexis CMS. This class is
 * responsible for processing installation requests, configuring system settings,
 * and testing database connections required for the application to function.
 *
 * ## Key Responsibilities:
 *  - **Site Configuration**: Updates and saves the site’s main configuration
 *    settings, such as site title, description, keywords, timezone, and security seed.
 *  - **Database Setup**: Configures database settings for both the statistics
 *    and web databases, ensuring connectivity and correctness of configuration.
 *  - **Database Connection Testing**: Validates the provided database
 *    connections, including checks for existing required tables.
 *  - **AJAX Response Handling**: Sends success or error results in JSON format
 *    for front-end consumption.
 *
 *  ## Features:
 *  - Processes `POST` requests for the installation process via a route to `/install`.
 *  - Loads database connection settings from user input and saves them securely.
 *  - Provides detailed error messages and validation for database connections.
 *  - Utilizes reusable models like `ConnectionTester` and `SchemaSetup` for backend operations.
 *
 * ## Usage:
 *  This class is intended to handle AJAX requests during the installation process
 *  of the CMS. It assumes that configuration settings and database credentials are
 *  provided as part of a `POST` request in a designated format.
 *
 * @package Modules\Install
 * @extends System\JsonController
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */
class AjaxController extends  JsonController
{
    /**
     * @var ConnectionTester
     */
    protected ConnectionTester $tester;

    /**
     * @var SchemaSetup
     */
    protected SchemaSetup $setup;

    /**
     * Handles the installation process by setting up site configuration and testing database connections.
     *
     * @return JsonResponse Returns a JSON response indicating success or failure of the installation process.
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ReflectionException
     */
    #[Route('/install', 'installer-ajax', ['POST'], true)]
    public function postIndex() : JsonResponse
    {
        if (!$this->isAction('settings'))
        {
            return $this->respondWith(false, 'Invalid action');
        }
        else if ($this->isLocked())
        {
            return $this->respondWith(false, 'Installer is locked');
        }

        // Set site settings
        $postData = $this->request->post();
        $config = System::Config();
        $config->set('default_timezone',    $postData['default_timezone']);
        $config->set('security_seed',       $postData['security_seed']);
        $config->save();

        $config = \Application::Config();
        $config->set('site_title',          $postData['site_title']);
        $config->set('description',         $postData['description']);
        $config->set('keywords',            $postData['keywords']);
        $config->save();

        // Load database config
        $dbFilePath = Path::Combine(SYSTEM_DIR, "config", "database.php");
        $config = ConfigManager::Load($dbFilePath);
        $config->set('stats', [
            'driver'	   => $postData['stats_db_type'],
            'host'         => $postData['stats_db_host'],
            'port'         => $postData['stats_db_port'],
            'username'     => $postData['stats_db_user'],
            'password'     => $postData['stats_db_pass'],
            'database'     => $postData['stats_db_name']
        ]);
        $config->set('web', [
            'driver'	   => $postData['web_db_type'],
            'host'         => $postData['web_db_host'],
            'port'         => $postData['web_db_port'],
            'username'     => $postData['web_db_user'],
            'password'     => $postData['web_db_pass'],
            'database'     => $postData['web_db_name']
        ]);
        $config->save();

        // Load model
        $this->loadModel(ConnectionTester::class, 'tester');

        // Let's test our Stats database connection
        try
        {
            $tablesExist = $this->tester->performChecks($config->get('stats'), false);
            if (!$tablesExist)
            {
                return $this->respondWith(false, 'Stats database tables do not exist', ['dataExists' => false]);
            }
        }
        catch (\Exception $e)
        {
            System::LogThrowable($e);
            return $this->respondWith(false, $e->getMessage(), ['dataExists' => false]);
        }

        // Let's test our Web database connection
        try
        {
            $tablesExist = $this->tester->performChecks($config->get('web'), true, 'bf2web');
        }
        catch (\Exception $e)
        {
            System::LogThrowable($e);
            return $this->respondWith(false, $e->getMessage(), ['dataExists' => false]);
        }

        // Check for existing data
        try
        {
            $emptyTables = $this->tester->checkEmptyTables('bf2web');
            return $this->respondWith(true, 'Success', ['dataExists' => !$emptyTables]);
        }
        catch (\Exception $e)
        {
            // Do nothing
            System::LogThrowable($e);
        }

        return $this->respondWith(true, 'Success', ['dataExists' => false]);
    }

    /**
     * Handles the installation of database tables and default data for the application.
     *
     * This method invokes the schema setup process, including creating the required
     * database tables and inserting default data. If the operations succeed, a success
     * response is returned; otherwise, an error response with the exception message is provided.
     *
     * @return JsonResponse A JSON response indicating the success or failure of the schema setup process.
     *
     * @throws ReflectionException
     * @throws FileNotFoundException
     */
    #[Route('/install/tables', 'installer-ajax-tables', ['POST'], true)]
    public function postTables() : JsonResponse
    {
        if (!$this->isAction('install'))
        {
            return $this->respondWith(false, 'Invalid action');
        }
        else if ($this->isLocked())
        {
            return $this->respondWith(false, 'Installer is locked');
        }

        // Load model
        $this->loadModel(SchemaSetup::class, 'setup');

        try
        {
            // Create schema
            $this->setup->installSchema();

            // Insert default data
            $this->setup->installDefaultData();

            // Be nice and respond
            return $this->respondWith(true, 'Success');
        }
        catch (\Exception $e)
        {
            System::LogThrowable($e);
            return $this->respondWith(false, $e->getMessage());
        }
    }

    /**
     * Finalizes the installation process by configuring the super admin account.
     *
     * This method processes the provided super admin details, creating a new account
     * or updating an existing one to have owner privileges, depending on the received input.
     * If successful, a success response is returned; otherwise, an error response with the exception
     * message is provided.
     *
     * @return JsonResponse A JSON response indicating the success or failure of the finalization process.
     */
    #[Route('/install/finalize', 'installer-ajax-finalize', ['POST'], true)]
    public function postFinalize() : JsonResponse
    {
        if (!$this->isAction('finalize'))
        {
            return $this->respondWith(false, 'Invalid action');
        }
        else if ($this->isLocked())
        {
            return $this->respondWith(false, 'Installer is locked');
        }

        try
        {
            // Extract variables
            /** @var System\Collections\Dictionary $postData */
            $postData = $this->request->post();
            $user = $postData['super_admin_user'];
            $pass = $postData['super_admin_pass'];

            // Verify if "new bf2 account" was checked on the form
            if ($postData->containsKey('newAccount') && $postData['newAccount'] == 1)
            {
				// Attempt to create the new user account
                $email = $postData['super_admin_email'];
                $iso = $postData['super_admin_iso'];
                UserIdentity::Create($user, md5($pass), $email, $iso, 1);
            }
            else
            {
                // Log into an existing user account. Throws exception if user cant be validated
                $user = UserIdentity::FromCredentials($user, md5($pass));

                // Update the user to owner status
                $connection = DbFactory::GetConnection('web');
                $connection->update('bf2web_accounts')
                    ->set('group_id', '=', 1)
                    ->where('id')->equals($user->getUserId())->apply()
                    ->execute();     
            }

            // Login the user using the provided credentials. This creates the auth cookies and session
            Authentication::Login($user->getUsername(), md5($pass));
			
			// Finally, lock the installer so it's inaccessible to the public!
			$filePath = Path::Combine(APP_DIR, 'config', 'installer.lock');
			File::Create($filePath);

            // Be nice and respond
            return $this->respondWith(true, 'Success');
        }
        catch (System\Database\SqlException $ex)
        {
            System::LogThrowable($ex);
            return $this->respondWith(false, $ex->getMessage(), ['query' => $ex->getQuery()]);
        }
        catch (\Exception $e)
        {
            System::LogThrowable($e);
            return $this->respondWith(false, $e->getMessage());
        }
    }

    /**
     * Determines whether the installer is locked.
     *
     * This method checks for the presence of a lock file in the specified directory
     * to determine if the installer process is currently locked.
     *
     * @return bool True if the lock file exists, indicating the installer is locked; otherwise, false.
     */
    protected function isLocked(): bool
    {
        $filePath = Path::Combine(APP_DIR, 'config', 'installer.lock');
        return File::Exists($filePath);
    }
}