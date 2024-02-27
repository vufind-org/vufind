<?php

/**
 * Unit tests for GetLuceneHelperCommand.
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
use VuFindSearch\Command\GetLuceneHelperCommand;

/**
 * Unit tests for GetLuceneHelperCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetLuceneHelperCommandTest extends TestCase
{
    /**
     * Test that an error is thrown for unsupported backends.
     *
     * @return void
     */
    public function testUnsupportedBackend(): void
    {
        $command = new GetLuceneHelperCommand('foo');
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('foo'));
        $this->assertFalse($command->execute($backend)->getResult());
    }

    /**
     * Test that an error is thrown for mismatched backend IDs.
     *
     * @return void
     */
    public function testMismatchedBackendId(): void
    {
        $command = new GetLuceneHelperCommand('foo');
        $this
            ->expectExceptionMessage('Expected backend instance foo instead of bar');
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('bar'));
        $command->execute($backend);
    }

    /**
     * Test that a supported backend behaves as expected.
     *
     * @return void
     */
    public function testSupportedBackend(): void
    {
        $helper = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\LuceneSyntaxHelper::class)
            ->disableOriginalConstructor()->getMock();
        $qb = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();
        $qb->expects($this->once())->method('GetLuceneHelper')
            ->will($this->returnValue($helper));
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('bar'));
        $backend->expects($this->once())->method('getQueryBuilder')
            ->will($this->returnValue($qb));
        $command = new GetLuceneHelperCommand('bar');
        $this->assertEquals($helper, $command->execute($backend)->getResult());
    }
}
