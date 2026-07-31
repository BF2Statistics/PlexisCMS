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
    // Define the single session cache driver to use.
    // Options: PHP, File, Redis, RedisCluster, Memcached
    'session_driver' => 'PHP',
    'session_ttl' => 1440,
    'key_prefix' => 'session:',  // Prefix all session keys
    'driver_config' => [
        'PHP' => [
            'class' => '\System\Http\Session\Storage\PhpSessionStorage',
            // No additional configuration needed. Session behavior is controlled via php.ini settings
        ],
        'Redis' => [
            'class' => '\System\Http\Session\Storage\RedisSessionStorage',
            'host' => '127.0.0.1',  // Redis server host
            'port' => 6379,         // Redis server port
            'timeout'  => 2.0,      // Connection timeout
            'username' => null,     // Redis username (optional)
            'password' => null,     // Redis password (optional)
            'database' => 1,        // Redis database index
            'persistent_id'  => 'session',   // Identifier to reuse a persistent connection
            'read_timeout'=> 2.5,       // Response timeout from the Redis server
            'retry_interval' => 100,    // Time to wait between connection retries
        ],
        'RedisCluster' => [
            'class' => '\System\Http\Session\Storage\RedisClusterSessionStorage',
            'nodes' => [
                '127.0.0.1:6379',
                '127.0.0.2:6379',
                '127.0.0.3:6379',
            ],
            'timeout' => 2.0,           // Connection timeout (seconds)
            'read_timeout' => 2.0,      // Read timeout (seconds)
            'persistent' => false,      // Use persistent connections
            'password' => null,         // Cluster password (if required)
        ],
        'Memcached' => [
            'class' => '\System\Http\Session\Storage\MemcachedSessionStorage',
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211],
            ],
        ],
        'File' => [
            'class' => '\System\Http\Session\Storage\FileSessionStorage',
            'path' => 'system/cache/sessions'
        ]
    ]
];