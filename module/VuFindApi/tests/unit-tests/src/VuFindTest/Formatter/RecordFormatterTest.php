<?php

/**
 * Unit tests for record formatter.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @link     https://vufind.org
 */
namespace VuFindTest\Formatter;
use \VuFindApi\Formatter\RecordFormatter;

/**
 * Unit tests for record formatter.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordFormatterTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Get default configuration to use in tests when no overrides are specified.
     *
     * @return array
     */
    protected function getDefaultDefs()
    {
        return [
            'cleanDOI' => [
                'method' => 'getCleanDOI',
            ],
        ];
    }

    /**
     * Get a helper plugin manager for the RecordFormatter.
     *
     * @return \Zend\View\HelperPluginManager
     */
    protected function getHelperPluginManager()
    {
        $hm = new \Zend\View\HelperPluginManager();
        $hm->setService('translate', new \VuFind\View\Helper\Root\Translate());
        return $hm;
    }

    /**
     * Get a formatter to test with.
     *
     * @param array $defs Configuration for formatter
     *
     * @return RecordFormatter
     */
    protected function getFormatter($defs = null)
    {
        return new RecordFormatter(
            $defs ?: $this->getDefaultDefs(), $this->getHelperPluginManager()
        );
    }

    /**
     * Get a record driver to test with.
     *
     * @return \VuFindTest\RecordDriver\TestHarness
     */
    protected function getDriver()
    {
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        $driver->setRawData(
            [
                'CleanDOI' => 'foo',
            ]
        );
        return $driver;
    }

    /**
     * Test the record formatter.
     *
     * @return void
     */
    public function testFormatter()
    {
        $formatter = $this->getFormatter();

        // Test requesting no fields.
        $this->assertEquals([], $formatter->format([$this->getDriver()], []));

        // Test requesting fields:
        $results = $formatter->format([$this->getDriver()], ['cleanDOI']);
        $expected = [
            [
                'cleanDOI' => 'foo',
            ],
        ];
        $this->assertEquals($expected, $results);
    }
}
