<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\SubMuncher;
use AndrewAndante\SubMuncher\Util;

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

    public function testConsolidateSubnetsPerformance()
    {
        $result = SubMuncher::consolidate_subnets($this->json_data['subnets'], 1);
        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testConsolidateSubnetsWithMaxRules()
    {
        $result = SubMuncher::consolidate_subnets($this->json_data['subnets'], 25);
        $fail = true;
        foreach ($this->json_data['raw_ips'] as $ip) {
            foreach ($result as $subnet) {
                if (Util::cidr_contains($subnet, $ip)) {
                    $fail = false;
                    break;
                }
            }
        }

        if ($fail) {
            $this->fail($ip . " not contained in any subnets returned");
        }
    }
}
