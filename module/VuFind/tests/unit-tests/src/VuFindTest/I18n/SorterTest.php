<?php

/**
 * Class SorterTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFindTest\I18n;

use VuFind\I18n\Sorter;

/**
 * Class SorterTest
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SorterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data Provider for compare tests
     *
     * @return array
     */
    public static function compareProvider(): array
    {
        return [
            [
                [
                    'strings' => ['a', 'b'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                -1,
            ],
            [
                [
                    'strings' => ['a', 'a'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                0,
            ],
            [
                [
                    'strings' => ['b', 'a'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                1,
            ],
            [
                [
                    'strings' => ['a', 'A'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                0,
            ],
            [
                [
                    'strings' => ['a', 'b'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                -1,
            ],
            [
                [
                    'strings' => ['a', 'a'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                0,
            ],
            [
                [
                    'strings' => ['b', 'a'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                1,
            ],
            [
                [
                    'strings' => ['a', 'A'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                0,
            ],
            [
                [
                    'strings' => ['č', 'd'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                1,
            ],
            [
                [
                    'strings' => ['č', 'd'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                -1,
            ],
            [
                [
                    'strings' => ['č', 'Č'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                0,
            ],
        ];
    }

    /**
     * Test compare function
     *
     * @param array $test     Test data
     * @param int   $expected Expected result
     *
     * @dataProvider compareProvider
     *
     * @return void
     */
    public function testCompare($test, $expected)
    {
        $sorter = $this->getSorterForTest($test);
        $result =  $sorter->compare($test['strings'][0], $test['strings'][1]);
        if ($expected === 1) {
            $this->assertGreaterThanOrEqual($expected, $result);
        } elseif ($expected === -1) {
            $this->assertLessThanOrEqual($expected, $result);
        } else {
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * Data Provider for sort tests
     *
     * @return array
     */
    public static function sortProvider(): array
    {
        return [
            [
                [
                    'input' => ['a', 'c', 'b'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                ['a', 'b', 'c'],
            ],
            [
                [
                    'input' => ['a', 'c', 'b'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                ['a', 'b', 'c'],
            ],
            [
                [
                    'input' => ['a', 'č', 'd', 'c'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['a', 'c', 'd', 'č'],
            ],
            [
                [
                    'input' => ['a', 'č', 'd', 'c', 'C'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['a', 'c', 'C', 'č', 'd'],
            ],
            [
                [
                    'input' => ['100', '3', '10', '2', '1'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['1', '2', '3', '10', '100'],
            ],
            [
                [
                    'input' => ['100', '3', '10', '2', '1'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['1', '2', '3', '10', '100'],
            ],
            [
                [
                    'input' => ['a100', 'a3', 'a10', 'a2', 'a1'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['a1', 'a10', 'a100', 'a2', 'a3'],
            ],
            [
                [
                    'input' => ['a100', 'a3', 'a10', 'a2', 'a1'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['a1', 'a10', 'a100', 'a2', 'a3'],
            ],
        ];
    }

    /**
     * Test sort function
     *
     * @param array $test     Test data
     * @param array $expected Expected result
     *
     * @dataProvider sortProvider
     *
     * @return void
     */
    public function testSort($test, $expected)
    {
        $sorter = $this->getSorterForTest($test);
        $result = $sorter->sort($test['input']);
        $this->assertEquals($expected, $test['input']);
        $this->assertTrue($result);
    }

    /**
     * Data Provider for asort tests
     *
     * @return array
     */
    public static function asortProvider(): array
    {
        return [
            [
                [
                    'input' => ['a' => 'a', 'c' => 'c', 'b' => 'b'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                ['a' => 'a', 'b' => 'b', 'c' => 'c'],
            ],
            [
                [
                    'input' => ['a' => 'a', 'c' => 'c', 'b' => 'b'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                ['a' => 'a', 'b' => 'b', 'c' => 'c'],
            ],
            [
                [
                    'input' => ['a' => 'a', 'č' => 'č', 'd' => 'd', 'c' => 'c'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['a' => 'a', 'c' => 'c', 'd' => 'd', 'č' => 'č'],
            ],
            [
                [
                    'input' => ['a' => 'a', 'č' => 'č', 'd' => 'd', 'c' => 'c'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['a' => 'a', 'c' => 'c', 'č' => 'č', 'd' => 'd'],
            ],
            [
                [
                    'input' => ['a' => '100', 'b' => '3', 'c' => '10', 'd' => '2', 'e' => '1'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['e' => '1', 'd' => '2', 'b' => '3', 'c' => '10', 'a' => '100'],
            ],
            [
                [
                    'input' => ['a' => '100', 'b' => '3', 'c' => '10', 'd' => '2', 'e' => '1'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['e' => '1', 'd' => '2', 'b' => '3', 'c' => '10', 'a' => '100'],
            ],
        ];
    }

    /**
     * Test asort function
     *
     * @param array $test     Test data
     * @param array $expected Expected result
     *
     * @dataProvider asortProvider
     *
     * @return void
     */
    public function testAsort($test, $expected)
    {
        $sorter = $this->getSorterForTest($test);
        $result = $sorter->asort($test['input']);
        $this->assertEquals($expected, $test['input']);
        $this->assertEquals(array_values($expected), array_values($test['input']));
        $this->assertTrue($result);
    }

    /**
     * Data provider for testNatsort()
     *
     * @return array
     */
    public static function natsortProvider(): array
    {
        return [
            [
                [
                    'input' => ['a' => 'img100', 'b' => 'img3', 'c' => 'img10', 'd' => 'img2', 'e' => 'img1'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['e' => 'img1', 'd' => 'img2', 'b' => 'img3', 'c' => 'img10', 'a' => 'img100'],
            ],
            [
                [
                    'input' => ['a' => 'img100', 'b' => 'img3', 'c' => 'img10', 'd' => 'img2', 'e' => 'img1'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                ['e' => 'img1', 'd' => 'img2', 'b' => 'img3', 'c' => 'img10', 'a' => 'img100'],
            ],
        ];
    }

    /**
     * Test natsort function
     *
     * @param array $test     Test data
     * @param array $expected Expected result
     *
     * @dataProvider natsortProvider
     *
     * @return void
     */
    public function testNatsort($test, $expected)
    {
        $sorter = $this->getSorterForTest($test);
        $result = $sorter->natsort($test['input']);
        $this->assertEquals($expected, $test['input']);
        $this->assertEquals(array_values($expected), array_values($test['input']));
        $this->assertTrue($result);
    }

    /**
     * Create sorter
     *
     * @param string $locale        Locale
     * @param bool   $respectLocale Does respect locale
     *
     * @return Sorter
     */
    protected function createSorter(
        string $locale,
        bool $respectLocale = false
    ): Sorter {
        $collator = new \Collator($locale);
        $collator->setStrength(\Collator::SECONDARY);
        return new Sorter($collator, $respectLocale);
    }

    /**
     * Get sorter for current test
     *
     * @param array $testCase Test definition
     *
     * @return Sorter
     */
    protected function getSorterForTest(array $testCase): Sorter
    {
        return $this->createSorter(
            $testCase['locale'],
            $testCase['respectLocale'],
        );
    }
}
