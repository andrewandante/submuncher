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
            // If not last and the next IP is the next sequential one, we are at the beginning of a subnet
            if (isset($sortedIPs[$index + 1]) && $sortedIPs[$index + 1] == Util::ip_after($ipv4)) {
                // if we've already started, just keep going, else kick one off
                $subnetStart = $subnetStart ?: $ipv4;
                // if not the first IP and the previous IP is sequential, we're at the end of a subnet
            } elseif (isset($sortedIPs[$index - 1]) && $subnetStart !== null) {
                $result = self::ip_range_to_subnet_array($subnetStart, $ipv4);
                $consolidatedSubnets = array_merge($consolidatedSubnets, $result);
                $subnetStart = null;
                // otherwise we are a lone /32, so add it straight in
            } else {
                $consolidatedSubnets[]= $ipv4.'/32';
                $subnetStart = null;
            }
        }

        if ($max === null || count($consolidatedSubnets) <= $max) {
            return $consolidatedSubnets;
        }

        return self::consolidate_subnets($consolidatedSubnets, $max);
    }

    /**
     * @param string $startip an IPv4 address
     * @param string $endip an IPv4 address
     *
     * @return string[] list of subnets that cover the ip range specified
     */
    public static function ip_range_to_subnet_array($startip, $endip)
    {

        if (!Util::is_ipaddr($startip) || !Util::is_ipaddr($endip)) {
            return [];
        }

        // Container for subnets within this range.
        $rangesubnets = [];

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
                return ["{$startip}/{$cidr}"];
            }

            // These remaining scenarios will find a subnet that uses the largest
            // chunk possible of the range being tested, and leave the rest to be
            // tested recursively after the loop.

            // Check if the subnet begins with $startip and ends before $endip
            if (($targetsub_min == $startip) && Util::ip_less_than($targetsub_max, $endip)) {
                break;
            }

            // Check if the subnet ends at $endip and starts after $startip
            if (Util::ip_greater_than($targetsub_min, $startip) && ($targetsub_max == $endip)) {
                break;
            }

            // Check if the subnet is between $startip and $endip
            if (Util::ip_greater_than($targetsub_min, $startip) && Util::ip_less_than($targetsub_max, $endip)) {
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
     * Function to figure out the least problematic subnets to combine based on
     * fewest additional IPs introduced. Then combines them as such, and runs
     * it back through the consolidator with one less subnet - until we have
     * reduced it down to the maximum number of rules
     *
     * @param array $subnetsArray array of cidrs
     * @param int $max
     *
     * @return array
     */
    public static function consolidate_subnets($subnetsArray, $max = null)
    {

        $subnetsArray = Util::sort_cidrs($subnetsArray);

        do {
            $countSubnetsArray = count($subnetsArray);
            $newSubnetsArray = [];
            $subnetToMaskMap = [];
            $ipReductionBySubnet = [];
            reset($subnetsArray);
            do {
                $cidr = current($subnetsArray);
                list($currentIP, $currentMask) = explode('/', $cidr);
                $nextIP = null;
                $nextMask = null;

                if (next($subnetsArray) !== false) {
                    list($nextIP, $nextMask) = explode('/', current($subnetsArray));
                    prev($subnetsArray);
                } else {
                    end($subnetsArray);
                }

                $endIP = Util::gen_subnet_max($currentIP, $currentMask);
                while (isset($nextIP) && Util::ip_after($endIP) == $nextIP) {
                    $nextEndIP = Util::gen_subnet_max($nextIP, $nextMask);
                    $consolidated = self::ip_range_to_subnet_array($currentIP, $nextEndIP);
                    if (count($consolidated) == 1) {
                        $endIP = $nextEndIP;
                        list($currentIP, $currentMask) = explode('/', $consolidated[0]);
                        if (next($subnetsArray) !== false) {
                            list($nextIP, $nextMask) = explode('/', current($subnetsArray));
                        } else {
                            end($subnetsArray);
                            $nextIP = null;
                            $nextMask = null;
                        }
                    } else {
                        break;
                    }
                }

                $newSubnetsArray[] = $currentIP . '/' . $currentMask;

                $subnetToMaskMap[$currentIP] = [
                    'startIP' => $currentIP,
                    'endIP' => $endIP,
                    'mask' => $currentMask,
                    'next' => isset($nextIP) ? $nextIP : 'none',
                ];

                $toJoin = Util::get_single_subnet($currentIP, Util::gen_subnet_max($nextIP, $nextMask));
                if (!$toJoin) {
                    continue;
                }
                list($joinIP, $joinMask) = explode('/', $toJoin);
                $diff = abs(Util::subnet_range_size($currentMask) - Util::subnet_range_size($joinMask));

                $ipReductionBySubnet[$joinIP] = [
                    'mask' => $joinMask,
                    'diff' => $diff,
                    'original' => $currentIP,
                ];
            } while (next($subnetsArray) !== false);
            $subnetsArray = $newSubnetsArray;
        } while (count($subnetsArray) !== $countSubnetsArray);

        // sort array by number of additional IPs introduced
        uasort($ipReductionBySubnet, function ($a, $b) {
            return $a['diff'] - $b['diff'];
        });

        $returnCIDRs = [];
        foreach ($subnetToMaskMap as $ip => $config) {
            $returnCIDRs[] = $ip.'/'.$config['mask'];
        }

        if ($max === null || count($returnCIDRs) <= $max) {
            return $returnCIDRs;
        }

        reset($ipReductionBySubnet);
        do {
            current($ipReductionBySubnet);
            $injectedIP = key($ipReductionBySubnet);

            $toUpdate = $ipReductionBySubnet[$injectedIP]['original'];
            if (isset($subnetToMaskMap[$toUpdate])) {
                $next = $subnetToMaskMap[$toUpdate]['next'];

                // remove the two subnets we've just mushed
                unset($subnetToMaskMap[$toUpdate]);
                unset($subnetToMaskMap[$next]);

                // chuck in the new one
                $subnetToMaskMap[$injectedIP] = [
                    'mask' => $ipReductionBySubnet[$injectedIP]['mask'],
                ];

                $returnCIDRs = [];
                foreach ($subnetToMaskMap as $ip => $config) {
                    $returnCIDRs[] = $ip . '/' . $config['mask'];
                }

                $returnCIDRs = Util::sort_cidrs($returnCIDRs);
            }
        } while (count($returnCIDRs) > $max && next($ipReductionBySubnet) !== false);

        if (count($returnCIDRs > $max)) {
            return self::consolidate_subnets($returnCIDRs, $max);
        }

        return $returnCIDRs;
    }

    /**
     * @param string[] $ipsArray
     * @param int|null $max
     * @return array
     */
    public static function consolidate_verbose($ipsArray, $max = null)
    {
        $consolidateResults = self::consolidate($ipsArray, $max);
        $totalIPs = [];
        foreach ($consolidateResults as $cidr) {
            $totalIPs = array_merge($totalIPs, Util::cidr_to_ips_array($cidr));
        }

        return [
            'consolidated_subnets' => $consolidateResults,
            'initial_IPs' => Util::sort_addresses($ipsArray),
            'total_IPs' => $totalIPs
        ];
    }

    /**
     * @param string[] $subnetsArray
     * @param int|null $max
     * @return array
     */
    public static function consolidate_subnets_verbose($subnetsArray, $max = null)
    {
        $ips = [];
        foreach ($subnetsArray as $subnet) {
            $ips = array_merge($ips, Util::cidr_to_ips_array($subnet));
        }

        return self::consolidate_verbose($ips, $max);
    }
}
