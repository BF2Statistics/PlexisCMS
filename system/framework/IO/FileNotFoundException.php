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

/**
 * Represents an exception that is thrown when an attempt to access a file
 * that does not exist fails.
 *
 * This exception generally occurs when a file path is invalid, the file has
 * been deleted, or permissions to access the file are insufficient.
 *
 * This class extends the base Exception class, inheriting its methods and
 * properties.
 */
class FileNotFoundException extends Exception
{
}