<?php

/**
 * Unit tests for RetrieveBatchCommand.
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
use VuFindSearch\Command\RetrieveBatchCommand;
use VuFindSearch\ParamBag;

/**
 * Unit tests for RetrieveBatchCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RetrieveBatchCommandTest extends TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test RetrieveBatch with RetrieveBatchInterface
     *
     * @return void
     */
    public function testExecute(): void
    {
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $ids = ['id1', 'id2'];
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $command = new RetrieveBatchCommand($backendId, $ids, $params);
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('retrieveBatch')
            ->with(
                $this->equalTo($ids),
                $this->equalTo($params)
            )->will($this->returnValue('result'));
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test RetrieveBatch without RetrieveBatchInterface
     *
     * @return void
     */
    public function testExecuteWithoutRetrieveBatchInterface(): void
    {
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $ids = ['id1', 'id2'];
        $command = new RetrieveBatchCommand($backendId, $ids, $params);
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\BackendInterface::class)
            ->disableOriginalConstructor()->getMock();
        $rci = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->disableOriginalConstructor()->getMock();
        $record = $this->getMockBuilder(\VuFindSearch\Response\RecordInterface::class)
            ->disableOriginalConstructor()->getMock();
        $this->expectConsecutiveCalls(
            $backend,
            'retrieve',
            [['id1', $params], ['id2', $params]],
            $rci
        );
        $rci->expects($this->once())->method('first')->will($this->returnValue($record));
        $rci->expects($this->once())->method('add')->with($this->equalTo($record));
        $this->assertEquals($rci, $command->execute($backend)->getResult());
    }

    /**
     * Test getArguments method
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $ids = ['id1', 'id2'];
        $command = new RetrieveBatchCommand($backendId, $ids, $params);
        $expected = [$ids, $params];
        $this->assertEquals(
            $expected,
            $command->getArguments()
        );
    }

    /**
     * Test getRecordIdentifiers method
     *
     * @return void
     */
    public function testgetRecordIdentifiers(): void
    {
        $backendId = 'bar';
        $ids = ['id1', 'id2'];
        $command = new RetrieveBatchCommand($backendId, $ids);
        $this->assertEquals(
            $ids,
            $command->getRecordIdentifiers()
        );
    }
}
