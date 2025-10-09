<?php

/**
 * Databases Test Class
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Laminas\Cache\Storage\StorageInterface as CacheAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Recommend\Databases;
use VuFind\Search\EDS\Results;

/**
 * Databases Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabasesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test forgetting the Databases config section.
     *
     * @return void
     */
    public function testEmptyConfig(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("must have section 'Databases'");

        $configData = [];
        $this->buildModuleAndProcessResults($configData);
    }

    /**
     * Test a default config.
     *
     * @return void
     */
    public function testNormal(): void
    {
        $configData = $this->mockConfigData();
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(3, $databases);
        $this->assertArrayHasKey('http://thepast.com', $databases);
        $this->assertArrayNotHasKey('http://fridakahlo.com', $databases);
    }

    /**
     * Test setting useQuery to false.
     *
     * @return void
     */
    public function testDontUseQuery(): void
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useQuery'] = false;
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(2, $databases);
        $this->assertArrayNotHasKey('http://thepast.com', $databases);
    }

    /**
     * Test setting useLibGuides to true.
     *
     * @return void
     */
    public function testUseLibGuides(): void
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useLibGuides'] = true;
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(4, $databases);
        $this->assertArrayHasKey('http://fridakahlo.com', $databases);
    }

    /**
     * Test using LibGuides with a query that matches an alternate name.
     *
     * @return void
     */
    public function testUseLibGuidesWithAlternateName(): void
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useLibGuides'] = true;
        $module = $this->buildModuleAndProcessResults($configData, 'Geometry');

        $databases = $module->getResults();
        $this->assertCount(4, $databases);
        $this->assertArrayHasKey('http://primenumbers.com', $databases);
    }

    /**
     * Test using LibGuides with a query that matches an alternate name,
     * but that config disabled.
     *
     * @return void
     */
    public function testUseLibGuidesWithAlternateNameDisabled(): void
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useLibGuides'] = true;
        $configData['Databases']['useLibGuidesAlternateNames'] = false;
        $module = $this->buildModuleAndProcessResults($configData, 'Geometry');

        $databases = $module->getResults();
        $this->assertCount(3, $databases);
        $this->assertArrayNotHasKey('http://primenumbers.com', $databases);
    }

    /**
     * Build a Databases module, set config and process results.
     *
     * @param $configData  array  A Databases config section
     * @param $queryString string Query string
     *
     * @return MockObject&Databases
     */
    protected function buildModuleAndProcessResults(
        array $configData,
        string $queryString = 'History'
    ): MockObject&Databases {
        $configManager = $this->createMock(\VuFind\Config\ConfigManagerInterface::class);
        $configManager->expects($this->any())->method('getConfigArray')
            ->willReturn($configData);

        $libGuidesGetter = function () {
            $libGuides = $this->createMock(\VuFind\Connection\LibGuides::class);
            $libGuidesData = $this->mockLibGuidesData();
            $libGuides->method('getAZ')->willReturn($libGuidesData);
            return $libGuides;
        };

        $cache = $this->createMock(CacheAdapter::class);
        $module = $this->getMockBuilder(Databases::class)
            ->setConstructorArgs([$configManager, $libGuidesGetter, $cache])
            ->onlyMethods(['getCachedData', 'putCachedData'])
            ->getMock();

        $settings = '5:EDS';
        $module->setConfig($settings);

        $facetList = $this->mockFacetList();
        $results = $this->mockResults($facetList, $queryString);
        $module->process($results);

        return $module;
    }

    /**
     * Mock up search results.
     *
     * @param $facetList   array Result facets
     * @param $queryString string Query string
     *
     * @return MockObject&Results
     */
    protected function mockResults(array $facetList, string $queryString): MockObject&Results
    {
        $results = $this->createMock(Results::class);
        $results->method('getFacetList')->willReturn($facetList);

        $params = $this->createMock(\VuFind\Search\Base\Params::class);
        $results->method('getParams')->willReturn($params);
        $query = $this->createMock(\VuFindSearch\Query\Query::class);
        $params->method('getQuery')->willReturn($query);
        $query->method('getString')->willReturn($queryString);

        return $results;
    }

    /**
     * Mock up a results facet list.
     *
     * @return array
     */
    protected function mockFacetList(): array
    {
        return [
            'ContentProvider' => [
                'list' => [
                    'db_1' => [
                        'value' => 'Sociology DB',
                    ],
                    'db_2' => [
                        'value' => 'Biology DB',
                    ],
                    'db_4' => [
                        'value' => 'Art DB',
                    ],
                ],
            ],
        ];
    }

    /**
     * Mock up a standard Databases config section.
     *
     * @return array
     */
    protected function mockConfigData(): array
    {
        return [
            'Databases' => [
                'resultFacet' => [
                    'ContentProvider',
                    'list',
                ],
                'resultFacetNameKey' => 'value',
                'useQuery' => true,
                'url' => [
                    'Sociology DB' => 'http://people.com',
                    'Biology DB' => 'http://cells.com',
                    'History DB' => 'http://thepast.com',
                ],
            ],
        ];
    }

    /**
     * Mock up LibGuides API databases data.
     *
     * @return array
     */
    protected function mockLibGuidesData(): array
    {
        return [
            'db_4' => (object)[
                'name' => 'Art DB',
                'url' => 'http://fridakahlo.com',
                'alt_names' => '',
            ],
            'db_5' => (object)[
                'name' => 'Math DB',
                'url' => 'http://primenumbers.com',
                'alt_names' => 'Geometry DB',
            ],
        ];
    }
}
