<?php

/**
 * Trait for tests requiring mock search Command objects
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use VuFindSearch\Command\AbstractBase as Command;
use VuFindSearch\ParamBag;

/**
 * Trait for tests requiring mock search Command objects
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait MockSearchCommandTrait
{
    /**
     * Get a mock search command.
     *
     * @param ParamBag $params    Parameters for command to return
     * @param mixed    $context   Context for command to return
     * @param string   $backendId Backend ID for command
     * @param mixed    $result    Result to return
     *
     * @return Command
     */
    protected function getMockSearchCommand(
        ParamBag $params = null,
        $context = null,
        string $backendId = 'foo',
        $result = null
    ): Command {
        $command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($params) {
            $command->expects($this->any())->method('getSearchParameters')
                ->will($this->returnValue($params));
        }
        if ($context) {
            $command->expects($this->any())->method('getContext')
                ->will($this->returnValue($context));
        }
        if ($result) {
            $command->expects($this->any())->method('getResult')
                ->will($this->returnValue($result));
        }
        $command->expects($this->any())->method('getTargetIdentifier')
            ->will($this->returnValue($backendId));
        return $command;
    }
}
