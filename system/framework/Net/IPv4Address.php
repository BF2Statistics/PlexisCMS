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
     * @var bool Indicates whether this is a local/private/reserved IP address
     */
    protected bool $isLocal;

    /**
     * IPv4Address constructor.
     *
     * @param string $address An IPv4 address.
     */
    public function __construct(string $address)
    {
        // Check for CIDR ranges — reject them rather than silently stripping
        $parts = explode('/', $address);
        if (count($parts) > 1)
            throw new \InvalidArgumentException(
                "CIDR notation is not supported in the constructor. Use isInCidr() for range checks."
            );

        // Make sure IP is valid!
        if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            throw new \InvalidArgumentException("IPv4 Address is invalid!");

        // Define local properties
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $this->ipAddress = $parts[0];
        $this->isLocal = (str_starts_with($parts[0], "127.") || !filter_var($parts[0], FILTER_VALIDATE_IP, $flags));
    }

    /**
     * Returns true only for loopback addresses (127.x.x.x).
     *
     * @return bool
     */
    public function isLoopback(): bool
    {
        return (str_starts_with($this->ipAddress, "127."));
    }

    /**
     * Returns true if this IP address is in a private or reserved range.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    /**
     * Indicates whether this address falls under the supplied CIDR
     *
     * @param string|IPAddressInterface $address the CIDR address range to compare against this IPAddress
     *  instance.
     *
     * @return bool true if this IPAddress falls under the supplied CIDR range. If no range is supplied,
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
        if (!str_contains($address, '/'))
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
     * @return string
     */
    public function __toString(): string
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
        return IPAddress::Ipv4To6($this);
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