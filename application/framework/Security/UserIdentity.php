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
namespace Application\Security;

use Exception;
use RuntimeException;
use System\ArgumentException;
use System\Database\DbFactory;
use System\Database\SqlException;
use System\Diagnostics\LogWriter;
use System\Http\Request;
use System\Security\Cryptography\Aes;
use System\Security\SecurityException;
use System\Security\UserInterface;

/**
 * Represents a user identity within the platform, encapsulating user-related
 * data, permissions, roles, and account attributes. This class is primarily
 * initialized and managed by the {@link SessionManager} class, ensuring secure and reliable
 * handling of user sessions and authentication states.
 *
 * ## Key Features:
 *  - **Permission Management**: Allows checking and managing user permissions
 *    with role-based access control.
 *  - **Role Identification**: Distinguishes between guest users, admins, and
 *    the root administrator (owner).
 *  - **Account Data Handling**: Manages user attributes such as account details
 *    while safeguarding sensitive information like passwords.
 *  - **Dynamic User Initialization**: Works dynamically with session cookies
 *    and the database to create an authenticated user identity.
 *
 *  ## Usage:
 *  - Check user roles (e.g., `isAdmin()`, `isGuest()`, `isOwner()`).
 *  - Verify access permissions using `checkAccess()`.
 *  - Retrieve user account information via `asArray()`.
 *
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025, Steven Wilson
 */
final class UserIdentity implements UserInterface
{
    /**
     * Regular expression pattern to validate a username.
     * Ensures the username contains only allowed characters and is between 4 and 32 characters in length.
     *
     * @var string
     */
    public const string VALID_USERNAME_REGEX = '/^[a-zA-Z0-9_\[\]+\-&.#*^%$><=()@]{4,32}$/';

    /**
     * The user's ID... if 0, means user is a guest
     * @var int
     */
    protected int $userId = 0;

    /**
     * @var array
     */
    protected array $userData = [];

    /**
     * Indicates whether this user identity is the website's root admin (Owner)
     * @var bool
     */
    protected bool $isOwner = false;

    /**
     * @var bool
     */
    protected bool $isAdmin = false;

    /**
     * @var bool
     */
    protected bool $isBanned = false;

    /**
     * An array of user permissions
     * @var string[]
     */
    protected array $permissions = array();

