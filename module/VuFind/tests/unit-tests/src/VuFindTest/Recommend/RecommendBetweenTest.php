<?php

/**
 * Between recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\View\Helper\Root\RecommendBetween;

/**
 * Between recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecommendBetweenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getMaxScoreDiff
     *
     * @return void
     */
    public function testMaxScoreDiff()
    {
        $between = $this->getRecommendBetween();
        $this->assertEquals(3, $between->getMaxScoreDiffIndex([100, null, 98, 50, 49, null, 48]));
    }

    /**
     * Get a Between recommendation module
     *
     * @return RecommendBetween
     */
    protected function getRecommendBetween()
    {
        return new RecommendBetween();
    }
}
