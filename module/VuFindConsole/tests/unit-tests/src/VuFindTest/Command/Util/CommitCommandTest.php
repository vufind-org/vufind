<?php

/**
 * CommitCommand test.
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
use VuFindConsole\Command\Util\CommitCommand;

/**
 * CommitCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CommitCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test success with all options set.
     *
     * @return void
     */
    public function testSuccessWithOptions()
    {
        $writer = $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $writer->expects($this->once())->method('commit')
            ->with($this->equalTo('foo'));
        $command = new CommitCommand($writer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['core' => 'foo']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }
}
