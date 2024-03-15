<?php

/**
 * AlphaBrowse Test Class
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

use VuFind\ChannelProvider\AlphaBrowse;
use VuFindSearch\ParamBag;
use VuFindTest\RecordDriver\TestHarness;

/**
 * AlphaBrowse Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlphaBrowseTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test deriving channel information from a record driver object.
     *
     * @return void
     */
    public function testGetFromRecord(): void
    {
        $recordDriver = $this->getDriver(['solrField' => 'foo']);
        [$alpha, $expectedResult] = $this->configureTestTargetAndExpectations();
        $this->assertSame($expectedResult, $alpha->getFromRecord($recordDriver));
    }

    /**
     * Test deriving channel information from a record driver object when we
     * have a token that does not match record driver.
     *
     * @return void
     */
    public function testGetFromRecordWhenChannelTokenIsSet(): void
    {
        $alpha = $this->getAlphaBrowse()['alpha'];
        $recordDriver = $this->getDriver();
        $this->assertSame([], $alpha->getFromRecord($recordDriver, 'foo_Token'));
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

        $recordDriver = $this->getDriver(['solrField' => 'foo']);
        $results->expects($this->once())->method('getResults')
            ->willReturn([$recordDriver]);
        [$alpha, $expectedResult] = $this->configureTestTargetAndExpectations();
        $this->assertSame($expectedResult, $alpha->getFromSearch($results));
    }

    /**
     * Test deriving channel information from a search results object when
     * maxRecordsToExamine is lessthan channels.
     *
     * @return void
     */
    public function testGetFromSearchWhenMaxRecordsIsLessthanChannels(): void
    {
        $objects = $this->getAlphaBrowse(['maxRecordsToExamine' => 0]);
        $alpha = $objects['alpha'];
        $alpha->setProviderId('foo_ProviderId');
        $results = $this->getMockBuilder(\VuFind\Search\Base\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver = $this->getDriver(['solrField' => 'foo']);
        $results->expects($this->once())->method('getResults')
            ->willReturn([$recordDriver]);
        $expectedResult = [[
                'title' => 'nearby_items',
                'providerId' => 'foo_ProviderId',
                'links' => [],
                'token' => 'foo_Id',
            ]];
        $this->assertSame($expectedResult, $alpha->getFromSearch($results));
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
        [$alpha, $expectedResult] = $this->configureTestTargetAndExpectations(
            ['maxRecordsToExamine' => 0],
            true
        );
        $this->assertSame(
            $expectedResult,
            $alpha->getFromSearch($results, 'channel_token')
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
        $objects = $this->getAlphaBrowse($options);
        $alpha = $objects['alpha'];
        $search = $objects['search'];
        $url = $objects['url'];
        $router = $objects['router'];
        $alpha->setProviderId('foo_ProviderId');
        $driver = $this->getDriver(['solrField' => 'foo']);

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $details = ['Browse' =>
                        ['items' =>
                           [
                            ['extras' =>
                                ['title' => [['foo_title']],
                                 'id' => [['foo_id']],
                                ],
                            ],
                           ],
                        ],
                    ];

        $params = new ParamBag(['extras' => 'title:author:isbn:id']);
        $alphabeticArgs = ['lcc', 'foo', 0, 20, $params, -10];
        $retrieveBatchArgs = [['foo_id'], new ParamBag()];
        $retrieveArgs = ['channel_token', new ParamBag()];
        $class = \VuFindSearch\Command\RetrieveCommand::class;
        $retrieveBatchClass = \VuFindSearch\Command\RetrieveBatchCommand::class;
        $collection = $this->getMockBuilder(
            \VuFindSearch\Response\RecordCollectionInterface::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        if ($fetchFromSearchService) {
            $collection->expects($this->once())->method('first')
                ->willReturn($driver);

            $commandObj->expects($this->exactly(3))->method('getResult')
                ->willReturnOnConsecutiveCalls(
                    $collection,
                    $details,
                    [$driver]
                );

            $this->expectConsecutiveCalls(
                $search,
                'invoke',
                [
                    [$this->callback(
                        $this->getCommandChecker($retrieveArgs, $class, 'foo_Identifier')
                    )],
                    [$this->callback(
                        $this->getCommandChecker($alphabeticArgs)
                    )],
                    [$this->callback(
                        $this->getCommandChecker($retrieveBatchArgs, $retrieveBatchClass)
                    )],
                ],
                $commandObj
            );
        } else {
            $commandObj->expects($this->exactly(2))->method('getResult')
                ->willReturnOnConsecutiveCalls(
                    $details,
                    [$driver]
                );
            $this->expectConsecutiveCalls(
                $search,
                'invoke',
                [
                    [$this->callback($this->getCommandChecker($alphabeticArgs))],
                    [$this->callback(
                        $this->getCommandChecker($retrieveBatchArgs, $retrieveBatchClass)
                    )],
                ],
                $commandObj
            );
        }

        $coverRouter = $this->getMockBuilder(\VuFind\Cover\Router::class)
            ->disableOriginalConstructor()
            ->getMock();
        $coverRouter->expects($this->once())->method('getUrl')
            ->with($this->equalTo($driver), $this->equalTo('medium'))
            ->willReturn('foo_Thumbnail');
        $alpha->setCoverRouter($coverRouter);
        $routeDetails = ['route' => 'test_route', 'params' => ['id' => 'route_id']];
        $router->expects($this->once())->method('getRouteDetails')
            ->with($this->equalTo($driver))
            ->willReturn($routeDetails);
        $this->expectConsecutiveCalls(
            $url,
            'fromRoute',
            [
                [$routeDetails['route'], $routeDetails['params']],
                ['channels-record'],
                ['alphabrowse-home'],
            ],
            ['url_test', 'channels-record', 'alphabrowse-home']
        );
        $expectedResult = [[
            'title' => 'nearby_items',
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
                    'url' => 'channels-record?id=foo_Id&source=foo_Identifier',
                ],
                [
                    'label' => 'channel_browse',
                    'icon' => 'fa-list',
                    'url' => 'alphabrowse-home?source=lcc&from=foo',
                ],
            ],
            'contents' => [[
                'title' => 'foo_title',
                'source' => 'Solr',
                'thumbnail' => false,
                'id' => 'foo_id'],
            ],
        ]];
        return [$alpha, $expectedResult];
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
        $class = \VuFindSearch\Command\AlphabeticBrowseCommand::class,
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
     * @param array $data Test data (solrField is only supported field)
     *
     * @return TestHarness
     */
    protected function getDriver($data = [])
    {
        $driver = new TestHarness();
        $data = [
            'Title' => 'foo_Title',
            'SourceIdentifier' => 'foo_Identifier',
            'Thumbnail' => 'foo_Thumbnail',
            'UniqueID' => 'foo_Id',
            'callnumber-raw' => $data['solrField'] ?? null,
        ];
        $driver->setRawData($data);
        return $driver;
    }

    /**
     * Get AlphaBrowse object
     *
     * @param array $options options for the provider
     *
     * @return array
     */
    protected function getAlphaBrowse($options = [])
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
        $alpha = new AlphaBrowse($search, $url, $router, $options);

        return compact('search', 'url', 'router', 'alpha');
    }
}
