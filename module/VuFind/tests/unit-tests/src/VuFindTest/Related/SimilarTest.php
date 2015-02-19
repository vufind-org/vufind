<?php
/**
 * Similar Related Items Test Class
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
use VuFind\Related\Similar;

/**
 * Similar Related Items Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SimilarTest extends \VuFindTest\Unit\TestCase
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
        $driver = $this->getMock('VuFind\RecordDriver\SolrDefault', ['getUniqueId']);
        $driver->expects($this->once())
            ->method('getUniqueId')
            ->will($this->returnValue('fakeid'));
        $service = $this->getMock('VuFindSearch\Service', ['similar']);
        $service->expects($this->once())
            ->method('similar')
            ->with($this->equalTo('Solr'), $this->equalTo('fakeid'))
            ->will($this->returnValue(['fakeresponse']));

        $similar = new Similar($service);
        $similar->init('', $driver);
        $this->assertEquals(['fakeresponse'], $similar->getResults());
    }
}