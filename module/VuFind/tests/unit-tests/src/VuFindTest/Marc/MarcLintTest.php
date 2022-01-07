<?php
/**
 * MarcLint Test Class
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

use VuFind\Marc\MarcCollection;
use VuFind\Marc\MarcLint;
use VuFind\Marc\MarcReader;

/**
 * MarcLint Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarcLintTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test check020 method
     *
     * @param string $expected Expected output
     * @param string $input    Input
     *
     * @dataProvider get020TestData
     *
     * @return void
     */
    public function testCheck020($expected, $input)
    {
        $record = new MarcReader("<record>$input</record>");
        $lint = new MarcLint();
        $this->callMethod($lint, 'check020', [$record->getField('020'), $record]);
        $this->assertEquals(
            $expected,
            $this->getProperty($lint, 'warnings')
        );
    }

    /**
     * Data provider for testCheck020
     *
     * @return array
     */
    public function get020TestData()
    {
        return [
            [
                ['020: Subfield a has the wrong number of digits, 154879473.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">154879473</subfield></datafield>' // too few digits
            ],
            [
                ['020: Subfield a has bad checksum, 1548794743.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1548794743</subfield></datafield>' // invalid checksum
            ],
            [
                ['020: Subfield a has the wrong number of digits, 15487947443.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">15487947443</subfield></datafield>' // 11 digits
            ],
            [
                ['020: Subfield a has the wrong number of digits, 15487947443324.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">15487947443324</subfield></datafield>' // 14 digits
            ],
            [
                [],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">9781548794743</subfield></datafield>' // 13 digit valid
            ],
            [
                ['020: Subfield a has bad checksum (13 digit), 9781548794745.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">9781548794745</subfield></datafield>' // 13 digit invalid
            ],
            [
                [],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1548794740 (10 : good checksum)</subfield></datafield>' // 10 digit valid with qualifier
            ],
            [
                ['020: Subfield a has bad checksum, 1548794745 (10 : bad checksum).'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1548794745 (10 : bad checksum)</subfield></datafield>' // 10 digit invalid with qualifier
            ],
            [
                ['020: Subfield a may have invalid characters.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1-54879-474-0 (hyphens and good checksum)</subfield></datafield>' // 10 digit invalid with hyphens and qualifier
            ],
            [
                [
                    '020: Subfield a may have invalid characters.',
                    '020: Subfield a has bad checksum, 1-54879-474-5 (hyphens and bad checksum).'
                ],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1-54879-474-5 (hyphens and bad checksum)</subfield></datafield>' // 10 digit invalid with hyphens and qualifier
            ],
            [
                ['020: Subfield a qualifier must be preceded by space, 1548794740(10 : unspaced qualifier).'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1548794740(10 : unspaced qualifier)</subfield></datafield>' // 10 valid without space before qualifier
            ],
            [
                [
                    '020: Subfield a qualifier must be preceded by space, 1548794745(10 : unspaced qualifier : bad checksum).',
                    '020: Subfield a has bad checksum, 1548794745(10 : unspaced qualifier : bad checksum).'
                ],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="a">1548794745(10 : unspaced qualifier : bad checksum)</subfield></datafield>' // 10 invalid without space before qualifier
            ],
            [
                [],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="z">1548794743</subfield></datafield>' // subfield z
            ],
            [
                ['020:  Subfield z is numerically valid.'],
                '<datafield tag="020" ind1=" " ind2=" "><subfield code="z">ISBN 1548794740</subfield></datafield>' // subfield z with valid checsum
            ],
        ];
    }

    /**
     * Test check041 method
     *
     * @param string $expected Expected output
     * @param string $input    Input
     *
     * @dataProvider get041TestData
     *
     * @return void
     */
    public function testCheck041($expected, $input)
    {
        $record = new MarcReader("<record>$input</record>");
        $lint = new MarcLint();
        $this->callMethod($lint, 'check041', [$record->getField('041'), $record]);
        $this->assertEquals(
            $expected,
            $this->getProperty($lint, 'warnings')
        );
    }

    /**
     * Data provider for testCheck041
     *
     * @return array
     */
    public function get041TestData()
    {
        return [
            [
                [
                    '041: Subfield _a, end (end), is not valid.',
                    '041: Subfield _a must be evenly divisible by 3 or exactly three characters if ind2 is not 7, (span).',
                    '041: Subfield _h, far, may be obsolete.',
                ],
                <<<EOT
<datafield tag="041" ind1="0" ind2=" ">
    <subfield code="a">end</subfield>  <!-- invalid -->
    <subfield code="a">span</subfield> <!-- too long -->
    <subfield code="h">far</subfield>  <!-- obsolete -->
</datafield>
EOT
            ],
            [
                [
                    '041: Subfield _a, endorviwo (end), is not valid.',
                    '041: Subfield _a, endorviwo (orv), is not valid.',
                    '041: Subfield _a, endorviwo (iwo), is not valid.',
                    '041: Subfield _a must be evenly divisible by 3 or exactly three characters if ind2 is not 7, (spanowpalasba).',
                ],
                <<<EOT
<datafield tag="041" ind1="1" ind2=" ">
    <subfield code="a">endorviwo</subfield>     <!-- invalid -->
    <subfield code="a">spanowpalasba</subfield> <!-- too long and invalid -->
</datafield>
EOT
            ],
        ];
    }

    /**
     * Test check043 method
     *
     * @return void
     */
    public function testCheck043()
    {
        $xml = <<<EOT
<record>
    <datafield tag="043" ind1=" " ind2=" ">
        <subfield code="a">n-----</subfield>   <!-- 6 chars vs. 7 -->
        <subfield code="a">n-us----</subfield> <!-- 8 chars vs. 7 -->
        <subfield code="a">n-ma-us</subfield>  <!-- invalid code -->
        <subfield code="a">e-ur-ai</subfield>  <!-- obsolete code -->
        <subfield code="c">us</subfield>       <!-- subfield c -->
    </datafield>
</record>
EOT;

        $record = new MarcReader($xml);
        $lint = new MarcLint();
        $this->callMethod($lint, 'check043', [$record->getField('043'), $record]);
        $this->assertEquals(
            [
                '043: Subfield _a must be exactly 7 characters, n-----',
                '043: Subfield _a must be exactly 7 characters, n-us----',
                '043: Subfield _a, n-ma-us, is not valid.',
                '043: Subfield _a, e-ur-ai, may be obsolete.',
            ],
            $this->getProperty($lint, 'warnings')
        );
    }

    /**
     * Test check245 method
     *
     * @param string $expected Expected output
     * @param string $input    Input
     *
     * @dataProvider get245TestData
     *
     * @return void
     */
    public function testCheck245($expected, $input)
    {
        $record = new MarcReader("<record>$input</record>");
        $lint = new MarcLint();
        $this->callMethod($lint, 'check245', [$record->getField('245'), $record]);
        $this->assertEquals(
            $expected,
            $this->getProperty($lint, 'warnings')
        );
    }

    /**
     * Data provider for testCheck245
     *
     * @return array
     */
    public function get245TestData()
    {
        return [
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Subfield a.</subfield></datafield>'
            ],
            [
                [
                    '245: Must have a subfield _a.',
                    '245: First subfield must be _a, but it is _b'
                ],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="b">no subfield a.</subfield></datafield>'
            ],
            [
                ['245: Must end with . (period).'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">No period at end</subfield></datafield>'
            ],
            [
                ['245: MARC21 allows ? or ! as final punctuation but LCRI 1.0C, Nov. 2003 (LCPS 1.7.1 for RDA records), requires period.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Other punctuation not followed by period!</subfield></datafield>'
            ],
            [
                ['245: MARC21 allows ? or ! as final punctuation but LCRI 1.0C, Nov. 2003 (LCPS 1.7.1 for RDA records), requires period.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Other punctuation not followed by period?</subfield></datafield>'
            ],
            [
                ['245: Subfield _c must be preceded by /'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub c</subfield><subfield code="c">not preceded by space-slash.</subfield></datafield>'
            ],
            [
                ['245: Subfield _c must be preceded by /'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub c/</subfield><subfield code="c">not preceded by space-slash.</subfield></datafield>'
            ],
            [
                ['245: Subfield _c initials should not have a space.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub c /</subfield><subfield code="c">initials in sub c B. B.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub c /</subfield><subfield code="c">initials in sub c B.B. (no warning).</subfield></datafield>'
            ],
            [
                ['245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b</subfield><subfield code="b">not preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                ['245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b=</subfield><subfield code="b">not preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                ['245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b:</subfield><subfield code="b">not preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                ['245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b;</subfield><subfield code="b">not preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b =</subfield><subfield code="b">preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b :</subfield><subfield code="b">preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub b ;</subfield><subfield code="b">preceded by proper punctuation.</subfield></datafield>'
            ],
            [
                ['245: Subfield _h should not be preceded by space.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub h </subfield><subfield code="h">[videorecording].</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub h-- </subfield><subfield code="h">[videorecording] :</subfield><subfield code="b">with elipses dash before h.</subfield></datafield>'
            ],
            [
                ['245: Subfield _h must have matching square brackets, videorecording :.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub h-- </subfield><subfield code="h">videorecording :</subfield><subfield code="b">without brackets around GMD.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub n.</subfield><subfield code="n">Number 1.</subfield></datafield>'
            ],
            [
                ['245: Subfield _n must be preceded by . (period).'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub n</subfield><subfield code="n">Number 2.</subfield></datafield>'
            ],
            [
                ['245: Subfield _p must be preceded by , (comma) when it follows subfield _n.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub n.</subfield><subfield code="n">Number 3.</subfield><subfield code="p">Sub n has period not comma.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub n.</subfield><subfield code="n">Number 3,</subfield><subfield code="p">Sub n has comma.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub p.</subfield><subfield code="p">Sub a has period.</subfield></datafield>'
            ],
            [
                ['245: Subfield _p must be preceded by . (period) when it follows a subfield other than _n.'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Precedes sub p</subfield><subfield code="p">Sub a has no period.</subfield></datafield>'
            ],
            [
                ['245: Non-filing indicator is non-numeric'],
                '<datafield tag="245" ind1="0" ind2="a"><subfield code="a">Invalid filing indicator.</subfield></datafield>'
            ],
            [
                ['245: First word, the, may be an article, check 2nd indicator (0).'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">The article.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="4"><subfield code="a">The article.</subfield></datafield>'
            ],
            [
                ['245: First word, an, may be an article, check 2nd indicator (2).'],
                '<datafield tag="245" ind1="0" ind2="2"><subfield code="a">An article.</subfield></datafield>'
            ],
            [
                ['245: First word, l, may be an article, check 2nd indicator (0).'],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">L&apos;article.</subfield></datafield>'
            ],
            [
                ['245: First word, a, does not appear to be an article, check 2nd indicator (2).'],
                '<datafield tag="245" ind1="0" ind2="2"><subfield code="a">A la mode.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="5"><subfield code="a">The &quot;quoted article&quot;.</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="5"><subfield code="a">The (parenthetical article).</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="6"><subfield code="a">(The) article in parentheses).</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="9"><subfield code="a">&quot;(The)&quot; &apos;article&apos; in quotes and parentheses).</subfield></datafield>'
            ],
            [
                [],
                '<datafield tag="245" ind1="0" ind2="5"><subfield code="a">[The supplied title].</subfield></datafield>'
            ],
            [
                [
                    '245: Must have a subfield _a.',
                    '245: Must end with . (period).',
                    '245: May have too few subfields.'
                ],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="6">sub6</subfield></datafield>'
            ],
            [
                [
                    '245: Must end with . (period).',
                    '245: First subfield must be _6, but it is a'
                ],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="a">Subfield a.</subfield><subfield code="6">sub6</subfield></datafield>'
            ],
            [
                [
                    '245: Must have a subfield _a.',
                    '245: First subfield after subfield _6 must be _a, but it is _b',
                    '245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.'
                ],
                '<datafield tag="245" ind1="0" ind2="0"><subfield code="6">sub6</subfield><subfield code="b">Subfield b.</subfield></datafield>'
            ],
        ];
    }

    /**
     * Test field 880
     *
     * @return void
     */
    public function test880()
    {
        $record = new MarcReader(
            $this->getFixture('marc/lint/880.xml')
        );
        $expected = [
            '245: Field is not repeatable.',
            '880: No subfield 6.',
        ];
        $lint = new MarcLint();
        $this->assertEquals($expected, $lint->checkRecord($record));
    }

    /**
     * Test records that cover the rest of the rules
     *
     * @return void
     */
    public function testRecords()
    {
        $lint = new MarcLint();
        $collection = new MarcCollection(
            $this->getFixture('marc/lint/camel.mrc')
        );
        $expected = [
            '100: Indicator 1 must be 0, 1 or 3 but it\'s "2"',
        ];
        $warnings = [];
        foreach ($collection as $record) {
            $warnings = array_merge($warnings, $lint->checkRecord($record));
        }
        $this->assertEquals($expected, array_filter($warnings));

        $record = new MarcReader(
            $this->getFixture('marc/lint/record2.xml')
        );
        $expected = [
            '1XX: Only one 1XX tag is allowed, but I found 2 of them.',
            '041: Subfield _a, end (end), is not valid.',
            '041: Subfield _a must be evenly divisible by 3 or exactly three characters if ind2 is not 7, (fren).',
            '043: Subfield _a, n-us-pn, is not valid.',
            '082: Subfield _R is not allowed.',
            '100: Indicator 2 must be blank but it\'s "4"',
            '245: Indicator 1 must be 0 or 1 but it\'s "9"',
            '245: Subfield _a is not repeatable.',
            '260: Subfield _r is not allowed.',
            '856: Indicator 2 must be blank, 0, 1, 2 or 8 but it\'s "3"'
        ];
        $this->assertEquals($expected, $lint->checkRecord($record));

        $marc = $this->getFixture('marc/lint/record3.xml');
        $reader = new \VuFind\Marc\MarcReader($marc);
        $expected = [
            '1XX: Only one 1XX tag is allowed, but I found 4 of them.',
            '245: No 245 tag.',
            '009: Subfields are not allowed in fields lower than 010',
            '100: Subfield _a is not repeatable.',
            '100: Subfield _a has an invalid control character',
            '110: Field is not repeatable.',
            '130: Indicator 1 must be 0, 1, 2, 3, 4, 5, 6, 7, 8 or 9 but it\'s "blank"',
            '130: Indicator 2 must be blank but it\'s "1"',
            '240: Subfield _b is not allowed.',
        ];
        $this->assertEquals($expected, $lint->checkRecord($reader));
    }

    /**
     * Test checkArticle method
     *
     * @param string $expected Expected output
     * @param string $input    Input
     *
     * @dataProvider getCheckArticleTestData
     *
     * @return void
     */
    public function testCheckArticle($expected, $input)
    {
        $record = new MarcReader("<record>{$input['data']}</record>");
        $lint = new MarcLint();
        $this->callMethod(
            $lint,
            'checkArticle',
            [
                $record->getField($input['tag']),
                $record
            ]
        );
        $this->assertEquals(
            $expected,
            $this->getProperty($lint, 'warnings')
        );
    }

    /**
     * Data provider for testCheck041
     *
     * @return array
     */
    public function getCheckArticleTestData()
    {
        return [
            [
                [],
                [
                    'tag' => '130',
                    'data' => '<datafield tag="130" ind1="0" ind2=" "><subfield code="a">Foo</subfield></datafield>'
                ]
            ],
            [
                ['130: Non-filing indicator is out of range'],
                [
                    'tag' => '130',
                    'data' => '<datafield tag="130" ind1="5" ind2=" "><subfield code="a">Foo</subfield></datafield>'
                ]
            ],
            [
                ['130: Non-filing indicator is non-numeric'],
                [
                    'tag' => '130',
                    'data' => '<datafield tag="130" ind1=" " ind2=" "><subfield code="a">Foo</subfield></datafield>'
                ]
            ],
            [
                ['Internal error: 650 is not a valid field for article checking'],
                [
                    'tag' => '650',
                    'data' => '<datafield tag="650" ind1=" " ind2=" "><subfield code="a">Foo</subfield></datafield>'
                ]
            ],
        ];
    }

    /**
     * Test parsing of rules
     *
     * @return void
     */
    public function testRuleParsing()
    {
        $lint = new \VuFind\Marc\MarcLint();

        // Test rule parsing for a range of subfields:
        $ruleGroup = [
            '999     R       LOCAL',
            'ind1    0-9     Undefined',
            'ind2    0-9     Undefined',
            'a-c     R       Undefined'
        ];
        $expected = [
            'repeatable' => '',
            'desc' => '',
            'ind1' => [
                'values' => '',
                'hr_values' => '',
                'desc' => '',
            ],
            'ind2' => [
                'values' => '',
                'hr_values' => '',
                'desc' => '',
            ],
            'suba' => [
                'repeatable' => '',
                'desc' => '',
            ],
            'subb' => [
                'repeatable' => '',
                'desc' => '',
            ],
            'subc' => [
                'repeatable' => '',
                'desc' => '',
            ],
        ];

        $lint = new \VuFind\Marc\MarcLint();
        $this->CallMethod(
            $lint,
            'processRuleGroup',
            [
                $ruleGroup
            ]
        );
        $rules = $this->getProperty($lint, 'rules');
        $this->assertEquals($expected, $rules['999']);
    }
}
