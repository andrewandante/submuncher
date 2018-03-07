<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\SubMuncher;

class SubMuncherVerboseTest extends \PHPUnit_Framework_TestCase
{
    public function testConsolidateVerbose()
    {
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/31'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1'],
            ],
            SubMuncher::consolidate_verbose(['10.10.10.0', '10.10.10.1'])
        );
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/31', '10.10.10.2/32'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.2'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.2'],
            ],
            SubMuncher::consolidate_verbose(['10.10.10.0', '10.10.10.1', '10.10.10.2'])
        );
        $longIPList = [
            '10.10.10.0',
            '10.10.10.1',
            '10.10.10.2',
            '100.10.10.0',
            '100.10.10.1',
            '100.10.10.2',
            '100.10.10.3',
            '100.10.10.4',
            '100.10.10.5'
        ];
        $results = SubMuncher::consolidate_verbose($longIPList);
        $this->assertEquals($results['initial_IPs'], $results['total_IPs']);
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            $results['consolidated_subnets']
        );
    }

    public function testConsolidateVerboseWithMaxRules()
    {
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/31'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1'],
            ],
            SubMuncher::consolidate_verbose(['10.10.10.0', '10.10.10.1'], 1)
        );
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/30'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.3'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.2', '10.10.10.3'],
            ],
            SubMuncher::consolidate_verbose(['10.10.10.0', '10.10.10.1', '10.10.10.3'], 1)
        );
    }

    public function testConsolidateSubnetsVerbose()
    {
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/31'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1'],
            ],
            SubMuncher::consolidate_subnets_verbose(['10.10.10.0/32', '10.10.10.1/32'])
        );
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/30'],
                'initial_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.2', '10.10.10.3'],
                'total_IPs' => ['10.10.10.0', '10.10.10.1', '10.10.10.2', '10.10.10.3'],
            ],
            SubMuncher::consolidate_subnets_verbose(['10.10.10.0/31', '10.10.10.2/31'])
        );

        $longSubnetList = [
            '10.10.10.0/32',
            '10.10.10.1/32',
            '10.10.10.2/32',
            '100.10.10.0/32',
            '100.10.10.1/32',
            '100.10.10.2/32',
            '100.10.10.3/32',
            '100.10.10.4/31',
        ];
        $result = SubMuncher::consolidate_subnets_verbose($longSubnetList);

        $this->assertEquals($result['initial_IPs'], $result['total_IPs']);
        $this->assertEquals(
            ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
            $result['consolidated_subnets']
        );
    }

    public function testConsolidateSubnetsVerboseWithMaxRules()
    {
        $initialIPs = [
            '10.10.10.0',
            '10.10.10.1',
            '10.10.10.2',
            '100.10.10.0',
            '100.10.10.1',
            '100.10.10.2',
            '100.10.10.3',
            '100.10.10.4',
            '100.10.10.5',
        ];
        $initialSubnets = [
            '10.10.10.0/32',
            '10.10.10.1/32',
            '10.10.10.2/32',
            '100.10.10.0/32',
            '100.10.10.1/32',
            '100.10.10.2/32',
            '100.10.10.3/32',
            '100.10.10.4/31',
        ];

        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/31', '10.10.10.2/32', '100.10.10.0/30', '100.10.10.4/31'],
                'initial_IPs' => $initialIPs,
                'total_IPs' => $initialIPs
            ],
            SubMuncher::consolidate_subnets_verbose($initialSubnets, 4)
        );
        $this->assertEquals(
            [
                'consolidated_subnets' => ['10.10.10.0/30', '100.10.10.0/30', '100.10.10.4/31'],
                'initial_IPs' => $initialIPs,
                'total_IPs' => [
                    '10.10.10.0',
                    '10.10.10.1',
                    '10.10.10.2',
                    '10.10.10.3',
                    '100.10.10.0',
                    '100.10.10.1',
                    '100.10.10.2',
                    '100.10.10.3',
                    '100.10.10.4',
                    '100.10.10.5',
                ]
            ],
            SubMuncher::consolidate_subnets_verbose($initialSubnets, 3)
        );
    }
}
