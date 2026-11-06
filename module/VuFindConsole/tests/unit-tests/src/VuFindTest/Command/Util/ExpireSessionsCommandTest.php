<?php

/**
 * ExpireSessionsCommand test.
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

use VuFind\Session\SessionRelatedTablesTrait;
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
class ExpireSessionsCommandTest extends AbstractExpireCommandTestCase
{
    use SessionRelatedTablesTrait;

    /**
     * Name of class being tested.
     *
     * @var string
     */
    protected $targetClass = ExpireSessionsCommand::class;

    /**
     * Name of a valid service class to test with.
     *
     * @var string
     */
    protected $validServiceClass = \VuFind\Db\Service\SessionService::class;

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'sessions';

    /**
     * Age parameter to use when testing illegal age input.
     *
     * @var float
     */
    protected $illegalAge = 0.01;

    /**
     * Expected minimum age in error message or null if not applicable.
     *
     * @var ?float
     */
    protected $expectedMinAge = 0.1;

    /**
     * Get extra expected lines.
     *
     * @return array
     */
    protected function getExtraExpectedLines(): array
    {
        $lines = [];
        foreach (array_keys($this->getSessionDatabaseServices()) as $serviceName) {
            $lines[] = "Total 0 {$serviceName} deleted.";
        }
        return $lines;
    }

    /**
     * Get the command constructor arguments.
     *
     * @return array
     */
    protected function getExtraCommandConstructorArgs(): array
    {

        $serviceManager = new \VuFindTest\Container\MockDbServicePluginManager($this);
        foreach ($this->getSessionDatabaseServices() as $databaseService) {
            $serviceMock = $this->createMock($databaseService);
            $serviceMock
                ->method('deleteRowsWithoutSession');
            $serviceManager->set($databaseService, $serviceMock);
        }

        return [$serviceManager];
    }
}
