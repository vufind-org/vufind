<?php

/**
 * HoldingsWorldCat Test Class
 *
 * PHP version 7
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

use VuFind\RecordTab\HoldingsWorldCat;
use VuFindSearch\Service;

/**
 * HoldingsWorldCat Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HoldingsWorldCatTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $searchObj = $this->getService();
        $obj = new HoldingsWorldCat($searchObj);
        $expected = 'Holdings';

        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public function isActiveProvider(): array
    {
        return ['Enabed' => ["foo", true], 'Not Enabled' => ["", false]];
    }

    /**
     * Test if the tab is active.
     *
     * @param string $oclcnum OCLCNum
     * @param bool   $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testIsActive(string $oclcnum, bool $expectedResult): void
    {
        $searchObj = $this->getService();
        $obj = new HoldingsWorldCat($searchObj);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\WorldCat::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('tryMethod')
            ->with($this->equalTo('getCleanOCLCNum'))
            ->will($this->returnValue($oclcnum));
        $obj->setRecordDriver($recordDriver);
        $this->assertSame($expectedResult, $obj->isActive());
    }

    /**
     * Test getting holdings information.
     *
     * @return void
     */
    public function testGetHoldings(): void
    {
        $searchObj = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new HoldingsWorldCat($searchObj);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\WorldCat::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('tryMethod')
            ->with($this->equalTo('getCleanOCLCNum'))
            ->will($this->returnValue("bar"));
        $obj->setRecordDriver($recordDriver);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->any())->method('getResult')
            ->will($this->returnValue(true));
        $checkCommand = function ($command) {
            return get_class($command) === \VuFindSearch\Backend\WorldCat\Command\GetHoldingsCommand::class
                    && $command->getArguments()[0] === "bar"
                    && $command->getTargetIdentifier() === "WorldCat";
        };
        $searchObj->expects($this->any())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        $this->assertTrue($obj->getHoldings());
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
