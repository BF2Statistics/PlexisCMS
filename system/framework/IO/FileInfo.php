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
use Exception;
use System\ObjectDisposedException;

/**
 * Provides properties and instance methods for various file operations
 *
 * Use the FileInfo class if you are going to reuse an object several times,
 * because a file exists check will not always be necessary, and will increase
 * performance over the static File class methods
 *
 * @author      Steven Wilson
 * @package     System
 * @subpackage  IO
 */
class FileInfo
{
    /**
     * The full path to the file's parent directory
     * @var string
     */
    protected string $parentDir;

    /**
     * The full path to the file's current location, including the filename
     * @var string
     */
    protected string $filePath;

    /**
     * Class Constructor
     *
     * @param string $path The full path the the file
     * @param bool $create Create the file if it doesn't exist?
     *
     * @throws IOException Thrown if the $path directory doesn't exist,
     *   $create is set to true, and there was an error creating the file.
     * @throws FileNotFoundException If the $path file does not exist, and $create is set to false.
     * @throws \Exception Thrown if the $path is not a file at all, but rather a directory
     */
    public function __construct(string $path, bool $create = false)
    {
        // Make sure the file exists, or we are creating a file
        if (!file_exists($path))
        {
            // Do we attempt to create?
            if (!$create)
                throw new FileNotFoundException("File '{$path}' does not exist");

            // Attempt to create the file
            $handle = @fopen($path, 'w+');
            if ($handle)
            {
                // Close the handle
                fclose($handle);
            }
            else
                throw new IOException("Cannot create file '{$path}'");
        }
        elseif (!is_file($path))
        {
            throw new Exception("'{$path}' is not a file!");
        }

        // Define path
        $this->filePath = $path;
        $this->parentDir = dirname($path);
    }

    /**
     * Returns the base file name
     *
     * @return string
     */
    public function name(): string
    {
        return basename($this->filePath);
    }

    /**
     * Returns the full path to the file, including the file name
     *
     * @return string
     */
    public function fullName(): string
    {
        return $this->filePath;
    }

    /**
     * Returns the extension part of the file
     *
     * @return string
     */
    public function extension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    /**
     * Returns the files's directory path
     *
     * @return string
     */
    public function directoryName(): string
    {
        return $this->parentDir;
    }

    /**
     * Gets an instance of the parent directory
     *
     * @return DirectoryInfo
     *
     * @throws DirectoryNotFoundException
     */
    public function directory(): DirectoryInfo
    {
        return new DirectoryInfo($this->parentDir);
    }

    /**
     * Appends the specified string to the file
     *
     * @param string $stringData The data string to write to the file
     *
     * @return bool Returns whether the operation was successful
     *
     * @throws IOException Thrown if there was an error opening, or writing to the file.
     */
    public function appendText(string $stringData): bool
    {
        $result = @file_put_contents($this->filePath, $stringData, FILE_APPEND | LOCK_EX);
        if ($result === false)
        {
            if (!is_writable($this->filePath))
                throw new IOException("File \"{$this->filePath}\" is not writable.");

            throw new IOException("Failed to append to file: \"{$this->filePath}\"");
        }
        return true;
    }

    /**
     * Opens a FileStream on the specified path with read/write access
     *
     * @throws IOException Thrown if there was an error opening the file.
     *
     * @return \System\IO\FileStream
     */
    public function open(): FileStream
    {
        return new FileStream($this->filePath, FileMode::Open, FileAccess::ReadWrite);
    }

    /**
     * Opens a FileStream on the specified path with read access
     *
     * @throws IOException Thrown if there was an error opening the file.
     *
     * @return \System\IO\FileStream
     */
    public function openRead(): FileStream
    {
        return new FileStream($this->filePath, FileMode::Open, FileAccess::Read);
    }

    /**
     * Opens a FileStream on the specified path with write access
     *
     * @throws IOException Thrown if there was an error opening the file.
     *
     * @return \System\IO\FileStream
     */
    public function openWrite(): FileStream
    {
        return new FileStream($this->filePath, FileMode::Open, FileAccess::Write);
    }

