<?php

/**
 * SimilarItems Test Class
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\ChannelProvider;

use VuFind\ChannelProvider\SimilarItems;
use VuFindSearch\ParamBag;
use VuFindTest\RecordDriver\TestHarness;

/**
 * SimilarItems Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SimilarItemsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test deriving channel information from a record driver object.
     *
     * @return void
     */
    public function testGetFromRecord(): void
    {
        [$similar, $expectedResult] = $this->configureTestTargetAndExpectations();
        $recordDriver = $this->getDriver();
        $this->assertSame($expectedResult, $similar->getFromRecord($recordDriver));
    }

    /**
     * Test deriving channel information from a record driver object when we
     * have a token that do not match record driver.
     *
     * @return void
     */
    public function testGetFromRecordWhenChannelTokenIsSet(): void
    {
        $similar = $this->getSimilarItems()['similar'];
        $recordDriver = $this->getDriver();
        $this->assertSame([], $similar->getFromRecord($recordDriver, 'foo_Token'));
    }

    /**
     * Test deriving channel information from a search results object.
     *
     * @return void
     */
    public function testGetFromSearch(): void
    {
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver = $this->getDriver();
        $results->expects($this->once())->method('getResults')
            ->willReturn([$recordDriver]);
        [$similar, $expectedResult] = $this->configureTestTargetAndExpectations();
        $this->assertSame($expectedResult, $similar->getFromSearch($results));
    }

    /**
     * Test deriving channel information from a search results object when
     * maxRecordsToExamine is lessthan channels.
     *
     * @return void
     */
    public function testGetFromSearchWhenMaxRecordsIsLessthanChannels(): void
    {
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver = $this->getDriver();
        $results->expects($this->once())->method('getResults')
            ->willReturn([$recordDriver]);
        $similar = $this->getSimilarItems(['maxRecordsToExamine' => 0])['similar'];
        $expectedResult = [[
            'title' => 'Similar Items: foo_Breadcrumb',
            'providerId' => 'foo_ProviderId',
            'links' => [],
            'token' => 'foo_Id',
        ]];
        $similar->setProviderId('foo_ProviderId');
        $this->assertSame($expectedResult, $similar->getFromSearch($results));
    }

    /**
     * Test deriving channel information from a search results object with
     * a specific single channel to load.
     *
     * @return void
     */
    public function testGetFromSearchWhenChannelsIsEmpty(): void
    {
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver = $this->getDriver();
        $results->expects($this->once())->method('getResults')
            ->willReturn([$recordDriver]);
        [$similar, $expectedResult]  = $this->configureTestTargetAndExpectations(
            ['maxRecordsToExamine' => 0],
            true
        );
        $this->assertSame(
            $expectedResult,
            $similar->getFromSearch($results, 'channel_token')
        );
    }

    /**
     * Support method to mock objects.
     *
     * @param array $options                Set options for the provider
     * @param bool  $fetchFromSearchService Flag indicating test case to fetch from
     * search service when the search results do not include object we are looking
     * for
     *
     * @return array
     */
    public function configureTestTargetAndExpectations(
        $options = ['maxRecordsToExamine' => 1],
        $fetchFromSearchService = false
    ) {
        $mockObjects = $this->getSimilarItems($options);
        $similar = $mockObjects['similar'];
        $search = $mockObjects['search'];
        $url = $mockObjects['url'];
        $router = $mockObjects['router'];
        $similar->setProviderId('foo_ProviderId');
        $params = new ParamBag(['rows' => 20]);
        $retrieveParams = new ParamBag();
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $collection = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver = $this->getDriver();
        $router->expects($this->once())->method('getTabRouteDetails')
            ->with($this->equalTo($recordDriver))
            ->willReturn('foo_Route');

        $arguments = ['foo_Id', $params];
        $retrieve =  ['channel_token', $retrieveParams];

        if ($fetchFromSearchService) {
            $class = \VuFindSearch\Command\RetrieveCommand::class;
            $collection->expects($this->once())->method('first')
                ->willReturn($recordDriver);

            $commandObj->expects($this->exactly(2))->method('getResult')
                ->willReturnOnConsecutiveCalls(
                    $collection,
                    [$recordDriver]
                );

            $this->expectConsecutiveCalls(
                $search,
                'invoke',
                [
                    [$this->callback($this->getCommandChecker($retrieve, $class))],
                    [$this->callback($this->getCommandChecker($arguments))],
                ],
                $commandObj
            );
        } else {
            $commandObj->expects($this->once())->method('getResult')
                ->willReturn([$recordDriver]);

            $search->expects($this->once())->method('invoke')
                ->with($this->callback($this->getCommandChecker($arguments)))
                ->willReturn($commandObj);
        }

        $expectedResult = [[
            'title' => 'Similar Items: foo_Breadcrumb',
            'providerId' => 'foo_ProviderId',
            'links' => [
                [
                    'label' => 'View Record',
                    'icon' => 'fa-file-text-o',
                    'url' => 'url_test',
                ],
                [
                    'label' => 'channel_expand',
                    'icon' => 'fa-search-plus',
                    'url' => 'channels-record?id=foo_Id&source=Solr',
                ],
            ],
            'contents' => [[
                'title' => 'foo_Title',
                'source' => 'Solr',
                'thumbnail' => false,
                'routeDetails' => 'foo_Route',
                'id' => 'foo_Id'],
            ],

        ]];
        $routeDetails = ['route' => 'test_route', 'params' => ['id' => 'route_id']];
        $router->expects($this->once())->method('getRouteDetails')
            ->with($this->equalTo($recordDriver))
            ->willReturn($routeDetails);
        $this->expectConsecutiveCalls(
            $url,
            'fromRoute',
            [
                [$this->equalTo($routeDetails['route']), $this->equalTo($routeDetails['params'])],
                [$this->equalTo('channels-record')],
            ],
            [
                'url_test',
                'channels-record',
            ]
        );
        return [$similar, $expectedResult];
    }

    /**
     * Get SimilarItems mock object
     *
     * @param array $options options for the provider
     *
     * @return array
     */
    protected function getSimilarItems($options = [])
    {
        $search = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $url = $this->getMockBuilder(\Laminas\Mvc\Controller\Plugin\Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $router = $this->getMockBuilder(\VuFind\Record\Router::class)
            ->disableOriginalConstructor()
            ->getMock();
        $similar = new SimilarItems($search, $url, $router, $options);

        return compact('search', 'url', 'router', 'similar');
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
        $class = \VuFindSearch\Command\SimilarCommand::class,
        $target = 'Solr'
    ) {
        return function ($command) use ($class, $args, $target) {
            return $command::class === $class
                && $command->getArguments() == $args
                && $command->getTargetIdentifier() === $target;
        };
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
            'Thumbnail' => 'foo_Thumbnail',
            'UniqueID' => 'foo_Id',
            'Breadcrumb' => 'foo_Breadcrumb',
            'SourceIdentifier' => 'Solr',
        ];
        $driver->setRawData($data);
        return $driver;
    }
}
