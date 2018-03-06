<?php

namespace AndrewAndante\SubMuncher;

class SubMuncher
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * @param array $ipsArray
     * @param int $max max number of rules returned
     * @return array
     */
    public static function consolidate($ipsArray, $max = null)
    {
        $consolidatedSubnets = [];
        $subnetStart = null;

        $ips = array_unique($ipsArray);
        $sortedIPs = Util::sort_addresses($ips);

        foreach ($sortedIPs as $index => $ipv4) {
            // first IP
            if ($index == 0) {
                $subnetStart = $ipv4;
            }
            // last IP
            if (!isset($sortedIPs[$index + 1])) {
                if ($subnetStart) {
                    $result = self::ip_range_to_subnet_array($subnetStart, $ipv4);
                    $consolidatedSubnets = array_merge($consolidatedSubnets, $result);
                } else {
                    $consolidatedSubnets[]= $ipv4.'/32';
                    $subnetStart = null;
                }
                // if the next IP is sequential, we want this as part of the subnet
            } elseif ($sortedIPs[$index + 1] == Util::ip_after($ipv4)) {
                // if we've already started, just keep going, else kick one off
                $subnetStart = $subnetStart ?: $ipv4;
                // if not the first IP and the previous IP is sequential, we're at the end of a subnet
            } elseif (isset($sortedIPs[$index - 1])) {
                    $result = self::ip_range_to_subnet_array($subnetStart, $ipv4);
                    $consolidatedSubnets = array_merge($consolidatedSubnets, $result);
                    $subnetStart = null;
                // otherwise we are a lone /32, so add it straight in
            } else {
                $consolidatedSubnets[]= $ipv4.'/32';
                $subnetStart = null;
            }
        }

        if (!$max || count($consolidatedSubnets) <= $max) {
            return $consolidatedSubnets;
        }

        return self::ultra_compression($consolidatedSubnets, $max);
    }

    /* Convert a range of IPs to an array of subnets which can contain the range. */
    public static function ip_range_to_subnet_array($startip, $endip)
    {

        if (!Util::is_ipaddr($startip) || !Util::is_ipaddr($endip)) {
            return array();
        }

        // Container for subnets within this range.
        $rangesubnets = array();

        // Figure out what the smallest subnet is that holds the number of IPs in the
        // given range.
        $cidr = Util::find_smallest_cidr(Util::ip_range_size($startip, $endip));

        // Loop here to reduce subnet size and retest as needed. We need to make sure
        // that the target subnet is wholly contained between $startip and $endip.
        for ($cidr; $cidr <= 32; $cidr++) {
            // Find the network and broadcast addresses for the subnet being tested.
            $targetsub_min = Util::gen_subnet($startip, $cidr);
            $targetsub_max = Util::gen_subnet_max($startip, $cidr);

            // Check best case where the range is exactly one subnet.
            if (($targetsub_min == $startip) && ($targetsub_max == $endip)) {
                // Hooray, the range is exactly this subnet!
                return array("{$startip}/{$cidr}");
            }

            // These remaining scenarios will find a subnet that uses the largest
            // chunk possible of the range being tested, and leave the rest to be
            // tested recursively after the loop.

            // Check if the subnet begins with $startip and ends before $endip
            if (($targetsub_min == $startip)
                && Util::ip_less_than($targetsub_max, $endip)
            ) {
                break;
            }

            // Check if the subnet ends at $endip and starts after $startip
            if (Util::ip_greater_than($targetsub_min, $startip)
                && ($targetsub_max == $endip)
            ) {
                break;
            }

            // Check if the subnet is between $startip and $endip
            if (Util::ip_greater_than($targetsub_min, $startip)
                && Util::ip_less_than($targetsub_max, $endip)
            ) {
                break;
            }
        }

        // Some logic that will recursively search from $startip to the first IP before
        // the start of the subnet we just found.
        // NOTE: This may never be hit, the way the above algo turned out, but is left
        // for completeness.
        if ($startip != $targetsub_min) {
            $rangesubnets = array_merge(
                $rangesubnets,
                self::ip_range_to_subnet_array($startip, Util::ip_before($targetsub_min))
            );
        }

        // Add in the subnet we found before, to preserve ordering
        $rangesubnets[] = "{$targetsub_min}/{$cidr}";

        // And some more logic that will search after the subnet we found to fill in
        // to the end of the range.
        if ($endip != $targetsub_max) {
            $rangesubnets = array_merge(
                $rangesubnets,
                self::ip_range_to_subnet_array(Util::ip_after($targetsub_max), $endip)
            );
        }

        return $rangesubnets;
    }


    /**
     * Should be an array of CIDRS eg ['1.1.1.0/24', '2.2.2.2/31']
     *
     * @param string[] $subnetsArray
     */
    public static function consolidate_subnets($subnetsArray, $max = null)
    {
        $ips = [];
        foreach ($subnetsArray as $subnet) {
            $ips = array_merge($ips, Util::cidr_to_ips_array($subnet));
        }

        return self::consolidate($ips, $max);
    }

    public static function ultra_compression($subnetsArray, $max = null)
    {
        $subnetToMaskMap = [];
        $ipReductionBySubnet = [];

        foreach ($subnetsArray as $index => $cidr) {
            $parts = explode('/', $cidr);
            $adjacentParts = [];

            if (isset($subnetsArray[$index + 1])) {
                $adjacentParts = explode('/', $subnetsArray[$index + 1]);
            }

            $subnetToMaskMap[$parts[0]] = [
                'mask' => $parts[1],
                'next' => isset($adjacentParts[0]) ? $adjacentParts[0] : 'none'
            ];

            if ($index == count($subnetsArray) - 1) {
                // we at the end
                break;
            }


            $toJoin = Util::get_single_subnet($parts[0], Util::gen_subnet_max($adjacentParts[0], $adjacentParts[1]));
            $joinAddress = explode('/', $toJoin)[0];
            $joinMask = explode('/', $toJoin)[1];
            $diff = abs(Util::subnet_range_size($parts[1]) - Util::subnet_range_size($joinMask));
            $ipReductionBySubnet[$joinAddress] = [
                'mask' => $joinMask,
                'diff' => $diff,
                'original' => $parts[0]
            ];
        }

        uasort($ipReductionBySubnet, function ($a, $b) {
            return $a['diff'] - $b['diff'];
        });
        reset($ipReductionBySubnet);
        $injectedIP = key($ipReductionBySubnet);

        $toUpdate = $ipReductionBySubnet[$injectedIP]['original'];
        $next = $subnetToMaskMap[$toUpdate]['next'];

        // remove the two subnets we've just mushed
        unset($subnetToMaskMap[$toUpdate]);
        unset($subnetToMaskMap[$next]);

        $subnetToMaskMap[$injectedIP] = [
            'mask' => $ipReductionBySubnet[$injectedIP]['mask'],
            'next' => 'none',
        ];

        $returnIPs = [];
        foreach ($subnetToMaskMap as $ip => $config) {
            $returnIPs[] = $ip.'/'.$config['mask'];
        }

        sort($returnIPs);

        if (!$max || count($returnIPs) <= $max) {
            return $returnIPs;
        }

        return self::consolidate_subnets($returnIPs, $max);
    }
}
