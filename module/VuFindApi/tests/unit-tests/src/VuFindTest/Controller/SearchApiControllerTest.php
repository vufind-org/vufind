<?php

/**
 * Search api controller test
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Controller;

use Generator;
use Laminas\Http\Headers;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\ApiKey\ApiKeyService;
use VuFind\Config\Config;
use VuFind\Config\ConfigManager;
use VuFind\Db\Service\OaiResumptionService;
use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\Db\Service\PluginManager as DbPluginManager;
use VuFind\Http\PhpEnvironment\Request;
use VuFind\Log\Logger;
use VuFind\Record\Loader;
use VuFind\RecordDriver\SolrMarc;
use VuFind\Search\Options\PluginManager as SearchPluginManager;
use VuFind\Search\Solr\Options;
use VuFindApi\Controller\SearchApiController;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Search api controller tests
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchApiControllerTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Data provider for testApiKeys functions
     *
     * @return Generator
     */
    public static function getTestApiKeysData(): Generator
    {
        yield 'test keys disabled' => [
            [],
            [
                'query' => [
                    'id' => 'record.1111',
                ],
                'post' => [],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        $config = [
            'API_Keys' => [
                'mode' => 'enabled',
                'log_requests' => true,
                'header_field' => 'test-field',
            ],
        ];
        yield 'test keys enabled and provided' => [
            $config,
            [
                'query' => [
                    'id' => 'record.1111',
                ],
                'post' => [],
                'headers' => [
                    'test-field' => '999999',
                ],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        yield 'test keys enabled and not provided' => [
            $config,
            [
                'query' => [
                    'id' => 'record.1111',
                ],
                'post' => [],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        $config['API_Keys']['mode'] = 'enforced';
        yield 'test keys enforced and provided' => [
            $config,
            [
                'query' => [
                    'id' => 'record.1111',
                ],
                'post' => [],
                'headers' => [
                    'test-field' => '999999',
                ],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        yield 'test keys enforced and not provided' => [
            $config,
            [
                'query' => [
                    'id' => 'record.1111',
                ],
                'post' => [],
            ],
            [
                'code' => 401,
                'content' => 'Provided API key is missing or invalid.',
            ],
        ];
    }

    /**
     * Create a mocked request object.
     *
     * @param array $requestParams Array containing request params.
     *
     * @return MockObject&Request
     */
    protected function createRequest(array $requestParams = []): MockObject&Request
    {
        $request = $this->getMockBuilder(Request::class)->onlyMethods([])
            ->disableOriginalConstructor()->getMock();
        $headers = new Headers();
        $headers->addHeaders($requestParams['headers'] ?? []);
        $request->setHeaders($headers);
        $queryParams = new Parameters($requestParams['query'] ?? []);
        $postParams = new Parameters($requestParams['post'] ?? []);
        $request->setQuery($queryParams);
        $request->setPost($postParams);
        return $request;
    }

    /**
     * Get an instance of a searchApiController
     *
     * @param array       $config  Main config
     * @param ?MockObject $request Request object
     *
     * @return MockObject&SearchApiController
     */
    protected function createController(array $config = [], ?MockObject $request = null): MockObject&SearchApiController
    {
        $configurationMap = [
            ['config', null, new Config($config)],
            ['facets', null, new Config([])],
        ];

        $configPluginManager = $this->getMockBuilder(ConfigManager::class)->disableOriginalConstructor()
            ->onlyMethods(['getConfigObject'])->getMock();
        $configPluginManager->expects($this->any())->method('getConfigObject')->willReturnMap($configurationMap);

        $solrOptions = $this->getMockBuilder(Options::class)->disableOriginalConstructor()
            ->onlyMethods(['getAPISettings'])->getMock();
        $solrOptions->expects($this->any())->method('getAPISettings')->willReturn([]);
        $optionsMap = [
            ['Solr', null, $solrOptions],
        ];
        $optionsPluginManager = $this->getMockBuilder(SearchPluginManager::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $optionsPluginManager->expects($this->any())->method('get')->willReturnMap($optionsMap);

        $resumptionService = $this->getMockBuilder(OaiResumptionService::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();

        $apiKeyService = $this->getMockBuilder(ApiKeyService::class)->disableOriginalConstructor()
            ->onlyMethods(['isTokenValid'])->getMock();
        $apiKeyService->expects($this->any())->method('isTokenValid')->willReturn(true);
        $dbServiceMap = [
            [OaiResumptionServiceInterface::class, null, $resumptionService],
        ];

        $dbPluginManager = $this->getMockBuilder(DbPluginManager::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $dbPluginManager->expects($this->any())->method('get')->willReturnMap($dbServiceMap);

        $mockRecord = $this->getMockBuilder(SolrMarc::class)->disableOriginalConstructor()->getMock();
        $recordMap = [
            ['record.1111', DEFAULT_SEARCH_BACKEND, false, null, $mockRecord],
        ];

        $recordLoader = $this->getMockBuilder(Loader::class)->disableOriginalConstructor()
            ->onlyMethods(['load'])->getMock();
        $recordLoader->expects($this->any())->method('load')->willReturn($recordMap);

        $dbPluginManager->expects($this->any())->method('get')->willReturnMap($dbServiceMap);
        $logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()
            ->onlyMethods(['debug'])->getMock();
        $this->setProperty($logger, 'writers', []);
        $serviceLocatorMap = [
            [ConfigManager::class, $configPluginManager],
            [SearchPluginManager::class, $optionsPluginManager],
            [DbPluginManager::class, $dbPluginManager],
            [Loader::class, $recordLoader],
            [ApiKeyService::class, $apiKeyService],
            [Logger::class, $logger],
        ];

        $serviceLocator = $this->getMockBuilder(ServiceManager::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $serviceLocator->expects($this->any())->method('get')->willReturnMap($serviceLocatorMap);

        $recordFormatter = $this->getMockBuilder(RecordFormatter::class)->disableOriginalConstructor()
            ->onlyMethods(['getRecordFields', 'format'])->getMock();
        $recordFormatter->expects($this->any())->method('getRecordFields')->willReturn([]);
        $recordFormatter->expects($this->any())->method('format')->willReturn([
            [
                'id' => 'record.1111',
                'title' => 'hai!',
            ],
        ]);

        $facetFormatter = $this->getMockBuilder(FacetFormatter::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();

        $controller = $this->getMockBuilder(SearchApiController::class)->setConstructorArgs([
            $serviceLocator,
            $recordFormatter,
            $facetFormatter,
        ])->onlyMethods(
            [
                'getRequest',
                'disableSessionWrites',
                'determineOutputMode',
                'isAccessDenied',
                'doCursorSearch',
                'doDefaultSearch',
            ]
        )->getMock();
        $request ??= $this->getMockBuilder(Request::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $controller->expects($this->any())->method('getRequest')->willReturn($request);
        $controller->expects($this->any())->method('isAccessDenied')->willReturn(false);
        $searchResponse = [
            'resultCount' => 1,
            'records' => [
                ['id' => 'record.1111', 'title' => 'hai!'],
            ],
        ];
        $controller->expects($this->any())->method('doDefaultSearch')->willReturn($searchResponse);
        return $controller;
    }

    /**
     * Test API Keys record
     *
     * @param array $config        Main config
     * @param array $requestParams Users request as params array
     * @param array $expected      Expected results
     *
     * @return       void
     * @dataProvider getTestApiKeysData
     */
    public function testApiKeysRecord(array $config, array $requestParams, array $expected): void
    {
        $request = $this->createRequest($requestParams);
        $controller = $this->createController($config, $request);
        $result = $controller->recordAction();
        $this->assertEquals($expected['code'], $result->getStatusCode());
        $this->assertEquals($expected['content'], $result->getContent());
    }

    /**
     * Test API Keys search
     *
     * @param array $config        Main config
     * @param array $requestParams Users request as params array
     * @param array $expected      Expected results
     *
     * @return       void
     * @dataProvider getTestApiKeysData
     */
    public function testApiKeysSearch(array $config, array $requestParams, array $expected): void
    {
        $request = $this->createRequest($requestParams);
        $controller = $this->createController($config, $request);
        $result = $controller->searchAction();
        $this->assertEquals($expected['code'], $result->getStatusCode());
        $this->assertEquals($expected['content'], $result->getContent());
    }
}
