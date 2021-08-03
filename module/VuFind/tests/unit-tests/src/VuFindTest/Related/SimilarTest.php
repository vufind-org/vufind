<?php
/**
 * Similar Related Items Test Class
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Related;

use VuFind\Related\Similar;

/**
 * Similar Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SimilarTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test results.
     *
     * @return void
     */
    public function testGetResults()
    {
        // Similar is really just a thin wrapper around the search service; make
        // sure it does its job properly with the help of some mocks.
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods(['getUniqueId'])
            ->getMock();
        $driver->expects($this->once())
            ->method('getUniqueId')
            ->will($this->returnValue('fakeid'));
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->onlyMethods(['similar'])
            ->getMock();
        $service->expects($this->once())
            ->method('similar')
            ->with($this->equalTo('Solr'), $this->equalTo('fakeid'))
            ->will($this->returnValue(['fakeresponse']));

        $similar = new Similar($service);
        $similar->init('', $driver);
        $this->assertEquals(['fakeresponse'], $similar->getResults());
    }
}
