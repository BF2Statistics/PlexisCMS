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
namespace System\Routing;
use Exception;

/**
 * Represents an exception that is thrown when a requested route cannot be found.
 *
 * This exception is typically used in the context of routing systems to indicate
 * that the specified route does not exist in the routing table or configuration.
 *
 * @package System\Routing
 */
class RouteNotFoundException extends Exception
{

}