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
use Exception;

/**
 * Represents an exception that is thrown when an operation is performed on an object
 * that has already been disposed or is no longer valid. This exception is typically
 * used to signal errors in object lifecycle management.
 *
 * This class extends the base Exception class, allowing it to integrate seamlessly
 * with PHP's exception handling mechanism.
 */
class ObjectDisposedException extends Exception
{
}