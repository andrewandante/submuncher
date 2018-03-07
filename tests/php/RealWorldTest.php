<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\SubMuncher;

class RealWorldTest extends \PHPUnit_Framework_TestCase
{
    private $json_data;

    protected function setUp()
    {
        parent::setUp();
        $this->json_data = json_decode(file_get_contents(__DIR__ . '/../data/real_world_data.json'), true);
    }

    public function testConsolidateSubnets()
    {
        $this->assertEquals(
            $this->json_data['consolidated_subnets'],
            SubMuncher::consolidate_subnets($this->json_data['subnets'])
        );
    }

    public function testConsolidateSubnetsVerbose()
    {
        $this->assertEquals(
            [
                'consolidated_subnets' => $this->json_data['consolidated_subnets'],
                'initial_IPs' => $this->json_data['raw_ips'],
                'total_IPs' => $this->json_data['raw_ips'],
            ],
            SubMuncher::consolidate_subnets_verbose($this->json_data['subnets'])
        );
    }

    public function testConsolidateSubnetsVerboseWithMaxRules()
    {
        $result = SubMuncher::consolidate_subnets_verbose($this->json_data['subnets'], 25);
        $this->assertCount(25, $result['consolidated_subnets']);
        $this->assertEquals($this->json_data['raw_ips'], $result['initial_IPs']);
        $this->assertGreaterThanOrEqual(count($this->json_data['raw_ips']), count($result['total_IPs']));

        foreach ($this->json_data['raw_ips'] as $raw_ip) {
            $this->assertContains($raw_ip, $result['total_IPs']);
        }
    }
}
