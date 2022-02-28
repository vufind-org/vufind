<?php
/**
 * MarcCollection Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
namespace VuFindTest\Marc;

use VuFind\Marc\MarcCollection;

/**
 * MarcCollection Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcCollectionTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Return collection fixtures for the tests
     *
     * @return array
     */
    public function collectionFixtures(): array
    {
        return [
            ['marc/marc_collection.xml'],
            ['marc/marc_collection.mrc'],
            ['marc/marc_collection.json'],
        ];
    }

    /**
     * Test MarcCollectionReader
     *
     * @dataProvider collectionFixtures
     *
     * @return void
     */
    public function testMarcCollection($fixture)
    {
        $marc = $this->getFixture($fixture);

        $collection = new MarcCollection($marc);

        $this->assertTrue($collection->valid());
        $collection->rewind();
        $this->assertTrue($collection->valid());
        $this->assertEquals(0, $collection->key());

        $record = $collection->current();
        $title = $record->getField('245');
        $this->assertEquals('The Foo', $record->getSubfield($title, 'a'));

        $collection->next();
        $this->assertTrue($collection->valid());
        $this->assertEquals(1, $collection->key());
        $record = $collection->current();
        $title = $record->getField('245');
        $this->assertEquals('The Bar', $record->getSubfield($title, 'a'));

        $collection->next();
        $this->assertFalse($collection->valid());
    }

    /**
     * Test bad collection format
     *
     * @return void
     */
    public function testBadCollectionFormat()
    {
        $this->expectExceptionMessage('MARC collection format not recognized');
        new MarcCollection('foo');
    }

    /**
     * Test empty MarcCollectionReader
     *
     * @return void
     */
    public function testEmptyMarcCollection()
    {
        $collection = new MarcCollection();
        $this->assertFalse($collection->valid());
    }
}
