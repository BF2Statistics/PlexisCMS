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
 * Represents HTTP methods as defined by the HTTP specification.
 *
 * This enum provides a standardized way to handle common HTTP methods
 * used in requests and responses, aiding in better code clarity and
 * reliability when working with HTTP operations.
 *
 * Compatible with PHP 8.1.0 and above.
 *
 * @license MIT License
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
    case PATCH = 'PATCH';
    case TRACE = 'TRACE';
    case CONNECT = 'CONNECT';
}