    /**
     * Moves the file to a new location.
     *
     * The old file will not be removed until the new file is created successfully.
     *
     * @param string $newPath The full file name (including full path) to move to
     *
     * @return void
     *
     * @throws DirectoryNotFoundException if the directory specified in fileName does not exist.
     * @throws IOException
     */
    public function moveTo(string $newPath): void
    {
        $dir = dirname($newPath);
        if (!is_dir($dir))
            throw new DirectoryNotFoundException("Could not find part of path: " . $dir);

        if (!@rename($this->filePath, $newPath))
        {
            // Cross-device fallback
            $this->copyTo($newPath, true);
            if (!@unlink($this->filePath))
                throw new IOException("Failed to remove original file: \"{$this->filePath}\"");
        }

        $this->filePath = $newPath;
        $this->parentDir = dirname($newPath);
    }

    /**
     * Copies the contents of this file to a new file
     *
     * @param string $fileName The name of the file we are copying to
     * @param bool $overwrite Defines whether to overwrite an existing
     *     file, if it exists
     *
     * @return bool Returns true on success, false otherwise
     *
     * @throws DirectoryNotFoundException if the directory specified in fileName does not exist.
     */
    public function copyTo(string $fileName, bool $overwrite = false): bool
    {
        if (!$overwrite && file_exists($fileName))
            return false;

        $dir = Path::GetDirectoryName($fileName);
        if (!Directory::Exists($dir))
            throw new DirectoryNotFoundException("Could not find part of path: " . $dir);

        return copy($this->filePath, $fileName);
    }

    /**
     * Renames the file (within the same directory).
     *
     * @param string $newName The new file name (not a full path).
     *
     * @return bool
     *
     * @throws IOException If the rename fails.
     */
    public function renameTo(string $newName): bool
    {
        $newPath = Path::Combine($this->parentDir, $newName);

        if (!@rename($this->filePath, $newPath))
            throw new IOException("Could not rename file '{$this->filePath}' to '{$newPath}'");

        $this->filePath = $newPath;
        return true;
    }

    /**
     * Completely removes all contents of the file
     *
     * @return void
     *
     * @throws IOException Thrown if the file could not be truncated.
     */
    public function truncate(): void
    {
        $f = @fopen($this->filePath, "r+");
        if ($f === false)
            throw new IOException("Could not open file for truncation: \"{$this->filePath}\"");

        ftruncate($f, 0);
        fclose($f);
    }

    /**
     * Gets last modification time of file
     *
     * @return int|bool Returns the time the file was last modified,
     * or FALSE on failure. The time is returned as a Unix timestamp.
     */
    public function lastWriteTime(): int|bool
    {
        return filemtime($this->filePath);
    }

    /**
     * Gets last access time of file
     *
     * @return int|bool Returns the time the file was last accessed,
     * or FALSE on failure. The time is returned as a Unix timestamp.
     */
    public function lastAccessTime(): int|bool
    {
        return fileatime($this->filePath);
    }

    /**
     * Gets the size, in bytes, of the current file.
     *
     * @return int
     */
    public function size(): int
    {
        $size = filesize($this->filePath);
        return ($size === false) ? 0 : $size;
    }

    /**
     * Sets the access permissions of the file
     *
     * @param int $chmod The permission level, as an octal, to set on the file (chmod).
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
     * @return bool returns the success value of setting the permissions.
     */
    public function setAccess($chmod): bool
    {
        return chmod($this->filePath, $chmod);
    }

    /**
     * Gets the access permissions of the file
     *
     * @return int the permissions on the file
     */
    public function getAccess(): int
    {
        return fileperms($this->filePath);
    }

    /**
     * Returns whether this file is writable or not.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return is_writable($this->filePath);
    }

    /**
     * Returns whether this file is readable or not.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return is_readable($this->filePath);
    }

    /**
     * Permanently deletes the file.
     *
     * @return void
     *
     * @throws FileNotFoundException If the file does not exist.
     * @throws IOException Thrown if the file could not be deleted.
     */
    public function delete(): void
    {
        if (!file_exists($this->filePath))
            throw new FileNotFoundException("File '{$this->filePath}' does not exist");

        if (!@unlink($this->filePath))
            throw new IOException("Failed to delete file: '{$this->filePath}'");
    }

    /**
     * When used as a string, this object returns the full path to the file.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->filePath;
    }
}