<?php
/**
 * SolrOverdrive Record Driver Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindTest\RecordDriver;

use Laminas\Config\Config;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\RecordDriver\SolrOverdrive;

/**
 * SolrOverdrive Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrOverdriveTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test supportsOpenUrl()
     *
     * @return void
     */
    public function testSupportsOpenUrl(): void
    {
        // Not supported:
        $this->assertFalse($this->getDriver()->supportsOpenUrl());
        $this->assertFalse($this->getDriver()->supportsCoinsOpenUrl());
    }

    /**
     * Test getOverdriveID in MARC mode
     *
     * @return void
     */
    public function testGetOverdriveIDWithMarc(): void
    {
        $connector = $this->getMockConnector(
            '{ "isMarc": true, "idField": "010", "idSubfield": "a" }'
        );
        $driver = $this->getDriver(null, null, $connector);
        $driver->setRawData(
            ['fullrecord' => $this->getFixture('marc/marctraits.xml')]
        );
        $this->assertEquals('lc123', $driver->getOverdriveID());
    }

    /**
     * Test getOverdriveID in non-MARC mode
     *
     * @return void
     */
    public function testGetOverdriveIDWithoutMarc(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": false }');
        $driver = $this->getDriver(null, null, $connector);
        $driver->setRawData(
            ['id' => 'LC345']
        );
        $this->assertEquals('lc345', $driver->getOverdriveID());
    }

    /**
     * Test getBreadcrumb()
     *
     * @return void
     */
    public function testGetBreadcrumb(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": false }');
        $driver = $this->getDriver(null, null, $connector);
        // Confirm that we use short title when available, title otherwise:
        $driver->setRawData(['title' => 'title : full', 'title_short' => 'title']);
        $this->assertEquals('title', $driver->getBreadcrumb());
        $driver->setRawData(['title' => 'title : full']);
        $this->assertEquals('title : full', $driver->getBreadcrumb());
    }

    /**
     * Test getTitleSection()
     *
     * @return void
     */
    public function testGetTitleSection(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": true }');
        $driver = $this->getDriver(null, null, $connector);
        $driver->setRawData(
            ['fullrecord' => $this->getFixture('marc/marctraits.xml')]
        );
        $this->assertEquals('2. Return', $driver->getTitleSection());
    }

    /**
     * Test getGeneralNotes()
     *
     * @return void
     */
    public function testGetGeneralNotes(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": true }');
        $driver = $this->getDriver(null, null, $connector);
        $driver->setRawData(
            ['fullrecord' => $this->getFixture('marc/marctraits.xml')]
        );
        $this->assertEquals(
            ['General notes here.', 'Translation.'], $driver->getGeneralNotes()
        );
    }

    /**
     * Test getRawData behavior in MARC mode
     *
     * @return void
     */
    public function testGetRawDataMarc(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": true }');
        $driver = $this->getDriver(null, null, $connector);
        $raw = ['foo' => 'bar'];
        $driver->setRawData($raw);
        $this->assertEquals($raw, $driver->getRawData());
    }

    /**
     * Test getRawData behavior in non-MARC mode
     *
     * @return void
     */
    public function testGetRawDataNonMarc(): void
    {
        $connector = $this->getMockConnector('{ "isMarc": false }');
        $driver = $this->getDriver(null, null, $connector);
        $raw = ['foo' => 'bar'];
        $driver->setRawData(['fullrecord' => json_encode($raw)]);
        $this->assertEquals($raw, $driver->getRawData());
    }

    /**
     * Get a record driver to test with.
     *
     * @param Config             $config       Main configuration
     * @param Config             $recordConfig Record configuration
     * @param OverdriveConnector $connector    Overdrive connector
     *
     * @return SolrOverdrive
     */
    protected function getDriver(Config $config = null, Config $recordConfig = null,
        OverdriveConnector $connector = null
    ): SolrOverdrive {
        return new SolrOverdrive(
            $config ?? new Config([]),
            $recordConfig ?? new Config([]),
            $connector ?? $this->getMockConnector()
        );
    }

    /**
     * Get a mock Overdrive connector.
     *
     * @param string $config JSON-formatted configuration
     *
     * @return OverdriveConnector
     */
    protected function getMockConnector(string $config = '{}'): OverdriveConnector
    {
        $connector = $this->getMockBuilder(OverdriveConnector::class)
            ->disableOriginalConstructor()->getMock();
        $connector->expects($this->any())->method('getConfig')
            ->will($this->returnValue(json_decode($config)));
        return $connector;
    }
}
