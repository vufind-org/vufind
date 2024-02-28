<?php

/**
 * Unit tests for GetIdsCommand.
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
use VuFindSearch\Command\GetIdsCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for GetIdsCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetIdsCommandTest extends TestCase
{
    /**
     * Test GetIds with GetIdsInterface
     *
     * @return void
     */
    public function testGetIdsInterface(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $command = new GetIdsCommand($backendId, $query, 0, 1, $params);
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('getIds')
            ->with(
                $this->equalTo($query),
                $this->equalTo(0),
                $this->equalTo(1),
                $this->equalTo($params)
            )->will($this->returnValue('result'));
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test GetIds without GetIdsInterface
     *
     * @return void
     */
    public function testGetIdsNoInterface(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\BackendInterface::class)
            ->disableOriginalConstructor()->getMock();
        $command = new GetIdsCommand($backendId, $query, 0, 1, $params);
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo(0),
                $this->equalTo(1),
                $this->equalTo($params)
            )->will($this->returnValue('result'));
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test getArguments method
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $query = new Query('foo');
        $params =  new ParamBag(['foo' => 'bar']);
        $command = new GetIdsCommand(
            'bar',
            $query,
            0,
            10,
            $params
        );
        $expected = [$query, 0, 10, $params];
        $this->assertEquals(
            $expected,
            $command->getArguments()
        );
    }
}
