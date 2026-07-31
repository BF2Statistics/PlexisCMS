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
namespace System\Database;

/**
 * Enum representing the modes of SQL non-query operations.
 */
enum NonQueryMode: string
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Upsert = 'UPSERT';
    case Delete = 'DELETE';
}
