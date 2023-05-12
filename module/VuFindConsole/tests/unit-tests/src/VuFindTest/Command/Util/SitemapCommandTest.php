<?php

/**
 * SitemapCommand test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Util\SitemapCommand;

/**
 * SitemapCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SitemapCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test success with all options set.
     *
     * @return void
     */
    public function testSuccessWithOptions()
    {
        $generator = $this->getMockBuilder(\VuFind\Sitemap\Generator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $generator->expects($this->once())->method('setBaseUrl')
            ->with($this->equalTo('http://foo'));
        $generator->expects($this->once())->method('setBaseSitemapUrl')
            ->with($this->equalTo('http://bar'));
        $generator->expects($this->once())->method('generate');
        $generator->expects($this->once())->method('getWarnings')
            ->will($this->returnValue(['Sample warning']));
        $command = new SitemapCommand($generator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['--baseurl' => 'http://foo', '--basesitemapurl' => 'http://bar']
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "Sample warning\n",
            $commandTester->getDisplay()
        );
    }
}
