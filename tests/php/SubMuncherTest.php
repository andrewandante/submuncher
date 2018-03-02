<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\SubMuncher;

class SubMuncherTest extends \PHPUnit_Framework_TestCase
{
    public function testIPRangeToSubnetArray()
    {
        $this->assertEquals(
            [
                '10.10.10.0/31'
            ],
            SubMuncher::ip_range_to_subnet_array(
                '10.10.10.0',
                '10.10.10.1'
            )
        );

        $this->assertEquals(
            [
                '10.10.10.0/24'
            ],
            SubMuncher::ip_range_to_subnet_array(
                '10.10.10.0',
                '10.10.10.255'
            )
        );

        $this->assertEquals(
            [
                '10.10.10.0/31',
                '10.10.10.2/32'
            ],
            SubMuncher::ip_range_to_subnet_array(
                '10.10.10.0',
                '10.10.10.2'
            )
        );

        $this->assertEquals(
            [
                '10.10.10.7/32',
                '10.10.10.8/29',
                '10.10.10.16/28',
                '10.10.10.32/27',
                '10.10.10.64/28',
                '10.10.10.80/31'
            ],
            SubMuncher::ip_range_to_subnet_array(
                '10.10.10.7',
                '10.10.10.81'
            )
        );
    }
}