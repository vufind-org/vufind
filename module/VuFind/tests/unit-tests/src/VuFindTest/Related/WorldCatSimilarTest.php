<?php
/**
 * WorldCat Similar Related Items Test Class
 *
 * PHP version 7
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Related;

use VuFind\Related\WorldCatSimilar;

/**
 * WorldCat Similar Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WorldCatSimilarTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test results.
     *
     * @return void
     */
    public function testGetResults()
    {
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\WorldCat::class)
            ->onlyMethods(
                [
                    'tryMethod',
                    'getPrimaryAuthor',
                    'getAllSubjectHeadings',
                    'getTitle',
                    'getUniqueId',
                    'getSourceIdentifier'
                ]
            )->getMock();
        $driver->expects($this->once())
            ->method('tryMethod')
            ->with($this->equalTo('getDeweyCallNumber'))
            ->will($this->returnValue('fakedc'));
        $driver->expects($this->once())
            ->method('getPrimaryAuthor')
            ->will($this->returnValue('fakepa'));
        $driver->expects($this->once())
            ->method('getAllSubjectHeadings')
            ->will($this->returnValue([['fakesh1a', 'fakesh1b'], ['fakesh2']]));
        $driver->expects($this->once())
            ->method('getTitle')
            ->will($this->returnValue('faketitle'));
        $driver->expects($this->once())
            ->method('getUniqueId')
            ->will($this->returnValue('fakeid'));
        $driver->expects($this->once())
            ->method('getSourceIdentifier')
            ->will($this->returnValue('WorldCat'));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response = $this->getMockBuilder(\VuFindSearch\Backend\WorldCat\Response\XML\RecordCollection::class)
            ->onlyMethods(['getRecords'])
            ->setConstructorArgs([['offset' => 0, 'total' => 0]])
            ->getMock();
        $response->expects($this->once())
            ->method('getRecords')
            ->will($this->returnValue(['fakeresponse']));

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($response));

        $checkCommand = function ($command) {
            $expectedTerms = '(srw.dd any "fakedc" or srw.au all "fakepa" or '
                . 'srw.su all "fakesh1a fakesh1b" or srw.su all "fakesh2" or '
                . 'srw.ti any "faketitle") not srw.no all "fakeid"';
            return get_class($command) === \VuFindSearch\Command\SearchCommand::class
                && $command->getTargetIdentifier() === "WorldCat"
                && $command->getArguments()[0]->getAllTerms() === $expectedTerms
                && $command->getArguments()[1] === 0
                && $command->getArguments()[2] === 5;
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));

        $similar = new WorldCatSimilar($service);
        $similar->init('', $driver);
        $this->assertEquals(['fakeresponse'], $similar->getResults());
    }
}
