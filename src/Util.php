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

    public static function ip_diff($ip1, $ip2)
    {
        return abs(self::ip2ulong($ip1) - self::ip2ulong($ip2));
    }

    public static function cidr_contains($cidr, $ip)
    {
        list($start, $mask) = explode('/', $cidr);
        $cidrMax = self::gen_subnet_max($start, $mask);
        return !self::ip_greater_than($ip, $cidrMax) && !self::ip_less_than($ip, $start);
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


    public static function get_single_subnet($startip, $endip)
    {
        if (!self::is_ipaddr($startip) || !self::is_ipaddr($endip)) {
            return null;
        }

        $cidr = self::find_smallest_cidr(self::ip_range_size($startip, $endip));
        $lowestCommonIP = self::find_smallest_common_IP($startip, $endip);

        while (self::ip_greater_than($endip, self::gen_subnet_max($lowestCommonIP, $cidr))) {
            $cidr--;
            if ($cidr == 0) {
                return null;
            }
        }
        return $lowestCommonIP . '/' . $cidr;
    }

    public static function find_smallest_common_IP($startip, $endip)
    {
        $startBits = explode('.', $startip);
        $endBits = explode('.', $endip);

        $returnIP = [];
        $broken = false;
        for ($i = 0; $i < 4; ++$i) {
            if ($broken) {
                $returnIP[$i] = "00000000";
                continue;
            }
            $startAsBinary = str_pad(decbin($startBits[$i]), 8, "0", STR_PAD_LEFT);
            $endAsBinary = str_pad(decbin($endBits[$i]), 8, "0", STR_PAD_LEFT);

            if ($startAsBinary === $endAsBinary) {
                $returnIP[$i] = $startAsBinary;
                continue;
            }

            $returnOctet = [];
            for ($j = 0; $j < 8; ++$j) {
                if (!$broken && $startAsBinary[$j] == $endAsBinary[$j]) {
                    $returnOctet[$j] = $startAsBinary[$j];
                    continue;
                } else {
                    $returnOctet[$j] = "0";
                    $broken = true;
                }
            }

            $returnIP[$i] = implode('', $returnOctet);
        }
        $returnIPDec = [];
        foreach ($returnIP as $returnIPBin) {
            $returnIPDec[] = bindec($returnIPBin);
        }
        return implode('.', $returnIPDec);
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
        $ipaddr = array_unique($ipaddr);
        $bitAddresses = [];
        $ipAddresses = [];
        foreach ($ipaddr as $ipv4) {
            if (self::is_ipaddr($ipv4)) {
                $bitAddresses[] = self::ip2ulong($ipv4);
            }
        }
        sort($bitAddresses);
        foreach ($bitAddresses as $raw) {
            $ipAddresses[] = self::long2ip32($raw);
        }
        return $ipAddresses;
    }

    /* takes an array of ip address, sorts and returns as an array */
    public static function sort_cidrs($cidrs)
    {
        $map = [];
        $bitAddresses = [];
        $sortedCidrs = [];
        foreach ($cidrs as $cidr) {
            $parts = explode('/', $cidr);
            if (self::is_ipaddr($parts[0])) {
                $map[$parts[0]] = $parts[1];
                $bitAddresses[] = self::ip2ulong($parts[0]);
            }
        }
        sort($bitAddresses);
        foreach ($bitAddresses as $raw) {
            $sortedCidrs[] = self::long2ip32($raw) . '/' . $map[self::long2ip32($raw)];
        }
        return $sortedCidrs;
    }
}
