<?php
/**
 * MarcReader Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcReaderTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Test MarcReader methods
     *
     * @return void
     */
    public function testMarcReader()
    {
        $marc = $this->getFixture('marc/marcreader.xml');

        $reader = new \VuFind\Marc\MarcReader($marc);

        // Test round-trips
        $reader = new \VuFind\Marc\MarcReader($reader->toFormat('MARCXML'));
        $reader = new \VuFind\Marc\MarcReader($reader->toFormat('ISO2709'));

        $this->assertRegExp('/^\d{5}cam a22\d{5}4i 4500$/', $reader->getLeader());
        $this->assertEquals(
            '021122s2020    en            000 0 eng d', $reader->getField('008')
        );

        $field = $reader->getField('245');
        $this->assertTrue(is_array($field));
        $this->assertEquals(0, $field['i1']);
        $this->assertEquals(4, $field['i2']);
        $this->assertEquals('The Foo:', $reader->getSubfield($field, 'a'));
        $this->assertEquals(
            'The Foo: Bar!', implode(' ', $reader->getSubfields($field, ''))
        );

        $empty = $reader->getField('246');
        $this->assertEquals([], $empty);
        $this->assertEquals([], $reader->getSubfields($empty, 'a'));

        $subjects = $reader->getFields('650');
        $this->assertTrue(is_array($subjects));
        $this->assertEquals(2, count($subjects));
        $this->assertEquals('Foo', $reader->getSubfield($subjects[0], 'a'));
        $this->assertEquals('Bar', $reader->getSubfield($subjects[1], 'a'));

        $this->assertEquals(
            ['Foo test', 'Bar test again'],
            $reader->getFieldsSubfields(650, ['a', 'g'])
        );
    }

    /**
     * Test invalid XML
     *
     * @return void
     */
    public function testInvalidXml()
    {
        $marc = '<colection><record>Foo</record></collection>';

        $this->expectExceptionMessageMatches(
            '/Error 76: Opening and ending tag mismatch/'
        );
        new \VuFind\Marc\MarcReader($marc);
    }

    /**
     * Test invalid ISO2709
     *
     * @return void
     */
    public function testInvalidISO2709()
    {
        $marc = '00123cam a22000854i 4500';

        $this->expectExceptionMessageMatches(
            '/Invalid MARC record \(end of field not found\)/'
        );
        new \VuFind\Marc\MarcReader($marc);
    }
}
