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
use VuFindSearch\Query\Query;

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
        $this->assertEquals([], $results->getFacetList());
        $this->assertEquals([], $results->getResults());
        $this->assertEquals(['empty_search_disallowed'], $results->getErrors());
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
