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

interface IPAddressInterface
{
    /**
     * The isLoopback method compares address to Loopback and returns true if the
     *  two IP addresses are the same.
     *
     * In the case of IPv4, that the IsLoopback method returns true for any IP address
     *  of the form 127.X.Y.Z (where X, Y, and Z are in the range 0-255), not just
     *  Loopback (127.0.0.1).
     *
     * @return bool
     */
    public function isLoopback(): bool;

    /**
     * Indicates whether this address falls under the supplied CIDR
     *
     * @param string|IPAddressInterface $address
     *
     * @return bool
     */
    public function isInCidr(string|IPAddressInterface $address): bool;

    /**
     * Compares the current IPAddress instance with the comparing parameter and returns true
     *  if the two instances contain the same IP address.
     *
     * @param string|IPAddressInterface $Ip The IP address to compare with
     *
     * @return bool
     */
    public function equals(string|IPAddressInterface $Ip): bool;

    /**
     * Returns the IP address type (@see IPAddress::IP_VERSION_*)
     *
     * @return int
     */
    public function getType(): int;

    /**
     * Maps the IPAddress object to an IPv6 address.
     *
     * @return IPv6Address
     */
    public function mapToIPv6(): IPv6Address;

    /**
     * Maps the IPAddress object to an IPv4 address.
     *
     * @return IPv4Address
     */
    public function mapToIPv4(): IPv4Address;

    /**
     * Returns either IPv4 dotted-quad or IPv6 colon-hexadecimal notation.
     *
     * @return string
     */
    public function toString(): string;
}