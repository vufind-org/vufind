<?php

/**
 * WorldCat v2 Similar Related Items Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Related;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\RecordDriver\WorldCat2;
use VuFind\Related\WorldCat2Similar;

/**
 * WorldCat v2 Similar Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WorldCat2SimilarTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Create a mock record driver object.
     *
     * @param string $id ID of mock object.
     *
     * @return MockObject&WorldCat2
     */
    protected function getMockRecordDriver(string $id): MockObject&WorldCat2
    {
        $driver = $this->getMockBuilder(WorldCat2::class)
            ->onlyMethods(
                [
                    'getPrimaryAuthor',
                    'getAllSubjectHeadings',
                    'getTitle',
                    'getUniqueId',
                    'getSourceIdentifier',
                ]
            )->getMock();
        $driver->method('getPrimaryAuthor')->willReturn('fakepa');
        $driver->method('getAllSubjectHeadings')->willReturn([['fakesh1a', 'fakesh1b'], ['fakesh2']]);
        $driver->method('getTitle')->willReturn('faketitle');
        $driver->method('getSourceIdentifier')->willReturn('WorldCat2');
        $driver->method('getUniqueID')->willReturn($id);
        return $driver;
    }

    /**
     * Test results.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $driver1 = $this->getMockRecordDriver('1');
        $driver2 = $this->getMockRecordDriver('2');
        $driver3 = $this->getMockRecordDriver('3');
        $service = $this->createMock(\VuFindSearch\Service::class);
        $response = $this->getMockBuilder(\VuFindSearch\Backend\WorldCat2\Response\RecordCollection::class)
            ->onlyMethods(['getRecords'])
            ->setConstructorArgs([['offset' => 0, 'total' => 0]])
            ->getMock();
        $response->expects($this->once())
            ->method('getRecords')
            ->willReturn([$driver1, $driver2, $driver3]);

        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $commandObj->expects($this->once())->method('getResult')->willReturn($response);

        $checkCommand = function ($command) {
            $expectedTerms = 'fakepa "fakesh1a fakesh1b" "fakesh2" "faketitle"';
            $this->assertEquals(\VuFindSearch\Command\SearchCommand::class, $command::class);
            $this->assertEquals('WorldCat2', $command->getTargetIdentifier());
            $this->assertEquals($expectedTerms, $command->getArguments()[0]->getAllTerms());
            $args = $command->getArguments();
            $this->assertEquals(1, $args[1]);
            $this->assertEquals(6, $args[2]);
            return true;
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->willReturn($commandObj);

        $similar = new WorldCat2Similar($service);
        $similar->init('', $driver1);
        $this->assertEquals([$driver2, $driver3], $similar->getResults());
    }
}
