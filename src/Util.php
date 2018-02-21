<?php

// The bulk of the following code is from /etc/inc/util.inc in pfSense v2.0.2
// See https://www.pfsense.org - seriously good open source router software

class SubnetConsolidator
{

    /* Convert IP address to long int, truncated to 32-bits to avoid sign extension
    on 64-bit platforms. */
    private function ip2long32($ip)
    {
        return (ip2long($ip) & 0xFFFFFFFF);
    }

    /* Convert IP address to unsigned long int. */
    private function ip2ulong($ip)
    {
        return sprintf("%u", $this->ip2long32($ip));
    }

    /* Convert long int to IP address, truncating to 32-bits. */
    private function long2ip32($ip)
    {
        return long2ip($ip & 0xFFFFFFFF);
    }

    /* returns true if $ipaddr is a valid dotted IPv4 address */
    private function is_ipaddr($ipaddr)
    {
        if (!is_string($ipaddr)) {
            return false;
        }

        $ip_long = ip2long($ipaddr);
        $ip_reverse = $this->long2ip32($ip_long);

        if ($ipaddr == $ip_reverse) {
            return true;
        } else {
            return false;
        }
    }

    /* Return true if the first IP is 'before' the second */
    private function ip_less_than($ip1, $ip2)
    {
        // Compare as unsigned long because otherwise it wouldn't work when
        // crossing over from 127.255.255.255 / 128.0.0.0 barrier
        return $this->ip2ulong($ip1) < $this->ip2ulong($ip2);
    }

    /* Return true if the first IP is 'after' the second */
    private function ip_greater_than($ip1, $ip2)
    {
        // Compare as unsigned long because otherwise it wouldn't work
        // when crossing over from 127.255.255.255 / 128.0.0.0 barrier
        return $this->ip2ulong($ip1) > $this->ip2ulong($ip2);
    }

    /* Return the next IP address after the given address */
    public function ip_after($ip)
    {
        return $this->long2ip32(ip2long($ip) + 1);
    }

    /* Find the smallest possible subnet mask which can contain a given number of IPs
    *  e.g. 512 IPs can fit in a /23, but 513 IPs need a /22
    */
    private function find_smallest_cidr($number)
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
    private function ip_range_size($startip, $endip)
    {
        if ($this->is_ipaddr($startip) && $this->is_ipaddr($endip)) {
            // Operate as unsigned long because otherwise it wouldn't work
            // when crossing over from 127.255.255.255 / 128.0.0.0 barrier
            return abs($this->ip2ulong($startip) - $this->ip2ulong($endip)) + 1;
        }
        return -1;
    }

    /* return the subnet address given a host address and a subnet bit count */
    private function gen_subnet($ipaddr, $bits)
    {
        if (!$this->is_ipaddr($ipaddr) || !is_numeric($bits)) {
            return "";
        }

        return long2ip(ip2long($ipaddr) & $this->gen_subnet_mask_long($bits));
    }

    /* returns a subnet mask (long given a bit count) */
    private function gen_subnet_mask_long($bits)
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
    private function gen_subnet_max($ipaddr, $bits)
    {
        if (!$this->is_ipaddr($ipaddr) || !is_numeric($bits)) {
            return "";
        }

        return $this->long2ip32(ip2long($ipaddr) | ~$this->gen_subnet_mask_long($bits));
    }

    public function sort_addresses($ipaddr)
    {
        $bitAddresses = [];
        $ipAddresses = [];
        foreach ($ipaddr as $ipv4) {
            $bitAddresses[] = $this->ip2ulong($ipv4);
        }
        sort($bitAddresses);
        foreach ($bitAddresses as $raw) {
            $ipAddresses[] = $this->long2ip32($raw);
        }
        return $ipAddresses;
    }

