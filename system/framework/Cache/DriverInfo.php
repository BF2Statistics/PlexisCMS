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
namespace System\Cache;

final readonly class DriverInfo
{
    /**
     * Driver name (e.g., "redis", "memcached").
     *
     * @var string
     */
    public string $name;

    /**
     * A human-readable, capitalized driver name, for example, "Redis" or "Memcached".
     *
     * @var string
     */
    public string $readableName;

    /**
     * Whether the driver is supported on the current system.
     *
     * @var bool
     */
    public bool $isSupported;

    /**
     * A human-readable description of the driver.
     *
     * @var string
     */
    public string $description;

    /**
     * Constructor for DriverInfo.
     *
     * @param string $name Driver name.
     * @param string $readableName Display-friendly driver name.
     * @param bool $isSupported Whether the driver is supported.
     * @param string $description Human-readable description of the driver.
     */
    public function __construct(string $name, string $readableName, bool $isSupported, string $description)
    {
        $this->name = $name;
        $this->readableName = $readableName;
        $this->isSupported = $isSupported;
        $this->description = $description;
    }

}