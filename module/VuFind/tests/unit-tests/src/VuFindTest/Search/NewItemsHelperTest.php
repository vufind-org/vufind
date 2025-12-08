<?php

/**
 * New items controller plugin tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search;

use VuFind\ILS\Connection;
use VuFind\Search\NewItemsHelper;

/**
 * New items controller plugin tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NewItemsHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a NewItemsHelper instance for testing.
     *
     * @param array           $config     Configuration array
     * @param Connection|null $connection ILS connection
     *
     * @return NewItemsHelper
     */
    protected function getNewItemsHelper(array $config = [], ?Connection $connection = null): NewItemsHelper
    {
        return new NewItemsHelper($config, $connection ?? $this->createMock(Connection::class));
    }

    /**
     * Test ILS bib ID retrieval.
     *
     * @return void
     */
    public function testGetBibIDsFromCatalog()
    {
        $flash = $this->createMock(\Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger::class);
        $newItems = $this->getNewItemsHelper(['result_pages' => 10], $this->getMockCatalog());
        $bibs = $newItems->getBibIDsFromCatalog(
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
        $newItems = $this->getNewItemsHelper(['result_pages' => 10], $this->getMockCatalog());
        $bibs = $newItems->getBibIDsFromCatalog(
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
        $catalog = $this->createMock(Connection::class);
        $catalog->expects($this->once())->method('checkCapability')
            ->with($this->equalTo('getFunds'))->willReturn(true);
        $catalog->expects($this->once())->method('__call')
            ->willReturnCallback(
                fn ($method) => $method === 'getFunds' ? ['a', 'b', 'c'] : null
            );

        $newItems = $this->getNewItemsHelper([], $catalog);
        $this->assertEquals(['a', 'b', 'c'], $newItems->getFundList());
    }

    /**
     * Test getFundList() in non-ILS mode.
     *
     * @return void
     */
    public function testGetFundListWithoutILS()
    {
        $newItems = $this->getNewItemsHelper(['method' => 'solr']);
        $this->assertEquals([], $newItems->getFundList());
    }

    /**
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetSingleHiddenFilter()
    {
        $newItems = $this->getNewItemsHelper(['filter' => 'a:b']);
        $this->assertEquals(['a:b'], $newItems->getHiddenFilters());
    }

    /**
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetMultipleHiddenFilters()
    {
        $newItems = $this->getNewItemsHelper(['filter' => ['a:b', 'b:c']]);
        $this->assertEquals(['a:b', 'b:c'], $newItems->getHiddenFilters());
    }

    /**
     * Test various default values.
     *
     * @return void
     */
    public function testDefaults()
    {
        $newItems = $this->getNewItemsHelper([]);
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
        $newItems = $this->getNewItemsHelper(['ranges' => '10,150,300']);
        $this->assertEquals([10, 150, 300], $newItems->getRanges());
    }

    /**
     * Test custom result pages setting.
     *
     * @return void
     */
    public function testCustomResultPages()
    {
        $newItems = $this->getNewItemsHelper(['result_pages' => '2']);
        $this->assertEquals(2, $newItems->getResultPages());
    }

    /**
     * Test illegal result pages setting.
     *
     * @return void
     */
    public function testIllegalResultPages()
    {
        $newItems = $this->getNewItemsHelper(['result_pages' => '-2']);
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
        $newItems = $this->getNewItemsHelper([]);
        $this->assertEquals($expected, $newItems->getSolrFilter($range));
    }

    /**
     * Get a mock catalog object (for use in getBibIDs tests).
     *
     * @return Connection
     */
    protected function getMockCatalog(): Connection
    {
        $catalog = $this->createMock(Connection::class);

        $catalog->expects($this->once())->method('__call')
            ->willReturnCallback(
                function ($method, $args) {
                    if ($method !== 'getNewItems') {
                        return null;
                    }
                    $this->assertEquals(1, $args[0]);
                    $this->assertEquals(200, $args[1]);
                    $this->assertEquals(10, $args[2]);
                    $this->assertEquals('a', $args[3]);

                    return ['results' => [['id' => 1], ['id' => 2]]];
                }
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
        $params = $this->createMock(\VuFind\Search\Solr\Params::class);
        $params->expects($this->once())->method('getLimit')
            ->willReturn(20);
        $params->expects($this->once())->method('getQueryIDLimit')
            ->willReturn($idLimit);
        return $params;
    }
}
