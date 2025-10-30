<?php

/**
 * BrowZine Test Class
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

namespace VuFindTest\IdentifierLinker;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\IdentifierLinker\BrowZine;
use VuFind\IdentifierLinker\BrowZineFactory;
use VuFind\Search\BackendManager;
use VuFindSearch\Backend\BrowZine\Connector;

/**
 * BrowZine Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BrowZineTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\SearchServiceTrait;

    /**
     * Given a connector, wrap it up in a backend and backend manager
     *
     * @param Connector $connector Connector
     *
     * @return BackendManager
     */
    protected function getBackendManager(Connector $connector): BackendManager
    {
        $backend = new \VuFindSearch\Backend\BrowZine\Backend($connector);
        $registry = new \VuFindTest\Container\MockContainer($this);
        $registry->set('BrowZine', $backend);
        return new BackendManager($registry);
    }

    /**
     * Get a mock connector
     *
     * @param array $ids      IDs expected by connector
     * @param array $response Response for connector to return
     *
     * @return Connector
     */
    protected function getMockConnector(array $ids, array $response): MockObject&Connector
    {
        $connector = $this->createMock(Connector::class);
        if (isset($ids['doi'])) {
            $connector->expects($this->once())
                ->method('lookupDoi')
                ->with($this->equalTo($ids['doi']))
                ->willReturn($response);
        }
        if (isset($ids['issn'])) {
            $connector->expects($this->once())
                ->method('lookupIssns')
                ->with($this->equalTo($ids['issn']))
                ->willReturn($response);
        }
        return $connector;
    }

    /**
     * Data provider for testDOIApiSuccess()
     *
     * @return array[]
     */
    public static function doiProvider(): array
    {
        return [
            'unfiltered' => [
                [],
                [],
                [],
                [
                    0 => [
                        [
                            'link' => 'https://weblink',
                            'label' => 'View Complete Issue',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-open-book-icon.svg',
                        ],
                        [
                            'link' => 'https://fulltext',
                            'label' => 'PDF Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                        ],
                    ],
                ],
            ],
            'exclude filter' => [
                ['filterType' => 'exclude', 'filter' => ['browzineWebLink']],
                [],
                [],
                [
                    0 => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'PDF Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                        ],
                    ],
                ],
            ],
            'include filter' => [
                ['filterType' => 'include', 'filter' => ['browzineWebLink']],
                [],
                [],
                [
                    0 => [
                        [
                            'link' => 'https://weblink',
                            'label' => 'View Complete Issue',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-open-book-icon.svg',
                        ],
                    ],
                ],
            ],
            'best integrator link with no section in config' => [
                [],
                ['bestIntegratorLink' => 'Get full text|browzine-best'],
                null,
                [
                    0 => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'PDF Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                        ],
                    ],
                ],
            ],
            'best integrator link with empty config section' => [
                [],
                ['bestIntegratorLink' => 'Get full text|browzine-best'],
                [],
                [
                    0 => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'Get full text',
                            'localIcon' => 'browzine-best',
                        ],
                    ],
                ],
            ],
            'best integrator link with configured label' => [
                [],
                ['bestIntegratorLink' => 'Get full text|browzine-best'],
                ['fullTextFile' =>
                    'Fancy Full Text|browzine-pdf|' .
                    'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg'],
                [
                    0 => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'Fancy Full Text',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                        ],
                    ],
                ],
            ],
            'best integrator link with browzine label override' => [
                ['useBrowzineLabel' => true],
                ['bestIntegratorLink' => 'Get full text|browzine-best'],
                ['fullTextFile' =>
                    'PDF Full Text|browzine-pdf|' .
                    'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg'],
                [
                    0 => [
                        [
                            'link' => 'https://fulltext',
                            'label' => 'Download Best PDF Ever',
                            'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-pdf-download-icon.svg',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the BrowZine handler to test.
     *
     * @param array  $ids                       ID test data
     * @param array  $rawData                   Raw data for connector to return
     * @param array  $identifierLinksConfig     BrowZine configuration for identifier links
     * @param ?array $doiServicesConfig         BrowZine configuration for DOI services
     * @param ?array $bestIntegratorLinksConfig BrowZine configuration for bestIntegratorLinks
     *
     * @return BrowZine
     */
    protected function getBrowZineHandler(
        array $ids,
        array $rawData,
        array $identifierLinksConfig = [],
        ?array $doiServicesConfig = null,
        ?array $bestIntegratorLinksConfig = null
    ): BrowZine {
        $connector = $this->getMockConnector($ids[0], $rawData);
        $ss = $this->getSearchService($this->getBackendManager($connector));

        // Use the factory to build the test object so that the correct default configs are
        // injected. We'll use a mock container to set up all the dependencies.
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(\VuFindSearch\Service::class, $ss);
        $configArray = ['IdentifierLinks' => $identifierLinksConfig];
        if ($doiServicesConfig) {
            $configArray['DOIServices'] = $doiServicesConfig;
        }
        if ($bestIntegratorLinksConfig !== null) {
            $configArray['BestIntegratorLinks'] = $bestIntegratorLinksConfig;
        }
        $mockConfigManager = $this->createMock(\VuFind\Config\ConfigManagerInterface::class);
        $mockConfigManager->expects($this->once())
            ->method('getConfigArray')
            ->with('BrowZine')
            ->willReturn($configArray);
        $container->set(\VuFind\Config\ConfigManagerInterface::class, $mockConfigManager);
        $factory = new BrowZineFactory();
        return $factory($container, BrowZine::class);
    }

    /**
     * Test a DOI API response.
     *
     * @param array $identifierLinksConfig     BrowZine configuration for identifier links
     * @param array $doiServicesConfig         BrowZine configuration for DOI services
     * @param array $bestIntegratorLinksConfig BrowZine configuration for bestIntegratorLinks
     * @param array $expectedResponse          Expected response
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('doiProvider')]
    public function testDOIApiSuccess(
        array $identifierLinksConfig,
        array $doiServicesConfig,
        ?array $bestIntegratorLinksConfig,
        array $expectedResponse
    ): void {
        $rawData = $this->getJsonFixture('browzine/doi.json');
        $ids = [['doi' => '10.1155/2020/8690540']];
        $browzine = $this->getBrowZineHandler(
            $ids,
            $rawData,
            $identifierLinksConfig,
            $doiServicesConfig,
            $bestIntegratorLinksConfig
        );
        foreach ($expectedResponse[0] as & $current) {
            $current['data'] = $rawData['data'];
        }
        unset($current);
        $this->assertEquals($expectedResponse, $browzine->getLinks($ids));
    }

    /**
     * Test an ISSN API response.
     *
     * @return void
     */
    public function testISSNApiSuccess(): void
    {
        $rawData = $this->getJsonFixture('browzine/issn.json');
        $ids = [['issn' => '0006-2952']];
        $browzine = $this->getBrowZineHandler($ids, $rawData);
        $this->assertEquals(
            [
                0 => [
                    [
                        'link' => 'https://weblink',
                        'label' => 'Browse Available Issues',
                        'data' => $rawData['data'][0],
                        'icon' => 'https://assets.thirdiron.com/images/integrations/browzine-open-book-icon.svg',
                    ],
                ],
            ],
            $browzine->getLinks($ids)
        );
    }
}
