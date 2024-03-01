<?php

/**
 * Unit tests for RandomCommand.
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
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Command\RandomCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for RandomCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RandomCommandTest extends TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test Random with RandomInterface
     *
     * @return void
     */
    public function testRandomInterface(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $command = new RandomCommand($backendId, $query, 10, $params);
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('random')
            ->with(
                $this->equalTo($query),
                $this->equalTo(10),
                $this->equalTo($params)
            )->will($this->returnValue('result'));
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test Random (without RandomInterface)
     *
     * @return void
     */
    public function testRandomNoInterfaceWithNoResults(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\BackendInterface::class)
            ->disableOriginalConstructor()->getMock();
        $command = new RandomCommand($backendId, $query, 10, $params);
        $rci = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->getMock();
        $rci->expects($this->once())->method('getTotal')
            ->will($this->returnValue(0));
        $backend->expects($this->once())->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo(0),
                $this->equalTo(0),
                $this->equalTo($params)
            )->will($this->returnValue($rci));
        $this->assertEquals($rci, $command->execute($backend)->getResult());
    }

    /**
     * Test Random (without RandomInterface)
     *
     * @return void
     */
    public function testRandomNoInterfaceWithResultsLessThanLimit(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\BackendInterface::class)
            ->disableOriginalConstructor()->getMock();
        $command = new RandomCommand($backendId, $query, 10, $params);
        $rci = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->addMethods(['shuffle'])
            ->getMockForAbstractClass();

        $rci->expects($this->once())->method('getTotal')
            ->will($this->returnValue(2));
        $rci->expects($this->once())->method('shuffle')
            ->will($this->returnValue(true));
        $this->expectConsecutiveCalls(
            $backend,
            'search',
            [
                [
                    $this->equalTo($query),
                    $this->equalTo(0),
                    $this->equalTo(0),
                    $this->equalTo($params),
                ],
                [
                    $this->equalTo($query),
                    $this->equalTo(0),
                    $this->equalTo(10),
                    $this->equalTo($params),
                ],
            ],
            $rci
        );
        $this->assertEquals($rci, $command->execute($backend)->getResult());
    }

    /**
     * Test Random (without RandomInterface)
     *
     * @return void
     */
    public function testRandomNoInterfaceWithResultsGreaterThanLimit(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $limit = 10;
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\BackendInterface::class)
            ->disableOriginalConstructor()->getMock();
        $command = new RandomCommand($backendId, $query, 10, $params);
        $rci = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->getMock();
        $rci->expects($this->once())->method('getTotal')
            ->will($this->returnValue(20));
        $inputs = [[$query, '0', '0', $params]];
        $outputs = [$rci];
        for ($i = 1; $i < $limit + 1; $i++) {
            $inputs[] = [$query, $this->anything(), '1', $params];
            $outputs[] = $rci;
        }
        $this->expectConsecutiveCalls($backend, 'search', $inputs, $outputs);
        $record = $this->getMockBuilder(\VuFindSearch\Response\RecordInterface::class)
            ->disableOriginalConstructor()->getMock();
        $rci->expects($this->exactly(9))->method('first')->will($this->returnValue($record));
        $rci->expects($this->exactly(9))->method('add')->with($this->equalTo($record));
        $this->assertEquals($rci, $command->execute($backend)->getResult());
    }

    /**
     * Test getting arguments
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $query = new Query('foo');
        $params =  new ParamBag(['foo' => 'bar']);
        $command = new RandomCommand(
            'bar',
            $query,
            10,
            $params
        );
        $expected = [$query, 10, $params];
        $this->assertEquals(
            $expected,
            $command->getArguments()
        );
        $this->assertEquals($query, $command->getQuery());
        $this->assertEquals(10, $command->getLimit());
    }
}
