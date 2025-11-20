<?php

/**
 * Unit tests for RawJsonSearchCommand.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Solr\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Command\RawJsonSearchCommand;
use VuFindSearch\Query\Query;

/**
 * Unit tests for RawJsonSearchCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawJsonSearchCommandTest extends TestCase
{
    /**
     * Test that an error is thrown for unsupported backends.
     *
     * @return void
     */
    public function testUnsupportedBackend(): void
    {
        $command = new RawJsonSearchCommand('foo', new Query());
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->willReturn('foo');
        $this->expectExceptionMessage('foo does not support rawJsonSearch()');
        $command->execute($backend);
    }

    /**
     * Test that a supported backend behaves as expected.
     *
     * @return void
     */
    public function testSupportedBackend(): void
    {
        $query = new Query();
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->willReturn('Solr');
        $backend->expects($this->once())->method('rawJsonSearch')
            ->with($this->equalTo($query), $this->equalTo(0), $this->equalTo(100))
            ->willReturn('[1, 2, 3]');
        $command = new RawJsonSearchCommand('Solr', $query, 0, 100);
        $this->assertEquals([1, 2, 3], $command->execute($backend)->getResult());
    }
}
