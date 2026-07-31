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
namespace System;

/**
 * A custom exception class that extends the base `\Exception` class to include
 * additional context information about an error. This context can provide more
 * detailed insights regarding the cause of the exception, which can be especially
 * useful for debugging and error logging.
 *
 * ## Key Responsibilities:
 * - **Exception Handling**: Acts as a standard exception class with enhanced capabilities for
 *   including contextual data.
 * - **Context Storage**: Allows developers to attach additional information in the form of an
 *   associative array when throwing the exception.
 * - **Error Logging and Debugging**: Facilitates better error diagnostics by providing detailed
 *   information about the cause of the error.
 *
 * ## Features:
 * - Inherits all the functionalities of the standard `\Exception` class.
 * - Stores additional context data related to the exception.
 * - Provides a method to retrieve the attached context information.
 *
 * ## Usage:
 * The `ContextException` class can be used in scenarios where more detailed error information
 * is required. Developers can attach additional context, such as variables, request details,
 * or system states, which can then be retrieved during exception handling.
 *
 * Example:
 * ```
 * try {
 *     // Some code that might throw an exception
 *     throw new ContextException(
 *         'An error occurred while processing the request.',
 *         ['userId' => 42, 'operation' => 'database query']
 *     );
 * } catch (ContextException $e) {
 *     echo $e->getMessage(); // Outputs: An error occurred while processing the request.
 *     print_r($e->getContext()); // Outputs: Array ( [userId] => 42, [operation] => database query )
 * }
 * ```
 *
 * ## Key Methods:
 * ### __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
 * - Initializes the exception with an error message, optional context, error code, and an optional
 *   previous throwable for exception chaining.
 * - **Parameters**:
 *   - `$message`: A descriptive error message.
 *   - `$context`: An optional associative array containing additional error-related information.
 *   - `$code`: An optional error code.
 *   - `$previous`: An optional previous exception for chaining.
 *
 * ### getContext(): array
 * - Retrieves the context data associated with the exception.
 * - **Returns**:
 *   An associative array containing the context information.
 *
 * ## Security Notes:
 * - Avoid including sensitive information (e.g., passwords or private keys) in the context data,
 *   especially if it will be logged or displayed.
 *
 * @package System
 * @extends \Exception
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
*/
class ContextException extends \Exception
{
    protected array $context;

    /**
     * Constructor for the class.
     *
     * @param string $message The error message.
     * @param array $context Additional information about the error.
     * @param int $code The error code (optional).
     * @param \Throwable|null $previous The previous throwable used for exception chaining (optional).
     *
     * @return void
     */
    public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Retrieves the context associated with the instance.
     *
     * @return array The context information.
     */
    public function getContext(): array
    {
        return $this->context;
    }
}