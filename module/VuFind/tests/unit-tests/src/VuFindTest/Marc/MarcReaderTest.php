<?php
/**
 * MarcReader Test Class
 *
 * PHP version 7
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
namespace VuFindTest\Marc;

/**
 * MarcReader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcReaderTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test MarcReader methods
     *
     * @return void
     */
    public function testMarcReader()
    {
        $marc = $this->getFixture('marc/marcreader.xml');

        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals([], $reader->getWarnings());

        // Test round-trips
        $reader = new \VuFind\Marc\MarcReader($reader->toFormat('MARCXML'));
        $reader = new \VuFind\Marc\MarcReader($reader->toFormat('ISO2709'));

        $this->assertMatchesRegularExpression(
            '/^\d{5}cam a22\d{5}4i 4500$/',
            $reader->getLeader()
        );
        $this->assertEquals(
            '021122s2020    en            000 0 eng d',
            $reader->getField('008')
        );

        $field = $reader->getField('100');
        foreach ($field['subfields'] as $subfield) {
            $this->assertTrue(is_string($subfield['code']));
        }

        $title = $reader->getField('245');
        $this->assertTrue(is_array($title));
        $this->assertEquals(0, $title['i1']);
        $this->assertEquals(4, $title['i2']);
        $this->assertEquals('The Foo:', $reader->getSubfield($title, 'a'));
        $this->assertEquals(
            '880-01 The Foo: Bar!',
            implode(' ', $reader->getSubfields($title, ''))
        );
        $link = $reader->getFieldLink($title);
        $this->assertEquals(
            [
                'field' => '880',
                'occurrence' => '01',
                'script' => '',
                'orientation' => '',
            ],
            $link
        );
        $linkedTitle = $reader
            ->getLinkedField($link['field'], $title['tag'], $link['occurrence']);
        $this->assertEquals('tHE fOO:', $reader->getSubfield($linkedTitle, 'a'));

        $empty = $reader->getField('246');
        $this->assertEquals([], $empty);
        $this->assertEquals([], $reader->getSubfields($empty, 'a'));

        $field505a = $reader->getFieldsSubfields('505', ['a']);
        $this->assertEquals(['Screenwriting Tip #30;'], $field505a);

        $subjects = $reader->getFields('650');
        $this->assertTrue(is_array($subjects));
        $this->assertEquals(2, count($subjects));
        $this->assertEquals('Foo', $reader->getSubfield($subjects[0], 'a'));
        $this->assertEquals('Bar', $reader->getSubfield($subjects[1], 'a'));

        $this->assertEquals(
            ['Foo test', 'Bar test again'],
            $reader->getFieldsSubfields('650', ['a', 'g'])
        );

        $this->assertEquals(
            ['Foo', 'test', 'Bar', 'test again'],
            $reader->getFieldsSubfields('650', ['a', 'g'], null)
        );

        $this->assertEquals([], $reader->getFieldsSubfields('008', ['a']));

        $altNote = $reader->getLinkedField('880', '500');
        $this->assertEquals(
            [
                '500-00/Foo',
                'Non-linked 880a',
                'Non-linked 880b',
            ],
            $reader->getSubfields($altNote)
        );
        $altNote = $reader->getLinkedField('880', '500', '', ['a']);
        $this->assertEquals(
            [
                'Non-linked 880a',
            ],
            $reader->getSubfields($altNote)
        );
        $this->assertEquals([], $reader->getLinkedField('880', '500', '4'));
        $this->assertEquals([], $reader->getLinkedField('008', '900'));

        $this->assertEquals(
            ['tHE fOO: bAR!'],
            $reader->getLinkedFieldsSubfields('880', '245', ['a', 'b'])
        );

        $this->assertEquals(
            ['tHE fOO:', 'bAR!'],
            $reader->getLinkedFieldsSubfields('880', '245', ['a', 'b'], null)
        );
    }

    /**
     * Test empty subfield in ISO2709
     *
     * @return void
     */
    public function testEmptySubfieldInSO2709()
    {
        $marc = "00047       00037       245000900000\x1e  \x1faFoo\x1f\x1e\x1d";

        $reader = new \VuFind\Marc\MarcReader($marc);
        $field = $reader->getField('245');
        $this->assertEquals([], $reader->getSubfields($field, 'b'));
    }

    /**
     * Test empty subfield in MARCXML serialization
     *
     * @return void
     */
    public function testEmptySubfieldInMarcXmlSerialization()
    {
//        $marc = "00047       00037       245000900000\x1e  \x1faFoo\x1f\x1e\x1d";
        $input = <<<EOT
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00047       00037       </leader>
    <datafield tag="245" ind1=" " ind2=" ">
      <subfield code="a">Foo</subfield>
      <subfield code="b"></subfield>
    </datafield>
  </record>
</collection>
EOT;

        $expected = <<<EOT
<collection xmlns="http://www.loc.gov/MARC21/slim"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd"
>
  <record>
    <leader>00047       00037       </leader>
    <datafield tag="245" ind1=" " ind2=" ">
      <subfield code="a">Foo</subfield>
    </datafield>
  </record>
</collection>
EOT;

        $reader = new \VuFind\Marc\MarcReader($input);
        $this->assertXmlStringEqualsXmlString(
            $expected,
            $reader->toFormat('MARCXML')
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

        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals(
            ['Invalid MARC record (end of field not found)'],
            $reader->getWarnings()
        );
    }

    /**
     * Test records too large for ISO2709
     *
     * @return void
     */
    public function testTooLargeForISO2709()
    {
        // A single too long field
        $longField = str_pad('Foo', 10000) . 'Bar';
        $marc = '<record><datafield tag="245"><subfield code="a">' . $longField
            . '</subfield></datafield></record>';

        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals('', $reader->toFormat('ISO2709'));
        $this->assertTrue($reader->toFormat('MARCXML') !== '');

        // Fields that together are too long
        $longishField = str_pad('Foo', 9980) . 'Bar';
        $marc = '<record>';
        $marc .= str_repeat('<datafield tag="650"><subfield code="a">'
            . $longishField . '</subfield></datafield>', 12);
        $marc .= '</record>';
        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals('', $reader->toFormat('ISO2709'));
        $this->assertTrue($reader->toFormat('MARCXML') !== '');

        // Fields that would fit, but exceed maximum record length when leader and
        // directory are included
        $longishField = str_pad('Foo', 9980) . 'Bar';
        $marc = '<record>';
        $marc .= str_repeat('<datafield tag="650"><subfield code="a">'
            . $longishField . '</subfield></datafield>', 10);
        $marc .= '</record>';
        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals('', $reader->toFormat('ISO2709'));
        $this->assertTrue($reader->toFormat('MARCXML') !== '');
    }

    /**
     * Test invalid record format
     *
     * @return void
     */
    public function testBadInputFormat()
    {
        $marc = 'title: foo';

        $this->expectExceptionMessage('MARC record format not recognized');
        new \VuFind\Marc\MarcReader($marc);
    }

    /**
     * Test requesting bad format
     *
     * @return void
     */
    public function testBadOutputFormat()
    {
        $marc = '<record></record>';

        $this->expectExceptionMessage("Unknown MARC format 'foo' requested");
        $reader = new \VuFind\Marc\MarcReader($marc);
        $reader->toFormat('foo');
    }

    /**
     * Test ISO2709 serialization of an invalid field tag
     *
     * @return void
     */
    public function testInvalidTagSerialization()
    {
        $marc = <<<EOT
<record>
  <datafield tag="12">
    <subfield code="a">Foo</subfield>
  </datafield>
  <datafield tag="245">
    <subfield code="a">Bar</subfield>
  </datafield>
</record>
EOT;

        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals(['Foo'], $reader->getFieldsSubfields('12', ['a']));
        $reader2 = new \VuFind\Marc\MarcReader($reader->toFormat('ISO2709'));
        $this->assertEquals([], $reader2->getFieldsSubfields('12', ['a']));
    }

    /**
     * Test field filtering
     *
     * @return void
     */
    public function testFieldFiltering()
    {
        $marc = $this->getFixture('marc/marcreader.xml');
        $marcFiltered = $this->getFixture('marc/marcreader_filtered.xml');

        $reader = new \VuFind\Marc\MarcReader($marc);
        $filtered = $reader->getFilteredRecord(
            [
                [
                    'tag' => '008',
                ],
                [
                    'tag' => '2..',
                    'subfields' => '6',
                ],
                [
                    'tag' => '650',
                    'subfields' => '[7g]',
                ],
                [
                    'tag' => '655',
                    'subfields' => '[ab]',
                ],
                [
                    'tag' => '880',
                ]
            ]
        );

        $this->assertXmlStringEqualsXmlString(
            $marcFiltered,
            $filtered->toFormat('MARCXML')
        );
    }

    /**
     * Test invalid data array handling
     *
     * @return void
     */
    public function testInvalidDataArray()
    {
        $this->expectExceptionMessage("Invalid data array format provided");
        new \VuFind\Marc\MarcReader([]);
    }

    /**
     * Test long record overflowing the maximum ISO2709 record length
     *
     * @return void
     */
    public function testLongISO2709()
    {
        $marc = $this->getFixture('marc/longrecord.mrc');

        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals(
            ['Invalid MARC record (end of field not found)'],
            $reader->getWarnings()
        );

        // Check for correct data in early fields:
        $this->assertEquals(
            ['1569008'],
            $reader->getFields('001')
        );
        $this->assertEquals(
            ['Federal reporter 1st Series (1-300), 2nd (1-830)'],
            $reader->getFieldsSubfields('245', ['a'])
        );
        $this->assertEquals(
            [
                'Law Library',
                'Second Floor',
                'KF105 .F3',
                '33940000424836'
            ],
            $reader->getSubfields($reader->getField('852'))
        );

        // We can't check for e.g. the last 852 since it will be garbled due to the
        // ISO2709 directory overflowing, but at least we can check the field count:
        $fields = $reader->getFields('852');
        $this->assertEquals(2046, count($fields));

        // Test that the records can still be serialized to MARCXML:
        $xml = $reader->toFormat('MARCXML');
        $this->assertIsObject(simplexml_load_string($xml));
    }
}
