<?php

/**
 * OAI-PMH auth unit test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @category Search
 * @package  Service
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFindTest\OAI;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\OAI\Server\Auth;

/**
 * OAI-PMH auth unit test.
 *
 * @category Search
 * @package  Service
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class AuthTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test an empty input.
     *
     * @return void
     */
    public function testEmptyInput(): void
    {
        $auth = $this->getAuth();
        $this->assertTrue(
            str_contains($auth->getResponse(), '<error code="badVerb">Missing Verb Argument</error>')
        );
    }

    /**
     * Get a auth object.
     *
     * @param array $config Server configuration
     *
     * @return Auth
     */
    protected function getAuth(array $config = []): Auth
    {
        // Force an email into the configuration if missing; this is required by the
        // server.
        if (!isset($config['Site']['email'])) {
            $config['Site']['email'] = 'fake@example.com';
        }

        $auth = new Auth(
            $this->getMockResultsManager(),
            $this->getMockRecordLoader(),
            $this->getMockChangeTracker(),
            $this->getMockResumptionService()
        );
        $auth->setRecordFormatter($this->getMockRecordFormatter());
        return $auth;
    }

    /**
     * Get a mock results manager
     *
     * @return MockObject&\VuFind\Search\Results\PluginManager
     */
    protected function getMockResultsManager(): MockObject&\VuFind\Search\Results\PluginManager
    {
        return $this->createMock(\VuFind\Search\Results\PluginManager::class);
    }

    /**
     * Get a mock record loader
     *
     * @return MockObject&\VuFind\Record\Loader
     */
    protected function getMockRecordLoader(): MockObject&\VuFind\Record\Loader
    {
        return $this->createMock(\VuFind\Record\Loader::class);
    }

    /**
     * Get a mock change tracker service
     *
     * @return MockObject&\VuFind\Db\Service\ChangeTrackerServiceInterface
     */
    protected function getMockChangeTracker(): MockObject&\VuFind\Db\Service\ChangeTrackerServiceInterface
    {
        return $this->createMock(\VuFind\Db\Service\ChangeTrackerServiceInterface::class);
    }

    /**
     * Get a mock record formatter
     *
     * @return MockObject&\VuFindApi\Formatter\RecordFormatter
     */
    protected function getMockRecordFormatter(): MockObject&\VuFindApi\Formatter\RecordFormatter
    {
        return $this->createMock(\VuFindApi\Formatter\RecordFormatter::class);
    }

    /**
     * Get a mock resumption Service
     *
     * @return MockObject&\VuFind\Db\Service\OaiResumptionService
     */
    protected function getMockResumptionService(): MockObject&\VuFind\Db\Service\OaiResumptionService
    {
        return $this->createMock(\VuFind\Db\Service\OaiResumptionService::class);
    }
}
