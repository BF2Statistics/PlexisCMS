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
namespace System\Http\Session\Storage;

use FilesystemIterator;
use RuntimeException;
use System\Cache\DriverInfo;
use System\IO\Directory;
use System\IO\File;
use System\IO\FileNotFoundException;
use System\IO\IOException;
use System\IO\Path;
use System\ObjectDisposedException;

/**
 * Provides file-based session storage implementation.
 *
 * This class handles session storage by creating, reading,
 * and managing session data files located in a specific
 * directory. Each session is stored as a file identified
 * by a hashed session ID.
 *
 * Implements the SessionStorageInterface and provides a
 * mechanism to initialize, load, save, and destroy sessions.
 *
 * Requires configuration options to specify the storage directory.
 * Directory creation is handled automatically if it doesn't exist.
 * Sessions are stored in files using sanitized filenames derived
 * from the hashed session IDs.
 */
class FileSessionStorage implements SessionStorageInterface
{
    /**
     * The base directory where session files will be stored.
     *
     * @var string
     */
    protected string $directory = '';

    /**
     * Constructs the FileSessionStorage instance and initializes the session storage directory.
     *
     * @param array $options Array containing configuration options for the session storage.
     *                       Expected 'path' key specifies the base directory for session storage.
     *
     * @throws RuntimeException If the directory cannot be created and does not exist.
     */
    public function __construct(array $options, string $keyPrefix = '')
    {
        $normalizedPath = Path::Normalize($options['path']);
        $this->directory = Path::Combine(ROOT, $normalizedPath);

        // Ensure directory exists
        if (!Directory::Exists($this->directory)) {
            Directory::CreateDirectory($this->directory);
        }
    }

    /**
     * @inheritDoc
     */
    public function initialize(string $sessionId, int $ttl): void
    {
        $filePath = $this->getFilePath($sessionId);

        // Update session file modification time
        if (file_exists($filePath)) {
            touch($filePath);
        }
    }

    /**
     * Loads session data associated with the given session ID.
     *
     * @param string $sessionId The session ID whose data will be loaded.
     *
     * @return string|null The session data as a string, or null if no data exists for the session.
     *
     * @throws FileNotFoundException
     * @throws IOException
     * @throws ObjectDisposedException
     */
    public function load(string $sessionId): ?string
    {
        $filePath = $this->getFilePath($sessionId);
        if (file_exists($filePath)) {
            return File::ReadAllText($filePath);
        }

        return null;
    }

    /**
     * Saves session data for the given session ID.
     *
     * @param string $sessionId The session ID for which data is being saved.
     * @param string $data The session data to save.
     * @param int $ttl The time-to-live (TTL) for the session, in seconds (not currently used).
     *
     * @throws IOException
     * @throws ObjectDisposedException
     */
    public function save(string $sessionId, string $data, int $ttl): void
    {
        $filePath = $this->getFilePath($sessionId);
        File::WriteAllText($filePath, $data);
    }

    /**
     * Deletes the session data associated with the given session ID.
     *
     * @param string $sessionId The session ID for which data will be destroyed.
     *
     * @throws RuntimeException If the session storage has not been initialized.
     */
    public function destroy(string $sessionId): void
    {
        $filePath = $this->getFilePath($sessionId);
        File::Delete($filePath);
    }

    /**
     * @inheritDoc
     */
    public function purgeStaleSessions(int $timeToLive): void
    {
        $fileSystemIterator = new FilesystemIterator($this->directory);
        $threshold = strtotime("-{$timeToLive} seconds");
        foreach ($fileSystemIterator as $file)
        {
            if ($threshold >= $file->getMTime())
            {
                File::Delete($file->getRealPath());
            }
        }
    }

    /**
     * Retrieves information about the driver used for caching.
     *
     * This method provides details about the caching driver, including its name
     * and whether it is supported in the current environment.
     *
     * @return DriverInfo An instance containing the driver's name and support status.
     */
    public static function GetDriverInfo(): DriverInfo
    {
        return new DriverInfo(
            name: 'File',
            readableName: 'File Storage',
            isSupported: true,
            description: 'File stores cached data in files on the local filesystem. It is always supported.'
        );
    }

    /**
     * Retrieves the file path for the specified session ID.
     *
     * @param string $sessionId The session ID used to generate the file path.
     *
     * @return string The full file path associated with the session ID.
     */
    protected function getFilePath(string $sessionId): string
    {
        $fileName = md5($sessionId) . '.session';
        return Path::Combine($this->directory, $fileName);
    }
}