<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

return [
    'default-src' => ["'self'"], // Default source to only load resources from the same origin
    'script-src'  => ["'self'"],  // Allow scripts from self and trusted domain
    'style-src'   => ["'self'", "'unsafe-inline'"], // Styles can only be loaded from self and trusted domain
    'img-src'     => ["'self'"],   // Images from self and trusted domain
    'connect-src' => ["'self'"],   // AJAX/fetch requests limited to self
    'font-src'    => ["'self'"],  // Fonts from self and trusted domain
];