<?php

/**
 * Unit tests for LookupIssnsCommand.
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

namespace VuFindTest\Backend\BrowZine\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\BrowZine\Command\LookupIssnsCommand;

/**
 * Unit tests for LookupIssnsCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LookupIssnsCommandTest extends TestCase
{
    /**
     * Test that a supported backend behaves as expected.
     *
     * @return void
     */
    public function testSupportedBackend(): void
    {
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\BrowZine\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue('BrowZine'));
        $backend->expects($this->once())->method('lookupIssns')
            ->with($this->equalTo(['1111-1111']))
            ->will($this->returnValue('foo'));
        $command = new LookupIssnsCommand('BrowZine', ['1111-1111']);
        $this->assertEquals('foo', $command->execute($backend)->getResult());
    }
}
