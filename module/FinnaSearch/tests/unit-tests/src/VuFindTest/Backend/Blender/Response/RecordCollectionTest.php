<?php

/**
 * Unit tests for Blender record collection
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2017.
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaTest\Backend\Blender\Response;

use PHPUnit\Framework\TestCase;
use FinnaSearch\Backend\Blender\Response\Json\RecordCollection;

/**
 * Unit tests for BrowZine record collection
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionTest extends TestCase
{
    /**
     * Test blending algorithm
     *
     * @return void
     */
    public function testIsPrimaryAtOffset()
    {
        $rc = new RecordCollection(
            [
                'Blending' => [
                    'boostPosition' => 3,
                    'boostCount' => 2
                ]
            ]
        );
        $primaryMap = [
            true,
            true,
            false,
            false,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false
        ];

        foreach ($primaryMap as $offset => $primary) {
            $this->assertEquals(
                $primary,
                $rc->isPrimaryAtOffset($offset, 10),
                "Primary at $offset should be " . ($primary ? 'true' : 'false')
            );
        }


        $rc = new RecordCollection(
            [
                'Blending' => [
                    'boostPosition' => 8,
                    'boostCount' => 2
                ]
            ]
        );
        $primaryMap = [
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            false,
            false,
            true,
            true,
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            false
        ];

        foreach ($primaryMap as $offset => $primary) {
            $this->assertEquals(
                $primary,
                $rc->isPrimaryAtOffset($offset, 10),
                "Primary at $offset should be " . ($primary ? 'true' : 'false')
            );
        }


        $rc = new RecordCollection(
            [
                'Blending' => [
                    'boostPosition' => 15,
                    'boostCount' => 5
                ]
            ]
        );
        $primaryMap = [
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            false,
            false,
            false,
            false,
            false,
            true,
            true,
            true,
            true,
            true,
            true,
            false,
            false,
            false,
            false,
            false
        ];

        foreach ($primaryMap as $offset => $primary) {
            $this->assertEquals(
                $primary,
                $rc->isPrimaryAtOffset($offset, 20),
                "Primary at $offset should be " . ($primary ? 'true' : 'false')
            );
        }

        for ($offset = 40; $offset < 80; $offset++) {
            $primary = floor($offset / 20) % 2 === 0;
            $this->assertEquals(
                $primary,
                $rc->isPrimaryAtOffset($offset, 20),
                "Primary at $offset should be " . ($primary ? 'true' : 'false')
            );
        }
    }
}
