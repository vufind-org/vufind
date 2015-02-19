<?php
/**
 * WorldCat Similar Related Items Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Related;
use VuFind\Related\WorldCatSimilar, VuFindSearch\Query\Query;

/**
 * WorldCat Similar Related Items Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class WorldCatSimilarTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test results.
     *
     * @return void
     */
    public function testGetResults()
    {
        $driver = $this->getMock(
            'VuFind\RecordDriver\WorldCat',
            ['tryMethod', 'getPrimaryAuthor', 'getAllSubjectHeadings', 'getTitle', 'getUniqueId', 'getResourceSource']
        );
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
            ->method('getResourceSource')
            ->will($this->returnValue('WorldCat'));
        $service = $this->getMock('VuFindSearch\Service', ['search']);
        $expectedQuery = new Query('(srw.dd any "fakedc" or srw.au all "fakepa" or srw.su all "fakesh1a fakesh1b" or srw.su all "fakesh2" or srw.ti any "faketitle") not srw.no all "fakeid"');
        $response = $this->getMock('VuFindSearch\Backend\WorldCat\Response\XML\RecordCollection', ['getRecords'], [['offset' => 0, 'total' => 0]]);
        $response->expects($this->once())
            ->method('getRecords')
            ->will($this->returnValue(['fakeresponse']));
        $service->expects($this->once())
            ->method('search')
            ->with($this->equalTo('WorldCat'), $this->equalTo($expectedQuery), $this->equalTo(0), $this->equalTo(5))
            ->will($this->returnValue($response));

        $similar = new WorldCatSimilar($service);
        $similar->init('', $driver);
        $this->assertEquals(['fakeresponse'], $similar->getResults());
    }
}