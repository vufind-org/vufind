<?php

/**
 * Unit tests for AutocompleteCommand.
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

namespace VuFindTest\Backend\EDS\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\EDS\Command\AutocompleteCommand;

/**
 * Unit tests for AutocompleteCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class AutocompleteCommandTest extends TestCase
{
    /**
     * Test that the command works as expected
     *
     * @return void
     */
    public function testCommand(): void
    {
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('autocomplete')
            ->with(
                $this->equalTo('foo'),
                $this->equalTo('bar')
            )->will($this->returnValue('result'));  // not a realistic value!
        $command = new AutocompleteCommand($backendId, 'foo', 'bar');
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }
}
