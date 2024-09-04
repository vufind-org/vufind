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
use VuFindSearch\ParamBag;

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
                    'getOCLC',
                    'getSourceIdentifier',
                    'getUniqueId',
                ]
            )->getMock();
        $driver->method('getPrimaryAuthor')->willReturn('fakepa');
        // Create a 100-term subject that will get ignored due to the query length limits:
        $longTermThatWillGetSkipped = implode(' ', range(1, 100));
        $driver->method('getAllSubjectHeadings')
            ->willReturn([['fakesh1a', 'fakesh1b'], ['fakesh2'], [$longTermThatWillGetSkipped]]);
        $driver->method('getTitle')->willReturn('faketitle');
        $driver->method('getSourceIdentifier')->willReturn('WorldCat2');
        $driver->method('getOCLC')->willReturn([$id]);
        $driver->method('getUniqueID')->willReturn($id);
        return $driver;
    }

    /**
     * Data provider for testGetResults()
     *
     * @return void
     */
    public static function getResultsProvider(): array
    {
        return [
            'default limit' => ['"fakesh1a fakesh1b" "fakesh2" "fakepa" "faketitle"', null],
            'limit of 1' => ['"fakesh2"', 1],
            'limit of 2' => ['"fakesh1a fakesh1b"', 2],
            'limit of 3' => ['"fakesh1a fakesh1b" "fakesh2"', 3],
        ];
    }

    /**
     * Test results.
     *
     * @param string $expectedTerms Terms expected in generated query
     * @param ?int   $termLimit     Term limit setting (null = default)
     *
     * @return void
     *
     * @dataProvider getResultsProvider
     */
    public function testGetResults(string $expectedTerms, ?int $termLimit): void
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

        $checkCommand = function ($command) use ($expectedTerms) {
            $this->assertEquals(\VuFindSearch\Command\SearchCommand::class, $command::class);
            $this->assertEquals('WorldCat2', $command->getTargetIdentifier());
            $this->assertEquals($expectedTerms, $command->getArguments()[0]->getAllTerms());
            $args = $command->getArguments();
            $this->assertEquals(1, $args[1]);
            $this->assertEquals(6, $args[2]);
            $expectedParams = new ParamBag(['groupRelatedEditions' => 'true']);
            $this->assertEquals($expectedParams, $args[3]);
            return true;
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->willReturn($commandObj);

        $similar = new WorldCat2Similar($service);
        if ($termLimit) {
            $similar->setTermLimit($termLimit);
        }
        $similar->init('', $driver1);
        $this->assertEquals([$driver2, $driver3], $similar->getResults());
    }
}
