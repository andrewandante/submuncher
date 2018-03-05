<?php

namespace AndrewAndante\SubMuncher;

// The bulk of the following code is from /etc/inc/util.inc in pfSense v2.0.2
// See https://www.pfsense.org - seriously good open source router software

class Util
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /* Convert IP address to long int, truncated to 32-bits to avoid sign extension
    on 64-bit platforms. */
    public static function ip2long32($ip)
    {
        return (ip2long($ip) & 0xFFFFFFFF);
    }

    /* Convert IP address to unsigned long int. */
    public static function ip2ulong($ip)
    {
        return sprintf("%u", self::ip2long32($ip));
    }

    /* Convert long int to IP address, truncating to 32-bits. */
    public static function long2ip32($ip)
    {
        return long2ip($ip & 0xFFFFFFFF);
    }

    /* returns true if $ipaddr is a valid dotted IPv4 address */
    public static function is_ipaddr($ipaddr)
    {
        if (!is_string($ipaddr)) {
            return false;
        }

        $ip_long = ip2long($ipaddr);
        $ip_reverse = self::long2ip32($ip_long);

        return ($ipaddr == $ip_reverse);
    }

    /* Return true if the first IP is 'before' the second */
    public static function ip_less_than($ip1, $ip2)
    {
        // Compare as unsigned long because otherwise it wouldn't work when
        // crossing over from 127.255.255.255 / 128.0.0.0 barrier
        return self::ip2ulong($ip1) < self::ip2ulong($ip2);
    }

    /* Return true if the first IP is 'after' the second */
    public static function ip_greater_than($ip1, $ip2)
    {
        // Compare as unsigned long because otherwise it wouldn't work
        // when crossing over from 127.255.255.255 / 128.0.0.0 barrier
        return self::ip2ulong($ip1) > self::ip2ulong($ip2);
    }

    /* Return the next IP address after the given address */
    public static function ip_after($ip, $increment = 1)
    {
        return self::long2ip32(ip2long($ip) + $increment);
    }

    /* Return the next IP address after the given address */
    public static function ip_before($ip, $decrement = 1)
    {
        return self::long2ip32(ip2long($ip) - $decrement);
    }

    /* Find the smallest possible subnet mask which can contain a given number of IPs
    *  e.g. 512 IPs can fit in a /23, but 513 IPs need a /22
    */
    public static function find_smallest_cidr($number)
    {
        $smallest = 1;
        for ($b = 32; $b > 0; $b--) {
            $smallest = ($number <= pow(2, $b)) ? $b : $smallest;
        }
        return (32 - $smallest);
    }

    /* Find out how many IPs are contained within a given IP range
    *  e.g. 192.168.0.0 to 192.168.0.255 returns 256
    */
    public static function ip_range_size($startip, $endip)
    {
        if (self::is_ipaddr($startip) && self::is_ipaddr($endip)) {
            // Operate as unsigned long because otherwise it wouldn't work
            // when crossing over from 127.255.255.255 / 128.0.0.0 barrier
            return abs(self::ip2ulong($startip) - self::ip2ulong($endip)) + 1;
        }
        return -1;
    }

    public static function subnet_range_size($subnetmask)
    {
        return (2 ** (32 - (int) $subnetmask));
    }

    public static function cidr_to_ips_array($cidr)
    {
        $parts = explode('/', $cidr);
        if (!self::is_ipaddr($parts[0])) {
            return false;
        }

        $subnetMask = isset($parts[1]) ? $parts[1] : "32";
        $ips = [];
        $currentIP = $parts[0];

        for ($i = 0; $i < self::subnet_range_size($subnetMask); ++$i) {
            $ips[] = $currentIP;
            $currentIP = self::ip_after($currentIP);
        }

        return $ips;
    }

    /* return the subnet address given a host address and a subnet bit count */
    public static function gen_subnet($ipaddr, $bits)
    {
        if (!self::is_ipaddr($ipaddr) || !is_numeric($bits)) {
            return "";
        }

        return long2ip(ip2long($ipaddr) & self::gen_subnet_mask_long($bits));
    }

    /* returns a subnet mask (long given a bit count) */
    public static function gen_subnet_mask_long($bits)
    {
        $sm = 0;
        for ($i = 0; $i < $bits; $i++) {
            $sm >>= 1;
            $sm |= 0x80000000;
        }
        return $sm;
    }

    /* return the highest (broadcast) address in the subnet given a host address and
    a subnet bit count */
    public static function gen_subnet_max($ipaddr, $bits)
    {
        if (!self::is_ipaddr($ipaddr) || !is_numeric($bits)) {
            return "";
        }

        return self::long2ip32(ip2long($ipaddr) | ~self::gen_subnet_mask_long($bits));
    }

    /* takes an array of ip address, sorts and returns as an array */
    public static function sort_addresses($ipaddr)
    {
        $bitAddresses = [];
        $ipAddresses = [];
        foreach ($ipaddr as $ipv4) {
            $bitAddresses[] = self::ip2ulong($ipv4);
        }
        sort($bitAddresses);
        foreach ($bitAddresses as $raw) {
            $ipAddresses[] = self::long2ip32($raw);
        }
        return $ipAddresses;
    }
}
