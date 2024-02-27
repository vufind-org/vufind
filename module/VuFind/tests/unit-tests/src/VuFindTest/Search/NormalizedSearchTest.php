<?php

/**
 * NormalizedSearch unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search;

use VuFind\Search\NormalizedSearch;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\Results;

/**
 * NormalizedSearch unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NormalizedSearchTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\SolrSearchObjectTrait;

    /**
     * Get a results manager to test with.
     *
     * @return ResultsManager
     */
    protected function getResultsManager(): ResultsManager
    {
        return $this->createMock(\VuFind\Search\Results\PluginManager::class);
    }

    /**
     * Get a results object to test with.
     *
     * @return Results
     */
    protected function getResults(): Results
    {
        $allMethods = get_class_methods(\VuFind\Search\Solr\Results::class);
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_diff($allMethods, ['getUrlQuery', 'getUrlQueryHelperFactory', 'minify', 'deminify']))
            ->getMock();
        $results->expects($this->any())
            ->method('getParams')
            ->will($this->returnValue($this->getSolrParams()));
        return $results;
    }

    /**
     * Get a normalized search object to test with.
     *
     * @param ?Results $results Results to normalize (null to use defaults)
     *
     * @return NormalizedSearch
     */
    protected function getNormalizedSearch(Results $results = null): NormalizedSearch
    {
        $finalResults = $results ?? $this->getResults();
        $manager = $this->getResultsManager();
        $manager->expects($this->any())
            ->method('get')->with($this->equalTo('Solr'))
            ->will($this->returnValue($finalResults));
        return new NormalizedSearch($manager, $finalResults);
    }

    /**
     * Test getRawResults.
     *
     * @return void
     */
    public function testGetRawResults(): void
    {
        $results = $this->getResults();
        $norm = $this->getNormalizedSearch($results);
        $this->assertEquals($results, $norm->getRawResults());
    }

    /**
     * Test getMinified.
     *
     * @return void
     */
    public function testGetMinified(): void
    {
        $minified = $this->getNormalizedSearch()->getMinified();
        $this->assertEquals('basic', $minified->ty);
        $this->assertEquals('Solr', $minified->cl);
    }

    /**
     * Test getNormalizedResults.
     *
     * @return void
     */
    public function testGetNormalizedResults(): void
    {
        $results = $this->getResults();
        // Because of the way we are mocking things for this test, we should
        // expect the normalized search to be the same object as the regular
        // search (because ResultsManager always returns the same thing).
        $this->assertEquals(
            $results,
            $this->getNormalizedSearch($results)->getNormalizedResults()
        );
    }

    /**
     * Test getUrl.
     *
     * @return void
     */
    public function testGetUrl(): void
    {
        $this->assertEquals('?', $this->getNormalizedSearch()->getUrl());
    }

    /**
     * Test getChecksum.
     *
     * @return void
     */
    public function testGetChecksum(): void
    {
        $this->assertEquals('73712304', $this->getNormalizedSearch()->getChecksum());
    }

    /**
     * Test positive equivalence.
     *
     * @return void
     */
    public function testEquivalentSearches(): void
    {
        $results = $this->getResults();
        $norm = $this->getNormalizedSearch($results);
        $this->assertTrue($norm->isEquivalentToMinifiedSearch(new \minSO($results)));
    }

    /**
     * Test negative equivalence.
     *
     * @return void
     */
    public function testNonEquivalentSearches(): void
    {
        $results = $this->getResults();
        $norm = $this->getNormalizedSearch($results);
        $mockMin = $this->createMock(\minSO::class);
        $otherSearch = $this->createMock(\VuFind\Search\EDS\Results::class);
        $mockMin->expects($this->once())
            ->method('deminify')
            ->will($this->returnValue($otherSearch));
        $this->assertFalse($norm->isEquivalentToMinifiedSearch($mockMin));
    }
}
