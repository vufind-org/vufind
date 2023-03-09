<?php

/**
 * ExpireSessionsCommand test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\Util;

use VuFindConsole\Command\Util\ExpireSessionsCommand;

/**
 * ExpireSessionsCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExpireSessionsCommandTest extends AbstractExpireCommandTest
{
    /**
     * Name of class being tested
     *
     * @var string
     */
    protected $targetClass = ExpireSessionsCommand::class;

    /**
     * Name of a valid table class to test with
     *
     * @var string
     */
    protected $validTableClass = \VuFind\Db\Table\Session::class;

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'sessions';

    /**
     * Age parameter to use when testing illegal age input.
     *
     * @var int
     */
    protected $illegalAge = 0.01;

    /**
     * Expected minimum age in error message.
     *
     * @var int
     */
    protected $expectedMinAge = 0.1;
}
