<?php
/**
 * Random Test Class
 *
 * PHP version 7
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

use VuFind\ChannelProvider\Random;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindTest\RecordDriver\TestHarness;

/**
 * Random Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RandomTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test deriving channel information from a record driver object.
     *
     * @return void
     */
    public function testGetFromRecord(): void
    {
        $parameters = $this->supportMethod();
        $random = $parameters[0];
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\AbstractBase::class)
            ->getMock();
        $recordDriver->expects($this->once())->method('getSourceIdentifier')
            ->willReturn('Solr');
        $this->assertSame([$parameters[1]], $random->getFromRecord($recordDriver));
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
        $parameters = $this->supportMethod();

        $results->expects($this->once())->method('getParams')
            ->willReturn($parameters[2]);

        $random = $parameters[0];
        $this->assertSame([$parameters[1]], $random->getFromSearch($results));
    }

    /**
     * Support method to mock objects.
     *
     * @return array
     */
    public function supportMethod()
    {
        $params = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuery', 'getSearchClassId'])
            ->addMethods(['getBackendParameters'])
            ->getMock();
        $search = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paramManager = $this->getMockBuilder(\VuFind\Search\Params\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $options = ['mode' => 'notRetain'];
        $random =  new Random($search, $paramManager, $options);
        $query = new Query('bar');
        $paramBag = new ParamBag(['far' => 'rar']);
        $paramManager->expects($this->once())->method('get')
            ->with($this->equalTo('Solr'))
            ->willReturn($params);
        $params->expects($this->once())->method('getQuery')
            ->willReturn($query);
        $params->expects($this->once())->method('getBackendParameters')
            ->willReturn($paramBag);
        $params->expects($this->any())->method('getSearchClassId')
            ->willReturn('Solr');
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rci = $this->getMockBuilder(\VuFindSearch\Response\RecordCollectionInterface::class)
            ->disableOriginalConstructor()->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($rci));
        $recordDriver = $this->getDriver();
        $rci->expects($this->once())->method('getRecords')
            ->will($this->returnValue([$recordDriver]));
        $checkCommand = function ($command) use ($paramBag, $query) {
            return get_class($command) == \VuFindSearch\Command\RandomCommand::class
                && $command->getTargetIdentifier() == "Solr"
                && $command->getArguments()[0] == $query
                && $command->getArguments()[1] == 20
                && $command->getArguments()[2] == $paramBag;
        };
        $search->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        $expectedResult = [
                'title' => 'random_recommendation_title',
                'providerId' => 'Test_ProviderID',
                'contents' => [[
                    'title' => 'Test_Title',
                    'source' => 'Test_Identifier',
                    'thumbnail' => 'Test_Thumbnail',
                    'routeDetails' => 'Test_Route',
                    'id' => 'Test_Id',
                ]]
        ];
        $random->setProviderId('Test_ProviderID');
        $coverRouter = $this->getMockBuilder(\VuFind\Cover\Router::class)
            ->disableOriginalConstructor()
            ->getMock();
        $coverRouter->expects($this->once())->method('getUrl')
            ->with($this->equalTo($recordDriver), $this->equalTo('medium'))
            ->willReturn('Test_Thumbnail');
        $recordRouter = $this->getMockBuilder(\VuFind\Record\Router::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordRouter->expects($this->once())->method('getTabRouteDetails')
            ->with($this->equalTo($recordDriver))
            ->willReturn('Test_Route');
        $random->setCoverRouter($coverRouter);
        $random->setRecordRouter($recordRouter);
        return [$random, $expectedResult, $params];
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
            'Title' => 'Test_Title',
            'SourceIdentifier' => 'Test_Identifier',
            'Thumbnail' => 'Test_Thumbnail',
            'UniqueID' => 'Test_Id'
        ];
        $driver->setRawData($data);
        return $driver;
    }
}
