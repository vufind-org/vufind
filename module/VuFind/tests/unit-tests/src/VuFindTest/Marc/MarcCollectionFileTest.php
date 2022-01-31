<?php
/**
 * MarcCollectionFile Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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

use VuFind\Marc\MarcCollectionFile;

/**
 * MarcCollectionFile Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcCollectionFileTest extends \PHPUnit\Framework\TestCase
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
            ['marc/marc_collection_ns.xml'],
            ['marc/marc_collection.mrc']
        ];
    }

    /**
     * Test MarcCollectionReader
     *
     * @dataProvider collectionFixtures
     *
     * @return void
     */
    public function testMarcCollectionFile($fixture)
    {
        $file = $this->getFixturePath($fixture);

        $collection = new MarcCollectionFile($file);

        $record = $collection->current();
        $title = $record->getField('245');
        $this->assertEquals('The Foo', $record->getSubfield($title, 'a'));

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
     * Test non-existent collection file
     *
     * @return void
     */
    public function testNonExistentCollectionFile()
    {
        $file = $this->getFixturePath('marc/marc_collection.xml')
            . '.does_not_exist';
        $this->expectExceptionMessage("File '$file' does not exist");
        new MarcCollectionFile($file);
    }

    /**
     * Test bad collection file format
     *
     * @return void
     */
    public function testBadCollectionFileFormat()
    {
        $file = $this->getFixturePath('marc/invalid_marc_collection.xml');
        $this->expectExceptionMessage('MARC collection file format not recognized');
        new MarcCollectionFile($file);
    }

    /**
     * Test empty collection file
     *
     * @return void
     */
    public function testEmptyCollectionFile()
    {
        $file = $this->getFixturePath('marc/empty.mrc');
        $this->expectExceptionMessage('MARC collection file format not recognized');
        new MarcCollectionFile($file);
    }

    /**
     * Test error handling of the ISO2709 serialization canParseCollectionFile method
     *
     * @return void
     */
    public function testISO2709SerializationErrorHandling()
    {
        $file = $this->getFixturePath('marc/marc_collection.mrc')
            . '.does_not_exist';
        $this->expectExceptionMessage("Cannot open file '$file' for reading");
        @\VuFind\Marc\Serialization\Iso2709::canParseCollectionFile($file);
    }

    /**
     * Test error handling of the MARCXML serialization canParseCollectionFile method
     *
     * @return void
     */
    public function testMARCXMLSerializationErrorHandling()
    {
        $file = $this->getFixturePath('marc/marc_collection.xml')
            . '.does_not_exist';
        $this->expectExceptionMessage("Cannot open file '$file' for reading");
        @\VuFind\Marc\Serialization\MarcXml::canParseCollectionFile($file);
    }

    /**
     * Test error handling of the ISO2709 serialization openCollectionFile method
     *
     * @return void
     */
    public function testISO2709SerializationOpenFileErrorHandling()
    {
        $file = $this->getFixturePath('marc/marc_collection.mrc')
            . '.does_not_exist';
        $this->expectExceptionMessage("Cannot open file '$file' for reading");
        $handler = new \VuFind\Marc\Serialization\Iso2709();
        @$handler->openCollectionFile($file);
    }

    /**
     * Test error handling of the MARCXML serialization openCollectionFile method
     *
     * @return void
     */
    public function testMARCXMLSerializationOpenFileErrorHandling()
    {
        $file = $this->getFixturePath('marc/marc_collection.xml')
            . '.does_not_exist';
        $this->expectExceptionMessage("Cannot open file '$file' for reading");
        $handler = new \VuFind\Marc\Serialization\MarcXml();
        @$handler->openCollectionFile($file);
    }

    /**
     * Test message callback
     *
     * @return void
     */
    public function testMessageCallback()
    {
        $file = $this->getFixturePath('marc/marc_collection_ns.xml');
        $messages = [];
        $callback = function (string $msg, int $level) use (&$messages) {
            $messages[] = compact('msg', 'level');
        };
        $collection = new MarcCollectionFile($file, $callback);
        while ($collection->valid()) {
            $collection->next();
        }

        $this->assertEquals(
            [
                [
                    'msg' => 'Unknown namespace "http://vufind.org/bad" for element "/collection/record"',
                    'level' => E_NOTICE
                ],
                [
                    'msg' => 'Unknown element "/collection/item"',
                    'level' => E_NOTICE
                ],
            ],
            $messages
        );
    }
}
