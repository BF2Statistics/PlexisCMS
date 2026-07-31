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
     * @return FileStream|null
     *
     * @throws IOException Thrown this method is unable to create the file
     */
    public static function Create(string $path, bool $returnStream = false): ?FileStream
    {
        $Stream = new FileStream($path);
        if ($returnStream)
            return $Stream;

        $Stream->close();

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
     *
     * @return FileStream
     *
     * @throws IOException Thrown if there was an error opening the file.
     *
     */
    public static function Open(string $path): FileStream
    {
        return new FileStream($path, FileStream::READWRITE);
    }

    /**
     * Opens a FileStream on the specified path with write access
     *
     * @param string $filePath The full path, including file name to the file.
     *
     * @return FileStream
     * @throws IOException Thrown if there was an error opening the file.
     *
     */
    public static function OpenWrite(string $filePath): FileStream
    {
        return new FileStream($filePath, FileStream::WRITE);
    }

    /**
     * Opens a FileStream on the specified path with read access
     *
     * @param string $filePath The full path, including file name to the file.
     *
     * @return FileStream
     * @throws IOException Thrown if there was an error opening the file.
     */
    public static function OpenRead(string $filePath): FileStream
    {
        return new FileStream($filePath, FileStream::READ);
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
     *@throws InvalidArgumentException Thrown if $lines is not an array, or ListObject
     *
     * @throws IOException Thrown if there was an error opening, or creating the file.
     * @throws ObjectDisposedException
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
     * @return bool Returns whether the operation was successful
     *
     * @throws IOException
     * @throws ObjectDisposedException
     */
    public static function AppendAllText(string $filePath, string $stringData): bool
    {
        // Get filestream
        $file = new FileStream($filePath, FileStream::WRITE);

        // Write file contents
        $wrote = $file->write($stringData);
        $file->close();

        return $wrote !== false;
    }

    /**
     * Opens a file, and gets all the lines of the file
     *
     * @param string $filePath The full path, including file name to the file.
     *
     * @return string[]
     *@throws IOException Thrown if there was an error opening the file.
     *
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
     * @throws \System\ObjectDisposedException
     */
    public static function ReadAllText(string $filePath): string
    {
        // Ensure the file exists
        if (!file_exists($filePath))
            throw new FileNotFoundException("File \"{$filePath}\" does not exist");

        // Read the contents from the file
        $file = new FileStream($filePath, FileStream::READ);
        $contents = $file->readToEnd();
        $file->close();

        // Return the file contents
        return $contents;
    }

    /**
     * Creates or overwrites a file, amd writes the specified string array to the file
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string[] $lines An array of lines to write to the file.
     *
     * @return bool Returns whether the operation was successful
     *
     * @throws InvalidArgumentException Thrown if $lines is not an array, or ListObject
     * @throws IOException Thrown if there was an error opening, or creating the file.
     * @throws ObjectDisposedException
     */
    public static function WriteAllLines(string $filePath, array $lines): bool
    {
        return self::WriteAllText($filePath, implode(PHP_EOL, $lines));
    }

    /**
     * Creates or overwrites a file and writes the specified string to the file
     *
     * @param string $filePath The full path, including file name to the file.
     * @param string $stringData The data string to write to the file
     *
     * @return bool Thrown if there was an error opening, or creating the file.
     *
     * @throws IOException Thrown if there was an error opening, or creating the file.
     * @throws ObjectDisposedException
     *
     */
    public static function WriteAllText(string $filePath, string $stringData): bool
    {
        // Get file stream
        $file = new FileStream($filePath, 'w');

        // Write file contents
        $wrote = $file->write($stringData);
        $file->close();

        return $wrote !== false;
    }

    /**
     * Opens a binary file, reads all contents as bytes, and closes the file.
     *
     * @param string $filePath The file to read.
     *
     * @return string The raw binary contents of the file.
     *
     * @throws FileNotFoundException If the file does not exist.
     * @throws IOException If the file could not be read.
     */
    public static function ReadAllBytes(string $filePath): string
    {
        if (!is_file($filePath))
            throw new FileNotFoundException("File \"{$filePath}\" does not exist");

        $contents = file_get_contents($filePath);
        if ($contents === false)
            throw new IOException("Unable to read file \"{$filePath}\"");

        return $contents;
    }

    /**
     * Creates a new file, writes the specified byte data, and closes the file.
     * If the file already exists, it is overwritten.
     *
     * @param string $filePath The file to write to.
     * @param string $data The raw binary data to write.
     *
     * @return int The number of bytes written.
     *
     * @throws IOException If the file could not be written.
     */
    public static function WriteAllBytes(string $filePath, string $data): int
    {
        $result = file_put_contents($filePath, $data);
        if ($result === false)
            throw new IOException("Unable to write to file \"{$filePath}\"");

        return $result;
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
     * @throws SecurityException
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
        try
        {
            $file = new FileStream($path, 'r+');
            $canWrite = $file->canWrite();
            $file->close();
            return $canWrite;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }
}