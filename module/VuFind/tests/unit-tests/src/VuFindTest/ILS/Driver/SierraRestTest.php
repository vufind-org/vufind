<?php

/**
 * SierraRest ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use VuFind\ILS\Driver\SierraRest;

/**
 * SierraRest ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SierraRestTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test bib IDs (raw value => formatted value)
     *
     * @var array
     */
    protected $bibIds = [
        '12345' => '.b123456',
        '23456' => '.b234564',
        '34567' => '.b345672',
        '45678' => '.b456780',
        '56789' => '.b567899',
        '191456' => '.b191456x',
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $sessionFactory = function ($namespace) {
            return new \Laminas\Session\Container($namespace);
        };
        $this->driver = new SierraRest(
            new \VuFind\Date\Converter(),
            $sessionFactory
        );
    }

    /**
     * Test ID extraction.
     *
     * @return void
     */
    public function testIdExtraction()
    {
        foreach ($this->bibIds as $raw => $formatted) {
            // Extraction should return the same result whether we extract from
            // the raw value or the formatted value:
            $this->assertEquals(
                $raw,
                $this->callMethod($this->driver, 'extractBibId', [$raw])
            );
            $this->assertEquals(
                $raw,
                $this->callMethod($this->driver, 'extractBibId', [$formatted])
            );
        }
    }

    /**
     * Test default ID formatting (no prefixing).
     *
     * @return void
     */
    public function testDefaultBibFormatting()
    {
        foreach (array_keys($this->bibIds) as $id) {
            $this->assertEquals(
                $id,
                $this->callMethod($this->driver, 'formatBibId', [$id])
            );
        }
    }

    /**
     * Test default ID formatting (no prefixing).
     *
     * @return void
     */
    public function testPrefixedBibFormatting()
    {
        $this->driver->setConfig(
            ['Catalog' => ['use_prefixed_ids' => true]]
        );
        foreach ($this->bibIds as $raw => $formatted) {
            $this->assertEquals(
                $formatted,
                $this->callMethod($this->driver, 'formatBibId', [$raw])
            );
        }
    }
}
