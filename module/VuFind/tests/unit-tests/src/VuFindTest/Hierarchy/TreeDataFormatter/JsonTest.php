<?php

/**
 * Hierarchy TreeDataFormatter Json Test Class
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
 * @author   Mathias Maaß <mathias.maass@uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Hierarchy\TreeDataFormatter;

use VuFind\Hierarchy\TreeDataFormatter\Json;

/**
 * Hierarchy TreeDataFormatter Json Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class JsonTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Tests method `getHierarchyPositionsInParents`.
     *
     * @return void
     */
    public function testGetHierarchyPositionsInParents()
    {
        $method = 'getHierarchyPositionsInParents';

        /**
         * If `validateHierarchySequences` is set to false
         * and fields have the same length then return the
         * correctly ordered position array.
         */

        $fields = (object)[
            'hierarchy_parent_id' => [1, 2],
            'hierarchy_sequence' => [3, 4],
        ];
        $json = new Json(false);
        $result = $this->callMethod($json, $method, [$fields]);
        $this->assertEquals($result, [
            1 => 3,
            2 => 4,
        ]);

        /**
         * If `validateHierarchySequences` is set to true
         * and fields have the same length then return the
         * correctly ordered position array.
         */

        $fields = (object)[
            'hierarchy_parent_id' => [1, 2],
            'hierarchy_sequence' => [3, 4],
        ];
        $json = new Json(true);
        $result = $this->callMethod($json, $method, [$fields]);
        $this->assertEquals($result, [
            1 => 3,
            2 => 4,
        ]);

        /**
         * If `validateHierarchySequences` is set to false
         * and `hierarchy_parent_id` is larger the `hierarchy_sequence`
         * then return an empty array.
         */

        $fields = (object)[
            'hierarchy_parent_id' => [1, 2, 3],
            'hierarchy_sequence' => [4, 5],
        ];
        $json = new Json(false);
        $result = $this->callMethod($json, $method, [$fields]);
        $this->assertEquals($result, []);

        /**
         * If `validateHierarchySequences` is set to true
         * and `hierarchy_parent_id` is larger the `hierarchy_sequence`
         * then throw an exception.
         */

        $fields = (object)[
            'hierarchy_parent_id' => [1, 2, 3],
            'hierarchy_sequence' => [4, 5],
        ];
        $json = new Json(true);
        $this->expectException(\Exception::class);
        $this->callMethod($json, $method, [$fields]);
    }
}
