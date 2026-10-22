<?php

/**
 * Unit tests for the RecordData cover loader.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2025.
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
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Content\Covers\RecordData;
use VuFind\Record\Loader;
use VuFind\RecordDriver\DefaultRecord;
use VuFind\RecordDriver\SolrMarc;

/**
 * Unit tests for the RecordData cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordDataTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test that the loader only supports record-based IDs.
     *
     * @return void
     */
    public function testSupports(): void
    {
        $loader = new RecordData($this->createMock(Loader::class));
        $this->assertFalse($loader->supports([]));
        $this->assertFalse($loader->supports(['isbn' => '123']));
        $this->assertTrue($loader->supports(['recordid' => '123']));
    }

    /**
     * Test that caching is allowed.
     *
     * @return void
     */
    public function testIsCacheAllowed(): void
    {
        $loader = new RecordData($this->createMock(Loader::class));
        $this->assertTrue($loader->isCacheAllowed());
    }

    /**
     * Test that getUrl() returns the cover URL from the record driver.
     *
     * @return void
     */
    public function testGetUrlReturnsCoverUrl(): void
    {
        $driver = $this->createMarcDriver('marccover.xml');
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->expects($this->once())
            ->method('load')->with('123', 'Solr')->willReturn($driver);
        $loader = new RecordData($recordLoader);
        $this->assertSame(
            'https://example.org/cover.jpg',
            $loader->getUrl(null, 'small', ['recordid' => '123', 'source' => 'Solr'])
        );
    }

    /**
     * Test that getUrl() falls back to the default source when none is given.
     *
     * @return void
     */
    public function testGetUrlDefaultSource(): void
    {
        $driver = $this->createMarcDriver('marccover.xml');
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->expects($this->once())
            ->method('load')->with('123', DEFAULT_SEARCH_BACKEND)->willReturn($driver);
        $loader = new RecordData($recordLoader);
        $this->assertSame(
            'https://example.org/cover.jpg',
            $loader->getUrl(null, 'small', ['recordid' => '123'])
        );
    }

    /**
     * Test that getUrl() returns false when the record has no cover.
     *
     * @return void
     */
    public function testGetUrlNoCover(): void
    {
        $driver = $this->createMarcDriver('marctraitsempty.xml');
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->method('load')->willReturn($driver);
        $loader = new RecordData($recordLoader);
        $this->assertFalse(
            $loader->getUrl(null, 'small', ['recordid' => '123', 'source' => 'Solr'])
        );
    }

    /**
     * Test that getUrl() returns false when the driver has no
     * getExternalCoverImageUrl() method (e.g. a non-MARC record driver).
     *
     * @return void
     */
    public function testGetUrlDriverWithoutCoverMethod(): void
    {
        $driver = new DefaultRecord();
        $recordLoader = $this->createMock(Loader::class);
        $recordLoader->method('load')->willReturn($driver);
        $loader = new RecordData($recordLoader);
        $this->assertFalse(
            $loader->getUrl(null, 'small', ['recordid' => '123', 'source' => 'Solr'])
        );
    }

    /**
     * Create a mock record driver from a MARC fixture.
     *
     * @param string $fixture Record metadata fixture
     *
     * @return SolrMarc&MockObject
     */
    protected function createMarcDriver(string $fixture): SolrMarc&MockObject
    {
        $record = new \VuFind\Marc\MarcReader($this->getFixture("marc/$fixture"));
        $obj = $this->getMockBuilder(SolrMarc::class)
            ->onlyMethods(['getMarcReader', 'getUniqueId'])->getMock();
        $obj->method('getMarcReader')->willReturn($record);
        $obj->method('getUniqueId')->willReturn('123');
        return $obj;
    }
}
