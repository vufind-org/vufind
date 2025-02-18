<?php

/**
 * SearchNormalizer unit tests.
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

use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Search\SearchNormalizer;

/**
 * SearchNormalizer unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchNormalizerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\SolrSearchObjectTrait;

    /**
     * Test normalizeMinifiedSearch(), which will also cover normalizeSearch().
     *
     * @return void
     */
    public function testNormalizeMinifiedSearch(): void
    {
        $allMethods = get_class_methods(\VuFind\Search\Solr\Results::class);
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_diff($allMethods, ['getUrlQuery', 'getUrlQueryHelperFactory']))
            ->getMock();
        $results->expects($this->any())
            ->method('getParams')
            ->will($this->returnValue($this->getSolrParams()));
        $manager = $this->createMock(\VuFind\Search\Results\PluginManager::class);
        $manager->expects($this->any())
            ->method('get')->with($this->equalTo('Solr'))
            ->will($this->returnValue($results));
        $minified = new \minSO($results);
        $normalizer = new SearchNormalizer($manager, $this->createMock(SearchServiceInterface::class));
        $normalized = $normalizer->normalizeMinifiedSearch($minified);
        $this->assertEquals($results, $normalized->getRawResults());
    }
}
