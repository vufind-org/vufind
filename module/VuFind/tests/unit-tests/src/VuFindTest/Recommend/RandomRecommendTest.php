<?php

/**
 * Random Recommend tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\RandomRecommend as Random;

/**
 * Random Recommend tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RandomRecommendTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;
    use \VuFindTest\Feature\SolrSearchObjectTrait;

    /**
     * Random recommendation module class
     *
     * @var Random
     */
    protected $recommend;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->recommend = new Random(
            $this->createMock(\VuFindSearch\Service::class),
            $this->createMock(\VuFind\Search\Params\PluginManager::class)
        );
    }

    /**
     * Test settings
     *
     * @return void
     */
    public function testCanSetSettings()
    {
        //[backend]:[limit]:[display mode]:[random mode]:[minimumset]:[facet1]:[facetvalue1]
        $this->recommend->setConfig('SolrWeb:5:mixed:disregard:20:facet1:value1:facet2:value2');
        $this->assertEquals(
            'SolrWeb',
            $this->getProperty($this->recommend, 'backend')
        );
        $this->assertEquals(
            '5',
            $this->getProperty($this->recommend, 'limit')
        );
        $this->assertEquals(
            'mixed',
            $this->getProperty($this->recommend, 'displayMode')
        );
        $this->assertEquals(
            'disregard',
            $this->getProperty($this->recommend, 'mode')
        );
        $this->assertEquals(
            '20',
            $this->getProperty($this->recommend, 'minimum')
        );
        $filters = $this->getProperty($this->recommend, 'filters');
        $this->assertIsArray($filters);
        $this->assertCount(2, $filters);
        $this->assertEquals('facet1:value1', $filters[0]);
        $this->assertEquals('facet2:value2', $filters[1]);
    }

    /**
     * Test default settings
     *
     * @return void
     */
    public function testDefaultSettings()
    {
        //[backend]:[limit]:[display mode]:[random mode]:[minimumset]:[facet1]:[facetvalue1]
        $this->recommend->setConfig('');
        $this->assertEquals(
            'Solr',
            $this->getProperty($this->recommend, 'backend')
        );
        $this->assertEquals(
            '10',
            $this->getProperty($this->recommend, 'limit')
        );
        $this->assertEquals(
            'standard',
            $this->getProperty($this->recommend, 'displayMode')
        );
        $this->assertEquals(
            'retain',
            $this->getProperty($this->recommend, 'mode')
        );
        $this->assertEquals(
            '0',
            $this->getProperty($this->recommend, 'minimum')
        );
        $this->assertEquals(
            [],
            $this->getProperty($this->recommend, 'filters')
        );
    }

    /**
     * Test initialisation
     *
     * @return void
     */
    public function testCanInitialise()
    {
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $paramManager = $this->createMock(\VuFind\Search\Params\PluginManager::class);
        $recommend = new Random($service, $paramManager);

        // Use Solr since some Base components are abstract:
        $params = $this->getSolrParams();
        $query = $this->unserializeFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock(\Laminas\Stdlib\Parameters::class);

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($this->createMock(\VuFindSearch\Response\RecordCollectionInterface::class)));

        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\RandomCommand::class
                && $command->getTargetIdentifier() === 'Solr'
                && $command->getArguments()[0]->getAllTerms() === 'john smith'
                && $command->getArguments()[1] === 10
                && $command->getArguments()[2]->getArrayCopy() ===
                    ['spellcheck' => ['true'],
                    'fq' => ['facet1:"value1"', 'facet2:"value2"'],
                    'hl' => ['false']];
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));

        $recommend->setConfig('Solr:10:mixed:retain:20:facet1:value1:facet2:value2');
        $recommend->init($params, $request);
    }

    /**
     * Test initialisation
     *
     * @return void
     */
    public function testCanInitialiseInDisregardMode()
    {
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $paramManager = $this->getMockBuilder(\VuFind\Search\Params\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $recommend = new Random($service, $paramManager);

        $params = $this->getSolrParams();

        $paramManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->will($this->returnValue($params));

        // Use Solr since some Base components are abstract:
        $query = $this->unserializeFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock(\Laminas\Stdlib\Parameters::class);

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($this->createMock(\VuFindSearch\Response\RecordCollectionInterface::class)));

        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\RandomCommand::class
                && $command->getTargetIdentifier() === 'Solr'
                && $command->getArguments()[0]->getAllTerms() === 'john smith'
                && $command->getArguments()[1] === 10
                && $command->getArguments()[2]->getArrayCopy() ===
                    ['spellcheck' => ['true'],
                    'fq' => ['facet1:"value1"',
                    'facet2:"value2"'], 'hl' => ['false']];
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));

        $recommend->setConfig('Solr:10:mixed:disregard:20:facet1:value1:facet2:value2');
        $recommend->init($params, $request);
    }

    /**
     * Get a module configured to return results.
     *
     * @param string $recConfig Recommendation module configuration
     *
     * @return Random
     */
    protected function getConfiguredModule($recConfig): Random
    {
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $paramManager = $this->createMock(\VuFind\Search\Params\PluginManager::class);
        $recommend = new Random($service, $paramManager);
        $records = ['1', '2', '3', '4', '5'];

        // Use Solr since some Base components are abstract:
        $results = $this->getSolrResults();
        $params = $results->getParams();
        $query = $this->unserializeFixture('query');
        $params->setBasicSearch($query->getString(), $query->getHandler());
        $request = $this->createMock(\Laminas\Stdlib\Parameters::class);

        $results = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->getMock();
        $results->expects($this->once())->method('getRecords')
            ->will($this->returnValue($records));

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($results));

        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\RandomCommand::class
                && $command->getTargetIdentifier() === 'Solr'
                && $command->getArguments()[0]->getAllTerms() === 'john smith'
                && $command->getArguments()[1] === 10
                && $command->getArguments()[2]->getArrayCopy() ===
                    ['spellcheck' => ['true'],
                    'fq' => ['facet1:"value1"',
                    'facet2:"value2"'], 'hl' => ['false']];
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));

        $recommend->setConfig($recConfig);
        $recommend->init($params, $request);
        $recommend->process($results);
        return $recommend;
    }

    /**
     * Test minimum result limit feature
     *
     * @return void
     */
    public function testWillReturnEmptyForMinimumResultLimit()
    {
        $recommend = $this->getConfiguredModule('Solr:10:mixed:retain:20:facet1:value1:facet2:value2');
        $output = $recommend->getResults();
        $this->assertEmpty($output);
    }

    /**
     * Test results coming back
     *
     * @return void
     */
    public function testWillReturnResults()
    {
        $recommend = $this->getConfiguredModule('Solr:10:mixed:retain:0:facet1:value1:facet2:value2');
        $output = $recommend->getResults();
        $this->assertEquals(['1', '2', '3', '4', '5'], $output);
    }

    /**
     * Test displaymode
     *
     * @return void
     */
    public function testCanSetDisplayMode()
    {
        $this->recommend->setConfig('Solr:10:mixed');
        $this->assertEquals('mixed', $this->recommend->getDisplayMode());
    }

    /**
     * Get a fixture object
     *
     * @param string $file Fixture name
     *
     * @return mixed
     */
    protected function unserializeFixture($file)
    {
        return unserialize($this->getFixture("searches/basic/$file"));
    }
}
