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
use VuFind\View\FlashMessenger\FlashMessengerInterface;

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
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetSingleHiddenFilter()
    {
        $newItems = $this->getNewItemsHelper(['filter' => 'a:b']);
        $this->assertSame(['a:b'], $newItems->getHiddenFilters());
    }

    /**
     * Test a single hidden filter.
     *
     * @return void
     */
    public function testGetMultipleHiddenFilters()
    {
        $newItems = $this->getNewItemsHelper(['filter' => ['a:b', 'b:c']]);
        $this->assertSame(['a:b', 'b:c'], $newItems->getHiddenFilters());
    }

    /**
     * Test various default values.
     *
     * @return void
     */
    public function testDefaults()
    {
        $newItems = $this->getNewItemsHelper([]);
        $this->assertSame([], $newItems->getHiddenFilters());
        $this->assertSame('ils', $newItems->getMethod());
        $this->assertSame(30, $newItems->getMaxAge());
        $this->assertSame([1, 5, 30], $newItems->getRanges());
        $this->assertSame(10, $newItems->getResultPages());
    }

    /**
     * Test custom range settings.
     *
     * @return void
     */
    public function testCustomRanges()
    {
        $newItems = $this->getNewItemsHelper(['ranges' => '10,150,300']);
        $this->assertSame([10, 150, 300], $newItems->getRanges());
    }

    /**
     * Test custom result pages setting.
     *
     * @return void
     */
    public function testCustomResultPages()
    {
        $newItems = $this->getNewItemsHelper(['result_pages' => '2']);
        $this->assertSame(2, $newItems->getResultPages());
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
        $this->assertSame(10, $newItems->getResultPages());
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
        $this->assertSame($expected, $newItems->getSolrFilter($range));
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
