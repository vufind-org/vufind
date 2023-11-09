<?php

/**
 * GetFacetData test class.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\GetFacetData;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\UrlQueryHelper;
use VuFind\Session\Settings;
use VuFindSearch\Query\Query;

/**
 * GetFacetData test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetFacetDataTest extends \VuFindTest\Unit\AjaxHandlerTest
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test input data.
     *
     * @var array
     */
    protected $facetList = [
        'format' => [
            'data' => [
                'list' =>
                [
                    [
                        'value' => '0/Book/',
                        'displayText' => 'Book',
                        'count' => 1000,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '0/AV/',
                        'displayText' => 'Audiovisual',
                        'count' => 600,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '0/Audio/',
                        'displayText' => 'Sound',
                        'count' => 400,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '1/Book/BookPart/',
                        'displayText' => 'Book Part',
                        'count' => 300,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '1/Book/Section/',
                        'displayText' => 'Book Section',
                        'count' => 200,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '1/Audio/Spoken/',
                        'displayText' => 'Spoken Text',
                        'count' => 100,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                    [
                        'value' => '1/Audio/Music/',
                        'displayText' => 'Music',
                        'count' => 50,
                        'operator' => 'OR',
                        'isApplied' => false,
                    ],
                ],
            ],
        ],
    ];

    /**
     * GetFacetData ajax handler
     *
     * @var GetFacetData
     */
    protected $handler;

    /**
     * Options
     *
     * @var Options
     */
    protected $options;

    /**
     * Get a mock results object.
     *
     * @return Results
     */
    protected function getMockResults(): \VuFind\Search\Solr\Results
    {
        $params = $this->getMockParams();
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Get a mock params object.
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams()
    {
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getDisplayQuery')
            ->will($this->returnValue(''));
        $params->expects($this->any())->method('getSearchType')
            ->will($this->returnValue('basic'));
        return $params;
    }

    /**
     * Set up
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $ss = $this->container->createMock(Settings::class, ['disableWrite']);
        $ss->expects($this->once())->method('disableWrite');
        $this->container->set(Settings::class, $ss);

        $queryMock = $this->getMockBuilder(UrlQueryHelper::class)
            ->setConstructorArgs([[''], new Query('search')])
            ->onlyMethods([])
            ->getMock();

        $this->options = $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()->getMock();

        $results = $this->getMockResults();
        $results->expects($this->once())
            ->method('getFullFieldFacets')
            ->will($this->returnValue($this->facetList));
        $results->expects($this->once())
            ->method('getUrlQuery')
            ->will($this->returnValue($queryMock));
        $results->expects($this->once())
            ->method('getOptions')
            ->will($this->returnValue($this->options));

        // Set up results manager:
        $resultsManager = $this->container
            ->createMock(ResultsManager::class, ['get']);
        $resultsManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->will($this->returnValue($results));
        $this->container->set(ResultsManager::class, $resultsManager);

        $this->handler = $this->getMockBuilder(GetFacetData::class)
            ->setConstructorArgs([$ss, new HierarchicalFacetHelper(), $resultsManager])
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Test the AJAX handler's basic response.
     *
     * @return void
     */
    public function testResponseDefault()
    {
        $this->options->expects($this->any())->method('getHierarchicalExcludeFilters')
            ->will($this->returnValue([]));
        $this->options->expects($this->any())->method('getHierarchicalFacetFilters')
            ->will($this->returnValue([]));

        $params = $this->getParamsHelper(
            [
                'facetName' => 'format',
            ]
        );
        $facets = $this->handler->handleRequest($params)[0]['facets'];
        $this->assertEquals('0/Book/', $facets[0]['value']);
        $this->assertEquals('1/Book/BookPart/', $facets[0]['children'][0]['value']);
        $this->assertEquals('1/Book/Section/', $facets[0]['children'][1]['value']);
        $this->assertEquals('0/AV/', $facets[1]['value']);
        $this->assertEquals('0/Audio/', $facets[2]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facets[2]['children'][0]['value']);
        $this->assertEquals('1/Audio/Music/', $facets[2]['children'][1]['value']);
    }

    /**
     * Test the AJAX handler's basic response with hierarchical exclude filters.
     *
     * @return void
     */
    public function testResponseExclude()
    {
        $exclude = [
            '0/Book/',
            '1/Audio/Spoken/',
        ];

        $this->options->expects($this->any())->method('getHierarchicalExcludeFilters')
            ->will($this->returnValue($exclude));
        $this->options->expects($this->any())->method('getHierarchicalFacetFilters')
            ->will($this->returnValue([]));

        $params = $this->getParamsHelper(
            [
                'facetName' => 'format',
            ]
        );
        $facets = $this->handler->handleRequest($params)[0]['facets'];
        $this->assertEquals('0/AV/', $facets[0]['value']);
        $this->assertEquals('0/Audio/', $facets[1]['value']);
        $this->assertEquals('1/Audio/Music/', $facets[1]['children'][0]['value']);
    }

    /**
     * Test the AJAX handler's basic response with hierarchical facet filters.
     *
     * @return void
     */
    public function testResponseFilter()
    {
        $filters = [
            '0/Audio/',
        ];

        $this->options->expects($this->any())->method('getHierarchicalExcludeFilters')
            ->will($this->returnValue([]));
        $this->options->expects($this->any())->method('getHierarchicalFacetFilters')
            ->will($this->returnValue($filters));

        $params = $this->getParamsHelper(
            [
                'facetName' => 'format',
            ]
        );
        $facets = $this->handler->handleRequest($params)[0]['facets'];
        $this->assertEquals('0/Audio/', $facets[0]['value']);
        $this->assertEquals('1/Audio/Spoken/', $facets[0]['children'][0]['value']);
        $this->assertEquals('1/Audio/Music/', $facets[0]['children'][1]['value']);
    }
}
