<?php

/**
 * Search and record API actions test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Action;

use Generator;
use GuzzleHttp\Psr7\Response;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Config\ConfigManager;
use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\Db\Service\PluginManager as DbPluginManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\DeveloperSettings\DeveloperSettingsStatus;
use VuFind\Record\Loader;
use VuFind\RecordDriver\SolrMarc;
use VuFind\Search\Base\Results;
use VuFind\Search\Options\PluginManager as SearchPluginManager;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Options;
use VuFindApi\Action\SearchApi\RecordAction;
use VuFindApi\Action\SearchApi\SearchAction;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;

/**
 * Search and record API actions test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchAndRecordActionsTest extends AbstractActionTestCase
{
    /**
     * Data provider for testApiKeys functions.
     *
     * @return Generator
     */
    public static function getTestApiKeysData(): Generator
    {
        yield 'test keys disabled' => [
            [],
            [
                'queryParams' => [
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
                'queryParams' => [
                    'id' => 'record.1111',
                ],
                'headers' => [
                    'test-field' => '999999',
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
                'queryParams' => [
                    'id' => 'record.1111',
                ],
                'headers' => [
                    'test-field' => '51',
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
                'queryParams' => [
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
                'queryParams' => [
                    'id' => 'record.1111',
                ],
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
                'queryParams' => [
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
     * Get an instance of an action class.
     *
     * @param bool  $recordAction Create record action instead of search action?
     * @param array $config       Main config
     *
     * @return SearchAction|RecordAction
     */
    protected function createAction(
        bool $recordAction,
        array $config = [],
    ): SearchAction|RecordAction {
        $solrOptions = $this->createMock(Options::class);
        $solrOptions->method('getAPISettings')->willReturn([]);
        $solrOptions->method('getFacetsIni')->willReturn('');
        $optionsPluginManager = $this->createMock(SearchPluginManager::class);
        $optionsPluginManager->method('get')->willReturn($solrOptions);
        $apiKeyMode = DeveloperSettingsStatus::fromSetting($config['API_Keys']['mode'] ?? '');
        $apiKeysEnabled = DeveloperSettingsStatus::settingEnabled($apiKeyMode->value);
        $developerSettingsService = $this->createMock(DeveloperSettingsService::class);
        $developerSettingsService->method('apiKeysEnabled')->willReturn($apiKeysEnabled);
        $developerSettingsService->method('getApiKeyMode')->willReturnCallback(
            fn () => $apiKeyMode
        );
        $developerSettingsService->method('isApiKeyAllowed')->willReturnCallback(
            function (?string $token) use ($apiKeyMode, $apiKeysEnabled): bool {
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
        $recordLoader->method('load')->willReturn($recordMap);
        $recordLoader->method('loadBatchForSource')->willReturn($recordMap);

        $resumptionService = $this->getMockBuilder(OaiResumptionServiceInterface::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $dbServiceMap = [
            [OaiResumptionServiceInterface::class, null, $resumptionService],
        ];

        $dbPluginManager = $this->getMockBuilder(DbPluginManager::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $dbPluginManager->method('get')->willReturnMap($dbServiceMap);
        $recordFormatter = $this->createMock(RecordFormatter::class);
        $recordFormatter->method('format')->willReturn([
            [
                'id' => 'record.1111',
                'title' => 'hai!',
            ],
        ]);
        $configManager = $this->createMock(ConfigManager::class);
        $configManager->method('getConfigArray')->willReturn($config);

        $authorizationService = $this->createMock(AuthorizationService::class);

        if ($recordAction) {
            $action = new RecordAction(
                $authorizationService,
                $developerSettingsService,
                $config,
                $recordFormatter,
                $configManager,
                $optionsPluginManager,
                $recordLoader
            );
        } else {
            $facetFormatter = $this->createMock(FacetFormatter::class);
            $resultsPluginManager = $this->createMock(ResultsPluginManager::class);
            $results = $this->createMock(Results::class);
            $results->method('getResults')
                ->willReturn([$mockRecord]);
            $results->method('getResultTotal')
                ->willReturn(1);
            $searchRunner = $this->createMock(SearchRunner::class);
            $searchRunner->method('run')
                ->willReturn($results);
            $hierarchicalFacetHelper = $this->createMock(HierarchicalFacetHelper::class);

            $action = new SearchAction(
                $authorizationService,
                $developerSettingsService,
                $config,
                $recordFormatter,
                $configManager,
                $optionsPluginManager,
                $facetFormatter,
                $resultsPluginManager,
                $searchRunner,
                $resumptionService,
                $hierarchicalFacetHelper
            );
        }

        $helpers = [
            PermissionHelper::class => $this->createMock(PermissionHelper::class),
            ResponseHelper::class => new ResponseHelper(),
        ];
        $this->initializeAction($action, $helpers);

        $action->setBackendId('Solr');

        return $action;
    }

    /**
     * Test record endpoint.
     *
     * @param array $config        Main config
     * @param array $requestParams Users request as params array
     * @param array $expected      Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApiKeysData')]
    public function testRecord(array $config, array $requestParams, array $expected): void
    {
        $this->doTest(true, $config, $requestParams, $expected);
    }

    /**
     * Test search endpoint.
     *
     * @param array $config        Main config
     * @param array $requestParams Users request as params array
     * @param array $expected      Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApiKeysData')]
    public function testSearch(array $config, array $requestParams, array $expected): void
    {
        $this->doTest(false, $config, $requestParams, $expected);
    }

    /**
     * Do test action.
     *
     * @param bool  $recordAction  Create record action instead of search action?
     * @param array $config        Main config
     * @param array $requestParams Users request as params array
     * @param array $expected      Expected results
     *
     * @return void
     */
    public function doTest(bool $recordAction, array $config, array $requestParams, array $expected): void
    {
        $action = $this->createAction($recordAction, $config);
        $request = $this->getServerRequest(
            queryParams: $requestParams['queryParams'] ?? [],
            headers: $requestParams['headers'] ?? []
        );
        $response = (new Response())->withStatus(200);
        $result = $action($request, $response);
        $this->assertEquals($expected['code'], $result->getStatusCode());
        $body = $result->getBody();
        $body->rewind();
        $this->assertEquals($expected['content'], $body->getContents());
    }
}
