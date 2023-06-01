<?php

/**
 * New items controller plugin tests.
 *
 * PHP version 8
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

namespace VuFindTest\Controller\Plugin;

use Laminas\Config\Config;
use VuFind\Controller\Plugin\NewItems;

/**
 * New items controller plugin tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NewItemsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test ILS bib ID retrieval.
     *
     * @return void
     */
    public function testGetBibIDsFromCatalog()
    {
        $flash = $this->createMock(\Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger::class);
        $config = new Config(['result_pages' => 10]);
        $newItems = new NewItems($config);
        $bibs = $newItems->getBibIDsFromCatalog(
            $this->getMockCatalog(),
            $this->getMockParams(),
            10,
            'a',
            $flash
        );
        $this->assertEquals([1, 2], $bibs);
    }

    /**
     * Test ILS bib ID retrieval with ID limit.
     *
     * @return void
     */
    public function testGetBibIDsFromCatalogWithIDLimit()
    {
        $flash = $this->createMock(\Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger::class);
        $flash->expects($this->once())->method('addMessage')
            ->with($this->equalTo('too_many_new_items'), $this->equalTo('info'));
        $config = new Config(['result_pages' => 10]);
        $newItems = new NewItems($config);
        $bibs = $newItems->getBibIDsFromCatalog(
            $this->getMockCatalog(),
            $this->getMockParams(1),
            10,
            'a',
            $flash
        );
        $this->assertEquals([1], $bibs);
    }

    /**
     * Test default ILS getFunds() behavior.
     *
     * @return void
     */
    public function testGetFundList()
    {
        $catalog = $this->getMockBuilder(\VuFind\ILS\Connection::class)
            ->onlyMethods(['checkCapability'])
            ->addMethods(['getFunds'])
            ->disableOriginalConstructor()
            ->getMock();
        $catalog->expects($this->once())->method('checkCapability')
            ->with($this->equalTo('getFunds'))->will($this->returnValue(true));
        $catalog->expects($this->once())->method('getFunds')
            ->will($this->returnValue(['a', 'b', 'c']));
        $controller = $this->getMockBuilder(\VuFind\Controller\SearchController::class)
            ->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('getILS')
            ->will($this->returnValue($catalog));
        $newItems = new NewItems(new Config([]));
        $newItems->setController($controller);
        $this->assertEquals(['a', 'b', 'c'], $newItems->getFundList());
    }

    /**
     * Test getFundList() in non-ILS mode.
     *
     * @return void
     */
    public function testGetFundListWithoutILS()
    {
        $newItems = new NewItems(new Config(['method' => 'solr']));
        $this->assertEquals([], $newItems->getFundList());
    }

    /**
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetSingleHiddenFilter()
    {
        $config = new Config(['filter' => 'a:b']);
        $newItems = new NewItems($config);
        $this->assertEquals(['a:b'], $newItems->getHiddenFilters());
    }

    /**
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetMultipleHiddenFilters()
    {
        $config = new Config(['filter' => ['a:b', 'b:c']]);
        $newItems = new NewItems($config);
        $this->assertEquals(['a:b', 'b:c'], $newItems->getHiddenFilters());
    }

    /**
     * Test various default values.
     *
     * @return void
     */
    public function testDefaults()
    {
        $config = new Config([]);
        $newItems = new NewItems($config);
        $this->assertEquals([], $newItems->getHiddenFilters());
        $this->assertEquals('ils', $newItems->getMethod());
        $this->assertEquals(30, $newItems->getMaxAge());
        $this->assertEquals([1, 5, 30], $newItems->getRanges());
        $this->assertEquals(10, $newItems->getResultPages());
    }

    /**
     * Test custom range settings.
     *
     * @return void
     */
    public function testCustomRanges()
    {
        $config = new Config(['ranges' => '10,150,300']);
        $newItems = new NewItems($config);
        $this->assertEquals([10, 150, 300], $newItems->getRanges());
    }

    /**
     * Test custom result pages setting.
     *
     * @return void
     */
    public function testCustomResultPages()
    {
        $config = new Config(['result_pages' => '2']);
        $newItems = new NewItems($config);
        $this->assertEquals(2, $newItems->getResultPages());
    }

    /**
     * Test illegal result pages setting.
     *
     * @return void
     */
    public function testIllegalResultPages()
    {
        $config = new Config(['result_pages' => '-2']);
        $newItems = new NewItems($config);
        // expect a default of 10 if a bad value was passed in
        $this->assertEquals(10, $newItems->getResultPages());
    }

    /**
     * Test Solr filter generator.
     *
     * @return void
     */
    public function testGetSolrFilter()
    {
        $range = 30;
        $expected = 'first_indexed:[NOW-' . $range . 'DAY TO NOW]';
        $newItems = new NewItems(new Config([]));
        $this->assertEquals($expected, $newItems->getSolrFilter($range));
    }

    /**
     * Get a mock catalog object (for use in getBibIDs tests).
     *
     * @return \VuFind\ILS\Connection
     */
    protected function getMockCatalog(): \VuFind\ILS\Connection
    {
        $catalog = $this->getMockBuilder(\VuFind\ILS\Connection::class)
            ->addMethods(['getNewItems'])
            ->disableOriginalConstructor()
            ->getMock();
        $catalog->expects($this->once())->method('getNewItems')
            ->with(
                $this->equalTo(1),
                $this->equalTo(200),
                $this->equalTo(10),
                $this->equalTo('a')
            )
            ->will(
                $this->returnValue(
                    ['results' => [['id' => 1], ['id' => 2]]]
                )
            );
        return $catalog;
    }

    /**
     * Get a mock params object.
     *
     * @param int $idLimit Mock ID limit value
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($idLimit = 1024)
    {
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->once())->method('getLimit')
            ->will($this->returnValue(20));
        $params->expects($this->once())->method('getQueryIDLimit')
            ->will($this->returnValue($idLimit));
        return $params;
    }
}
