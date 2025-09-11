<?php

/**
 * Blender Results Tests
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\Blender;

use VuFind\Config\Config;
use VuFind\Search\Blender\Options;
use VuFind\Search\Blender\Params;
use VuFind\Search\Blender\Results;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\Backend\Blender\Response\Json\RecordCollection;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Command\SearchCommand;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * Blender Results Tests
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Test performing a search
     *
     * @return void
     */
    public function testPerformSearch()
    {
        $callback = function (CommandInterface $command): CommandInterface {
            $this->assertInstanceOf(SearchCommand::class, $command);

            $collection = new RecordCollection();
            $collection->setSourceIdentifier('Blender');
            $collection->initBlended([], 0, 10, 20);
            $collection->addError('Error Message');
            $this->callMethod($command, 'finalizeExecution', [$collection]);

            return $command;
        };

        $mockConfigManager = $this->getMockConfigManager(['Primo' => []]);

        $paramsClasses = [
            new \VuFind\Search\Solr\Params(
                new \VuFind\Search\Solr\Options($mockConfigManager),
                $mockConfigManager
            ),
            new \VuFind\Search\Primo\Params(
                new \VuFind\Search\Primo\Options($mockConfigManager),
                $mockConfigManager
            ),
        ];

        $params = new Params(
            new Options($mockConfigManager),
            $mockConfigManager,
            new HierarchicalFacetHelper(),
            $paramsClasses,
            new Config([]),
            []
        );
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->getMock();
        $searchService->expects($this->once())
            ->method('invoke')
            ->will($this->returnCallback($callback));
        $recordLoader = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results = new Results($params, $searchService, $recordLoader);
        $results->performAndProcessSearch();

        $this->assertEquals(20, $results->getResultTotal());
        $this->assertEquals([], $results->getResults());
        $this->assertEquals(['Error Message'], $results->getErrors());
    }
}
