<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    public function testIsIPAddress()
    {
        $this->assertTrue(Util::is_ipaddr('10.10.10.10'));
        $this->assertTrue(Util::is_ipaddr('255.255.255.255'));
        $this->assertTrue(Util::is_ipaddr('0.0.0.0'));

        $this->assertFalse(Util::is_ipaddr(10));
        $this->assertFalse(Util::is_ipaddr('10.100.1000.100000'));
        $this->assertFalse(Util::is_ipaddr('10.10.10'));
        $this->assertFalse(Util::is_ipaddr('IP ADDRESS'));
        $this->assertFalse(Util::is_ipaddr(['10.10.10.10']));
    }

    public function testIPLessThan()
    {
        $this->assertTrue(Util::ip_less_than('10.10.10.0', '10.10.10.1'));
        $this->assertTrue(Util::ip_less_than('10.10.10.0', '10.10.20.0'));
        $this->assertTrue(Util::ip_less_than('10.10.10.10', '10.10.20.0'));
        $this->assertFalse(Util::ip_less_than('10.10.10.2', '10.10.10.1'));
    }

    public function testIPGreaterThan()
    {
        $this->assertFalse(Util::ip_greater_than('10.10.10.0', '10.10.10.1'));
        $this->assertTrue(Util::ip_greater_than('10.10.10.2', '10.10.10.1'));
        $this->assertTrue(Util::ip_greater_than('10.10.20.0', '10.10.10.0'));
        $this->assertTrue(Util::ip_greater_than('10.10.20.0', '10.10.10.20'));
    }

    public function testIPAfter()
    {
        $this->assertEquals('10.10.10.1', Util::ip_after('10.10.10.0'));
        $this->assertEquals('10.10.10.2', Util::ip_after('10.10.10.0', 2));
        $this->assertEquals('10.10.11.0', Util::ip_after('10.10.10.255'));
        $this->assertEquals('10.10.11.1', Util::ip_after('10.10.10.255', 2));
    }

    public function testIPBefore()
    {
        $this->assertEquals('10.10.10.0', Util::ip_before('10.10.10.1'));
        $this->assertEquals('10.10.10.0', Util::ip_before('10.10.10.2', 2));
        $this->assertEquals('10.10.9.255', Util::ip_before('10.10.10.0'));
        $this->assertEquals('10.10.9.255', Util::ip_before('10.10.10.1', 2));
    }

    public function testFindSmallestCidr()
    {
        $this->assertEquals(31, Util::find_smallest_cidr(2));
        $this->assertEquals(24, Util::find_smallest_cidr(248));
        $this->assertEquals(23, Util::find_smallest_cidr(259));
    }

    public function testIPRangeSize()
    {
        $this->assertEquals(2, Util::ip_range_size('10.10.10.0', '10.10.10.1'));
        $this->assertEquals(16, Util::ip_range_size('10.10.10.0', '10.10.10.15'));
        $this->assertEquals(3, Util::ip_range_size('127.255.255.255', '128.0.0.1'));
        $this->assertEquals(512, Util::ip_range_size('10.10.10.0', '10.10.11.255'));
    }

    public function testSortIPAddresses()
    {
        $orderedIPs = $shuffledIPs = [
            '10.10.0.0',
            '10.10.10.0',
            '10.10.10.1',
            '10.255.255.0',
            '255.255.255.0',
        ];

        while ($orderedIPs === $shuffledIPs) {
            shuffle($shuffledIPs);
        }

        $this->assertEquals($orderedIPs, Util::sort_addresses($shuffledIPs));
    }

    public function testSubnetRangeSize()
    {
        $this->assertEquals(1, Util::subnet_range_size(32));
        $this->assertEquals(2, Util::subnet_range_size(31));
        $this->assertEquals(256, Util::subnet_range_size(24));
    }

    public function testCidrToIPsArray()
    {
        $this->assertEquals(
            [
                '10.10.10.0',
                '10.10.10.1',
                '10.10.10.2',
                '10.10.10.3',
            ],
            Util::cidr_to_ips_array('10.10.10.0/30')
        );
        $this->assertEquals(['10.10.10.0'], Util::cidr_to_ips_array('10.10.10.0'));
    }

    public function testGetSingleSubnet()
    {
        $this->assertEquals('10.10.10.0/30', Util::get_single_subnet('10.10.10.0', '10.10.10.3'));
        $this->assertEquals('0.0.0.0/1', Util::get_single_subnet('10.10.10.0', '100.100.100.30'));
    }
}
