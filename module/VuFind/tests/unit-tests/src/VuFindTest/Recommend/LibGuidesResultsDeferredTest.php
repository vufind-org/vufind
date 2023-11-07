<?php

/**
 * LibGuidesResultsDeferred recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

/**
 * LibGuidesResultsDeferred recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LibGuidesResultsDeferredTest extends \VuFindTest\Unit\RecommendDeferredTestCase
{
    /**
     * Test standard operation
     *
     * @return void
     */
    public function testStandardOperation()
    {
        $mod = $this->getRecommend(
            \VuFind\Recommend\LibGuidesResultsDeferred::class,
            ':3',
            new \Laminas\Stdlib\Parameters(['lookfor' => 'foo'])
        );
        $this->assertEquals(
            'mod=LibGuidesResults&params=lookfor%3A3&lookfor=foo',
            $mod->getUrlParams()
        );
    }
}
