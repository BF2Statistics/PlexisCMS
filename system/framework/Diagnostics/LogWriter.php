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
namespace System\Diagnostics;

use System\IO\FileStream;
use System\IO\IOException;

/**
 * Class for managing logging operations with various severity levels.
 */
class LogWriter
{
    /**
     * Security level constant
     * @var int
     */
    const int SECURITY = 0;

    /**
     * Error level constant
     * @var int
     */
    const int ERROR = 1;

    /**
     * Warning level constant
     * @var int
     */
    const int WARN = 2;

    /**
     * Notice level constant
     * @var int
     */
    const int NOTICE = 3;

    /**
     * Debug level constant
     * @var int
     */
    const int DEBUG = 4;

    /**
     * An array of logger instances for different log files.
     * @var LogWriter[]
     */
    protected static array $logWriters = array();

    /**
     * The log files resource stream
     * @var resource
     */
    protected $file = false;

    /**
     * The level in which the logger should log
     * @var int
     */
    protected int $logLevel = self::DEBUG;

    /**
     * The log date format
     * @var string
     */
    protected string $dataFormat = "Y-m-d H:i:s";

    /**
     * An array of logs to write to the file
     * @var string[]
     */
    protected array $messages = array();

    /**
     * Constructor
     *
     * @param string|null $filepath File path to the log file. If left null, a temporary file
     *   will be created using php's {@link tmpfile()} function, and removed once the
     *   current php script finishes.
     * @param string|null $instanceName The instance id (used for retrieving this instance later)
     *
     * @throws IOException Thrown if opening of the file stream failed for any reason
     */
    public function __construct(?string $filepath = null, ?string $instanceName = null)
    {
        // Create our FileStream instance
        $this->file = new FileStream($filepath, FileStream::WRITE);

        // Add instance
        if (!empty($instanceName))
            self::$logWriters[$instanceName] = $this;
    }

    /**
     * Sets the minimum log level in order to log messages
     *
     * @param int $level The minimum log level to record the message
     *
     * @return void
     */
    public function setLogLevel(int $level): void
    {
        $this->logLevel = $level;
    }

    /**
     * Writes a line to the log without pre-pending a status or timestamp
     *
     * @param string $string The line to write to the log file
     *
     * @return void
     */
    public function writeLine(string $string): void
    {
        $this->messages[] = $string;
    }

    /**
     * Writes a line to the log with the severity of SECURITY
     *
     * @param string $message The line to write to the log file
     * @param bool|\string[] $args An array of replacements in the string
     *
     * @return void
     */
    public function logSecurity(string $message, array|bool $args = false): void
    {
        // Make sure we want to log this
        if ($this->logLevel >= self::SECURITY)
            $this->writeLine($this->format($message, $args, self::SECURITY));
    }

    /**
     * Writes a line to the log with the severity of ERROR
     *
     * @param string $message The line to write to the log file
     * @param bool|\string[] $args An array of replacements in the string
     *
     * @return void
     */
    public function logError(string $message, array|bool $args = false): void
    {
        // Make sure we want to log this
        if ($this->logLevel >= self::ERROR)
            $this->writeLine($this->format($message, $args, self::ERROR));
    }

    /**
     * Writes a line to the log with the severity of DEBUG
     *
     * @param string $message The line to write to the log file
     * @param bool|\string[] $args An array of replacements in the string
     *
     * @return void
     */
    public function logDebug(string $message, array|bool $args = false): void
    {
        // Make sure we want to log this
        if ($this->logLevel >= self::DEBUG)
            $this->writeLine($this->format($message, $args, self::DEBUG));
    }

    /**
     * Writes a line to the log with the severity of WARN
     *
     * @param string $message The line to write to the log file
     * @param bool|\string[] $args An array of replacements in the string
     *
     * @return void
     */
    public function logWarning(string $message, array|bool $args = false): void
    {
        // Make sure we want to log this
        if ($this->logLevel >= self::WARN)
            $this->writeLine($this->format($message, $args, self::WARN));
    }

    /**
     * Writes a line to the log with the severity of NOTICE
     *
     * @param string $message he line to write to the log file
     * @param bool|\string[] $args An array of replacements in the string
     *
     * @return void
     */
    public function logNotice(string $message, array|bool $args = false): void
    {
        // Make sure we want to log this
        if ($this->logLevel >= self::NOTICE)
            $this->writeLine($this->format($message, $args, self::NOTICE));
    }

    /**
     * Acts as a singleton to fetch a logger object with the given ID
     *
     * @param int|string $id The instance id or name that was provided in the logger class'
     *   constructor.
     *
     * @return LogWriter|bool Returns false if the $id was never set.
     */
    public static function Instance(int|string $id) : LogWriter|false
    {
        return (isset(self::$logWriters[$id])) ? self::$logWriters[$id] : false;
    }

    /**
     * Creates or retrieves a LogWriter instance based on the provided identifier.
     *
     * @param string $filepath The file path where logs will be written
     * @param string $id The unique identifier for the log writer
     *
     * @return LogWriter The LogWriter instance associated with the provided identifier
     *
     * @throws IOException
     */
    public static function Create(string $filepath, string $id): LogWriter
    {
        return (isset(self::$logWriters[$id])) ? self::$logWriters[$id] : new LogWriter($filepath, $id);
    }

    /**
     * Formats a log message with the specified arguments, prepending it with a timestamp and
     * the appropriate log level label based on the given mode.
     *
     * @param string $message The log message to be formatted.
     * @param array|string $args The arguments to be inserted into the message using sprintf-style formatting.
     * @param bool|int $mode The mode for log level; corresponds to predefined log level constants (e.g., SECURITY, ERROR, etc.).
     *
     * @return string The formatted log message including the timestamp and log level label.
     */
    public function format(string $message, array|string $args, bool|int $mode = false): string
    {
        // Trim message
        $message = trim($message);

        // Correctly format args
        if (!empty($args))
        {
            if (!is_array($args))
                $args = array($args);

            $message = vsprintf($message, $args);
        }

        // Process initial string value
        $start = date($this->dataFormat, time());
        return match ($mode) {
            self::SECURITY => $start . ' -- SECURITY: ' . $message,
            self::ERROR => $start . ' -- ERROR: ' . $message,
            self::DEBUG => $start . ' -- DEBUG: ' . $message,
            self::WARN => $start . ' -- WARNING: ' . $message,
            self::NOTICE => $start . ' -- NOTICE: ' . $message,
            default => $start . ' -- INFO: ' . $message,
        };
    }

    /**
     * Sets the date format used inside the log file
     *
     * @param string $format Valid format string for date()
     *
     * @return void
     */
    public function setDateFormat(string $format): void
    {
        $this->dataFormat = $format;
    }

    /**
     * Flushes all the messages from the queue by writing them to the file stream,
     * and then clears the queue.
     *
     * @return void
     *
     * @throws IOException
     * @throws \System\ObjectDisposedException
     */
    public function flush(): void
    {
        if ($this->file instanceof FileStream)
        {
            // Empty message Queue
            if (!empty($this->messages))
            {
                $this->file->write(implode(PHP_EOL, $this->messages) . PHP_EOL);
                $this->messages = array();
            }
        }
    }

    /**
     * Class Destructor. Closes the file handle.
     *
     * @return void
     */
    public function __destruct()
    {
        // Close file if its open
        if ($this->file instanceof FileStream)
        {
            // Empty message Queue
            if (!empty($this->messages))
            {
                try {
                    $this->file->write(implode(PHP_EOL, $this->messages) . PHP_EOL);
                }
                catch (\Exception $e) {

                }
            }

            $this->file->close();
        }
    }
}