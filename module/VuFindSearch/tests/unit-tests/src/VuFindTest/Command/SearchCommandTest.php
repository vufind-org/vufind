<?php

/**
 * Unit tests for SearchCommand.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for SearchCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchCommandTest extends TestCase
{
    /**
     * Test that the command works as expected
     *
     * @return void
     */
    public function testCommand(): void
    {
        $query = new Query('foo');
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo(0),
                $this->equalTo(1),
                $this->equalTo($params)
            )->will($this->returnValue('result'));  // not a realistic value!
        $command = $this->getCommand();
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test setter and getter of Search query.
     *
     * @return void
     */
    public function testSearchQuery()
    {
        $command = $this->getCommand();
        $query = new Query('foo');
        $this->assertEquals($query, $command->getQuery());
        $query2 = new Query('fox');
        $command->setQuery($query2);
        $this->assertEquals($query2, $command->getQuery());
    }

    /**
     * Test setter and getter of Search offset.
     *
     * @return void
     */
    public function testSearchOffset()
    {
        $command = $this->getCommand();
        $this->assertEquals(0, $command->getOffset());
        $command->setOffset(20);
        $this->assertEquals(20, $command->getOffset());
    }

    /**
     * Test setter and getter of Search limit.
     *
     * @return void
     */
    public function testSearchLimit()
    {
        $command = $this->getCommand();
        $this->assertEquals(1, $command->getLimit());
        $command->setLimit(2);
        $this->assertEquals(2, $command->getLimit());
    }

    /**
     * Test setter and getter of Search Parameters.
     *
     * @return void
     */
    public function testSearchParameters()
    {
        $command = $this->getCommand();
        $this->assertEquals(
            new ParamBag(['foo' => 'bar']),
            $command->getSearchParameters()
        );
        $params2 = new ParamBag(['foo' => 'baz']);
        $command->setSearchParameters($params2);
        $this->assertEquals($params2, $command->getSearchParameters());
    }

    /**
     * Test setter and getter of command context.
     *
     * @return void
     */
    public function testCommandContext()
    {
        $command = $this->getCommand();
        $this->assertEquals('search', $command->getContext());
        $command->setContext('search2');
        $this->assertEquals('search2', $command->getContext());
    }

    /**
     * Test setter and getter of target backend identifier.
     *
     * @return void
     */
    public function testTargetBackendIdentifier()
    {
        $backendId = 'bar';
        $command = $this->getCommand();
        $this->assertEquals($backendId, $command->getTargetIdentifier());
        $command->setTargetIdentifier($backendId . '2');
        $this->assertEquals($backendId . '2', $command->getTargetIdentifier());
    }

    /**
     * Test that the command throws an exception results are requested before execute
     *
     * @return void
     */
    public function testTooEarlyResults(): void
    {
        $command = $this->getCommand();
        $this->expectExceptionMessage('Command was not yet executed');
        $command->getResult();
    }

    /**
     * Test extra request details
     *
     * @return void
     */
    public function testExtraRequestDetails(): void
    {
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('getExtraRequestDetails')
            ->will($this->returnValue(['foo' => 'bar']));
        $command = $this->getCommand();
        $this->assertEqualsCanonicalizing(['foo' => 'bar'], $command->execute($backend)->getExtraRequestDetails());
    }

    /**
     * Get test SearchCommand Object
     *
     * @return SearchCommand
     */
    public function getCommand()
    {
        $command = new SearchCommand(
            'bar',
            new Query('foo'),
            0,
            1,
            new ParamBag(['foo' => 'bar'])
        );
        return $command;
    }
}
