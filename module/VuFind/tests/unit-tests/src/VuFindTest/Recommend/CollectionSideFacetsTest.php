<?php

/**
 * CollectionSideFacets recommendation module Test Class
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

use VuFind\Recommend\CollectionSideFacets;

/**
 * CollectionSideFacets recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CollectionSideFacetsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\SolrSearchObjectTrait;

    /**
     * Test "getResults"
     *
     * @return void
     */
    public function testKeywordFilter()
    {
        $results = $this->getSolrResults($this->getMockParams());
        $results->getParams()->expects($this->once())->method('getDisplayQuery')->will($this->returnValue('foo'));
        $csf = $this->getSideFacets(null, $results, '::facets:true');
        $this->assertEquals('foo', $csf->getKeywordFilter());
        $this->assertTrue($csf->keywordFilterEnabled());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Config\PluginManager $configLoader config loader
     * @param \VuFind\Search\Solr\Results  $results      results
     * object
     * @param string                       $settings     settings
     * @param \Laminas\Stdlib\Parameters   $request      request
     *
     * @return SideFacets
     */
    protected function getSideFacets(
        $configLoader = null,
        $results = null,
        $settings = '',
        $request = null
    ) {
        $sf = new CollectionSideFacets($configLoader ?? $this->getMockConfigPluginManager([]));
        $sf->setConfig($settings);
        $sf->init(
            $results->getParams(),
            $request ?? new \Laminas\Stdlib\Parameters([])
        );
        $sf->process($results ?? $this->getSolrResults());
        return $sf;
    }

    /**
     * Get a mock params object.
     *
     * @param \VuFindSearch\Query\Query $query Query to include in container.
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = null)
    {
        if (null === $query) {
            $query = new \VuFindSearch\Query\Query('foo', 'bar');
        }
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        return $params;
    }
}
