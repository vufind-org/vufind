<?php

/**
 * SwitchQuery recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\Recommend;

use VuFind\Recommend\SwitchQuery;
use VuFind\Search\BackendManager;

/**
 * SwitchQuery recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SwitchQueryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\SearchServiceTrait;

    /**
     * Test "getResults"
     *
     * @return void
     */
    public function testGetResults()
    {
        $results = $this->getMockResults();
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals($results, $sq->getResults());
    }

    /**
     * Test lowercase booleans
     *
     * @return void
     */
    public function testLowercaseBooleans()
    {
        $results = $this->getMockResults('a or b');
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals(
            [
                'switchquery_lowercasebools' => 'a OR b',
                'switchquery_wildcard' => 'a or b*',
            ],
            $sq->getSuggestions()
        );
    }

    /**
     * Test lowercase booleans with case insensitive setting (should be skipped)
     *
     * @return void
     */
    public function testLowercaseBooleansAndCaseInsensitivity()
    {
        $results = $this->getMockResults('a or b');
        $bm = $this->getMockBackendManager(false);
        $sq = $this->getSwitchQuery($results, ':unwantedbools,wildcard', $bm);
        $this->assertEquals([], $sq->getSuggestions());
    }

    /**
     * Test id query
     *
     * @return void
     */
    public function testIdQuery()
    {
        $results = $this->getMockResults('id:foo');
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals(
            [],
            $sq->getSuggestions()
        );
    }

    /**
     * Test advanced query
     *
     * @return void
     */
    public function testAdvancedQuery()
    {
        $results = $this->getMockResults('', 'advanced');
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals(
            [],
            $sq->getSuggestions()
        );
    }

    /**
     * Test unwanted booleans
     *
     * @return void
     */
    public function testUnwantedBools()
    {
        $results = $this->getMockResults('AND NOT OR');
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals(
            [
                'switchquery_unwantedbools' => '"AND NOT OR"',
                'switchquery_wildcard' => 'AND NOT OR*',
            ],
            $sq->getSuggestions()
        );
    }

    /**
     * Test unwanted quotes
     *
     * @return void
     */
    public function testUnwantedQuotes()
    {
        $results = $this->getMockResults('"my phrase"');
        $sq = $this->getSwitchQuery($results);
        $this->assertEquals(
            [
                'switchquery_unwantedquotes' => 'my phrase',
            ],
            $sq->getSuggestions()
        );
    }

    /**
     * Test transform unwanted character
     *
     * @return void
     */
    public function testTransformUnwantedCharacter()
    {
        $results = $this->getMockResults('abcd');
        $sq = $this->getSwitchQuery($results, ':wildcard:truncatechar');
        $this->assertEquals(
            [
                'switchquery_truncatechar' => 'abc',
            ],
            $sq->getSuggestions()
        );
    }

    /**
     * Test transform unwanted character on phrase (should omit suggestion)
     *
     * @return void
     */
    public function testTransformUnwantedCharacterOnPhrase()
    {
        $results = $this->getMockResults('"my phrase"');
        $sq = $this->getSwitchQuery($results, ':unwantedquotes:truncatechar');
        $this->assertEquals([], $sq->getSuggestions());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Search\Solr\Results $results  results object
     * @param string                      $settings settings
     * @param BackendManager              $bm       backend manager
     *
     * @return SwitchQuery
     */
    protected function getSwitchQuery($results = null, $settings = '', $bm = null)
    {
        $results ??= $this->getMockResults();
        $sq = new SwitchQuery(
            $this->getSearchService($bm ?? $this->getMockBackendManager())
        );
        $sq->setConfig($settings);
        $sq->init($results->getParams(), new \Laminas\Stdlib\Parameters([]));
        $sq->process($results);
        return $sq;
    }

    /**
     * Get a mock backend manager.
     *
     * @param bool|string $csBools  Case sensitive Booleans setting
     * @param bool        $csRanges Case sensitive ranges setting
     *
     * @return BackendManager
     */
    protected function getMockBackendManager($csBools = true, $csRanges = true)
    {
        $helper = new \VuFindSearch\Backend\Solr\LuceneSyntaxHelper($csBools, $csRanges);
        $queryBuilder = $this->getMockBuilder(\VuFindSearch\Backend\Solr\QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();
        $queryBuilder->expects($this->any())->method('getLuceneHelper')
            ->will($this->returnValue($helper));
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->any())->method('getIdentifier')
            ->will($this->returnValue('Solr'));
        $backend->expects($this->any())->method('getQueryBuilder')
            ->will($this->returnValue($queryBuilder));
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set('Solr', $backend);
        return new BackendManager($container);
    }

    /**
     * Get a mock results object.
     *
     * @param string $query Query to include
     * @param string $type  Query type ('basic' or 'advanced')
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults($query = '', $type = 'basic')
    {
        $params = $this->getMockParams($query, $type);
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Get a mock params object.
     *
     * @param string $query Query to include
     * @param string $type  Query type ('basic' or 'advanced')
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = '', $type = 'basic')
    {
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getDisplayQuery')
            ->will($this->returnValue($query));
        $params->expects($this->any())->method('getSearchType')
            ->will($this->returnValue($type));
        return $params;
    }
}
