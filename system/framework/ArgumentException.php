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
 * ArgumentException
 *
 * @author      Steven Wilson
 * @package     System
 * @subpackage  Exceptions
 */
class ArgumentException extends Exception
{
    protected $argument = '';

    public function __construct($message, $argument = '', $inner = null)
    {
        parent::__construct($message, 0, $inner);
        $this->argument = $argument;
    }

    public function getArgument()
    {
        return $this->argument;
    }
}