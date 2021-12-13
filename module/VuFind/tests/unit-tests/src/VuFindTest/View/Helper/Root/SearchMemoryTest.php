<?php
/**
 * SearchMemory view helper Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
namespace VuFindTest\View\Helper\Root;

use Laminas\Stdlib\Parameters;
use Laminas\View\Helper\Url;
use VuFind\Search\Memory;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\View\Helper\Root\SearchMemory;
use VuFind\View\Helper\Root\SearchParams;

/**
 * SearchMemory view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SearchMemoryTest extends \PHPUnit\Framework\TestCase
{
    use  \VuFindTest\Feature\ViewTrait;

    /**
     * Test search memory helper
     *
     * @param string $query                Query to parse
     * @param array  $expectedRequestArray Expected request parameters to parse
     *
     * @return void
     *
     * @dataProvider getLastSearchParamsProvider
     */
    public function testGetLastSearchParams(
        string $query,
        array $expectedRequestArray
    ): void {
        $expectedRequest = new Parameters($expectedRequestArray);
        $searchRoute = 'foo-bar';
        $searchBasePath = '/foo/bar';
        $lastSearchUrl = $searchBasePath . $query;

        $memory = $this->getMockBuilder(Memory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $memory->expects($this->once())->method('retrieveSearch')
            ->will($this->returnValue($lastSearchUrl));
        $helper = new SearchMemory($memory);
        $url = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo($searchRoute))
            ->will($this->returnValue($searchBasePath));
        $solrOptions = $this->getMockBuilder(Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solrOptions->expects($this->once())->method('getSearchAction')
            ->will($this->returnValue($searchRoute));
        $solrParams = $this->getMockBuilder(Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $solrParams->expects($this->once())->method('getOptions')
            ->will($this->returnValue($solrOptions));
        $solrParams->expects($this->once())->method('initFromRequest')
            ->with($this->equalTo($expectedRequest));
        $searchParams = $this->getMockBuilder(SearchParams::class)
            ->disableOriginalConstructor()
            ->getMock();
        $searchParams->expects($this->once())->method('__invoke')
            ->with($this->equalTo('Solr'))->will($this->returnValue($solrParams));
        $plugins = compact('searchParams', 'url');
        $helper->setView($this->getPhpRenderer($plugins));
        $this->assertEquals($solrParams, $helper->getLastSearchParams('Solr'));
    }

    public function getLastSearchParamsProvider(): array
    {
        return [
            'no parameters' => ['?', []],
            'lookfor parameter' => ['?lookfor=foo', ['lookfor' => 'foo']],
        ];
    }
}
