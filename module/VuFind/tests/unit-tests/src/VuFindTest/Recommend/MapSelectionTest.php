<?php

/**
 * MapSelection recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Recommend;

use VuFind\Recommend\MapSelection;
use VuFindSearch\Service;

/**
 * MapSelection recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MapSelectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock search service.
     *
     * @return Service
     */
    protected function getMockSearchService(): Service
    {
        return $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * Get the class to test.
     *
     * @param Service $ss Search service
     *
     * @return MapSelection
     */
    protected function getMapSelection(Service $ss = null): MapSelection
    {
        $defaultBasemapOptions = [
            'basemap_url' => 'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
            'basemap_attribution' => '<a href="https://wikimediafoundation.org/'
                . 'wiki/Maps_Terms_of_Use">Wikimedia</a> | &copy; <a '
                . 'href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        ];
        $defaultMapSelectionOptions = [
            'default_coordinates' => '-95, 30, 72, 15',
            'height' => '320',
        ];
        return new MapSelection(
            $ss ?? $this->getMockSearchService(),
            $defaultBasemapOptions,
            $defaultMapSelectionOptions
        );
    }

    /**
     * Test getter for geofield
     *
     * @return void
     */
    public function testGetGeoField(): void
    {
        $this->assertEquals('long_lat', $this->getMapSelection()->getGeoField());
    }

    /**
     * Test getter for height
     *
     * @return void
     */
    public function testGetHeight(): void
    {
        $this->assertEquals(320, $this->getMapSelection()->getHeight());
    }

    /**
     * Test getter for default coordinates.
     *
     * @return void
     */
    public function testGetDefaultCoordinates(): void
    {
        $this->assertEquals(
            [-95, 30, 72, 15],
            $this->getMapSelection()->getDefaultCoordinates()
        );
    }

    /**
     * Test getter for basemap
     *
     * @return void
     */
    public function testGetBasemap(): void
    {
        $this->assertEquals(
            [
                'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
                '<a href="https://wikimediafoundation.org/'
                . 'wiki/Maps_Terms_of_Use">Wikimedia</a> | &copy; <a '
                . 'href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            ],
            $this->getMapSelection()->getBasemap()
        );
    }
}
