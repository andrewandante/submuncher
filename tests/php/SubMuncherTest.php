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

    public function testConsolidate()
    {
        $this->assertEquals(['10.10.10.0/31'], SubMuncher::consolidate(['10.10.10.0', '10.10.10.1']));
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32'],
            SubMuncher::consolidate(['10.10.10.0', '10.10.10.1', '10.10.10.2'])
        );
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            SubMuncher::consolidate(
                [
                    '10.10.10.0',
                    '10.10.10.1',
                    '10.10.10.2',
                    '100.10.10.0',
                    '100.10.10.1',
                    '100.10.10.2',
                    '100.10.10.3',
                    '100.10.10.4',
                    '100.10.10.5'
                ]
            )
        );
    }

    public function testConsolidateWithMaxRules()
    {
        $this->assertEquals(['10.10.10.0/32', '10.10.10.3/32'], SubMuncher::consolidate(
            ['10.10.10.0', '10.10.10.3'],
            2
        ));
        $this->assertEquals(['10.10.10.0/30'], SubMuncher::consolidate(
            ['10.10.10.0', '10.10.10.3'],
            1
        ));
        $this->assertEquals(['0.0.0.0/1'], SubMuncher::consolidate(
            ['10.10.10.0', '100.100.100.30'],
            1
        ));
    }

    public function testConsolidateSubnets()
    {
        $this->assertEquals(['10.10.10.0/31'], SubMuncher::consolidate_subnets(['10.10.10.0/32', '10.10.10.1/32']));
        $this->assertEquals(['10.10.10.0/30'], SubMuncher::consolidate_subnets(['10.10.10.0/31', '10.10.10.2/31']));
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            SubMuncher::consolidate_subnets(
                [
                    '10.10.10.0/32',
                    '10.10.10.1/32',
                    '10.10.10.2/32',
                    '100.10.10.0/32',
                    '100.10.10.1/32',
                    '100.10.10.2/32',
                    '100.10.10.3/32',
                    '100.10.10.4/31',
                ]
            )
        );
    }

    public function testConsolidateSubnetsWithDuplicates()
    {
        $this->assertEquals(['10.10.10.0/31'], SubMuncher::consolidate_subnets(
            ['10.10.10.0/32', '10.10.10.1/32', '10.10.10.1/32']));
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            SubMuncher::consolidate_subnets(
                [
                    '10.10.10.0/32',
                    '10.10.10.0/32',
                    '10.10.10.1/32',
                    '10.10.10.2/32',
                    '100.10.10.0/32',
                    '100.10.10.1/32',
                    '100.10.10.2/32',
                    '100.10.10.3/32',
                    '100.10.10.4/31',
                ]
            )
        );
    }

    public function testConsolidateSubnetsWithMaxRules()
    {
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            SubMuncher::consolidate_subnets(
                [
                    '10.10.10.0/32',
                    '10.10.10.1/32',
                    '10.10.10.2/32',
                    '100.10.10.0/32',
                    '100.10.10.1/32',
                    '100.10.10.2/32',
                    '100.10.10.3/32',
                    '100.10.10.4/31',
                ],
                4
            )
        );
        $this->assertEquals(
            ['10.10.10.0/30', '100.10.10.0/30', '100.10.10.4/31'],
            SubMuncher::consolidate_subnets(
                [
                    '10.10.10.0/32',
                    '10.10.10.1/32',
                    '10.10.10.2/32',
                    '100.10.10.0/32',
                    '100.10.10.1/32',
                    '100.10.10.2/32',
                    '100.10.10.3/32',
                    '100.10.10.4/31',
                ],
                3
            )
        );
    }
}
