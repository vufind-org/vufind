<?php

/**
 * ComponentParts Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\ComponentParts;

/**
 * ComponentParts Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ComponentPartsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $searchObj = $this->getService();
        $obj = new ComponentParts($searchObj);
        $expected = 'child_records';
        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Test Maxresults.
     *
     * @return void
     */
    public function testGetMaxResults(): void
    {
        $searchObj = $this->getService();
        $obj = new ComponentParts($searchObj);
        $this->assertSame(100, $obj->getMaxResults());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public static function isActiveProvider(): array
    {
        return ['no children' => [0, false], 'children' => [10, true]];
    }

    /**
     * Test if the tab is active.
     *
     * @param int  $childCount     Child count for record driver to report
     * @param bool $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testIsActive(int $childCount, bool $expectedResult): void
    {
        $searchObj = $this->getService();
        $obj = new ComponentParts($searchObj);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\DefaultRecord::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getChildRecordCount'))
            ->will($this->returnValue($childCount));
        $obj->setRecordDriver($recordDriver);
        $this->assertSame($expectedResult, $obj->isActive());
    }

    /**
     * Test getting contents for display.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rci = $this->getMockBuilder(
            \VuFindSearch\Response\RecordCollectionInterface::class
        )->disableOriginalConstructor()->getMock();
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\DefaultRecord::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('getUniqueID')
            ->will($this->returnValue('foo'));
        $recordDriver->expects($this->any())->method('getSourceIdentifier')
            ->will($this->returnValue('bar'));
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($rci));
        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\SearchCommand::class
                && $command->getTargetIdentifier() === 'bar'
                && $command->getArguments()[0]->getAllTerms() === 'hierarchy_parent_id:"foo"'
                && $command->getArguments()[1] === 0
                && $command->getArguments()[2] === 101
                && $command->getArguments()[3]->getArrayCopy() === [
                    'hl' => ['false'],
                    'sort' => ['hierarchy_sequence ASC,title ASC'],
                ];
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        $obj = new ComponentParts($service);
        $obj->setRecordDriver($recordDriver);
        $this->assertEquals($rci, $obj->getResults());
    }

    /**
     * Get a Service object
     *
     * @return Service
     */
    public function getService()
    {
        $searchObj = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $searchObj;
    }
}
