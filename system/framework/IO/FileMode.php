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

/**
 * Specifies how the operating system should open a file.
 */
enum FileMode: int
{
    /** Create new, fail if exists */
    case CreateNew = 1;

    /** Create new or overwrite existing */
    case Create = 2;

    /** Open existing, fail if not found */
    case Open = 3;

    /** Open if exists, create if not */
    case OpenOrCreate = 4;

    /** Open existing and truncate, fail if not found */
    case Truncate = 5;

    /** Open/create and seek to end */
    case Append = 6;
}