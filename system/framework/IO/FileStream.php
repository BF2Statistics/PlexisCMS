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
use System\ArgumentOutOfRangeException;
use System\ObjectDisposedException;

/**
 * Provides properties and instance methods for various file stream operations
 *
 * Use the FileStream class to read from, write to, open, and close files on a file system
 *
 * @author      Steven Wilson
 * @package     System
 * @subpackage  IO
 */
class FileStream
{
    // FileMode constant for Read + Write
    const string READWRITE = "a+";

    // FileMode constant Read Only
    const string READ = "r";

    // FileMode constant Write Only
    const string WRITE = "a";

    /**
     * The file stream
     * @var Resource
     */
    protected mixed $stream = false;

    /**
     * @var string
     */
    protected string $path;

    /**
     * File mode variable
     * @var string
     */
    protected string $mode;

    /**
     * Gets a value indicating whether the current stream supports reading.
     * @var  bool
     */
    protected bool $canRead;

    /**
     * Gets a value indicating whether the current stream supports writing.
     * @var  bool
     */
    protected bool $canWrite;

    /**
     * Gets a value indicating whether the current stream is closed.
     * @var  bool
     */
    protected bool $isClosed = false;

    /**
     * Specifies which file modes allow reading
     * @var  string[]
     */
    protected static array $readModes = ['r', 'r+', 'w+', 'a+', 'x+', 'c+'];

    /**
     * Specifies which file modes allow writing
     * @var  string[]
     */
    protected static array $writeModes = ['w', 'a', 'x', 'w+', 'a+', 'x+', 'r+', 'c', 'c+'];

    /**
     * Constructor
     *
     * @param string $file The full path to the file. If the file does not exist, it will be created
     * @param string $mode The Read / Write mode of the file (See class Constants READ,
     *     WRITE, READWRITE etc ).
     *
     * @throws IOException Thrown if opening of the file stream failed for any reason
     */
    public function __construct(string $file, string $mode = self::READWRITE)
    {
        // Open the file stream
        $this->path = $file;
        $this->stream = @fopen($file, $mode);

        // Make sure our stream is valid
        if (empty($this->stream))
        {
            $error = error_get_last();
            if ($error === null)
                throw new IOException("Unable to open file stream for file \"{$file}\".");
            else
                throw new IOException($error["message"]);
        }

        /**
         * Set readable and writable status of the stream
         * Replace both b and t flags, as we don't care about those.
         */
        $this->mode = str_replace(['b', 't'], '', $mode);
        $this->canRead = in_array($this->mode, self::$readModes);
        $this->canWrite = in_array($this->mode, self::$writeModes);

        // Set write buffer to 0 to prevent multiple streams on this file messing up
        stream_set_write_buffer($this->stream, 0);
    }

    /**
     * Reads data from file
     *
     * @param int $numBytesToRead The maximum amount of bytes to read.
     *
     * @return string Returns the remaining contents in a string, up to $numBytesToRead bytes
     *                and starting at the specified offset.
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support reading.
     */
    public function read(int $numBytesToRead = 1): string
    {
        // if a negative number is passed, just read to end
        if ($numBytesToRead < 0)
            return $this->readToEnd();

        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can read from this stream
        $this->ensureCanRead();

        // Read next characters
        return fread($this->stream, $numBytesToRead);
    }

    /**
     * Reads a line of characters from the current stream and returns the data as a string.
     *
     * @param ?string $delim The end of line delimiter. Do not set unless your having problems
     *     with detecting the end lines, or want to set a custom line break.
     *
     * @return ?string The next line from the input stream, or null if the end of the input stream is reached.
     *
     * @throws IOException The stream does not support reading.
     * @throws ObjectDisposedException The stream is closed.
     */
    public function readLine(?string $delim = null): ?string
    {
        $this->checkDisposed();
        $this->ensureCanRead();

        if ($delim === null)
        {
            $line = fgets($this->stream);
            return ($line === false) ? null : $line;
        }

        $result = "";
        while (!feof($this->stream))
        {
            $tmp = fgetc($this->stream);
            if ($tmp === false)
                break;

            if ($tmp === $delim)
                return $result;

            $result .= $tmp;
        }

        return ($result === '') ? null : $result;
    }

