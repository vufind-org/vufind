<?php

/**
 * ExpireSearchesCommand test.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use VuFindConsole\Command\Util\ExpireOaiResumptionCommand;

/**
 * ExpireOaiResumptionCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExpireOaiResumptionCommandTest extends AbstractExpireCommandTest
{
    /**
     * Name of class being tested
     *
     * @var string
     */
    protected $targetClass = ExpireOaiResumptionCommand::class;

    /**
     * Name of a valid service class to test with
     *
     * @var string
     */
    protected $validServiceClass = \VuFind\Db\Service\OaiResumptionService::class;

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'resumption tokens';

    /**
     * Age parameter to use when testing illegal age input.
     *
     * @var float
     */
    protected $illegalAge = -1.0;

    /**
     * Expected threshold.
     *
     * @var float
     */
    protected $expectedThreshold = 0.0;

    /**
     * Expected minimum age in error message or null if not applicable.
     *
     * @var ?float
     */
    protected $expectedMinAge = null;
}
