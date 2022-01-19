<?php
declare(strict_types=1);

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
 * @link     https://knihovny.cz Main Page
 */
namespace VuFindTest\Service;

/**
 * Class SorterTest
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
class SorterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test compare function
     *
     * @return void
     */
    public function testCompare()
    {
        $testCases = [
            [
                'strings' => ['a', 'b'],
                'locale' => 'en',
                'respectLocale' => false,
                'result' => -1
            ],
            [
                'strings' => ['a', 'a'],
                'locale' => 'en',
                'respectLocale' => false,
                'result' => 0
            ],
            [
                'strings' => ['b', 'a'],
                'locale' => 'en',
                'respectLocale' => false,
                'result' => 1
            ],
            [
                'strings' => ['a', 'A'],
                'locale' => 'en',
                'respectLocale' => false,
                'result' => 0
            ],
            [
                'strings' => ['a', 'b'],
                'locale' => 'en',
                'respectLocale' => true,
                'result' => -1
            ],
            [
                'strings' => ['a', 'a'],
                'locale' => 'en',
                'respectLocale' => true,
                'result' => 0
            ],
            [
                'strings' => ['b', 'a'],
                'locale' => 'en',
                'respectLocale' => true,
                'result' => 1
            ],
            [
                'strings' => ['a', 'A'],
                'locale' => 'en',
                'respectLocale' => true,
                'result' => -1
            ],
            [
                'strings' => ['č', 'd'],
                'locale' => 'cs',
                'respectLocale' => false,
                'result' => 1
            ],
            [
                'strings' => ['č', 'd'],
                'locale' => 'cs',
                'respectLocale' => true,
                'result' => -1
            ],

        ];
        foreach ($testCases as $key => $test) {
            $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
            $result =  $sorter->compare($test['strings'][0], $test['strings'][1]);
            if ($test['result'] === 1) {
                $this->assertGreaterThanOrEqual($test['result'], $result, 'Failed test with key ' . $key);
            } elseif ($test['result'] === -1) {
                $this->assertLessThanOrEqual($test['result'], $result, 'Failed test with key ' . $key);
            } else {
                $this->assertEquals($test['result'], $result, 'Failed test with key ' . $key);
            }
        }
    }

    /**
     * Test sort function
     *
     * @return void
     */
    public function testSort()
    {
        $testCases = [
            [
                'input' => ['a', 'c', 'b'],
                'output' => ['a', 'b', 'c'],
                'locale' => 'en',
                'respectLocale' => false,
            ],
            [
                'input' => ['a', 'c', 'b'],
                'output' => ['a', 'b', 'c'],
                'locale' => 'en',
                'respectLocale' => true,
            ],
            [
                'input' => ['a', 'č', 'd', 'c'],
                'output' => ['a', 'c', 'd', 'č'],
                'locale' => 'cs',
                'respectLocale' => false,
            ],
            [
                'input' => ['a', 'č', 'd', 'c'],
                'output' => ['a', 'c', 'č', 'd'],
                'locale' => 'cs',
                'respectLocale' => true,
            ]
        ];
        foreach ($testCases as $key => $test) {
            $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
            $result = $sorter->sort($test['input']);
            $this->assertEquals($test['output'], $test['input'], 'Failed test with key ' . $key);
            $this->assertTrue($result);
        }
    }

    /**
     * Test asort function
     *
     * @return void
     */
    public function testAsort()
    {
        $testCases = [
            [
                'input' => ['a' => 'a', 'c' => 'c', 'b' => 'b'],
                'output' => ['a' => 'a', 'b' => 'b', 'c' => 'c'],
                'locale' => 'en',
                'respectLocale' => false,
            ],
            [
                'input' => ['a' => 'a', 'c' => 'c', 'b' => 'b'],
                'output' => ['a' => 'a', 'b' => 'b', 'c' => 'c'],
                'locale' => 'en',
                'respectLocale' => true,
            ],
            [
                'input' => ['a' => 'a', 'č' => 'č', 'd' => 'd', 'c' => 'c'],
                'output' => ['a' => 'a', 'c' => 'c', 'd' => 'd', 'č' => 'č'],
                'locale' => 'cs',
                'respectLocale' => false,
            ],
            [
                'input' => ['a' => 'a', 'č' => 'č', 'd' => 'd', 'c' => 'c'],
                'output' => ['a' => 'a', 'c' => 'c', 'č' => 'č', 'd' => 'd'],
                'locale' => 'cs',
                'respectLocale' => true,
            ]
        ];
        foreach ($testCases as $key => $test) {
            $sorter = $this->createSorter($test['locale'], $test['respectLocale']);
            $result = $sorter->asort($test['input']);
            $this->assertEquals($test['output'], $test['input'], 'Failed test with key ' . $key);
            $this->assertTrue($result);
        }
    }

    protected function createSorter(string $locale, bool $respectLocale = false)
    {
        return new \VuFind\Service\Sorter($locale, $respectLocale);
    }
}
