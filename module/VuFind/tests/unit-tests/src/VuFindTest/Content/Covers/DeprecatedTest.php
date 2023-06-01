<?php

/**
 * Unit tests for Deprecated cover loader.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFindCode\ISBN;

/**
 * Unit tests for Deprecated cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DeprecatedTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the handler never actually does anything.
     *
     * @return void
     */
    public function testEverythingDisabled(): void
    {
        $sizes = ['small', 'medium', 'large'];
        foreach ($sizes as $size) {
            $this->assertFalse($this->getUrl($size));
        }
    }

    /**
     * Simulate retrieval of a cover URL for a particular size.
     *
     * @param string $size Size to retrieve
     * @param string $isbn ISBN to retrieve (empty for none)
     *
     * @return string|bool
     */
    protected function getUrl($size, $isbn = '0739313126')
    {
        $deprecated = new \VuFind\Content\Covers\Deprecated();
        $params = empty($isbn) ? [] : ['isbn' => new ISBN($isbn)];
        return $deprecated->getUrl('fakekey', $size, $params);
    }
}
