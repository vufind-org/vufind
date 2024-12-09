<?php

/**
 * Record Driver Marc Traits Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2022.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver\Feature;

use PHPUnit\Framework\MockObject\MockObject;
use VuFindTest\RecordDriver\MarcBasicTraitTestHarness;

/**
 * Record Driver Marc Traits Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcBasicTraitTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test methods in MarcBasicTrait.
     *
     * @return void
     */
    public function testMarcBasicTrait()
    {
        $obj = $this->createMockRecord('marctraits.xml');

        $this->assertEquals(
            ['9783161484100', '9783161484101', '1843560283'],
            $obj->getISBNs()
        );
        $this->assertEquals(
            [
                '0000-1111', '1111-2222', '2222-3333', '3333-4444', '4444-5555',
                '5555-6666', '6666-7777', '7777-8888',
            ],
            $obj->getISSNs()
        );
        $this->assertEquals(['Format 1 Format 2'], $obj->getFormats());
        $this->assertEquals('123', $obj->getUniqueID());
        $this->assertEquals(['c1', 'c3', 'd1'], $obj->getCallNumbers());
        $this->assertEquals(['Author, Test (1800-)'], $obj->getPrimaryAuthors());
        $this->assertEquals(['eng', 'ger'], $obj->getLanguages());
        $this->assertEquals('The Foo: Bar! /', $obj->getTitle());
        $this->assertEquals('The Foo:', $obj->getShortTitle());
        $this->assertEquals('Bar! /', $obj->getSubtitle());
        $this
            ->assertEquals(['Publisher,', 'The Publishers,'], $obj->getPublishers());
        $this->assertEquals(['2020', '2020'], $obj->getPublicationDates());
        $this->assertEquals(['2020-'], $obj->getDateSpan());
        $this
            ->assertEquals(['Testcorp', 'Foobar Inc.'], $obj->getCorporateAuthors());
        $this->assertEquals([], $obj->getSecondaryAuthors());
        $this->assertEquals(['New Journal'], $obj->getNewerTitles());
        $this->assertEquals(['Old Journal'], $obj->getPreviousTitles());
        $this->assertEquals('2nd ed.', $obj->getEdition());
        $this->assertEquals(
            ['1 book : colored, 28 cm 1 cd'],
            $obj->getPhysicalDescriptions()
        );
    }

    /**
     * Test methods in MarcBasicTrait with missing fields.
     *
     * @return void
     */
    public function testMarcBasicTraitMissingFields()
    {
        $obj = $this->createMockRecord('marctraitsempty.xml');

        $this->assertSame([], $obj->getFormats());
        $this->assertSame([], $obj->getCallNumbers());
        $this->assertSame([], $obj->getISBNs());
        $this->assertSame([], $obj->getISSNs());
        $this->assertSame([], $obj->getPrimaryAuthors());
        $this->assertSame('', $obj->getTitle());
    }

    /**
     * Create mock record
     *
     * @param string $fixture Record metadata fixture
     *
     * @return MarcBasicTraitTestHarness&MockObject
     */
    protected function createMockRecord(string $fixture): MarcBasicTraitTestHarness&MockObject
    {
        $xml = $this->getFixture("marc/$fixture");
        $record = new \VuFind\Marc\MarcReader($xml);
        $obj = $this->getMockBuilder(MarcBasicTraitTestHarness::class)
            ->onlyMethods(['getMarcReader'])->getMock();
        $obj->expects($this->any())
            ->method('getMarcReader')
            ->willReturn($record);
        return $obj;
    }
}
