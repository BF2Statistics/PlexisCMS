<?php
return [
    // Define the single cache driver to use. Options: Database, File, Redis, RedisCluster, Memcached
    'cache_driver' => 'File',
    'driver_config' => [
        'Redis' => [
            'host' => '127.0.0.1',  // Redis server host
            'port' => 6379,         // Redis server port
            'timeout'  => 2.0,      // Connection timeout
            'username' => null,     // Redis username (optional)
            'password' => null,     // Redis password (optional)
            'database' => 0,        // Redis database index
            'persistent_id'  => 'bf2web',   // Identifier to reuse a persistent connection
            'read_timeout'=> 2.5,       // Response timeout from the Redis server
            'retry_interval' => 100,    // Time to wait between connection retries
            'serializer' => class_exists(Redis::class) ? Redis::SERIALIZER_PHP : 1, // Serializer for Redis to use
        ],
        'RedisCluster' => [
            'nodes' => [
                '127.0.0.1:6379',
                '127.0.0.2:6379',
                '127.0.0.3:6379',
            ],
            'password' => null
        ],
        'Memcached' => [
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211],
            ],
            'defaultTtl' => 3600
        ],
        'File' => [
            'path' => 'application/cache',
            'defaultTtl' => 3600
        ],
        'Database' => [
            'table' => 'bf2web_cache',
        ]
    ]
];