<?php

/**
 * NewSearchItems Test Class
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\ChannelProvider;

use VuFind\ChannelProvider\NewSearchItems;
use VuFind\Controller\Plugin\NewItems;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindTest\RecordDriver\TestHarness;

/**
 * NewSearchItems Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NewSearchItemsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test deriving channel information from a record driver object.
     *
     * @return void
     */
    public function testGetFromRecord(): void
    {
        [$newSearchItems, $expectedResult] = $this->setUpTestInputsAndExpectations();
        $recordDriver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $recordDriver->expects($this->once())->method('getSourceIdentifier')->willReturn('Solr');
        $this->assertSame($expectedResult, $newSearchItems->getFromRecord($recordDriver));
    }

    /**
     * Test deriving channel information from a search results object.
     *
     * @return void
     */
    public function testGetFromSearch(): void
    {
        $results = $this->createMock(\VuFind\Search\Base\Results::class);
        [$newSearchItems, $expectedResult, $params] = $this->setUpTestInputsAndExpectations();
        $results->expects($this->once())->method('getParams')->willReturn($params);
        $this->assertSame($expectedResult, $newSearchItems->getFromSearch($results));
    }

    /**
     * Support method to mock objects.
     *
     * @return array
     */
    public function setUpTestInputsAndExpectations()
    {
        $query = new Query('bar');
        $paramBag = new ParamBag(['far' => 'rar']);
        $params = $this->getConfiguredParamsMock($query, $paramBag);
        $search = $this->createMock(\VuFindSearch\Service::class);
        $paramManager = $this->createMock(\VuFind\Search\Params\PluginManager::class);
        $newItemsHelper = $this->createMock(NewItems::class);
        $newItemsHelper->expects($this->once())->method('getSolrFilter')->with(30)->willReturn('foo-filter');
        $options = ['mode' => 'notRetain'];
        $newSearchItems =  new NewSearchItems($search, $paramManager, $newItemsHelper, $options);
        $paramManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->willReturn($params);
        $commandObj = $this->createMock(\VuFindSearch\Command\AbstractBase::class);
        $rci = $this->createMock(\VuFindSearch\Response\RecordCollectionInterface::class);
        $commandObj->expects($this->once())->method('getResult')
            ->willReturn($rci);
        $recordDriver = $this->getDriver();
        $rci->expects($this->once())->method('getRecords')
            ->willReturn([$recordDriver]);
        $search->expects($this->once())->method('invoke')
            ->with($this->callback($this->getCommandChecker([$query, 0, 20, $paramBag])))
            ->willReturn($commandObj);
        $expectedResult = [[
            'title' => 'New Items',
            'providerId' => 'foo_ProviderID',
            'contents' => [[
                'title' => 'foo_Title',
                'source' => 'foo_Identifier',
                'thumbnail' => 'foo_Thumbnail',
                'routeDetails' => 'foo_Route',
                'id' => 'foo_Id',
            ]],
        ]];
        $newSearchItems->setProviderId('foo_ProviderID');
        $coverRouter = $this->getConfiguredCoverRouterMock($recordDriver);
        $recordRouter = $this->getConfiguredRecordRouterMock($recordDriver);
        $newSearchItems->setCoverRouter($coverRouter);
        $newSearchItems->setRecordRouter($recordRouter);
        return [$newSearchItems, $expectedResult, $params];
    }

    /**
     * Get a configured cover router mock.
     *
     * @param mixed $recordDriver expected input record driver for getUrl method.
     *
     * @return MockObject
     */
    protected function getConfiguredCoverRouterMock($recordDriver)
    {
        $coverRouter = $this->createMock(\VuFind\Cover\Router::class);
        $coverRouter->expects($this->once())->method('getUrl')
            ->with($this->equalTo($recordDriver), $this->equalTo('medium'))
            ->willReturn('foo_Thumbnail');
        return $coverRouter;
    }

    /**
     * Get a configured record router mock.
     *
     * @param mixed $recordDriver expected input record driver for
     * getTabRouteDetails method.
     *
     * @return MockObject
     */
    protected function getConfiguredRecordRouterMock($recordDriver)
    {
        $recordRouter = $this->createMock(\VuFind\Record\Router::class);
        $recordRouter->expects($this->once())->method('getTabRouteDetails')
            ->with($this->equalTo($recordDriver))
            ->willReturn('foo_Route');
        return $recordRouter;
    }

    /**
     * Support method to test callbacks.
     *
     * @param array  $args   Command arguments
     * @param string $class  Command class
     * @param string $target Target identifier
     *
     * @return callable
     */
    protected function getCommandChecker(
        $args = [],
        $class = \VuFindSearch\Command\SearchCommand::class,
        $target = 'Solr'
    ) {
        return function ($command) use ($class, $args, $target) {
            return $command::class === $class
                && $command->getArguments() == $args
                && $command->getTargetIdentifier() === $target;
        };
    }

    /**
     * Get a configured parameters object mock.
     *
     * @param \VuFindSearch\Query\Query $query    Search query object to be
     * returned by getQuery method.
     * @param \VuFindSearch\ParamBag    $paramBag Request parameters to be returned by
     * getBackendParameters method.
     *
     * @return MockObject
     */
    protected function getConfiguredParamsMock($query, $paramBag)
    {
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $params->expects($this->once())->method('getQuery')->willReturn($query);
        $params->expects($this->once())->method('getBackendParameters')->willReturn($paramBag);
        $params->expects($this->atMost(2))->method('getSearchClassId')->willReturn('Solr');
        $params->expects($this->once())->method('addHiddenFilter')->with('foo-filter');
        return $params;
    }

    /**
     * Get a fake record driver
     *
     * @return TestHarness
     */
    protected function getDriver()
    {
        $driver = new TestHarness();
        $data = [
            'Title' => 'foo_Title',
            'SourceIdentifier' => 'foo_Identifier',
            'Thumbnail' => 'foo_Thumbnail',
            'UniqueID' => 'foo_Id',
        ];
        $driver->setRawData($data);
        return $driver;
    }
}
