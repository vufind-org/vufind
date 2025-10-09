<?php

/**
 * ExpireAuditEventsCommand test.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Util;

use VuFind\Db\Service\AuditEventService;
use VuFindConsole\Command\Util\ExpireAuditEventsCommand;

/**
 * ExpireAuditEventsCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExpireAuditEventsCommandTest extends AbstractExpireCommandTest
{
    /**
     * Name of class being tested
     *
     * @var string
     */
    protected $targetClass = ExpireAuditEventsCommand::class;

    /**
     * Name of a valid service class to test with
     *
     * @var string
     */
    protected $validServiceClass = AuditEventService::class;

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'audit events';

    /**
     * Age parameter to use when testing illegal age input.
     *
     * @var float
     */
    protected $illegalAge = 0.9;

    /**
     * Expected minimum age in error message or null if not applicable.
     *
     * @var ?float
     */
    protected $expectedMinAge = 1.0;

    /**
     * Expected threshold.
     *
     * @var float
     */
    protected $expectedThreshold = 365.0;
}
