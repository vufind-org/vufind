<?php

/**
 * Unit tests for WorkExpressionsCommand.
 *
 * PHP version 8
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
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
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
        $result = new RecordCollection([]);
        $backend->expects($this->once())->method('workExpressions')
            ->with(
                $this->equalTo('id'),
                $this->equalTo(true),
                $this->equalTo($params)
            )->willReturn($result);  // not a realistic value!
        $command = new WorkExpressionsCommand(
            $backendId,
            'id',
            true,
            $params
        );
        $this->assertEquals($result, $command->execute($backend)->getResult());
    }
}
