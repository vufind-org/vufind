<?php

/**
 * CSV Importer Configuration Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\CSV;

use VuFind\CSV\ImporterConfig;

/**
 * CSV Importer Configuration Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ImporterConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that options sent to the constructor are respected.
     *
     * @return void
     */
    public function testConstructorOptions(): void
    {
        $config = new ImporterConfig(['batchSize' => 7, 'encoding' => 'foo']);
        $this->assertEquals(7, $config->getBatchSize());
        $this->assertEquals('foo', $config->getEncoding());
    }

    /**
     * Test that defaults are set if no options are provided to constructor.
     *
     * @return void
     */
    public function testConstructorDefaults(): void
    {
        $config = new ImporterConfig();
        $this->assertEquals(100, $config->getBatchSize());
        $this->assertEquals('UTF-8', $config->getEncoding());
    }

    /**
     * Test column configuration merging behavior.
     *
     * @return void
     */
    public function testColumnConfigurationMerging(): void
    {
        $config = new ImporterConfig();
        // Start with one value:
        $config->configureColumn(0, ['foo' => 'bar']);
        // Add a second value:
        $config->configureColumn(0, ['bar' => 'baz']);
        // Override the first value:
        $config->configureColumn(0, ['foo' => 'bar2']);
        $this->assertEquals(
            ['foo' => 'bar2', 'bar' => 'baz'],
            $config->getColumn(0)
        );
    }

    /**
     * Test field configuration merging behavior.
     *
     * @return void
     */
    public function testFieldConfigurationMerging(): void
    {
        $config = new ImporterConfig();
        // Start with one value:
        $config->configureField('test', ['foo' => 'bar']);
        // Add a second value:
        $config->configureField('test', ['bar' => 'baz']);
        // Override the first value:
        $config->configureField('test', ['foo' => 'bar2']);
        $this->assertEquals(
            ['foo' => 'bar2', 'bar' => 'baz'],
            $config->getField('test')
        );
    }

    /**
     * Confirm that adding a column to field mapping adds the field
     * to the field list.
     *
     * @return void
     */
    public function testColumnFieldConfiguration(): void
    {
        $config = new ImporterConfig();
        // Test single value
        $config->configureColumn(0, ['field' => 'foo']);
        // Test array of values
        $config->configureColumn(1, ['field' => ['bar', 'baz']]);
        $this->assertEquals(['foo', 'bar', 'baz'], $config->getAllFields());
    }

    /**
     * Test retrieval of hard-coded field values.
     *
     * @return void
     */
    public function testGetFixedFieldValues(): void
    {
        $config = new ImporterConfig();
        $config->configureColumn(1, ['field' => ['bar', 'baz']]);
        $config->configureField('baz', ['value' => 'hard-coded']);
        $this->assertEquals(
            ['bar' => [], 'baz' => ['hard-coded']],
            $config->getFixedFieldValues()
        );
    }

    /**
     * Test getOutstandingCallbacks.
     *
     * @return void
     */
    public function testGetOutstandingCallbacks(): void
    {
        $config = new ImporterConfig();
        $config->configureField('foo', ['callback' => 'fooCallback']);
        $config->configureField('bar', ['callback' => 'barCallback']);
        $config->configureField('baz', []);
        // If foo has already been called, bar is the only other value with
        // callbacks, so that is the only value returned.
        $this->assertEquals(
            ['bar'],
            array_values($config->getOutstandingCallbacks(['foo']))
        );
    }
}
