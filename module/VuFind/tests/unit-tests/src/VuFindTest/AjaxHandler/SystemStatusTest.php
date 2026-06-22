<?php

/**
 * SystemStatus test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\SessionManager;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\AjaxHandler\SystemStatus;
use VuFind\Config\Config;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * SystemStatus test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SystemStatusTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get SystemStatus Ajax handler.
     *
     * @param ?SessionManager          $sessionManager Session manager
     * @param ?ResultsManager          $resultsManager Results plugin manager
     * @param array                    $config         Config
     * @param ?SessionServiceInterface $sessionService Session service
     * @param bool                     $accessGranted  If access is granted
     *
     * @return SystemStatus
     */
    protected function getHandler(
        ?SessionManager $sessionManager = null,
        ?ResultsManager $resultsManager = null,
        array $config = [],
        ?SessionServiceInterface $sessionService = null,
        bool $accessGranted = true
    ): SystemStatus {
        $sessionManager ??= $this->createMock(SessionManager::class);
        $resultsManager ??= $this->createMock(ResultsManager::class);
        $sessionService ??= $this->createMock(SessionServiceInterface::class);
        $handler = new SystemStatus($sessionManager, $resultsManager, new Config($config), $sessionService);
        $mockAuth = $this->createMock(AuthorizationService::class);
        $mockAuth->method('isGranted')
            ->with('access.SystemStatus')
            ->willReturn($accessGranted);
        $handler->setAuthorizationService($mockAuth);
        return $handler;
    }

    /**
     * Test the AJAX handler's response if access is denied.
     *
     * @return void
     */
    public function testAccessDenied(): void
    {
        $this->expectException(\VuFind\Exception\Forbidden::class);
        $this->expectExceptionMessage('Access denied');
        $handler = $this->getHandler(accessGranted: false);
        $handler->handleRequest($this->getMockRequestParams());
    }

    /**
     * Test the AJAX handler's "health check file" response.
     *
     * @return void
     */
    public function testHealthCheckFile(): void
    {
        $config = ['System' => ['healthCheckFile' => __FILE__]];
        $handler = $this->getHandler(config: $config);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals(['Health check file exists', 503], $response);
    }

    /**
     * Test the AJAX handler's Solr failure response.
     *
     * @return void
     */
    public function testSolrFailure(): void
    {
        $resultsManager = $this->createMock(ResultsManager::class);
        $results = $this->createMock(\VuFind\Search\Solr\Results::class);
        $e = new \Exception('kaboom');
        $results->expects($this->once())->method('performAndProcessSearch')->willThrowException($e);
        $resultsManager->expects($this->once())->method('get')->with('Solr')->willReturn($results);
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $results->expects($this->once())->method('getParams')->willReturn($params);
        $handler = $this->getHandler(resultsManager: $resultsManager);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals(['Search index error: kaboom', 500], $response);
        // Disable index check:
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0']));
        $this->assertEquals([''], $response);
    }

    /**
     * Test the AJAX handler's database failure response.
     *
     * @return void
     */
    public function testDatabaseFailure(): void
    {
        $sessionService = $this->createMock(SessionServiceInterface::class);
        $e = new \Exception('kaboom');
        $sessionService->expects($this->once())->method('getSessionById')->willThrowException($e);
        $handler = $this->getHandler(sessionService: $sessionService);
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0']));
        $this->assertEquals(['Database error: kaboom', 500], $response);
        // Disable database check:
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0', 'database' => '0']));
        $this->assertEquals([''], $response);
    }

    /**
     * Test the AJAX handler's successful response.
     *
     * @return void
     */
    public function testSuccessfulResponse(): void
    {
        $sessionManager = $this->createMock(SessionManager::class);
        $sessionManager->expects($this->once())->method('destroy');
        $resultsManager = $this->createMock(ResultsManager::class);

        $results = $this->createMock(\VuFind\Search\Solr\Results::class);
        $results->expects($this->once())->method('performAndProcessSearch');
        $resultsManager->expects($this->once())->method('get')->with('Solr')->willReturn($results);
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $results->expects($this->once())->method('getParams')->willReturn($params);

        $sessionService = $this->createMock(SessionServiceInterface::class);
        $sessionService->expects($this->once())->method('getSessionById');
        $handler = $this->getHandler(
            sessionManager: $sessionManager,
            resultsManager: $resultsManager,
            sessionService: $sessionService
        );
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals([''], $response);
    }

    /**
     * Get mock Params class for request params.
     *
     * @param array $requestParams Parameters to return
     *
     * @return MockObject&Params
     */
    protected function getMockRequestParams(array $requestParams = []): Params
    {
        $params = $this->createMock(Params::class);
        $params->method('fromQuery')
            ->willReturnCallback(
                function ($param, $default = null) use ($requestParams) {
                    return $requestParams[$param] ?? $default;
                }
            );
        return $params;
    }
}
