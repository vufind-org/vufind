<?php

/**
 * Unit tests for GetUniqueKeyCommand.
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
use VuFindSearch\Command\GetUniqueKeyCommand;

/**
 * Unit tests for GetUniqueKeyCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetUniqueKeyCommandTest extends TestCase
{
    /**
     * Test that an error is thrown for unsupported backends.
     *
     * @return void
     */
    public function testUnsupportedBackend(): void
    {
        $command = new GetUniqueKeyCommand('foo', []);
        $this->expectExceptionMessage('Unsupported backend');
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('foo'));
        $command->execute($backend);
    }

    /**
     * Test that an error is thrown for mismatched backend IDs.
     *
     * @return void
     */
    public function testMismatchedBackendId(): void
    {
        $command = new GetUniqueKeyCommand('foo', []);
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
        $connector = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->disableOriginalConstructor()->getMock();
        $connector->expects($this->once())->method('getUniqueKey')
            ->will($this->returnValue('foo'));
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('bar'));
        $backend->expects($this->once())->method('getConnector')
            ->will($this->returnValue($connector));
        $command = new GetUniqueKeyCommand('bar', []);
        $this->assertEquals('foo', $command->execute($backend)->getResult());
    }
}
