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
class IPv6Address implements IPAddressInterface
{
    /**
     * The original IP address that was passed
     * @var string
     */
    protected string $ipAddress = "";

    /**
     * Full expanded IP address
     * @var string
     */
    protected string $fullIpAddress = "";

    /**
     * Indicates whether the IP address is local/private/reserved
     * @var bool
     */
    protected bool $isLocal;

    /**
     * IPv6Address constructor.
     *
     * @param string $address An IPv6 address.
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
        if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
            throw new \InvalidArgumentException("IPv6 Address is invalid!");

        $flags = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $this->ipAddress = $parts[0];
        $this->fullIpAddress = IPAddress::ExpandIPv6Notation($parts[0], true);
        $this->isLocal = !filter_var($parts[0], FILTER_VALIDATE_IP, $flags);
    }

    /**
     * Returns true only for the loopback address (::1).
     *
     * @return bool
     */
    public function isLoopback(): bool
    {
        return ($this->ipAddress === '::1' || $this->fullIpAddress === '0000:0000:0000:0000:0000:0000:0000:0001');
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
     * @see https://www.ipaddressguide.com/ipv6-cidr
     */
    public function isInCidr(string|IPAddressInterface $address): bool
    {
        if ($address instanceof IPv6Address)
        {
            $address = $address->toString();
        }

        // if no forward slash, just compare
        if (!str_contains($address, '/'))
        {
            return $this->equals($address);
        }

        list($subnet, $mask) = explode('/', $address);
        $subnet = inet_pton($subnet);
        $addr = inet_pton($this->ipAddress);
        $binMask = $this->iPv6MaskToByteArray($mask);

        // Mask the subnet before comparison
        $subnet = $subnet & $binMask;

        return ($addr & $binMask) == $subnet;
    }

    /**
     * Compares the current IPAddress instance with the comparing parameter and returns true
     *  if the two instances contain the same IP address.
     *
     * @param string|IPAddressInterface $Ip The IP address to compare with
     *
     * @return bool
     */
    public function equals(string|IPAddressInterface $Ip): bool
    {
        if ($Ip instanceof IPv6Address)
            return ($this->fullIpAddress === $Ip->fullIpAddress);
        else
            return ($this->fullIpAddress === IPAddress::ExpandIPv6Notation($Ip, true));
    }

    /**
     * Returns the IP address type (@see IPAddress::IP_VERSION_*)
     *
     * @return int
     */
    public function getType(): int
    {
        return IPAddress::IP_VERSION_6;
    }

    /**
     * Returns the IPv6 colon-hexadecimal notation.
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

    private function iPv6MaskToByteArray($subnetMask): string
    {
        $addr = str_repeat("f", $subnetMask / 4);
        switch ($subnetMask % 4) {
            case 0:
                break;
            case 1:
                $addr .= "8";
                break;
            case 2:
                $addr .= "c";
                break;
            case 3:
                $addr .= "e";
                break;
        }
        $addr = str_pad($addr, 32, '0');
        $addr = pack("H*", $addr);
        return $addr;
    }

    /**
     * Maps the IPAddress object to an IPv6 address.
     *
     * @return IPv6Address
     */
    public function mapToIPv6(): IPv6Address
    {
        return $this;
    }

    /**
     * Maps the IPAddress object to an IPv4 address.
     * Supports IPv4-mapped IPv6 addresses (::ffff:x.x.x.x).
     *
     * @return IPv4Address
     * @throws \BadMethodCallException if the address is not an IPv4-mapped IPv6 address.
     */
    public function mapToIPv4(): IPv4Address
    {
        // Check if this is an IPv4-mapped IPv6 address (::ffff:x.x.x.x)
        $prefix = '0000:0000:0000:0000:0000:ffff:';
        if (str_starts_with(strtolower($this->fullIpAddress), $prefix))
        {
            // Extract the last two groups and convert to IPv4
            $hexPart = substr($this->fullIpAddress, strlen($prefix));
            $groups = explode(':', $hexPart);
            if (count($groups) === 2)
            {
                $high = hexdec($groups[0]);
                $low = hexdec($groups[1]);
                $ipv4 = sprintf(
                    '%d.%d.%d.%d',
                    ($high >> 8) & 0xFF,
                    $high & 0xFF,
                    ($low >> 8) & 0xFF,
                    $low & 0xFF
                );
                return new IPv4Address($ipv4);
            }
        }

        throw new \BadMethodCallException(
            'Cannot convert IPv6 to IPv4: address is not an IPv4-mapped IPv6 address (::ffff:x.x.x.x).'
        );
    }
}