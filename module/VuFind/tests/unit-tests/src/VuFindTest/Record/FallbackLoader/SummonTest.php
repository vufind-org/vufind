<?php

/**
 * Summon fallback loader test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021, 2022.
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
namespace VuFindTest\Record\FallbackLoader;

use SerialsSolutions\Summon\Laminas as Connector;
use VuFind\Record\FallbackLoader\Summon;
use VuFindSearch\ParamBag;

/**
 * Summon fallback loader test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SummonTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the fallback loader.
     *
     * @return void
     */
    public function testLoader(): void
    {
        $record = $this->getMockBuilder(\VuFind\RecordDriver\Summon::class)
            ->disableOriginalConstructor()->getMock();
        $record->expects($this->once())->method('setPreviousUniqueId')
            ->with($this->equalTo('oldId'));
        $record->expects($this->once())->method('getUniqueId')
            ->will($this->returnValue('newId'));
        $collection = new \VuFindSearch\Backend\Summon\Response\RecordCollection(
            ['recordCount' => 1]
        );
        $collection->add($record);
        $expectedParams = new ParamBag(
            ['summonIdType' => Connector::IDENTIFIER_BOOKMARK]
        );
        $search = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($collection));
        $checkCommand = function ($command) use ($expectedParams) {
            return get_class($command) === \VuFindSearch\Command\RetrieveCommand::class
                && $command->getTargetIdentifier() === "Summon"
                && $command->getArguments()[0] === 'bar'
                && $command->getArguments()[1] == $expectedParams;
        };
        $search->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));

        $resource = $this->getMockBuilder(\VuFind\Db\Table\Resource::class)
            ->disableOriginalConstructor()->getMock();
        $resource->expects($this->once())->method('updateRecordId')
            ->with(
                $this->equalTo('oldId'),
                $this->equalTo('newId'),
                $this->equalTo('Summon')
            );
        $row = $this->getMockBuilder(\VuFind\Db\Row\Resource::class)
            ->disableOriginalConstructor()->getMock();
        $row->expects($this->once())->method('__get')
            ->with($this->equalTo('extra_metadata'))
            ->will($this->returnValue('{ "bookmark": "bar" }'));
        $resource->expects($this->once())->method('findResource')
            ->with($this->equalTo('oldId'), $this->equalTo('Summon'))
            ->will($this->returnValue($row));
        $loader = new Summon($resource, $search);
        $this->assertEquals([$record], $loader->load(['oldId']));
    }
}
