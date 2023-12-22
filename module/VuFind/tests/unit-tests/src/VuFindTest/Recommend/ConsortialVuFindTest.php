<?php

/**
 * ConsortialVuFind recommendation module Test Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Laminas\Config\Config;
use VuFind\Config\PluginManager as ConfigPluginManager;
use VuFind\Connection\ExternalVuFind;
use VuFind\Recommend\ConsortialVuFind;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFindTest\Search\TestHarness\Results;

/**
 * ConsortialVuFind recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConsortialVuFindTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * ExternalVuFind connection object
     *
     * @var ExternalVuFind
     */
    protected $connector;

    /**
     * ConsortialVuFind object
     *
     * @var ConsortialVuFind
     */
    protected $consortialVuFind;

    /**
     * Set up mocks for tests.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Mock ExternalVuFind connector
        $this->connector = $this->getMockBuilder(ExternalVuFind::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchResultsFixture = $this->getFixture('externalvufind/search');
        $searchResults = json_decode(substr($searchResultsFixture, strpos($searchResultsFixture, '{')), true);
        $this->connector->method('search')->willReturn($searchResults);
    }

    /**
     * Test the getResults function
     *
     * @return void
     */
    public function testGetResults()
    {
        $config = $this->buildConfig();
        $consortialVuFind = $this->buildConsortialVuFind($config);

        $searchResults = $consortialVuFind->getResults();
        $this->assertNotEmpty($searchResults);
        $this->assertEquals(152916, $searchResults['resultCount']);

        $records = $searchResults['records'];
        $this->assertCount(20, $records);

        // Ensure the 'url' attribute is added
        $firstRecord = $records[0];
        $this->assertEquals(
            'https://some.url/Record/5d28e433-d9ba-4b2f-ae7e-769441452d3a',
            $firstRecord['url']
        );
    }

    /**
     * Test the getMoreResultsUrl function
     *
     * @return void
     */
    public function testGetMoreResultsUrl()
    {
        $config = $this->buildConfig();
        $consortialVuFind = $this->buildConsortialVuFind($config);

        $moreResultsUrl = $consortialVuFind->getMoreResultsUrl();
        $this->assertEquals('https://some.url/Search/Results?lookfor=civil+war', $moreResultsUrl);
    }

    /**
     * Build an object representing an ExternalVuFind.ini configuration file
     *
     * @return ConsortialVuFind
     */
    protected function buildConfig()
    {
        $config = new Config([
            'ReShare' => [
                'api_base_url' => 'http://some.url/api',
                'record_base_url' => 'https://some.url/Record',
                'results_base_url' => 'https://some.url/Search/Results',
            ],
        ], true);
        return $config;
    }

    /**
     * Build and pre-process a ConsortialVuFind object
     *
     * @param Config $config The config object
     *
     * @return ConsortialVuFind
     */
    protected function buildConsortialVuFind($config)
    {
        $consortialVuFind = new ConsortialVuFind($config, $this->connector);
        $consortialVuFind->setConfig('lookfor:3:ReShare');

        $queryResults = $this->buildQueryResults('civil war');
        $consortialVuFind->process($queryResults);

        return $consortialVuFind;
    }

    /**
     * Build a partially mocked Results object for a given query string
     *
     * @param string $queryString The query string
     * @param array  $facets      The result facets
     *
     * @return Results The Results object
     */
    protected function buildQueryResults($queryString, $facets = [])
    {
        // Build query Params
        $queryParams = new Params(
            $this->createStub(Options::class),
            $this->createStub(ConfigPluginManager::class)
        );
        $queryParams->getQuery()->setString($queryString);

        // Build Results object with mock search service and record loader
        $queryResults = new Results(
            $queryParams,
            $this->createStub(\VuFindSearch\Service::class),
            $this->getMockBuilder(\VuFind\Record\Loader::class)
                ->disableOriginalConstructor()
                ->getMock(),
            null,
            $facets
        );
        return $queryResults;
    }
}
