<?php

/**
 * FacetCloud recommendation module Test Class
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

use VuFind\Recommend\FacetCloud;

/**
 * FacetCloud recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FacetCloudTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test getEmptyResults()
     *
     * @return void
     */
    public function testGetFacetLimit()
    {
        $results = $this->getMockResults();
        $results->getParams()->expects($this->once())->method('getFacetSettings')
            ->will($this->returnValue(['limit' => 50]));
        $fc = $this->getFacetCloud(null, $results);
        $this->assertEquals(49, $fc->getFacetLimit());
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Config\PluginManager $configLoader config loader
     * @param \VuFind\Search\Solr\Results  $results      populated results object
     * @param \VuFind\Search\Solr\Results  $emptyResults empty results object
     * @param string                       $settings     settings
     * @param \Laminas\Stdlib\Parameters   $request      request
     *
     * @return FacetCloud
     */
    protected function getFacetCloud(
        $configLoader = null,
        $results = null,
        $emptyResults = null,
        $settings = '',
        $request = null
    ) {
        if (null === $results) {
            $results = $this->getMockResults();
        }
        $fc = new FacetCloud(
            $configLoader ?? $this->getMockConfigPluginManager([]),
            $emptyResults ?? $this->getMockResults()
        );
        $fc->setConfig($settings);
        $fc->init(
            $results->getParams(),
            $request ?? new \Laminas\Stdlib\Parameters([])
        );
        $fc->process($results);
        return $fc;
    }

    /**
     * Get a mock results object.
     *
     * @param \VuFind\Search\Solr\Params $params Params to include in container.
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults($params = null)
    {
        if (null === $params) {
            $params = $this->getMockParams();
        }
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
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
