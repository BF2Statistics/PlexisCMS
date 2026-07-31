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

use System\ArgumentException;

/**
 * Provides an Internet Protocol (IP) address.
 *
 * @package System\Net
 */
class IPAddress
{
    const IP_VERSION_6 = 6;
    const IP_VERSION_4 = 4;

    /**
     * Determines whether a string is a valid IP address
     *
     * @param string $input A string that contains an IP address in dotted-quad notation for IPv4 and in colon-hexadecimal notation for IPv6.
     * @param IPAddressInterface $out [Reference Variable] The IPAddress Object for the
     * provided IP address version.
     *
     * @return bool True if the ipString is a valid IP address; otherwise, false.
     */
    public static function TryParse(string $input, ?IPAddressInterface &$out): bool
    {
        // Check for CIDR ranges
        $parts = explode('/', $input);

        // Check IPv4
        if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            // Valid IPv4
            $out = new IPv4Address($input);
            return true;
        }
        elseif (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        {
            // Valid IPv6
            $out = new IPv6Address($input);
            return true;
        }
        else
        {
            // Invalid IP
            $out  = null;
            return false;
        }
    }

    /**
     * Converts an IP address string to an IPAddress instance.
     *
     * @param string $input A string that contains an IP address in dotted-quad notation for IPv4 and in colon-hexadecimal notation for IPv6.
     *
     * @return IPAddressInterface
     *
     * @throws \System\ArgumentException if the supplied string is not a valid IP address.
     */
    public static function Parse(string $input): IPAddressInterface
    {
        $out = null;
        if (!self::TryParse($input, $out))
            throw new \System\ArgumentException('Invalid IP Address string passed "'. $input .'"', 'input');

        return $out;
    }

    /**
     * Determine if a given IP address is within any of the specified CIDR ranges.
     *
     * @param string $address The IP address to check.
     * @param array $ranges An array of CIDR ranges to evaluate against.
     *
     * @return bool True if the IP address is within any of the specified ranges, otherwise false.
     * @throws ArgumentException
     */
    public static function IsInCIDR(string $address, array $ranges): bool
    {
        if (!($address instanceof IPAddressInterface))
        {
            $address = self::Parse($address);
        }

        foreach ($ranges as $range)
        {
            if ($address->isInCidr($range))
                return true;
        }

        return false;
    }

    /**
     * Convert an IPv4 address to IPv6
     *
     * @param IPv4Address $Address
     *
     * @return bool|IPv6Address
     */
    public static function IPv4To6(IPv4Address $Address): IPv6Address|bool
    {
        // This tells IPv6 it has an IPv4 address
        static $Mask = '::ffff:';

        // Convert to string
        $Ip = $Address->toString();

        // Determine Ip version
        $IPv6 = (strpos($Ip, '::') === 0);
        $IPv4 = (strpos($Ip, '.') > 0);
        if (!$IPv4 && !$IPv6)
            return false; // Not a valid IP address

        // Strip IPv4 Compatibility notation
        if ($IPv6 && $IPv4)
            $Ip = substr($Ip, strrpos($Ip, ':') + 1);

        // Seems to be IPv6 already?
        elseif (!$IPv4)
            return $Ip;

        // Make sure there are 4 parts to the IPv4 address
        $Ip = array_pad(explode('.', $Ip), 4, 0);
        if (count($Ip) > 4)
            return false;

        // Make sure that the 4 parts do not exceed the limit
        for ($i = 0; $i < 4; $i++)
            if ($Ip[$i] > 255)
                return false;

        // Convert ipv4 parts to ipv6
        $Part7 = base_convert(($Ip[0] * 256) + $Ip[1], 10, 16);
        $Part8 = base_convert(($Ip[2] * 256) + $Ip[3], 10, 16);

        return new IPv6Address($Mask . $Part7 . ':' . $Part8);
    }

    /**
     * Replace '::' with appropriate number of ':0's
     *
     * @param string $Ip The Ipv6 address to expand
     * @param bool $pad By setting to true, 0 values will not be filtered,
     *   returning an absolute, full 32 character ip address
     *
     * @return string|array
     */
    public static function ExpandIPv6Notation(string $Ip, bool $pad = false): string|array
    {
        if (strpos($Ip, '::') !== false)
            $Ip = str_replace('::', str_repeat(':0', 8 - substr_count($Ip, ':')) . ':', $Ip);
        if (strpos($Ip, ':') === 0)
            $Ip = '0' . $Ip;
        elseif ($Ip[strlen($Ip) - 1] == ":")
            $Ip .= "0";

        // Pad 0's ?
        if ($pad)
        {
            $ipparts = explode('::', $Ip, 2);

            $head = $ipparts[0];
            $tail = isset($ipparts[1]) ? $ipparts[1] : '';

            $headparts = explode(':', $head);
            $ippad = array();
            foreach ($headparts as $val)
                $ippad[] = str_pad($val, 4, '0', STR_PAD_LEFT);

            if (count($ipparts) > 1)
            {
                $tailparts = explode(':', $tail);
                $midparts = 8 - count($headparts) - count($tailparts);

                for ($i = 0; $i < $midparts; $i++)
                    $ippad[] = '0000';

                foreach ($tailparts as $val)
                    $ippad[] = str_pad($val, 4, '0', STR_PAD_LEFT);
            }

            return implode(':', $ippad);
        }

        return $Ip;
    }

    /**
     * @param string $Ip The IPv6 address to collapse
     *
     * @return string The collapsed IPv6 address
     */
    public static function CollapseIPv6Notation(string $Ip): string
    {
        $best_pos = $zeros_pos = false;
        $best_count = $zeros_count = 0;

        // If already compacted
        if (strpos($Ip, '::') !== false)
            return $Ip;

        // Expand all blocks
        $headparts = explode(':', $Ip);
        $parts = array();
        foreach ($headparts as $val)
            $parts[] = str_pad($val, 4, '0', STR_PAD_LEFT);

        foreach ($parts as $i => $quad)
        {
            $parts[$i] = ($quad == '0000') ? '0' : ltrim($quad, '0');
            if ($quad == '0000')
            {
                if ($zeros_pos === false)
                    $zeros_pos = $i;

                $zeros_count++;

                if ($zeros_count > $best_count)
                {
                    $best_count = $zeros_count;
                    $best_pos = $zeros_pos;
                }
            }
            else
            {
                $zeros_count = 0;
                $zeros_pos = false;
                $parts[$i] = ltrim($quad, '0');
            }
        }

        if ($best_pos !== false)
        {
            $insert = array(null);

            if ($best_pos == 0 || $best_pos + $best_count == 8)
            {
                $insert[] = null;
                if ($best_count == count($parts))
                {
                    $best_count--;
                }
            }
            array_splice($parts, $best_pos, $best_count, $insert);
        }

        return implode(':', $parts);
    }

    /**
     * Validate an IP address.
     *
     * @param string $ip The IP address to validate.
     * @param bool $allowPrivateRange Whether to allow private and reserved IP ranges.
     *
     * @return bool True if the IP address is valid, false otherwise.
     */
    public static function Validate(string $ip, bool $allowPrivateRange = false): bool
    {
        $flags = FILTER_VALIDATE_IP;
        if (!$allowPrivateRange)
            $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, $flags);
    }
}