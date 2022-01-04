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
     * Test MarcLint
     *
     * @return void
     */
    public function testMarcLint()
    {
        $lint = new \VuFind\Marc\MarcLint();

        $marc = $this->getFixture('marc/marclint.xml');
        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals([], $lint->checkRecord($reader));

        $marc = $this->getFixture('marc/marclint_bad.xml');
        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals(28, count($lint->checkRecord($reader)));

        $marc = $this->getFixture('marc/marclint_bad2.xml');
        $reader = new \VuFind\Marc\MarcReader($marc);
        $this->assertEquals(38, count($lint->checkRecord($reader)));

        // Test checkArticle with field 130 and an invalid field:
        $lint = new \VuFind\Marc\MarcLint();
        $this->CallMethod(
            $lint,
            'checkArticle',
            [
                [
                    'tag' => '130',
                    'i1' => ' ',
                    'i2' => ' ',
                    's' => [
                        ['a' => 'Foo']
                    ]
                ],
                $reader
            ]
        );
        $this->CallMethod(
            $lint,
            'checkArticle',
            [
                [
                    'tag' => '650',
                    'i1' => ' ',
                    'i2' => ' ',
                    's' => [
                        ['a' => 'Foo']
                    ]
                ],
                $reader
            ]
        );
        $this->assertEquals(
            [
                '130: Non-filing indicator is non-numeric',
                'Internal error: 650 is not a valid field for article checking'
            ],
            $this->getProperty($lint, 'warnings')
        );

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
