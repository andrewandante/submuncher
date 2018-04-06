<?php

namespace AndrewAndante\SubMuncher\Test;

use AndrewAndante\SubMuncher\SubMuncher;

class SubMuncherIPv6Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @TODO remove this when we have actual IPv6 functionality
     */
    public function testIgnoresIPv6()
    {
        $this->assertEmpty(SubMuncher::consolidate(['::10', '::11']));
        $this->assertEquals(
            ['10.10.10.10/31'],
            SubMuncher::consolidate(['10.10.10.10', '10.10.10.11', '::10', '::11'])
        );
    }
}
