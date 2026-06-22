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
use VuFind\ILS\Connection;
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
     * @param ?Connection              $ilsConnection  ILS connection
     * @param bool                     $accessGranted  If access is granted
     *
     * @return SystemStatus
     */
    protected function getHandler(
        ?SessionManager $sessionManager = null,
        ?ResultsManager $resultsManager = null,
        array $config = [],
        ?SessionServiceInterface $sessionService = null,
        ?Connection $ilsConnection = null,
        bool $accessGranted = true
    ): SystemStatus {
        $sessionManager ??= $this->createMock(SessionManager::class);
        $resultsManager ??= $this->createMock(ResultsManager::class);
        $sessionService ??= $this->createMock(SessionServiceInterface::class);
        $ilsConnection ??= $this->createMock(Connection::class);
        $handler = new SystemStatus(
            $sessionManager,
            $resultsManager,
            new Config($config),
            $sessionService,
            $ilsConnection
        );
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
     * Test the AJAX handler's EDS failure response.
     *
     * @return void
     */
    public function testEDSFailure(): void
    {
        $resultsManager = $this->createMock(ResultsManager::class);
        $results = $this->createMock(\VuFind\Search\EDS\Results::class);
        $e = new \Exception('kaboom');
        $results->expects($this->once())->method('performAndProcessSearch')->willThrowException($e);
        $resultsManager->expects($this->once())->method('get')->with('EDS')->willReturn($results);
        $params = $this->createMock(\VuFind\Search\EDS\Params::class);
        $results->expects($this->once())->method('getParams')->willReturn($params);
        $handler = $this->getHandler(resultsManager: $resultsManager);
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0']));
        $this->assertEquals([''], $response);
        // Enable EDS check:
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0', 'eds' => '1']));
        $this->assertEquals(['EDS connection error: kaboom', 500], $response);
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
     * Test the AJAX handler's ILS failure response.
     *
     * @return void
     */
    public function testILSFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $e = new \Exception('kaboom');
        $connection->expects($this->once())->method('getOfflineMode')->willThrowException($e);
        $handler = $this->getHandler(ilsConnection: $connection);
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0']));
        $this->assertEquals([''], $response);
        // Enable ILS check:
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0', 'ils' => '1']));
        $this->assertEquals(['ILS connection error: kaboom', 500], $response);
    }

    /**
     * Test the AJAX handler's successful response.
     *
     * @return void
     */
    public function testSuccessfulResponse(): void
    {
        $sessionManager = $this->createMock(SessionManager::class);
        $sessionManager->expects($this->exactly(2))->method('destroy');
        $resultsManager = $this->createMock(ResultsManager::class);

        $solrResults = $this->createMock(\VuFind\Search\Solr\Results::class);
        $solrResults->expects($this->exactly(2))->method('performAndProcessSearch');
        $solrParams = $this->createMock(\VuFind\Search\Solr\Params::class);
        $solrResults->expects($this->exactly(2))->method('getParams')->willReturn($solrParams);

        $edsResults = $this->createMock(\VuFind\Search\EDS\Results::class);
        $edsResults->expects($this->once())->method('performAndProcessSearch');
        $edsParams = $this->createMock(\VuFind\Search\EDS\Params::class);
        $edsResults->expects($this->once())->method('getParams')->willReturn($edsParams);

        $resultsManager->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(
                fn (string $type) => match ($type) {
                    'Solr' => $solrResults,
                    'EDS' => $edsResults,
                }
            );

        $sessionService = $this->createMock(SessionServiceInterface::class);
        $sessionService->expects($this->exactly(2))->method('getSessionById');
        $handler = $this->getHandler(
            sessionManager: $sessionManager,
            resultsManager: $resultsManager,
            sessionService: $sessionService
        );
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals([''], $response);
        // Enable EDS and ILS check:
        $response = $handler->handleRequest($this->getMockRequestParams(['eds' => '1', 'ils' => '1']));
        $this->assertEquals([''], $response);
    }

    /**
     * Test the AJAX handler's does not check a component if disabled.
     *
     * @return void
     */
    public function testDisabledSettings(): void
    {
        $sessionService = $this->createMock(SessionServiceInterface::class);
        $sessionService->expects($this->never())->method('getSessionById');

        $handler = $this->getHandler(
            sessionService: $sessionService,
            config: ['System' => ['statusChecks' => ['database' => 'always_disabled']]]
        );

        $handler->handleRequest($this->getMockRequestParams());
        $handler->handleRequest($this->getMockRequestParams(['database' => '0']));
        $handler->handleRequest($this->getMockRequestParams(['database' => '1']));
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
