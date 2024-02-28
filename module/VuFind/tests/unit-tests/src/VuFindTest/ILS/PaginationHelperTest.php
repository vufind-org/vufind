<?php

/**
 * Pagination helper test
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS;

use VuFind\ILS\PaginationHelper;

/**
 * Pagination helper test
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PaginationHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test options supporting pagination
     *
     * @return void
     */
    public function testPaginationSupported()
    {
        $helper = new PaginationHelper();

        $functionConfig = [
            'max_results' => 100,
            'sort' => [
                '-due_date' => 'sort_due_date_desc',
                '+due_date' => 'sort_due_date_asc',
                '+title' => 'sort_title',
            ],
            'default_sort' => '+due_date',
        ];

        // Typical first page
        $result = $helper->getOptions(1, null, 50, $functionConfig);
        $this->assertTrue($result['ilsPaging']);
        $this->assertIsArray($result['ilsParams']);
        $this->assertEquals(1, $result['ilsParams']['page']);
        $this->assertEquals(50, $result['ilsParams']['limit']);
        $this->assertEquals('+due_date', $result['ilsParams']['sort']);
        $this->assertIsArray($result['sortList']);
        $this->assertEquals(
            [
                '-due_date' => [
                    'desc' => 'sort_due_date_desc',
                    'url' => '?sort=' . urlencode('-due_date'),
                    'selected' => false,
                ],
                '+due_date' => [
                    'desc' => 'sort_due_date_asc',
                    'url' => '?sort=' . urlencode('+due_date'),
                    'selected' => true,
                ],
                '+title' => [
                    'desc' => 'sort_title',
                    'url' => '?sort=' . urlencode('+title'),
                    'selected' => false,
                ],
            ],
            $result['sortList']
        );

        // Second page and sort
        $result = $helper->getOptions(2, '+title', 50, $functionConfig);
        $this->assertTrue($result['ilsPaging']);
        $this->assertIsArray($result['ilsParams']);
        $this->assertEquals(2, $result['ilsParams']['page']);
        $this->assertEquals(50, $result['ilsParams']['limit']);
        $this->assertEquals('+title', $result['ilsParams']['sort']);
        $this->assertIsArray($result['sortList']);
        $this->assertEquals(
            [
                '-due_date' => [
                    'desc' => 'sort_due_date_desc',
                    'url' => '?sort=' . urlencode('-due_date'),
                    'selected' => false,
                ],
                '+due_date' => [
                    'desc' => 'sort_due_date_asc',
                    'url' => '?sort=' . urlencode('+due_date'),
                    'selected' => false,
                ],
                '+title' => [
                    'desc' => 'sort_title',
                    'url' => '?sort=' . urlencode('+title'),
                    'selected' => true,
                ],
            ],
            $result['sortList']
        );

        // Page size limit and invalid sort
        $result = $helper->getOptions(1, 'foo', 150, $functionConfig);
        $this->assertTrue($result['ilsPaging']);
        $this->assertIsArray($result['ilsParams']);
        $this->assertEquals(1, $result['ilsParams']['page']);
        $this->assertEquals(100, $result['ilsParams']['limit']);
        $this->assertEquals('+due_date', $result['ilsParams']['sort']);
        $this->assertIsArray($result['sortList']);
        $this->assertEquals(
            [
                '-due_date' => [
                    'desc' => 'sort_due_date_desc',
                    'url' => '?sort=' . urlencode('-due_date'),
                    'selected' => false,
                ],
                '+due_date' => [
                    'desc' => 'sort_due_date_asc',
                    'url' => '?sort=' . urlencode('+due_date'),
                    'selected' => true,
                ],
                '+title' => [
                    'desc' => 'sort_title',
                    'url' => '?sort=' . urlencode('+title'),
                    'selected' => false,
                ],
            ],
            $result['sortList']
        );
    }

    /**
     * Test options not supporting pagination
     *
     * @return void
     */
    public function testPaginationNotSupported()
    {
        $helper = new PaginationHelper();

        $functionConfig = false;

        // Typical first page
        $result = $helper->getOptions(1, null, 50, $functionConfig);
        $this->assertFalse($result['ilsPaging']);
        $this->assertIsArray($result['ilsParams']);
        $this->assertEmpty($result['ilsParams']);
        $this->assertIsArray($result['sortList']);
        $this->assertEmpty($result['sortList']);

        // Second page and sort
        $result = $helper->getOptions(2, '+title', 50, $functionConfig);
        $this->assertFalse($result['ilsPaging']);
        $this->assertIsArray($result['ilsParams']);
        $this->assertEmpty($result['ilsParams']);
        $this->assertIsArray($result['sortList']);
        $this->assertEmpty($result['sortList']);
    }
}
