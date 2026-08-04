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
namespace System\IO;
use System\Convert;
use System\ObjectDisposedException;
use System\Security\SecurityException;

/**
 * Provides properties and instance methods for various folder operations
 *
 * Use the DirectoryInfo class if you are going to reuse an object several times,
 * because a folder exists check will not always be necessary, and will increase
 * performance over the static Directory class methods
 *
 * @author      Steven Wilson
 * @package     System
 * @subpackage  IO
 */
class DirectoryInfo
{
    /**
     * An array of files in this directory
     * @var FileInfo[]
     */
    protected array $filelist;

    /**
     * An array of sub directories in this directory
     * @var DirectoryInfo[]
     */
    protected array $subdirs;

    /**
     * Part of the load on request. Returns whether the current
     * directory has been scanned, and $filelist/$subdirs have been
     * populated
     * @var bool
     */
    protected bool $scanned = false;

    /**
     * Full path to the parent directory
     * @var string
     */
    protected string $parentDir;

    /**
     * Full path to the current directory
     * @var string
     */
    protected string $rootPath;

    /**
     * Indicates whether this directory is disposed (deleted)
     * @var bool
     */
    protected bool $disposed = false;

    /**
     * Class Constructor
     *
     * @param string $path The full path the directory
     * @param bool $create Create the directory if it doesn't exist?
     *
     *   $create is set to true, and there was an error creating the directory
     * @throws \System\IO\DirectoryNotFoundException If the $path directory does not exist,
     *   and $create is set to false.
     *   for various security reasons such as permissions.
     */
    public function __construct(string $path, bool $create = false)
    {
        // If the directory doesn't exist
        if (!is_dir($path))
        {
            // Are we trying to create a new dir?
            if ($create)
                Directory::CreateDirectory($path, 0755);
            else
                // Cant continue from here D:
                throw new DirectoryNotFoundException("Directory '{$path}' does not exist.");
        }

        // Set path
        $this->rootPath = rtrim($path, DIRECTORY_SEPARATOR);
        $this->parentDir = dirname($path);
    }

    /**
     * Returns the base folder name
     *
     * @return string
     */
    public function name(): string
    {
        return basename($this->rootPath);
    }

    /**
     * Returns the full path to the folder, including the folder name
     *
     * @return string
     */
    public function fullName(): string
    {
        return $this->rootPath;
    }

    /**
     * Gets the parent directory of this current directory
     *
     * @return DirectoryInfo
     * @throws DirectoryNotFoundException
     */
    public function getParent(): DirectoryInfo
    {
        return new DirectoryInfo($this->parentDir);
    }

    /**
     * Returns a file list from the current directory matching the given search pattern.
     *
     * @param string|null $searchPattern The regex to run on the files. A file must match this regex
     *     to be added to the list.
     *
     * @return FileInfo[] A list object filled with FileInfo objects
     *
     * @throws SecurityException
     */
    public function getFiles(?string $searchPattern = null): array
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        // Scan directory if we haven't already
        if (!$this->scanned)
            $this->refresh();

        // If we have a search pattern, we loop though each file, and do a compare
        if (!empty($searchPattern))
        {
            // Create a new ObjectListArray
            $return = [];
            foreach ($this->filelist as $file)
            {
                // If filename matches the regex, add to list
                if (preg_match("/{$searchPattern}/i", $file->name()))
                    $return[] = $file;
            }

            return $return;
        }

