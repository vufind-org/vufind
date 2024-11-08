<?php

/**
 * HoldingsWorldCat2 Test Class
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\HoldingsWorldCat2;
use VuFindSearch\ParamBag;

/**
 * HoldingsWorldCat2 Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HoldingsWorldCat2Test extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $searchObj = $this->createMock(\VuFindSearch\Service::class);
        $obj = new HoldingsWorldCat2($searchObj);
        $expected = 'Holdings';

        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public static function isActiveProvider(): array
    {
        return ['Enabled' => ['foo', true], 'Not Enabled' => ['', false]];
    }

    /**
     * Test if the tab is active.
     *
     * @param string $oclcnum        OCLCNum
     * @param bool   $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testIsActive(string $oclcnum, bool $expectedResult): void
    {
        $searchObj = $this->createMock(\VuFindSearch\Service::class);
        $obj = new HoldingsWorldCat2($searchObj);
        $recordDriver = $this->createMock(\VuFind\RecordDriver\WorldCat2::class);
        $callback = function ($method) use ($oclcnum) {
            return $method === 'getCleanOCLCNum' ? $oclcnum : null;
        };
        $recordDriver->method('tryMethod')->willReturnCallback($callback);
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
        $searchObj = $this->createMock(\VuFindSearch\Service::class);
        $obj = new HoldingsWorldCat2($searchObj, ['extra' => 'xyzzy']);
        $recordDriver = $this->createMock(\VuFind\RecordDriver\WorldCat2::class);
        $callback = function ($method) {
            return $method === 'getCleanOCLCNum' ? 'bar' : null;
        };
        $recordDriver->method('tryMethod')->willReturnCallback($callback);
        $obj->setRecordDriver($recordDriver);
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->any())->method('getResult')->willReturn(true);
        $checkCommand = function ($command) {
            $this->assertEquals($command::class, \VuFindSearch\Backend\WorldCat2\Command\GetHoldingsCommand::class);
            $expectedParams = new ParamBag(
                [
                    'oclcNumber' => 'bar',
                    'extra' => 'xyzzy',
                ]
            );
            $this->assertEquals([$expectedParams], $command->getArguments());
            $this->assertEquals('WorldCat2', $command->getTargetIdentifier());
            return true;
        };
        $searchObj->expects($this->any())->method('invoke')
            ->with($this->callback($checkCommand))
            ->willReturn($commandObj);
        $this->assertTrue($obj->getHoldings());
    }
}
