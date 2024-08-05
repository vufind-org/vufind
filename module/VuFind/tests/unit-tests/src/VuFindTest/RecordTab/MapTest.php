<?php

/**
 * Map Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\Map;

/**
 * Map Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MapTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Get a Map object
     *
     * @return Map
     */
    public function getMap()
    {
        $mapTabDisplay = true;
        $basemapOptions = [
            'basemap_url' => 'www.foo.com',
            'basemap_attribution' => 'bar',
        ];
        $mapTabOptions = [
            'displayCoords' => true,
            'mapLabels'     => null,
            'graticule'     => true,
        ];
        $obj = new Map($mapTabDisplay, $basemapOptions, $mapTabOptions);
        return $obj;
    }

    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $obj = $this->getMap();
        $expected = 'Map View';
        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Test if the tab loaded via AJAX.
     *
     * @return void
     */
    public function testSupportsAjax(): void
    {
        $obj = $this->getMap();
        $this->assertFalse($obj->supportsAjax());
    }

    /**
     * Test getting Graticule.
     *
     * @return void
     */
    public function testGetMapGraticule(): void
    {
        $configuredMap = $this->getMap();
        $defaultMap = new Map();
        $this->assertTrue($configuredMap->getMapGraticule());
        $this->assertFalse($defaultMap->getMapGraticule());
    }

    /**
     * Test getting basemap Configuration.
     *
     * @return void
     */
    public function testGetBasemap(): void
    {
        $obj = $this->getMap();
        $expected = ['www.foo.com','bar'];
        $this->assertSame($expected, $obj->getBasemap());
    }

    /**
     * Test if the tab is Active.
     *
     * @return void
     */
    public function testIsActive(): void
    {
        $obj = $this->getMap();
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->exactly(2))->method('tryMethod')
            ->with($this->equalTo('getGeoLocation'))
            ->willReturnOnConsecutiveCalls('555', null);
        $obj->setRecordDriver($recordDriver);
        $this->assertTrue($obj->isActive());
        $this->assertFalse($obj->isActive());
    }

    /**
     * Test get map display coordinates.
     *
     * @return void
     */
    public function testGetDisplayCoords(): void
    {
        $obj = $this->getMap();
        $coordinates = ['00 00 56 56', '45 56 87 89'];
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('tryMethod')
            ->with($this->equalTo('getDisplayCoordinates'))
            ->will($this->returnValue($coordinates));
        $obj->setRecordDriver($recordDriver);
        $value = ['56 00', '89 87 45 56'];
        $this->assertSame($value, $obj->getDisplayCoords());
    }

    /**
     * Test geo-location coordinates.
     *
     * @return void
     */
    public function testGetGeoLocationCoords(): void
    {
        $obj = $this->getMap();
        $coordinates = ['ENVELOPE(25.8,43.9,5.0,4.6)'];
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('tryMethod')
            ->with($this->equalTo('getGeoLocation'))
            ->will($this->returnValue($coordinates));
        $obj->setRecordDriver($recordDriver);
        $value = [[25.8,4.6,43.9,5.0]];
        $this->assertSame($value, $obj->getGeoLocationCoords());
    }

    /**
     * Test construction of map-coordinates adn labels.
     *
     * @return void
     */
    public function testGetMapTabData(): void
    {
        $obj = $this->getMap();
        $coordinates = ['ENVELOPE(25.8,43.9,5.0,4.6)'];
        $displayCoord = ['45 56 87 89'];
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectConsecutiveCalls(
            $recordDriver,
            'tryMethod',
            [['getGeoLocation'], ['getDisplayCoordinates']],
            [$coordinates, $displayCoord]
        );

        $obj->setRecordDriver($recordDriver);
        $expected = [[25.8,4.6,43.9,5.0,'','89 87 45 56']];
        $this->assertSame($expected, $obj->getMapTabData());
    }
}
