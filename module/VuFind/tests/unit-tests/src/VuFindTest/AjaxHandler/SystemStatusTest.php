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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\AjaxHandler\SystemStatus;

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
     * Test the AJAX handler's "health check file" response
     *
     * @return void
     */
    public function testHealthCheckFile(): void
    {
        $sessionManager = $this->createMock(\Laminas\Session\SessionManager::class);
        $resultsManager = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $config = new \Laminas\Config\Config(['System' => ['healthCheckFile' => __FILE__]]);
        $sessionService = $this->createMock(\VuFind\Db\Service\SessionServiceInterface::class);
        $handler = new SystemStatus($sessionManager, $resultsManager, $config, $sessionService);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals(['Health check file exists', 503], $response);
    }

    /**
     * Test the AJAX handler's Solr failure response
     *
     * @return void
     */
    public function testSolrFailure(): void
    {
        $sessionManager = $this->createMock(\Laminas\Session\SessionManager::class);
        $resultsManager = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $results = $this->createMock(\VuFind\Search\Solr\Results::class);
        $e = new \Exception('kaboom');
        $results->expects($this->once())->method('performAndProcessSearch')->willThrowException($e);
        $resultsManager->expects($this->once())->method('get')->with($this->equalTo('Solr'))->willReturn($results);
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $results->expects($this->once())->method('getParams')->willReturn($params);
        $config = new \Laminas\Config\Config([]);
        $sessionService = $this->createMock(\VuFind\Db\Service\SessionServiceInterface::class);
        $handler = new SystemStatus($sessionManager, $resultsManager, $config, $sessionService);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals(['Search index error: kaboom', 500], $response);
        // Disable index check:
        $response = $handler->handleRequest($this->getMockRequestParams(['index' => '0']));
        $this->assertEquals([''], $response);
    }

    /**
     * Test the AJAX handler's database failure response
     *
     * @return void
     */
    public function testDatabaseFailure(): void
    {
        $sessionManager = $this->createMock(\Laminas\Session\SessionManager::class);
        $resultsManager = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $results = $this->createMock(\VuFind\Search\Solr\Results::class);
        $results->expects($this->exactly(2))->method('performAndProcessSearch');
        $resultsManager->expects($this->exactly(2))->method('get')->with($this->equalTo('Solr'))->willReturn($results);
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $results->expects($this->exactly(2))->method('getParams')->willReturn($params);
        $config = new \Laminas\Config\Config([]);
        $sessionService = $this->createMock(\VuFind\Db\Service\SessionServiceInterface::class);
        $e = new \Exception('kaboom');
        $sessionService->expects($this->once())->method('getSessionById')->willThrowException($e);
        $handler = new SystemStatus($sessionManager, $resultsManager, $config, $sessionService);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals(['Database error: kaboom', 500], $response);
        // Disable database check:
        $response = $handler->handleRequest($this->getMockRequestParams(['database' => '0']));
        $this->assertEquals([''], $response);
    }

    /**
     * Test the AJAX handler's successful response
     *
     * @return void
     */
    public function testSuccessfulResponse(): void
    {
        $sessionManager = $this->createMock(\Laminas\Session\SessionManager::class);
        $sessionManager->expects($this->once())->method('destroy');
        $resultsManager = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $results = $this->createMock(\VuFind\Search\Solr\Results::class);
        $results->expects($this->once())->method('performAndProcessSearch');
        $resultsManager->expects($this->once())->method('get')->with($this->equalTo('Solr'))->willReturn($results);
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $results->expects($this->once())->method('getParams')->willReturn($params);
        $config = new \Laminas\Config\Config([]);
        $sessionService = $this->createMock(\VuFind\Db\Service\SessionServiceInterface::class);
        $sessionService->expects($this->once())->method('getSessionById');
        $handler = new SystemStatus($sessionManager, $resultsManager, $config, $sessionService);
        $response = $handler->handleRequest($this->getMockRequestParams());
        $this->assertEquals([''], $response);
    }

    /**
     * Get mock Params class for request params
     *
     * @param array $requestParams Parameters to return
     *
     * @return MockObject&Params
     */
    protected function getMockRequestParams(array $requestParams = []): Params
    {
        $params = $this->getMockBuilder(Params::class)->getMock();
        $params->expects($this->any())
            ->method('fromQuery')
            ->willReturnCallback(
                function ($param, $default = null) use ($requestParams) {
                    return $requestParams[$param] ?? $default;
                }
            );
        return $params;
    }
}
