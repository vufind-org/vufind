<?php

/**
 * Unit tests for RetrieveCommand.
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
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\ParamBag;

/**
 * Unit tests for RetrieveCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RetrieveCommandTest extends TestCase
{
    /**
     * Test that a supported backed behaves as expected
     *
     * @return void
     */
    public function testExecute(): void
    {
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $result = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->getMock();
        $command = new RetrieveCommand($backendId, 'id', $params);
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('retrieve')
            ->with(
                $this->equalTo('id'),
                $this->equalTo($params)
            )->will($this->returnValue($result));
        $this->assertEquals($result, $command->execute($backend)->getResult());
    }

    /**
     * Test getArguments method
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $params = new ParamBag(['foo' => 'bar']);
        $command = new RetrieveCommand(
            'bar',
            'id',
            $params
        );
        $expected = ['id', $params];
        $this->assertEquals(
            $expected,
            $command->getArguments()
        );
    }
}