    /**
     * Reads a CSV-formatted line of characters from the current stream and returns the data as an array.
     *
     * @return array|false|null The next line from the input stream, or null if the end of the input stream is reached.
     *
     * @throws IOException The stream does not support reading.
     * @throws ObjectDisposedException The stream is closed.
     */
    public function readCSVLine(): array|false|null
    {
        $this->checkDisposed();
        $this->ensureCanRead();

        return fgetcsv($this->stream);
    }

    /**
     * Reads all characters from the current position to the end of the stream.
     *
     * @return string The rest of the stream as a string, from the current position to the end.
     *        If the current position is at the end of the stream, returns an empty string ("").
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support reading.
     */
    public function readToEnd(): string
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can read from this stream
        $this->ensureCanRead();

        // Read the stream until the end of file
        $result = "";
        while (!feof($this->stream))
        {
            // Read next character
            $result .= fread($this->stream, 4096);
        }

        return $result;
    }

    /**
     * Reads the next character from the input stream and advances the character position by one character.
     *
     * @return string Returns the next character from the input stream, or an empty string if the end of the stream is reached.
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support reading.
     */
    public function readChar(): string
    {
        $this->checkDisposed();
        $this->ensureCanRead();

        $char = fgetc($this->stream);
        return ($char === false) ? "" : $char;
    }

    /**
     * Returns the next available $count of characters, but does not consume them.
     *
     * @param int $count The number of characters to peek from the current stream position
     *
     * @return string|null The next $count of characters, or null if there are no characters to be read
     *
     * @throws ArgumentOutOfRangeException if $count is negative.
     * @throws IOException The stream does not support reading.
     * @throws ObjectDisposedException The stream is closed.
     */
    public function peek(int $count = 1): ?string
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // We must have a positive number
        if ($count < 1)
            throw new ArgumentOutOfRangeException("Peek count must be greater than zero.");

        // Ensure we can read from this stream
        $this->ensureCanRead();

        // Check if we are at the end of the stream
        if (feof($this->stream))
            return null;

        // Read next character
        $result = fread($this->stream, $count);

        // Get the previous position. We do not use ftell() here because it does
        // not return the correct position if the file is opened with the WRITE
        // attribute ('a'), and also does not count carriage returns ('\r').
        $position = strlen($result);

        // Reset position in stream
        $this->seek(-$position, SEEK_CUR);

        return $result;
    }

    /**
     * Writes to the file stream
     *
     * @param string $stringData The string to write to the file
     *
     * @return false|int Returns the number of bytes that were written, or false if an error occurred
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function write(string $stringData): false|int
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can write to this stream
        $this->ensureCanWrite();

        return fwrite($this->stream, $stringData);
    }

    /**
     * Writes a line terminator to the text string or stream.
     *
     * @param string $stringData The string to write to the file
     *
     * @return int Returns the number of bytes that were written
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function writeLine(string $stringData): int
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can write to this stream
        $this->ensureCanWrite();

        return fwrite($this->stream, $stringData . PHP_EOL);
    }

    /**
     * Writes an array to the current file stream, formatted in CSV format.
     *
     * @param array $dataArray
     *
     * @return int Returns the number of bytes that were written
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function writeCSVLine(array $dataArray): int
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can write to this stream
        $this->ensureCanWrite();

        return fputcsv($this->stream, $dataArray);
    }

    /**
     * Truncates the file to the specified size
     *
     * @param int $size The size to truncate to. If size is larger than the file then the
     *        file is extended with null bytes.
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function truncate(int $size = 0): bool
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can write to this stream
        $this->ensureCanWrite();

        return ftruncate($this->stream, $size);
    }

    /**
     * Sets the length of the stream to the specified value.
     *
     * @param int $size The desired length of the stream in bytes.
     *        If size is larger than the file, the file is extended with null bytes.
     *        If size is smaller, the file is truncated.
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function setLength(int $size): bool
    {
        return $this->truncate($size);
    }

    /**
     * Gets the length in bytes of the stream.
     *
     * @return int
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function getLength(): int
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Fetch stream stats using fstat()
        $stat = fstat($this->stream);

        return $stat['size'] ?? 0;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function getPosition(): int
    {
        $this->checkDisposed();

        return (int)ftell($this->stream);
    }

    /**
     * Sets the file position indicator for the file
     *
     * @param int $position The offset, measured in bytes from the beginning of the file
     * @param int $whence The seek constant type (SEEK_SET, SEEK_CUR, SEEK_END)
     *
     * @return bool Returns whether the seek was successful
     *
     * @throws ObjectDisposedException The stream is closed.
     *@see http://www.php.net/manual/en/function.fseek.php
     *
     */
    public function seek(int $position, int $whence = SEEK_SET): bool
    {
        // Ensure the stream is open
        $this->checkDisposed();

        return (fseek($this->stream, $position, $whence) == 0);
    }

    /**
     * Locks the current file with an advisory level lock
     *
     * @param bool $exclusive
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function lock(bool $exclusive = true, bool $nonBlocking = false): bool
    {
        // Ensure the stream is open
        $this->checkDisposed();
        $flags = ($exclusive) ? LOCK_EX : LOCK_SH;
        if ($nonBlocking)
            $flags |= LOCK_NB;

        return flock($this->stream, $flags);
    }

    /**
     * Un-Locks the current file
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function unlock(): bool
    {
        // Ensure the stream is open
        $this->checkDisposed();

        return flock($this->stream, LOCK_UN);
    }

    /**
     * Flushes the output to a file
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The stream does not support writing.
     */
    public function flush(): bool
    {
        // Ensure the stream is open
        $this->checkDisposed();

        // Ensure we can write to the stream
        $this->ensureCanWrite();

        // perform flush
        return fflush($this->stream);
    }

    /**
     * Returns the underlying file stream resource.
     *
     * @return resource
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function getStream(): mixed
    {
        $this->checkDisposed();
        return $this->stream;
    }

    /**
     * Returns whether the stream position is at the end of the stream.
     *
     * @return bool
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    public function isEndOfStream(): bool
    {
        $this->checkDisposed();
        return feof($this->stream);
    }

    /**
     * Returns the file path associated with this stream.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Copies the contents of this stream to another stream.
     *
     * @param FileStream $destination The destination stream to copy to.
     * @param int $bufferSize The size of the buffer used during copy.
     *
     * @return int Total bytes copied.
     *
     * @throws ObjectDisposedException The stream is closed.
     * @throws IOException The source stream does not support reading or destination does not support writing.
     */
    public function copyTo(FileStream $destination, int $bufferSize = 8192): int
    {
        $this->checkDisposed();
        $this->ensureCanRead();
        $destination->ensureCanWrite();

        $totalBytes = 0;
        while (!feof($this->stream))
        {
            $data = fread($this->stream, $bufferSize);
            if ($data === false || $data === '')
                break;

            $written = $destination->write($data);
            $totalBytes += $written;
        }

        return $totalBytes;
    }

    /**
     * Gets a value indicating whether the current stream supports reading.
     *
     * @return bool
     */
    public function canRead(): bool
    {
        return $this->canRead;
    }

    /**
     * Gets a value indicating whether the current stream supports writing.
     *
     * @return bool
     */
    public function canWrite(): bool
    {
        return $this->canWrite;
    }

    /**
     * Closes the file stream
     *
     * @return void
     */
    public function close(): void
    {
        // Don't call close multiple times
        if ($this->isClosed || !is_resource($this->stream)) return;

        // Flush and close the Stream
        fflush($this->stream);
        fclose($this->stream);
        $this->isClosed = true;
    }

    /**
     * Checks if this stream can be written to, and throws an exception if not.
     *
     * @return void
     *
     * @throws ObjectDisposedException The stream is closed.
     */
    protected function checkDisposed(): void
    {
        // Ensure we can write to this stream
        if ($this->isClosed || !is_resource($this->stream))
            throw new ObjectDisposedException("The stream is closed.");
    }

    /**
     * Checks if this stream can be written to, and throws an exception if not.
     *
     * @return void
     *
     * @throws IOException The stream does not support writing.
     */
    protected function ensureCanWrite(): void
    {
        // Ensure we can write to this stream
        if (!$this->canWrite)
            throw new IOException("The stream does not support writing.");
    }

    /**
     * Checks if this stream can supports reading, and throws an exception if not.
     *
     * @return void
     *
     * @throws IOException The stream does not support reading.
     */
    protected function ensureCanRead(): void
    {
        // Ensure we can read from this stream
        if (!$this->canRead)
            throw new IOException("The stream does not support reading.");
    }

    /**
     * Handles cleanup and resource deallocation when the object is destroyed.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }
}