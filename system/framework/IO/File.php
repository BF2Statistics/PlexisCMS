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
use InvalidArgumentException;
use System\ObjectDisposedException;
use System\Security\SecurityException;

/**
 * Provides static methods for various file operations
 *
 * @author      Steven Wilson
 * @package     System
 * @subpackage  IO
 */
class File
{
    /**
     * Creates a new file to the path specified
     *
     * @param string $path The full file path, including filename, of the
     *        file we are creating
     * @param bool $returnStream Return the FileStream for reading/writing?
     *
     * @return ?FileStream
     *
     * @throws IOException Thrown this method is unable to create the file, or if
     *  the file already exists
     * @throws DirectoryNotFoundException Thrown if the parent directory does not exist when creating a new file
     */
    public static function Create(string $path, bool $returnStream = false): ?FileStream
    {
        $stream = new FileStream($path, FileMode::CreateNew, FileAccess::Write);
        if ($returnStream)
        {
            return $stream;
        }

        $stream->close();
        return null;
    }

    /**
     * Returns whether a file path exists or not.
     *
     * @param string $path The full file path, including filename, of the
     *        file we are checking for
     *
     * @return bool
     */
    public static function Exists(string $path): bool
    {
        return is_file($path);
    }

    /**
     * Removes a file from the filesystem
     *
     * @param string $path The full file path, including filename, of the
     *        file we are removing
     *
     * @return bool
     */
    public static function Delete(string $path): bool
    {
        return @unlink($path);
    }

    /**
     * Opens a FileStream on the specified path with read/write access
     *
     * @param string $path The full path, including file name to the file.
     * @param FileMode $mode The mode in which to open the file.
     *
     * @return FileStream
     *
     * @throws IOException Thrown if there was an error opening the file.
     * @throws FileNotFoundException Thrown if the file does not exist and the
     *         mode is FileMode::Open or FileMode::Truncate.
     *
     */
    public static function Open(string $path, FileMode $mode): FileStream
    {
        return new FileStream($path, $mode, FileAccess::ReadWrite);
    }

    /**
     * Opens a FileStream on the specified path with write access
     *
     * @param string $filePath The full path, including file name to the file.
     * @param FileMode $mode The mode in which to open the file.
     *
     * @return FileStream
     *
    *  @throws IOException Thrown if there was an error opening the file.
     * @throws FileNotFoundException Thrown if the file does not exist and the
     *          mode is FileMode::Open or FileMode::Truncate.
     */
    public static function OpenWrite(string $filePath, FileMode $mode): FileStream
    {
        return new FileStream($filePath, $mode, FileAccess::Write);
    }

    /**
     * Opens a FileStream on the specified path with read access
     *
     * @param string $filePath The full path, including file name to the file.
     * @param FileMode $mode The mode in which to open the file.
     *
     * @return FileStream
     *
     * @throws IOException Thrown if there was an error opening the file.
     * @throws FileNotFoundException Thrown if the file does not exist and the
     *           mode is FileMode::Open or FileMode::Truncate.
     */
    public static function OpenRead(string $filePath, FileMode $mode): FileStream
    {
        return new FileStream($filePath, $mode, FileAccess::Read);
    }

    /**
     * Appends lines to a file
     *
     * If the specified file does not exist, this method creates a file,
     * and writes the specified lines to the file.
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string[] $lines An array of lines to write to the file.
     *
     * @return bool Returns whether the operation was successful
     * @throws InvalidArgumentException Thrown if $lines is not an array, or ListObject
     *
     * @throws IOException Thrown if there was an error opening, or creating the file.
     */
    public static function AppendAllLines(string $filePath, array $lines): bool
    {
        return self::AppendAllText($filePath, implode(PHP_EOL, $lines));
    }

    /**
     * Appends string data to a file
     *
     * If the specified file does not exist, this method creates a file,
     * and writes the specified lines to the file.
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string $stringData The data string to write to the file
     *
     * @return void
     *
     * @throws IOException Thrown if there was an error writing to the file.
     * @throws FileNotFoundException Thrown if the file does not exist
     */
    public static function AppendAllText(string $filePath, string $stringData): void
    {
        $result = @file_put_contents($filePath, $stringData, FILE_APPEND | LOCK_EX);
        if ($result === false)
        {
            if (!is_file($filePath))
                throw new FileNotFoundException("File \"{$filePath}\" does not exist.");

            if (!is_writable($filePath))
                throw new IOException("File \"{$filePath}\" is not writable.");

            throw new IOException("Failed to append to file: \"{$filePath}\"");
        }
    }

    /**
     * Opens a file, and gets all the lines of the file
     *
     * @param string $filePath The full path, including file name to the file.
     *
     * @return string[]
     *
     * @throws IOException Thrown if there was an error opening or reading the file.
     * @throws FileNotFoundException Thrown if the file does not exist
     */
    public static function ReadAllLines(string $filePath): array
    {
        return explode("\n", str_replace("\r\n", "\n", self::ReadAllText($filePath)));
    }

