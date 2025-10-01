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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * Config object to test
     *
     * @var ?Config
     */
    protected $config = null;

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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->config = new Config(self::$configArray);
    }

    /**
     * Test that toArray returns original array.
     *
     * @return void
     */
    public function testToArray(): void
    {
        $this->assertEquals(self::$configArray, $this->config->toArray());
    }

    /**
     * Test that undefined values do not exist.
     *
     * @return void
     */
    public function testUnsetValue(): void
    {
        $this->assertFalse(isset($this->config->section3->doesNotExist));
    }

    /**
     * Test that object notation works for access.
     *
     * @return void
     */
    public function testObjectNotation(): void
    {
        $this->assertEquals('value1', $this->config->section1->setting1);
    }

    /**
     * Test that array notation works for access.
     *
     * @return void
     */
    public function testArrayNotation(): void
    {
        $this->assertEquals('value2', $this->config['section1']['setting2']);
    }

    /**
     * Test that nested arrays can be retrieved.
     *
     * @return void
     */
    public function testNestedArray(): void
    {
        $this->assertEquals(['foo', 'bar'], $this->config->section2->setting3->toArray());
    }
}