        return $this->filelist;
    }

    /**
     * Returns the subdirectories of the current directory matching the specified search pattern
     *
     * @param string|null $searchPattern The regex to run on the files. A file must match this regex
     *     to be added to the list.
     *
     * @return DirectoryInfo[] A list object filled with DirectoryInfo objects
     *
     * @throws SecurityException
     */
    public function getDirectories(?string $searchPattern = null): array
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        // Scan directory if we haven't already
        if (!$this->scanned)
            $this->refresh();

        if (!empty($searchPattern))
        {
            $return = [];
            foreach ($this->subdirs as $dir)
            {
                // If filename matches the regex, add to list
                if (preg_match("/{$searchPattern}/i", $dir->name()))
                    $return[] = $dir;
            }

            return $return;
        }

        return $this->subdirs;
    }

    /**
     * Sets the access permissions of the directory
     *
     * @param int $chmod The permission level, as an octal, to set on the  directory (chmod).
     *
     * @return bool returns the success value of setting the permissions.
     *@throws \System\ObjectDisposedException Thrown if the directory was deleted prior to calling
     *      this method.
     *
     * @remarks
     *        Permissions:
     *            0 - no permissions,
     *            1 – can execute,
     *            2 – can write,
     *            4 – can read
     *
     *        The octal number is the sum of those three permissions.
     *
     *        Position of the digit in value:
     *            1 - Always zero, to signify an octal value!!
     *            2 - what the owner can do,
     *            3 - users in the file group,
     *            4 - users not in the file group
     *
     * @example
     *        0600 – owner can read and write
     *        0700 – owner can read, write and execute
     *        0666 – all can read and write
     *        0777 – all can read, write and execute
     *
     */
    public function setAccess(int $chmod): bool
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        return chmod($this->rootPath, $chmod);
    }

    /**
     * Gets the access permissions of the directory
     *
     * @return int the permissions on the directory
     *@throws \System\ObjectDisposedException Thrown if the directory was deleted prior to calling
     *      this method.
     *
     */
    public function getAccess(): int
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        return fileperms($this->rootPath);
    }

    /**
     * Moves the directory and all of its contents to a new path.
     *
     * The old directory will not be removed until the new directory is created successfully.
     *
     * @param string $destinationName The full path to move the contents of this
     *   folder to.
     *
     * @return void
     *
     * @throws SecurityException Thrown if the destination directory could not be opened
     *   for various security reasons such as permissions.
     *
     * @throws IOException Thrown if there was an error creating the new directory path
     */
    public function moveTo(string $destinationName): void
    {
        // Make sure the destination directory exists
        if (is_dir($destinationName))
            throw new IOException("Destination directory \"{$destinationName}\" already exists.", 1);

        // Rename this directory
        if (@rename($this->rootPath, $destinationName) === false)
            throw new IOException("Failed to move directory \"{$this->rootPath}\" to \"{$destinationName}\".");

        // Clear stats cache
        clearstatcache();

        // Reset the root path, and rescan
        $this->rootPath = $destinationName;
        $this->parentDir = dirname($this->rootPath);
        $this->refresh();
    }

    /**
     * Deletes this instance of a DirectoryInfo, and all subdirectories and files.
     *
     * @return void
     *@throws \Exception|IOException Thrown if there is an error Removing the directory
     * @throws \System\ObjectDisposedException Thrown if the directory was deleted prior to calling
     *      this method.
     */
    public function delete(): void
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        // Scan directory if we haven't already
        if (!$this->scanned)
            $this->refresh();

        // Remove directories first!
        foreach ($this->subdirs as $Dir)
        {
            try
            {
                $Dir->delete();
            }
            catch (IOException $e)
            {
                throw $e;
            }
            catch (\Exception $e)
            {
                throw new IOException('Could not remove directory: "' . $Dir->fullName() . '". Exception thrown : ' . $e->getMessage());
            }
        }

        // Now Files
        foreach ($this->filelist as $f)
        {
            // Throw exception if there is an error removing a file
            if (@unlink($f->fullName()) === false)
                throw new IOException("Could not remove file: " . $f->fullName());
        }

        // Remove self
        if (@rmdir($this->rootPath) === false)
        {
            $error = error_get_last();
            $message = $error["message"] ?? "Could not remove directory: {$this->rootPath}";
            throw new IOException($message);
        }

        // Clear stats cache
        clearstatcache();
        $this->disposed = true;
    }

    /**
     * Returns whether this directory still exists on disk.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return is_dir($this->rootPath);
    }

    /**
     * Gets last modification time of the folder
     *
     * @return int Returns the time the file was last modified.
     *             The time is returned as a Unix timestamp.
     */
    public function lastWriteTime(): int
    {
        return filemtime($this->rootPath . DIRECTORY_SEPARATOR . '.');
    }

    /**
     * Gets last access time of folder
     *
     * @return int Returns the time the file was last accessed.
     *             The time is returned as a Unix timestamp.
     */
    public function lastAccessTime(): int
    {
        return fileatime($this->rootPath . DIRECTORY_SEPARATOR . '.');
    }

    /**
     * Fetches the size of all files within the directory, including
     * those in all subdirectories (full recursive).
     *
     * This method will not factor in the size of files within directories
     * that cannot be opened due to permissions.
     *
     * @param bool $format Format the size into a human-readable format?
     *
     * @return float|int|string Returns the size of all sub files recursively
     *
     */
    public function size(bool $format = false): float|int|string
    {
        // Make sure we are not disposed from a deletion
        if ($this->disposed)
            throw new ObjectDisposedException("This Directory object has been disposed.");

        // Scan directory if we haven't already
        if (!$this->scanned)
            $this->refresh();

        $size = 0;

        // Directories first
        foreach ($this->subdirs as $Dir)
        {
            try
            {
                $size += $Dir->size();
            }
            catch (\Exception $e)
            {
            }
        }

        // Now Files
        foreach ($this->filelist as $File)
        {
            try
            {
                $size += $File->size();
            }
            catch (\Exception $e)
            {
            }
        }

        return ($format) ? Convert::BytesToUnits($size) : $size;
    }

    /**
     * Returns whether this directory is writable or not.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return is_writable($this->rootPath);
    }

    /**
     * ReScans the current directory, and setting the filelist and subdir list
     * variables.
     *
     * @return void
     * @throws SecurityException Thrown if the folder was not able to be opened
     *
     */
    public function refresh(): void
    {
        // Open the directory
        $handle = @opendir($this->rootPath);
        if ($handle === false)
            throw new SecurityException('Unable to open folder "' . $this->rootPath . '"');

        // Refresh vars
        $this->subdirs = [];
        $this->filelist = [];

        // Loop through each file
        while (false !== ($f = readdir($handle)))
        {
            // Skip "." and ".." directories
            if ($f === "." || $f === "..") continue;

            // make sure we establish the full path to the file again
            $file = $this->rootPath . DIRECTORY_SEPARATOR . $f;

            // If is directory, call this method again to loop and delete ALL sub dirs.
            if (is_dir($file))
                $this->subdirs[] = new DirectoryInfo($file);
            else
                $this->filelist[] = new FileInfo($file);
        }

        // Set that we have scanned
        $this->scanned = true;

        // Close our directory handle
        closedir($handle);
    }

    /**
     * When used as a string, this object returns the full path to the folder.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->rootPath;
    }
}