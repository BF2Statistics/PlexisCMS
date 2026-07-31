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
use Random\RandomException;
use System\ArgumentException;
use System\Database\DbFactory;
use System\Http\Cookie;
use System\Http\Request;
use System\Http\SameSiteSetting;
use System\Security\Cryptography\Aes;
use System\Security\SecurityException;

/**
 * Class Authentication
 *
 * Provides static methods to authenticate users, manage sessions, rotate tokens, and handle authentication cookies.
 * This class serves as the primary entry point for user authentication management.
 */
final class Authentication
{
    /**
     * Authenticates a user based on their username and MD5 hashed password.
     *
     * This method checks the provided credentials against the stored data. If valid, it creates
     * a session token, sets a secure cookie, and logs the user in.
     *
     * @param string $username The username of the account to authenticate.
     *                         Must be between 4 and 32 characters, only allowing alphanumeric characters
     *                         and special characters specific to the system.
     * @param string $password The MD5 hashed password to validate. Must always be 32 characters long.
     *                         The password is treated as sensitive input.
     * @param bool $rememberMe If `true`, the user's session will be extended for the extended session length.
     *
     * @return bool Returns `true` if the user is successfully authenticated, otherwise `false`.
     *
     * @throws ArgumentException If the username or password does not meet validation rules.
     * @throws SecurityException If password validation fails.
     * @throws Exception For any other unexpected errors during execution.
     */
    public static function Login(string $username, #[\SensitiveParameter] string $password, bool $rememberMe = false): bool
	{
        // Load the UserIdentity. This will throw an exception if the credentials are invalid.
        $userIdentity = UserIdentity::FromCredentials($username, $password);
        $userId = $userIdentity->getUserId();

        $maxSessions = (int)\System::Config()->get('session_concurrency_limit', 5);
        $connection = DbFactory::GetConnection('web');

        // Fetch all current active sessions for this user, ordered by OLDEST first
        $query = $connection->from('bf2web_sessions')->select('selector')->where('account_id')->equals($userId)->apply();
        $query->orderBy('last_seen', 'ASC');
        $rows = $query->execute()->fetchAll();

        // Check if we are at (or over) capacity. We need to make room for 1 new session, so if count >= max, we must delete.
        if (count($rows) >= $maxSessions)
        {
            // Calculate how many we need to remove (usually just 1, but maybe more if config changed)
            // (Current Count) - (Max Limit) + 1 (for the new one coming in)
            $excess = count($rows) - $maxSessions + 1;

            // Slice the array to get the oldest N records
            $ids = [];
            $toDelete = array_slice($rows, 0, $excess);
            foreach ($toDelete as $victim)
            {
                $ids[] = $victim['selector'];
            }

            $connection->delete('bf2web_sessions')->where('selector')->in($ids)->execute();
        }

        // Generate the "Selector:Validator" pair
        $cookieToken = ''; // Initialize for reference passing
        $hashedValidator = self::CreateValidator($cookieToken);
        $selector = self::GenerateSelector();

        // Force convert to UTF-8 (Fixes any weird Latin-1 junk)
        // We ignore invalid chars that can't be converted.
        $rawAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $rawAgent);

        // set our session cookie
        $time = time();
        $key = ($rememberMe) ? 'session_length_extended' : 'session_length_default';
        $expireTime = ((int)\System::Config()->get($key) + $time);
        $data = [
            'selector' => $selector,
            'validator' => $hashedValidator,
            'account_id' => $userId,
            'expires' => $expireTime,
            'refresh' => $time + 900,
            'last_seen' => $time,
            'fingerprint' => self::GetClientFingerprint(),
            'user_agent' => mb_substr($clean, 0, 512, 'UTF-8'),
            'last_ip' => Request::ClientIp()->toString(),
        ];

        // Insert into website tables
        $connection->insert('bf2web_sessions')->setValues($data)->execute();

        // Set user session cookie
        self::SetAuthCookie($selector . '::' . $cookieToken, $expireTime);

        // Attach the user to the current request
        $request = Request::Global();
        $session = $request->session();
        $session->attachUser($userIdentity);

        // Regenerate ID!
        $session->regenerateId();

        // Signal back
		return true;
	}

