<?php

/**
 * LibGuidesProfile recommendation module Test Class
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

use Laminas\Cache\Storage\StorageInterface as CacheAdapter;
use Laminas\Config\Config;
use VuFind\Config\PluginManager as ConfigPluginManager;
use VuFind\Connection\LibGuides;
use VuFind\Recommend\LibGuidesProfile;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFindTest\Search\TestHarness\Results;

/**
 * LibGuidesProfile recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LibGuidesProfileTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * LibGuides connection object
     *
     * @var LibGuides
     */
    protected $connector;

    /**
     * Cache adapter object
     *
     * @var CacheAdapter
     */
    protected $cacheAdapter;

    /**
     * LibGuidesProfile object
     *
     * @var LibGuidesProfile
     */
    protected $libGuidesProfile;

    /**
     * Set up mocks for tests.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Mock LibGuides connector
        $this->connector = $this->getMockBuilder(LibGuides::class)
            ->disableOriginalConstructor()
            ->getMock();
        $accountsFixture = $this->getFixture('libguides/api/accounts');
        $accounts = json_decode(substr($accountsFixture, strpos($accountsFixture, '[')));
        $this->connector->method('getAccounts')->willReturn($accounts);
    }

    /**
     * Test search term that is an exact match for a subject specialty
     *
     * @return void
     */
    public function testSubjectExactMatch()
    {
        $config = new Config([], true);
        $config->Profile = ['strategies' =>  ['Subject']];
        $libGuidesProfile = $this->buildProfile($config);

        $queryResults = $this->buildQueryResults('Geography');
        $libGuidesProfile->process($queryResults);

        $account = $libGuidesProfile->getResults();
        $this->assertEquals('eratosthenes@alexandria.org', $account->email);
    }

    /**
     * Test search term that is a substring of a subject specialty
     *
     * @return void
     */
    public function testSubjectSubstring()
    {
        $config = new Config([], true);
        $config->Profile = ['strategies' =>  ['Subject']];
        $libGuidesProfile = $this->buildProfile($config);

        // Exact match would be "Decimal Classification"
        $queryResults = $this->buildQueryResults('Classification');
        $libGuidesProfile->process($queryResults);

        $account = $libGuidesProfile->getResults();
        $this->assertEquals('melvil@dewey.edu', $account->email);
    }

    /**
     * Test search term that is a loose match for a subject specialty
     *
     * @return void
     */
    public function testSubjectLooseMatch()
    {
        $config = new Config([], true);
        $config->Profile = ['strategies' =>  ['Subject']];
        $libGuidesProfile = $this->buildProfile($config);

        // Exact match would be "Music Theory"
        $queryResults = $this->buildQueryResults('Rock Musicians');
        $libGuidesProfile->process($queryResults);

        $account = $libGuidesProfile->getResults();
        $this->assertEquals('eratosthenes@alexandria.org', $account->email);
    }

    /**
     * Test call number match
     *
     * @return void
     */
    public function testCallNumberMatch()
    {
        $config = new Config([], true);
        $config->Profile = [
            'strategies' =>  ['CallNumber'],
            'profile_aliases' => [
                'Dewey' => 1234,
                'Eratosthenes' => 5678,
            ],
            'call_numbers' => [
                'D' => 'Eratosthenes',
                'P' => 'Dewey',
            ],
        ];
        $libGuidesProfile = $this->buildProfile($config);

        // D (World History) is the most prominent subject, which matches Eratosthenes
        $facets = [
            'callnumber-first' => [
                'list' => [
                    [
                        'value' => 'D - World History',
                        'count' => 8,
                    ],
                    [
                        'value' => 'P - Language and Literature',
                        'count' => 7,
                    ],
                ],
            ],
        ];
        $queryResults = $this->buildQueryResults('Query does not matter', $facets);
        $libGuidesProfile->process($queryResults);

        $account = $libGuidesProfile->getResults();
        $this->assertEquals('eratosthenes@alexandria.org', $account->email);
    }

    /**
     * Build a partially mocked LibGuidesProfile object
     *
     * @param Config $config The config object
     *
     * @return LibGuidesProfile
     */
    protected function buildProfile($config)
    {
        // Mock caching logic in LibGuidesProfile.
        // Caching is from a trait, which is not the point of this test suite.
        $this->cacheAdapter = $this->createMock(CacheAdapter::class);

        // For the target class LibGuidesProfile, only mock the caching methods
        $libGuidesProfile = $this->getMockBuilder(LibGuidesProfile::class)
            ->setConstructorArgs([$this->connector, $config, $this->cacheAdapter])
            ->onlyMethods(['getCachedData', 'putCachedData'])
            ->getMock();
        $libGuidesProfile->method('getCachedData')->willReturn(null);
        return $libGuidesProfile;
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
