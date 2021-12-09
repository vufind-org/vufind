<?php

/**
 * Unit tests for WorkExpressionsCommand.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Command\WorkExpressionsCommand;

/**
 * Unit tests for WorkExpressionsCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WorkExpressionsCommandTest extends TestCase
{
    /**
     * Test that the command works as expected with both parameters provided
     *
     * @return void
     */
    public function testBasicUsageOfCommand(): void
    {
        $params = new \VuFindSearch\ParamBag([]);
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('workExpressions')
            ->with(
                $this->equalTo('id'),
                $this->equalTo(['key1', 'key2']),
                $this->equalTo($params)
            )->will($this->returnValue('result'));  // not a realistic value!
        $command = new WorkExpressionsCommand(
            $backendId,
            'id',
            ['key1', 'key2'],
            $params
        );
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test that the command looks up work keys if they are omitted
     *
     * @return void
     */
    public function testWorkKeyAutofill()
    {
        $params = new \VuFindSearch\ParamBag([]);
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $collection = new \VuFindSearch\Backend\Solr\Response\Json\RecordCollection(
            ['response' => ['numFound' => 1]]
        );
        $mockRecord = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()->getMock();
        $mockRecord->expects($this->once())->method('getRawData')
            ->will($this->returnValue(['work_keys_str_mv' => ['key1', 'key2']]));
        $collection->add($mockRecord);
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('id'))
            ->will($this->returnValue($collection));
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('workExpressions')
            ->with(
                $this->equalTo('id'),
                $this->equalTo(['key1', 'key2']),
                $this->equalTo($params)
            )->will($this->returnValue('result'));  // not a realistic value!
        $command = new WorkExpressionsCommand(
            $backendId,
            'id',
            null
        );
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }
}
