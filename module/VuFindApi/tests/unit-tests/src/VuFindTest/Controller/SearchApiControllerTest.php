<?php

/**
 * Search api controller test
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\ConfigManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\Db\Service\PluginManager as DbPluginManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\DeveloperSettings\DeveloperSettingsStatus;
use VuFind\Http\PhpEnvironment\Request;
use VuFind\Record\Loader;
use VuFind\RecordDriver\SolrMarc;
use VuFind\Search\Options\PluginManager as SearchPluginManager;
use VuFind\Search\Solr\Options;
use VuFindApi\Controller\SearchApiController;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;
use VuFindTest\Container\MockContainer;

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
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        $config = [
            'API_Keys' => [
                'mode' => DeveloperSettingsStatus::OPTIONAL->value,
                'log_requests' => true,
                'header_field' => 'test-field',
            ],
        ];
        yield 'test keys enabled and provided' => [
            $config,
            [
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
                'headers' => [
                    ['test-field', '999999'],
                ],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        yield 'test keys enabled and provided non-working' => [
            $config,
            [
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
                'headers' => [
                    ['test-field', '51'],
                ],
            ],
            [
                'code' => 401,
                'content' => '{"status":"UNAUTHORIZED","statusMessage":"API key invalid"}',
            ],
        ];
        yield 'test keys enabled and not provided' => [
            $config,
            [
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
            ],
            [
                'code' => 200,
                'content' => '{"resultCount":1,"records":[{"id":"record.1111","title":"hai!"}],"status":"OK"}',
            ],
        ];
        $config['API_Keys']['mode'] = DeveloperSettingsStatus::ENFORCED->value;
        yield 'test keys enforced and provided' => [
            $config,
            [
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
                'headers' => [
                    ['test-field', '999999'],
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
                'queryAndPost' => [
                    'id' => 'record.1111',
                ],
            ],
            [
                'code' => 401,
                'content' => '{"status":"UNAUTHORIZED","statusMessage":"API key missing or invalid"}',
            ],
        ];
    }

    /**
     * Get an instance of a searchApiController
     *
     * @param array $config      Main config
     * @param array $paramsArray Parameters
     *
     * @return MockObject&SearchApiController
     */
    protected function createController(
        array $config = [],
        array $paramsArray = [],
    ): MockObject&SearchApiController {
        $solrOptions = $this->createMock(Options::class);
        $solrOptions->expects($this->any())->method('getAPISettings')->willReturn([]);
        $solrOptions->expects($this->any())->method('getFacetsIni')->willReturn('');
        $optionsPluginManager = $this->createMock(SearchPluginManager::class);
        $optionsPluginManager->expects($this->any())->method('get')->willReturn($solrOptions);
        $apiKeyMode = DeveloperSettingsStatus::fromSetting($config['API_Keys']['mode'] ?? '');
        $apiKeysEnabled = DeveloperSettingsStatus::settingEnabled($apiKeyMode->value);
        $developerSettingsService = $this->createMock(DeveloperSettingsService::class);
        $developerSettingsService->expects($this->any())->method('apiKeysEnabled')->willReturn($apiKeysEnabled);
        $developerSettingsService->expects($this->any())->method('getApiKeyMode')->willReturnCallback(
            fn () => $apiKeyMode
        );
        $developerSettingsService->expects($this->any())->method('isApiKeyAllowed')->willReturnCallback(
            function ($token) use ($apiKeyMode, $apiKeysEnabled) {
                if (!$apiKeysEnabled) {
                    return true;
                }
                if ($apiKeyMode === DeveloperSettingsStatus::ENFORCED) {
                    return $token === '999999';
                }
                return null === $token || $token === '999999';
            }
        );

        $mockRecord = $this->createMock(SolrMarc::class);
        $recordMap = [
            ['record.1111', DEFAULT_SEARCH_BACKEND, false, null, $mockRecord],
        ];

        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->expects($this->any())->method('load')->willReturn($recordMap);
        $recordLoader->expects($this->any())->method('loadBatchForSource')->willReturn($recordMap);

        $resumptionService = $this->getMockBuilder(OaiResumptionServiceInterface::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $dbServiceMap = [
            [OaiResumptionServiceInterface::class, null, $resumptionService],
        ];

        $dbPluginManager = $this->getMockBuilder(DbPluginManager::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $dbPluginManager->expects($this->any())->method('get')->willReturnMap($dbServiceMap);
        $facetFormatter = $this->createMock(FacetFormatter::class);
        $recordFormatter = $this->createMock(RecordFormatter::class);
        $recordFormatter->expects($this->any())->method('getRecordFields')->willReturn([]);
        $recordFormatter->expects($this->any())->method('format')->willReturn([
            [
                'id' => 'record.1111',
                'title' => 'hai!',
            ],
        ]);
        $configManager = $this->getMockBuilder(ConfigManager::class)->disableOriginalConstructor()->getMock();
        $configManager->expects($this->any())->method('getConfigArray')->willReturn($config);

        $container = new MockContainer($this);
        $container->set(SearchPluginManager::class, $optionsPluginManager);
        $container->set(Loader::class, $recordLoader);
        $container->set(DeveloperSettingsService::class, $developerSettingsService);
        $container->set(DbPluginManager::class, $dbPluginManager);
        $container->set(ConfigManagerInterface::class, $configManager);
        $controller = $this->getMockBuilder(SearchApiController::class)
            ->onlyMethods(
                [
                    'getRequest',
                    'disableSessionWrites',
                    'determineOutputMode',
                    'isAccessDenied',
                    'doCursorSearch',
                    'doDefaultSearch',
                    'getConfig',
                    'setResumptionService',
                    'getAllRequestParams',
                    'getHeader',
                ]
            )->setConstructorArgs([$container, $recordFormatter, $facetFormatter])
            ->getMock();
        $controller->expects($this->any())->method('isAccessDenied')->willReturn(false);
        $controller->expects($this->any())->method('getAllRequestParams')->willReturn($paramsArray['queryAndPost']);
        $controller->expects($this->any())->method('getHeader')->willReturnMap($paramsArray['headers'] ?? []);
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
        $controller = $this->createController($config, $requestParams);
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
        $controller = $this->createController($config, $requestParams);
        $result = $controller->searchAction();
        $this->assertEquals($expected['code'], $result->getStatusCode());
        $this->assertEquals($expected['content'], $result->getContent());
    }
}
