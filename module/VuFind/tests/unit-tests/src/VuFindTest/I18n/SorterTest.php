<?php
declare(strict_types=1);

/**
 * Class SorterTest
 *
 * PHP version 7
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
namespace VuFindTest\I18n;

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
                -1
            ],
            [
                [
                    'strings' => ['a', 'a'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                0
            ],
            [
                [
                    'strings' => ['b', 'a'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                1
            ],
            [
                [
                    'strings' => ['a', 'A'],
                    'locale' => 'en',
                    'respectLocale' => false,
                ],
                0
            ],
            [
                [
                    'strings' => ['a', 'b'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                -1
            ],
            [
                [
                    'strings' => ['a', 'a'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                0
            ],
            [
                [
                    'strings' => ['b', 'a'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                1
            ],
            [
                [
                    'strings' => ['a', 'A'],
                    'locale' => 'en',
                    'respectLocale' => true,
                ],
                -1
            ],
            [
                [
                    'strings' => ['č', 'd'],
                    'locale' => 'cs',
                    'respectLocale' => false,
                ],
                1
            ],
            [
                [
                    'strings' => ['č', 'd'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                -1
            ],
        ];
    }

    /**
     * Test compare function
     *
     * @dataProvider compareProvider
     *
     * @return void
     */
    public function testCompare($test, $expected)
    {
        $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
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
                    'input' => ['a', 'č', 'd', 'c'],
                    'locale' => 'cs',
                    'respectLocale' => true,
                ],
                ['a', 'c', 'č', 'd'],
            ]
        ];
    }

    /**
     * Test sort function
     *
     * @dataProvider sortProvider
     *
     * @return void
     */
    public function testSort($test, $expected)
    {
        $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
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
            ]
        ];
    }

    /**
     * Test asort function
     *
     * @dataProvider asortProvider
     *
     * @return void
     */
    public function testAsort($test, $expected)
    {
        $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
        $result = $sorter->asort($test['input']);
        $this->assertEquals($expected, $test['input']);
        $this->assertTrue($result);
    }

    /**
     * Create sorter
     *
     * @param string $locale
     * @param bool   $respectLocale
     *
     * @return \VuFind\I18n\Sorter
     */
    protected function createSorter(string $locale, bool $respectLocale = false)
    {
        return new \VuFind\I18n\Sorter($locale, $respectLocale);
    }
}
