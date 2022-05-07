<?php

/**
 * Unit tests for SearchCommand.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
        $command = new SearchCommand($backendId, $query, 0, 1, $params);
        $this->assertEquals('result', $command->execute($backend)->getResult());

        $this->assertEquals($query, $command->getQuery());
        $query2 = new Query('fox');
        $command->setQuery($query2);
        $this->assertEquals($query2, $command->getQuery());

        $this->assertEquals(0, $command->getOffset());
        $command->setOffset(20);
        $this->assertEquals(20, $command->getOffset());

        $this->assertEquals(1, $command->getLimit());
        $command->setLimit(2);
        $this->assertEquals(2, $command->getLimit());

        $this->assertEquals('search', $command->getContext());
        $command->setContext('search2');
        $this->assertEquals('search2', $command->getContext());

        $this->assertEquals($backendId, $command->getTargetIdentifier());
        $command->setTargetIdentifier($backendId . '2');
        $this->assertEquals($backendId . '2', $command->getTargetIdentifier());

        $this->assertEquals($params, $command->getSearchParameters());
        $params2 = new ParamBag(['foo' => 'baz']);
        $command->setSearchParameters($params2);
        $this->assertEquals($params2, $command->getSearchParameters());
    }

    /**
     * Test that the command throws an exception results are requested before execute
     *
     * @return void
     */
    public function testTooEarlyResults(): void
    {
        $command = new SearchCommand('bar', new Query(), 0, 20);
        $this->expectExceptionMessage('Command was not yet executed');
        $command->getResult();
    }
}
