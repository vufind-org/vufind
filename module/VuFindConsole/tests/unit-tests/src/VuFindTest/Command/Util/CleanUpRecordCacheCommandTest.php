<?php

/**
 * CleanUpRecordCacheCommand test.
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
use VuFind\Db\Service\RecordServiceInterface;
use VuFindConsole\Command\Util\CleanUpRecordCacheCommand;

/**
 * CleanUpRecordCacheCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CleanUpRecordCacheCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the cache clear action is delegated properly.
     *
     * @return void
     */
    public function testBasicOperation(): void
    {
        $service = $this->createMock(RecordServiceInterface::class);
        $service->expects($this->once())->method('cleanup')->willReturn(5);
        $command = new CleanUpRecordCacheCommand($service);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "5 records deleted.\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
