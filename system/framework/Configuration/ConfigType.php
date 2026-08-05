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
namespace System\Configuration;

enum ConfigType {
    case XML;
    case JSON;
    case INI;
    case PHP;
    case YAML;
}