    /* Convert a range of IPs to an array of subnets which can contain the range. */
    public function ip_range_to_subnet_array($startip, $endip)
    {

        if (!$this->is_ipaddr($startip) || !$this->is_ipaddr($endip)) {
            return array();
        }

        // Container for subnets within this range.
        $rangesubnets = array();

        // Figure out what the smallest subnet is that holds the number of IPs in the
        // given range.
        $cidr = $this->find_smallest_cidr($this->ip_range_size($startip, $endip));

        // Loop here to reduce subnet size and retest as needed. We need to make sure
        // that the target subnet is wholly contained between $startip and $endip.
        for ($cidr; $cidr <= 32; $cidr++) {
            // Find the network and broadcast addresses for the subnet being tested.
            $targetsub_min = $this->gen_subnet($startip, $cidr);
            $targetsub_max = $this->gen_subnet_max($startip, $cidr);

            // Check best case where the range is exactly one subnet.
            if (($targetsub_min == $startip) && ($targetsub_max == $endip)) {
                // Hooray, the range is exactly this subnet!
                return array("{$startip}/{$cidr}");
            }

            // These remaining scenarios will find a subnet that uses the largest
            // chunk possible of the range being tested, and leave the rest to be
            // tested recursively after the loop.

            // Check if the subnet begins with $startip and ends before $endip
            if (($targetsub_min == $startip) &&
                $this->ip_less_than($targetsub_max, $endip)) {
                break;
            }

            // Check if the subnet ends at $endip and starts after $startip
            if ($this->ip_greater_than($targetsub_min, $startip) &&
                ($targetsub_max == $endip)) {
                break;
            }

            // Check if the subnet is between $startip and $endip
            if ($this->ip_greater_than($targetsub_min, $startip) &&
                $this->ip_less_than($targetsub_max, $endip)) {
                break;
            }
        }

        // Some logic that will recursivly search from $startip to the first IP before
        // the start of the subnet we just found.
        // NOTE: This may never be hit, the way the above algo turned out, but is left
        // for completeness.
        if ($startip != $targetsub_min) {
            $rangesubnets =
                array_merge($rangesubnets,
                    $this->ip_range_to_subnet_array($startip,
                        $this->ip_before($targetsub_min)));
        }

        // Add in the subnet we found before, to preserve ordering
        $rangesubnets[] = "{$targetsub_min}/{$cidr}";

        // And some more logic that will search after the subnet we found to fill in
        // to the end of the range.
        if ($endip != $targetsub_max) {
            $rangesubnets =
                array_merge($rangesubnets,
                    $this->ip_range_to_subnet_array($this->ip_after($targetsub_max), $endip));
        }

        return $rangesubnets;
    }
    public static function checkIP4($requestIP, $ip) {
        if (!filter_var($requestIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (false !== strpos($ip, '/')) {
            list($address, $netmask) = explode('/', $ip, 2);

            if ($netmask === '0') {
                return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            }

            if ($netmask < 0 || $netmask > 32) {
                return false;
            }
        } else {
            $address = $ip;
            $netmask = 32;
        }

        return 0 === substr_compare(sprintf('%032b', ip2long($requestIP)), sprintf('%032b', ip2long($address)), 0, $netmask);

    }

    public static function checkIP($requestIP, $ips) {
        if (!is_array($ips)) {
            $ips = array($ips);
        }

//        $method = substr_count($requestIP, ':') > 1 ? 'checkIP6' : 'checkIP4';

        foreach ($ips as $ip) {
            if (self::checkIP4($requestIP, trim($ip))) {
                return true;
            }
        }

        return false;
    }
}
// ============================================================================
// MAIN

//if(!is_ipaddr($argv[1])) {
//    exit(1);
//}
//
//if(!is_ipaddr($argv[2])) {
//    exit(1);
//}
//
//if(ip_less_than($argv[2], $argv[1])) {
//    exit(1);
//}
//
//print implode("\n", ip_range_to_subnet_array($argv[1], $argv[2]));
//exit(0);

// E.G., arguments of "192.168.1.1" and "192.168.1.12"
// should yield:
// 192.168.1.1/32
// 192.168.1.2/31
// 192.168.1.4/30
// 192.168.1.8/30
// 192.168.1.12/32
