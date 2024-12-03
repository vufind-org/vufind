<?php

/**
 * Configuration Wrapper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\Config;

/**
 * Configuration Wrapper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test configuration data
     *
     * @var array
     */
    protected static $configArray = [
        'section1' => [
            'setting1' => 'value1',
            'setting2' => 'value2',
        ],
        'section2' => [
            'setting3' => ['foo', 'bar'],
        ],
    ];

    /**
     * Data provider for testBasicBehavior
     *
     * @return array[]
     */
    public static function basicBehaviorProvider(): array
    {
        return [
            'toArray returns original array' =>
                [fn ($test, $config) => $test->assertEquals(self::$configArray, $config->toArray())],
            'undefined values do not exist' =>
                [fn ($test, $config) => $test->assertFalse(isset($config->section3->doesNotExist))],
            'object notation works for access' =>
                [fn ($test, $config) => $test->assertEquals('value1', $config->section1->setting1)],
            'array notation works for access' =>
                [fn ($test, $config) => $test->assertEquals('value2', $config['section1']['setting2'])],
            'nested arrays can be retrieved' =>
                [fn ($test, $config) => $test->assertEquals(['foo', 'bar'], $config->section2->setting3->toArray())],
        ];
    }

    /**
     * Test basic configuration behavior.
     *
     * @param callable $callback Callback to test a behavior (receives test object and config object as arguments)
     *
     * @return void
     *
     * @dataProvider basicBehaviorProvider
     */
    public function testBasicBehavior(callable $callback): void
    {
        $config = new Config(self::$configArray);
        $callback($this, $config);
    }
}
