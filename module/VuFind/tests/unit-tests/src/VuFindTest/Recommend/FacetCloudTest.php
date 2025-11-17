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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Test getEmptyResults()
     *
     * @return void
     */
    public function testGetFacetLimit()
    {
        $results = $this->getMockResults();
        $results->getParams()->expects($this->once())->method('getFacetSettings')
            ->willReturn(['limit' => 50]);
        $fc = $this->getFacetCloud($results);
        $this->assertEquals(49, $fc->getFacetLimit());
    }

    /**
     * Get a fully configured module
     *
     * @param ?\VuFind\Search\Solr\Results $results populated results object
     *
     * @return FacetCloud
     */
    protected function getFacetCloud(?\VuFind\Search\Solr\Results $results = null): FacetCloud
    {
        if (null === $results) {
            $results = $this->getMockResults();
        }
        $fc = new FacetCloud(
            $this->getMockConfigManager(),
            $this->getMockResults()
        );
        $fc->setConfig('');
        $fc->init(
            $results->getParams(),
            new \Laminas\Stdlib\Parameters([])
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
            ->willReturn($params);
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
            ->willReturn($query);
        return $params;
    }
}
