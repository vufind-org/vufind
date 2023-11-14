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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Laminas\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;
use VuFind\Recommend\Databases;

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
    public function testEmptyConfig()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("must have section 'Databases'");

        $configData = [];
        $module = $this->buildModuleAndProcessResults($configData);

        $settings = '5:EDS';
        $module->setConfig($settings);
    }

    /**
     * Test a default config.
     *
     * @return void
     */
    public function testNormal()
    {
        $configData = $this->mockConfigData();
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(3, $databases);
        $this->assertArrayHasKey('History DB', $databases);
        $this->assertArrayNotHasKey('Art DB', $databases);
    }

    /**
     * Test setting useQuery to false.
     *
     * @return void
     */
    public function testDontUseQuery()
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useQuery'] = false;
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(2, $databases);
        $this->assertArrayNotHasKey('History DB', $databases);
    }

    /**
     * Test setting useLibGuides to true.
     *
     * @return void
     */
    public function testUseLibGuides()
    {
        $configData = $this->mockConfigData();
        $configData['Databases']['useLibGuides'] = true;
        $module = $this->buildModuleAndProcessResults($configData);

        $databases = $module->getResults();
        $this->assertCount(4, $databases);
        $this->assertArrayHasKey('Art DB', $databases);
    }

    /**
     * Build a Databases module, set config and process results.
     *
     * @param $configData array A Databases config section
     *
     * @return void
     */
    protected function buildModuleAndProcessResults($configData)
    {
        $configManager = $this->createMock(\VuFind\Config\PluginManager::class);
        $configManager->expects($this->any())->method('get')
            ->will($this->returnValue(new \Laminas\Config\Config($configData)));

        $libGuidesGetter = function () {
            $libGuides = $this->getMockBuilder(\VuFind\Connection\LibGuides::class)
                ->disableOriginalConstructor()
                ->getMock();
            $libGuidesData = $this->mockLibGuidesData();
            $libGuides->method('getAZ')->willReturn($libGuidesData);
            return $libGuides;
        };

        $cache = $this->getMockBuilder(CacheAdapter::class)->getMock();
        $module = $this->getMockBuilder(Databases::class)
            ->setConstructorArgs([$configManager, $libGuidesGetter, $cache])
            ->onlyMethods(['getCachedData', 'putCachedData'])
            ->getMock();

        $settings = '5:EDS';
        $module->setConfig($settings);

        $facetList = $this->mockFacetList();
        $results = $this->mockResults($facetList);
        $module->process($results);

        return $module;
    }

    /**
     * Mock up search results.
     *
     * @param $facetList   array Result facets
     * @param $queryString string Query string
     *
     * @return void
     */
    protected function mockResults($facetList, $queryString = 'History')
    {
        $results = $this->getMockBuilder(\VuFind\Search\EDS\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->method('getFacetList')->willReturn($facetList);

        $params = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->method('getParams')->willReturn($params);
        $query = $this->getMockBuilder(\VuFindSearch\Query\Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        $params->method('getQuery')->willReturn($query);
        $query->method('getString')->willReturn($queryString);

        return $results;
    }

    /**
     * Mock up a standard Databases config section.
     *
     * @return void
     */
    protected function mockConfigData()
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
     * Mock up a results facet list.
     *
     * @return void
     */
    protected function mockFacetList()
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
     * Mock up LibGuides API databases data.
     *
     * @return void
     */
    protected function mockLibGuidesData()
    {
        return [
            'db_4' => (object)[
                'name' => 'Art DB',
                'url' => 'fridakahlo.com',
            ],
        ];
    }
}
