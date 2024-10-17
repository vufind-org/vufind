<?php

/**
 * WorldCat2 Search Object Results Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Search\WorldCat2;

use VuFind\Config\PluginManager;
use VuFind\Record\Loader;
use VuFind\Search\WorldCat2\Options;
use VuFind\Search\WorldCat2\Params;
use VuFind\Search\WorldCat2\Results;
use VuFindSearch\Backend\WorldCat2\Response\RecordCollection;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindSearch\Service;

/**
 * WorldCat2 Search Object Results Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test that empty searches are blocked.
     *
     * @return void
     */
    public function testEmptySearch(): void
    {
        $query = new Query();
        $params = $this->getParams();
        $params->setQuery($query);
        $results = $this->getResults($params);
        // Test getting facets first, see testOverlongSearch for a different approach
        $this->assertEquals([], $results->getFacetList());
        $this->assertEquals([], $results->getResults());
        $this->assertEquals(['empty_search_disallowed'], $results->getErrors());
    }

    /**
     * Test that overlong searches are blocked.
     *
     * @return void
     */
    public function testOverlongSearch(): void
    {
        $query = new Query(implode(' ', range(1, 100)));
        $params = $this->getParams();
        $params->setQuery($query);
        $results = $this->getResults($params);
        // Test getting results first, as a variation of testEmptySearch()
        $this->assertEquals([], $results->getResults());
        $this->assertEquals([], $results->getFacetList());
        $this->assertEquals(
            [
                [
                    'msg' => 'too_many_query_terms',
                    'tokens' => ['%%terms%%' => 100, '%%maxTerms%%' => 30],
                ],
            ],
            $results->getErrors()
        );
    }

    /**
     * Test a successful search.
     *
     * @return void
     */
    public function testSuccessfulSearch(): void
    {
        $query = new Query('foo');
        $params = $this->getParams();
        $params->setQuery($query);
        $searchService = $this->createMock(Service::class);
        $expectedParams = new ParamBag(['orderBy' => 'bestMatch', 'facets' => []]);
        $expectedCommand = new SearchCommand('WorldCat2', $query, 1, 20, $expectedParams);
        $records = new RecordCollection(
            [
                'offset' => 0,
                'total' => 5,
            ]
        );
        $mockCommand = $this->createMock(SearchCommand::class);
        $mockCommand->method('getResult')->willReturn($records);
        $searchService->expects($this->once())->method('invoke')->with($expectedCommand)->willReturn($mockCommand);
        $results = $this->getResults($params, $searchService);
        $this->assertEquals(5, $results->getResultTotal());
    }

    /**
     * Get Params object
     *
     * @param ?Options       $options    Options object (null to create)
     * @param ?PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        ?Options $options = null,
        ?PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }

    /**
     * Get Results object
     *
     * @param ?Params                $params Params object (null to create)
     * @param ?\VuFindSearch\Service $search Search service (null to create)
     * @param ?Loader                $loader Record loader (null to create)
     *
     * @return Results
     */
    protected function getResults(
        ?Params $params = null,
        ?\VuFindSearch\Service $search = null,
        ?Loader $loader = null,
    ): Results {
        return new Results(
            $params ?? $this->getParams(),
            $search ?? $this->createMock(\VuFindSearch\Service::class),
            $loader ?? $this->createMock(Loader::class)
        );
    }
}