    /**
     * Opens a file, and gets all data of the file
     *
     * @param string $filePath The full path, including file name to the file.
     *
     * @return string
     * @throws IOException Thrown if there was an error opening the file.
     *
     * @throws FileNotFoundException Thrown if the file does not exist
     * @throws IOException Thrown if the file cannot be opened or read from
     */
    public static function ReadAllText(string $filePath): string
    {
        // Attempt to read the contents of the file. This will return false if
        // the file does not exist, is not readable, or any other error.
        $contents = @file_get_contents($filePath);
        if ($contents === false)
        {
            // Check if file is readable. This will return false also if the file does not exist
            if (!is_readable($filePath))
            {
                // Ensure the file exists
                if (!is_file($filePath))
                    throw new FileNotFoundException("File \"{$filePath}\" does not exist");
                else
                    throw new IOException("File \"{$filePath}\" is not readable");
            }

            throw new IOException("Failed to read file contents on file: \"{$filePath}\"");
        }

        // Return the file contents
        return $contents;
    }

    /**
     * Creates or overwrites a file, amd writes the specified string array to the file
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string[] $lines An array of lines to write to the file.
     *
     * @return void
     *
     * @throws InvalidArgumentException Thrown if $lines is not an array, or ListObject
     * @throws IOException Thrown if there was an error opening, or creating the file.
     * @throws DirectoryNotFoundException Thrown if the parent directory does not exist.
     */
    public static function WriteAllLines(string $filePath, array $lines): void
    {
        self::WriteAllText($filePath, implode(PHP_EOL, $lines));
    }

    /**
     * Creates or overwrites a file and writes the specified string to the file
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string $stringData The data string to write to the file
     *
     * @return void
     *
     * @throws IOException Thrown if there was an error opening, or creating the file.
     * @throws DirectoryNotFoundException
     */
    public static function WriteAllText(string $filePath, string $stringData): void
    {
        $result = @file_put_contents($filePath, $stringData);
        if ($result === false)
        {
            $dir = dirname($filePath);
            if (!is_dir($dir))
                throw new DirectoryNotFoundException("Directory \"{$dir}\" does not exist.");

            if (is_file($filePath) && !is_writable($filePath))
                throw new IOException("File \"{$filePath}\" is not writable.");

            throw new IOException("Failed to write to file: \"{$filePath}\"");
        }
    }

    /**
     * Moves a source file to a destination file. If the destination file already exists,
     * it will be overwritten.
     *
     * @param string $source The full file path, including filename, of the
     *        file we are moving
     * @param string $destination The full file path, including filename, of the
     *        file that will be created or overwritten.
     *
     * @return void
     *
     * @throws DirectoryNotFoundException
     * @throws FileNotFoundException
     * @throws IOException Thrown if there was an error moving the file, or
     *     creating the destination file's directory if it did not exist
     */
    public static function Move(string $source, string $destination): void
    {
        // Make sure we have a filename
        if (empty($source) || empty($destination))
            throw new InvalidArgumentException("Invalid file name passed");

        // Correct new path
        $newPath = dirname($destination);

        // Make sure Dest directory exists
        if (!Directory::Exists($newPath))
            Directory::CreateDirectory($newPath, 0777);

        // Create new file
        $file = new FileInfo($source);
        $file->moveTo($destination);
    }

    /**
     * Copies a source file to a destination file. If the destination file already exists,
     * it will be overwritten.
     *
     * @param string $source The full file path, including filename, of the
     *        file we are moving
     * @param string $destination The full file path, including filename, of the
     *        file that will be created or overwritten.
     *
     * @return void
     * @throws IOException Thrown if there was an error copying the file, or
     *     creating the destination file's directory if it did not exist
     *
     * @throws InvalidArgumentException Thrown if any parameters are left null
     * @throws FileNotFoundException
     * @throws DirectoryNotFoundException
     */
    public static function Copy(string $source, string $destination): void
    {
        // Make sure we have a filename
        if (empty($source) || empty($destination))
            throw new InvalidArgumentException("Invalid file name passed");

        // Correct new path
        $newPath = dirname($destination);

        // Make sure Dest directory exists
        if (!Directory::Exists($newPath))
            Directory::CreateDirectory($newPath, 0777);

        // Create new file
        $file = new FileInfo($source);
        $file->copyTo($destination);
    }

    /**
     * Returns whether the specified file is writable or not.
     *
     * @param string $path The full path
     *
     * @return bool true if the file exists and is writable, false otherwise.
     */
    public static function IsWritable(string $path): bool
    {
        return is_file($path) && is_writable($path);
    }
}