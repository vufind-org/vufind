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

use Laminas\Config\Config;
use VuFind\Search\Blender\Options;
use VuFind\Search\Blender\Params;
use VuFind\Search\Blender\Results;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\Backend\Blender\Response\Json\RecordCollection;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Command\SearchCommand;

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

        $solrConfigMgr = $this->createMock(\VuFind\Config\PluginManager::class);
        $configMgr = $this->getConfigManager();

        $paramsClasses = [
            new \VuFind\Search\Solr\Params(
                new \VuFind\Search\Solr\Options($solrConfigMgr),
                $solrConfigMgr
            ),
            new \VuFind\Search\Primo\Params(
                new \VuFind\Search\Primo\Options($configMgr),
                $configMgr
            ),
        ];

        $params = new Params(
            new Options($configMgr),
            $configMgr,
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

    /**
     * Get mock config manager
     *
     * @return object
     */
    protected function getConfigManager()
    {
        $configs = [
            'Primo' => new Config([]),
        ];

        $callback = function (string $configName) use ($configs) {
            return $configs[$configName] ?? null;
        };

        $configManager = $this->getMockBuilder(\VuFind\Config\PluginManager::class)
                ->disableOriginalConstructor()
                ->getMock();
        $configManager
            ->expects($this->any())
            ->method('get')
            ->will($this->returnCallback($callback));

        return $configManager;
    }
}
