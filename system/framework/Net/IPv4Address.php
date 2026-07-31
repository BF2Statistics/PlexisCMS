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
namespace System\Net;

/**
 * Provides an Internet Protocol (IP) address.
 *
 * @package System\Net
 */
class IPv4Address implements IPAddressInterface
{
    /**
     * @var string The ip address string
     */
    protected string $ipAddress = "";

    /**
     * @var bool Indicates whether this is a local IP address
     */
    protected bool $isLocal;

    /**
     * IPv4Address constructor.
     *
     * @param string $address An IPv4 address.
     */
    public function __construct(string $address)
    {
        // Check for CIDR ranges
        $parts = explode('/', $address);

        // Make sure IP is valid!
        if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            throw new \InvalidArgumentException("IPv4 Address is invalid!");

        // Define local properties
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $this->ipAddress = $parts[0];
        $this->isLocal = (substr($parts[0], 0, 4) == "127." || !filter_var($parts[0], FILTER_VALIDATE_IP, $flags));
    }

    /**
     * Returns whether this IP address is the loopback address (Localhost)
     *
     * @return bool
     */
    public function isLoopback(): bool
    {
        return $this->isLocal;
    }

    /**
     * Indicates whether this address falls under the supplied CIDR
     *
     * @param string|IPAddressInterface $address the CIDR address range to compare against this IPAddress
     *  instance.
     *
     * @return bool true if this IPAddress fulls under the supplied CIDR range. If no range is supplied,
     *  this address will be directly compared and will return whether both addresses are equal.
     *
     * @see https://www.ipaddressguide.com/cidr
     */
    public function isInCidr(string|IPAddressInterface $address): bool
    {
        if ($address instanceof IPv4Address)
        {
            $address = $address->toString();
        }

        // if no forward slash, just compare
        if (strpos($address, '/') === false)
        {
            return $this->equals($address);
        }

        list ($subnet, $bits) = explode('/', $address);
        $ip = ip2long($this->ipAddress);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; // in case the supplied subnet was not correctly aligned
        return ($ip & $mask) == $subnet;
    }

    /**
     * Returns whether this IP Address is equal to the supplied IP
     *
     * @param string|IPAddressInterface $Ip The IPAddress to compare to
     *
     * @return bool
     */
    public function equals(string|IPAddressInterface $Ip): bool
    {
        if ($Ip instanceof IPv4Address)
            return ($Ip->toString() == $this->ipAddress);
        else
            return ($this->ipAddress == $Ip);
    }

    /**
     * Returns the IP address type (@see IPAddress::IP_VERSION_*)
     *
     * @return int
     */
    public function getType(): int
    {
        return IPAddress::IP_VERSION_4;
    }

    /**
     * Returns the IPv4 dotted-quad notation.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->ipAddress;
    }

    /**
     * Returns the string representation of this IPAddress
     */
    public function __toString()
    {
        return $this->ipAddress;
    }

    /**
     * Maps the IPAddress object to an IPv6 address.
     *
     * @return IPv6Address
     */
    public function mapToIPv6(): IPv6Address
    {
        return IPAddress::IPv4To6($this);
    }

    /**
     * Maps the IPAddress object to an IPv4 address.
     *
     * @return IPv4Address
     */
    public function mapToIPv4(): IPv4Address
    {
        return $this;
    }
}