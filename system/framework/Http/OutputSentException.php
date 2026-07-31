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
namespace System\Http;
/**
 * Output Sent Exception, Thrown when headers have already been set, and a Response method is called
 *
 * @package     Core
 * @subpackage  Exceptions
 */
class OutputSentException extends \Exception
{
}