    /**
     * Constructor method to initialize the user with specified ID and account details.
     *
     * @param int $userId The unique identifier for the user. Defaults to 0.
     * @param array|null $account Optional account details for the user. The password element, if present, will be removed.
     *
     * @throws SqlException
     */
    public function __construct(int $userId = 0, #[\SensitiveParameter] ?array $account = null)
    {
        // Set internal vars
        $this->userId = $userId;
        if ($account !== null)
        {
            unset($account['password']);
            $this->userData = $account;
        }

        // Initialize user variables
        $this->loadPermissions();
    }

    /**
     * @inheritDoc
     */
    public function isGranted(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * @inheritDoc
     */
    public function isGuest(): bool
    {
        return $this->userId == 0;
    }

    /**
     * @inheritDoc
     */
    public function getUsername(): string
    {
        return $this->userData['username'];
    }

    /**
     * @inheritDoc
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Loads and initializes user permissions based on the account's group ID.
     * Fetches account group attributes, determines administrative status, and retrieves associated permissions.
     *
     * @return void
     * @throws SqlException
     */
    protected function loadPermissions(): void
    {
        // Init vars
        $Log = LogWriter::Instance('debug');
        $connection = DbFactory::GetConnection('web');

        // Non guest
        if ($this->userId != 0)
        {
            // Grab account info
            $query = $connection->from('bf2web_account_groups')->where('id')->equals($this->userData['group_id'])->apply();

            // Query our database and get the users information
            $statement = $query->execute();
            $result = $statement->fetch();

            // Set userdata based on group
            $this->isOwner = ($result['is_super_admin'] == 1);
            $this->isAdmin = ($result['is_admin'] == 1);
            $this->isBanned = ($result['is_banned'] == 1);
            $statement->closeCursor();

            // Grab permissions
            $query = $connection->from('bf2web_permissions')->where('group_id')->equals($this->userData['group_id'])->apply();
            $statement = $query->execute();
            $result = $statement->fetchAll();

            foreach ($result as $perm) {
                $this->permissions[] = $perm['key'];
            }
            $statement->closeCursor();
        }
    }

    /**
     * Creates a new user account and adds the user data to both the stats and website databases.
     *
     * @param string $username The nickname of the user being created
     * @param string $password The hashed password of the user (must be an MD5 hash)
     * @param string $email The email address of the user
     * @param string $isoCode The ISO country code representing the user's location
     * @param int $userGroupId The ID of the group to which the user belongs
     *
     * @return void
     *
     * @throws ArgumentException If the password does not appear to be an MD5 hash
     * @throws Exception If the stats database is Offline
     */
    public static function Create(string $username, #[\SensitiveParameter] string $password, string $email, string $isoCode, int $userGroupId): void
    {
        // Init vars
        $Log = LogWriter::Instance('debug');
        $connection = DbFactory::GetConnection('web');
        $statsConnection = \Application::TryStatsDatabaseConnection();

        // Did we successfully connect?
        if ($statsConnection === false)
        {
            $Log->logWarning("[UserIdentity::Create] Unable to connect to the stats database!");
            throw new Exception("Unable to connect to the stats database!");
        }

        // Ensure the password passed is already a md5 hash
        if (!preg_match('/^[a-f0-9]{32}$/', $password))
            throw new ArgumentException("Invalid password! Password must be an md5 hash!");

        // Validate the allowed characters in a username. Battlefield 2 was very nice and allowed
        // so many special characters in the name -_-
        $username = trim($username);
        if (!preg_match(UserIdentity::VALID_USERNAME_REGEX, $username))
        {
            throw new ArgumentException(
                'Username must be between 4 and 32 characters long and can only contain letters, numbers, and the characters _ [ ] + - & . # * ^ % $ > < = ( ) @'
            );
        }

        // Ensure we have at least 3 normal characters in the name
        if (!preg_match('/[a-zA-Z].*[a-zA-Z].*[a-zA-Z]/', $username))
        {
            throw new ArgumentException('Username must contain at least 3 normal letters (a-z or A-Z).');
        }

        // Encrypt password
        $security_seed = \System::Config()->get('security_seed');
        $key = self::HashSecurityKey($security_seed);
        $aes = new Aes($password, $key, 'aes-256-cbc');
        $encPassword = $aes->encrypt();

        // Create insert data
        $data = [
            'nick' => $username,
            'password' => $encPassword,
            'email' => $email,
            'country' => $isoCode,
            'joined' => time()
        ];

        // Insert into stats tables first!
        $statsConnection->insert('player')->setValues($data)->execute();
        $pid = $statsConnection->lastInsertId("id");

        // Create insert data
        $data = [
            'id' => $pid,
            'username' => $username,
            'password' => $encPassword,
            'email' => $email,
            'group_id' => $userGroupId,
        ];

        // Insert into website tables
        $connection->insert('bf2web_accounts')->setValues($data)->execute();
    }

    /**
     * Authenticates a user and creates a UserIdentity instance using the provided credentials. This method
     * does NOT log the user into the platform. To login into the platform, you must use {@link Authentication::Login()}
     *
     * This method will import the provided username into the Web database from the Stats database if the user
     * only exists in the stats database.
     *
     * @param string $username The username of the user. Must be between 3 and 32 characters long.
     * @param string $password The MD5 hashed password of the user. Must be exactly 32 characters long.
     *                         Marked as a sensitive parameter for improved security handling.
     *
     * @return UserIdentity Returns a UserIdentity object if the credentials are valid and authentication succeeds.
     *
     * @throws ArgumentException If the username or password does not meet the required format.
     * @throws UserNotFoundException If the username is not found in the database.
     * @throws RuntimeException If the user is not found in the database after an attempted import.
     * @throws SecurityException If authentication fails due to an invalid password.
     * @throws SqlException
     */
    public static function FromCredentials(string $username, #[\SensitiveParameter] string $password): UserIdentity
    {
        // Fetch user account
        $password = trim($password);
        if (strlen($password) !== 32)
            throw new ArgumentException('Password must be MD5 hashed and 32 characters long');

        // Validate the allowed characters in a username. Battlefield 2 was very nice and allowed
        // so many special characters in the name -_-
        $username = trim($username);
        if (!preg_match(UserIdentity::VALID_USERNAME_REGEX, $username))
        {
            throw new ArgumentException(
                'Username must be between 4 and 32 characters long and can only contain letters, numbers, and the characters _ [ ] + - & . # * ^ % $ > < = ( ) @'
            );
        }

        // Ensure we have at least 3 normal characters in the name
        if (!preg_match('/[a-zA-Z].*[a-zA-Z].*[a-zA-Z]/', $username))
        {
            throw new ArgumentException('Username must contain at least 3 normal letters (a-z or A-Z).');
        }

        // Fetch database
        $connection = DbFactory::GetConnection('web');
        $query = $connection->from('bf2web_accounts')->where('username')->equals($username)->apply();
        $statement = $query->execute();

        // Ensure user exists
        $result = $statement->fetch();
        if (empty($result))
        {
            // Close the cursor
            $statement->closeCursor();

            // Try and import the user from the stats database
            $result = self::TryImportFromStatsDb($username, $password);
            if ($result === false)
            {
                throw new UserNotFoundException("Username '{$username}' not found.");
            }

            // Try again now
            $statement = $query->execute();
            $result = $statement->fetch();
        }

        // Sanity check
        if (empty($result))
        {
            \System::Log()->logError("[UserIdentity::FromCredentials] User not found in database after import!");
            throw new RuntimeException("User not found in database after import!");
        }

        // Derive the decryption key. This will make decrypting the password
        // impossible without both the security_seed AND md5 password hash
        $securitySeed = \System::Config()->get('security_seed');
        $key = self::HashSecurityKey($securitySeed);

        // Load AES class
        $encryption = new Aes($password, $key);
        $password = $encryption->encrypt();
        if ($result['password'] != $password)
        {
            throw new SecurityException("Authentication failed. Invalid password.");
        }

        $userId = (int) $result['id'];
        unset($result['password']);

        return new UserIdentity($userId, $result);
    }

    /**
     * Attempts to import a user's data from the statistics database into the system.
     *
     * @param string $username The username of the user to be imported.
     * @param string $password The MD5 hashed password corresponding to the user's account in the stats database.
     *                         Marked as sensitive to ensure protection during handling.
     *
     * @return bool Returns true if the import is successful, or false if the user did not exist in the stats database.
     *
     * @throws SecurityException If the password given does not match the password in the stats database
     * @throws Exception If the stats database is offline or is outdated
     */
    private static function TryImportFromStatsDb(string $username, #[\SensitiveParameter] string $password): bool
    {
        // Grab logger
        $logWriter = \System::Log();

        // First, we need to make sure the stats database is online
        $connection = \Application::TryStatsDatabaseConnection();
        if ($connection === false)
        {
            $logWriter->logDebug("[Auth] Stats database is offline. Cannot import player data.");
            throw new Exception("Stats database is offline. Cannot import player data.");
        }

        // Verify stats database version meets the requirement
        $statsVersion = \Application::StatsDbVersion();
        $reqVersion = \Application::ExpectedStatsDbVersion();
        if ($statsVersion < $reqVersion)
        {
            $logWriter->logDebug("[Auth] Stats database version is outdated. Cannot import player data.");
            throw new Exception("Stats database version is outdated. Cannot import player data. Please update your stats database to version ". $reqVersion ." or higher.");
        }

        // Check if username exists
        $query = $connection->from('player')
            ->select('id', 'password', 'email', 'country', 'rank_id')
            ->where('name')->equals($username)->apply();
        $statement = $query->execute();

        // Ensure user exists
        $result = $statement->fetch();
        if (empty($result))
        {
            return false;
        }

        // Get our encryption key
        $securitySeed = \System::Config()->get('security_seed');
        $key = self::HashSecurityKey($securitySeed);
		
		// See if the database is using the old MD5 hashing of the password (legacy)
		if ($result['password'] == $password)
		{
			//throw new SecurityException("Authentication failed. Stats database is using legacy MD5 password hashing, which is not secure.");
			
			// Lets update the stats database password with the new hashing System
			$encryption = new Aes($password, $key);
			$result['password'] = $encryption->encrypt();
			
			// Update the stats database
			$connection->update('player')
                ->setValues(['password' => $result['password']])
                ->where('id')->equals($result['id'])->apply()
                ->execute();
			
			// Log the change
            $logWriter->logDebug("[Auth] Updated user password from legacy MD5 to new AES encryption in Stats database for player: ". $username);
		}
		else
		{
			// Load AES class
			$encryption = new Aes($password, $key);
			$encPassword = $encryption->encrypt();
			if ($result['password'] != $encPassword)
			{
				throw new SecurityException("Authentication failed. Invalid password.");
			}
		}

        // Ok, so if we are here, the player exists, so lets port over the data
        $webData = [
            'id' => $result['id'],
            'username' => $username,
            'password' => $result['password'],
            'email' => $result['email'],
            'group_id' => \System::Config()->get('default_group_id'),
            'session_ip' => Request::ClientIp()->toString(),
            'timezone' => \System::Config()->get('default_timezone'),
            'last_seen' => time(),
        ];

        $webConnection = DbFactory::GetConnection('web');
        $webConnection->insert('bf2web_accounts')->setValues($webData)->execute();
        return true;
    }

    /**
     * Derives a 32-byte encryption key using SHA-256.
     *
     * @param string $baseKey The base key utilized as the HMAC secret key.
     *
     * @return string The derived encryption key in binary format.
     */
    private static function HashSecurityKey(#[\SensitiveParameter] string $baseKey): string
    {
        // Derive a 32-byte encryption key using SHA-256
        return hash('sha256', $baseKey, true); // Output in binary
    }
}