    /**
     * Logs the current user out of their session.
     *
     * Removes the authentication session cookie. If the `$immediately` parameter is set to true,
     * the user's session will be terminated immediately for the current request. If it's false, the session
     * remains active until the next request.
     *
     * @param bool $immediately If `true`, immediately detaches the session for the current request. Defaults to `true`.
     * @param bool $allSessions If `true`, deletes all sessions for the current user.
     * @param int $userId The user ID to delete sessions for. Defaults to the currently logged in user.
     *  Parameter $allSessions must be true to use this parameter.
     *
     * @return void
     *
     * @throws Exception If an error occurs while handling the session.
     */
    public static function Logout(bool $immediately = true, bool $allSessions = false, int $userId = 0): void
    {
        $request = Request::Global();
        $currentUser = $request->session()->getUser();
        $currentUserId = $currentUser?->getUserId() ?? 0;

        // 1. DETERMINE TARGET
        // If 0 passed, target the current user.
        $targetUserId = ($userId > 0) ? $userId : $currentUserId;

        // 2. DATABASE CLEANUP (Force Logout)
        // We do this regardless of whether WE have a cookie.
        // We only need the DB connection.
        if ($allSessions && $targetUserId > 0)
        {
            $connection = DbFactory::GetConnection('web');
            $connection->delete('bf2web_sessions')
                ->where('account_id')->equals($targetUserId)
                ->apply()
                ->execute();
        }

        // 3. BROWSER CLEANUP (Am I logging *myself* out?)
        // We only delete cookies/detach if the target is ME (or 0).
        // If I am Admin (1) logging out User (55), skip this.
        $isLoggingSelfOut = ($userId === 0 || $userId === $currentUserId);
        if ($isLoggingSelfOut)
        {
            // Handle the specific "Remember Me" cookie logic
            $cookie = $request->cookie('auth');

            // If we didn't already wipe the DB above, delete the specific token now
            if (!empty($cookie) && !$allSessions && substr_count($cookie, '::') == 1)
            {
                list($selector, $token) = explode('::', $cookie);
                $connection = DbFactory::GetConnection('web');
                $connection->delete('bf2web_sessions')
                    ->where('selector')->equals($selector)
                    ->apply()
                    ->execute();
            }

            // Kill the browser cookie
            Cookie::Delete('auth');

            // Detach from memory
            if ($immediately)
            {
                $request->session()?->detachUser();
            }
        }
    }

    /**
     * Rotates the authentication token for a specific user.
     *
     * Updates the user's session in the database with a newly generated session token
     * and sets a refreshed secure authentication cookie.
     *
     * @param string $selector The session selector value to rotate.
     * @param int $expires The UNIX timestamp indicating when the user session cookie is to expire.
     * @param string $ip_address The IP address of the user's current connection.
     *
     * @return void
     *
     * @throws Exception If the token cannot be generated, database updates fail, or cookie encryption encounters issues.
     */
    public static function RotateValidator(string $selector, int $expires, string $ip_address): void
    {
        // Update session token in the database
        $cookieToken = ''; // Create a reference for passing
        $connection = DbFactory::GetConnection('web');
        $connection->update('bf2web_sessions')
            ->setValues([
                'validator' => self::CreateValidator($cookieToken),
                'refresh' => time() + 900,
                'last_seen' => time(),
                'last_ip' => $ip_address
            ])
            ->where('selector')->equals($selector)
            ->apply()
            ->execute();

        // Update cookie
        self::SetAuthCookie($selector . '::' . $cookieToken, $expires);
    }

    /**
     * Generates a unique fingerprint for the client.
     *
     * This method combines the client's HTTP user agent with a configured security seed
     * to create a unique SHA-256 hash. This fingerprint is used to enhance session security
     * by tying authentication data to specific client characteristics.
     *
     * @return string Returns a SHA-256 hash representing the unique client fingerprint.
     */
    public static function GetClientFingerprint(): string
    {
        // Build a unique hash for the client. We add the security seed to ensure a rainbow table can't be used
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $agent . \System::Config()->get('security_seed'));
    }

    /**
     * Sets a secure authentication cookie with the provided data.
     *
     * This method generates a session cookie containing encrypted authentication data.
     * Additionally, it applies strict settings such as `HttpOnly`, `Secure`, and `SameSite`.
     *
     * @param string $data The encrypted data to store in the cookie. Treated as sensitive.
     * @param int $expireTime The UNIX timestamp when the cookie should expire.
     *
     * @return void
     *
     * @throws Exception If the cookie cannot be set due to system restrictions or other errors.
     */
    protected static function SetAuthCookie(#[\SensitiveParameter] string $data, int $expireTime): void
    {
        $cookie = new Cookie('auth', $data, $expireTime);
        $cookie->httpOnly = true;
        $cookie->secure = Request::IsSecure();
        $cookie->sameSite = SameSiteSetting::STRICT;
        $cookie->setStatic();
    }

    /**
     * Creates a secure session token for authentication.
     *
     * @param string &$cookieToken A reference to the variable where the generated client-visible
     *                             token (32-character hex string) will be stored.
     *
     * @return string Returns the server-side HMAC signature (`proofToken`) as a binary string.
     *
     * @throws RandomException If the random token generation fails.
     */
    protected static function CreateValidator(string &$cookieToken): string
    {
        // 1. Generate the Raw Key (The "Password" for this session)
        $cookieToken = bin2hex(random_bytes(16)); // 32 chars
        $fingerprint = Authentication::GetClientFingerprint();
        $data = $cookieToken . '::' . $fingerprint;

        // 2. Generate the Lock (HMAC-SHA256)
        $key = \System::Config()->get('security_seed');
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Generates a random selector value for the authentication token that is exactly 12 characters in length.
     *
     * @return string Returns a 12-character hex string representing the random selector
     * @throws RandomException
     */
    protected static function GenerateSelector(): string
    {
        return bin2hex(random_bytes(6));
    }
}