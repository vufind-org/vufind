<?php

/**
 * Unit tests for record formatter.
 *
 * PHP version 7
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

use VuFind\I18n\TranslatableString;
use VuFindApi\Formatter\RecordFormatter;

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
                'vufind.method' => 'getCleanDOI',
                'description' => 'First valid DOI',
                'type' => 'string'
            ],
            'dedupIds' => [
                'vufind.method' => 'Formatter::getDedupIds',
                'description' => 'IDs of all records deduplicated',
                'type' => 'array',
                'items' => ['type' => 'string']
            ],
            'fullRecord' => ['vufind.method' => 'Formatter::getFullRecord'],
            'rawData' => ['vufind.method' => 'Formatter::getRawData'],
            'buildings' => ['vufind.method' => 'getBuilding'],
            'recordPage' => ['vufind.method' => 'Formatter::getRecordPage'],
            'subjectsExtended' => [
                'vufind.method' => 'Formatter::getExtendedSubjectHeadings'
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
        $hm = new \Zend\View\HelperPluginManager(
            $this->createMock('Interop\Container\ContainerInterface')
        );
        $hm->setService('translate', new \VuFind\View\Helper\Root\Translate());

        $mockRecordLink = $this->getMockBuilder('VuFind\View\Helper\Root\RecordLink')
            ->disableOriginalConstructor()->getMock();
        $mockRecordLink->expects($this->any())->method('getUrl')
            ->will($this->returnValue('http://record'));
        $hm->setService('recordLink', $mockRecordLink);
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
                'DedupData' => [['id' => 'bar']],
                'fullrecord' => 'xyzzy',
                'spelling' => 's',
                'Building' => ['foo', new TranslatableString('bar', 'xyzzy')],
                'AllSubjectHeadings' => [['heading' => 'subject']],
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

        $driver = $this->getDriver();

        // Test requesting no fields.
        $this->assertEquals([], $formatter->format([$driver], []));

        // Test requesting fields:
        $results = $formatter->format(
            [$driver], array_keys($this->getDefaultDefs())
        );
        $expectedRaw = $driver->getRawData();
        unset($expectedRaw['spelling']);
        $expectedRaw['Building'] = [
            'foo', ['value' => 'bar', 'translated' => 'xyzzy']
        ];
        $expected = [
            [
                'cleanDOI' => 'foo',
                'dedupIds' => ['bar'],
                'fullRecord' => 'xyzzy',
                'rawData' => $expectedRaw,
                'buildings' => ['foo', ['value' => 'bar', 'translated' => 'xyzzy']],
                'recordPage' => 'http://record',
                'subjectsExtended' => [['heading' => 'subject']],
            ],
        ];
        $this->assertEquals($expected, $results);

        // Test filtered XML
        $filtered = '<filtered></filtered>';
        $driver->setFilteredXML($filtered);
        $results = $formatter->format(
            [$driver], array_keys($this->getDefaultDefs())
        );
        $expected[0]['fullRecord'] = $filtered;
        $expected[0]['rawData']['FilteredXML'] = $filtered;
        $this->assertEquals($expected, $results);
    }

    /**
     * Test getting the field specs.
     *
     * @return void
     */
    public function testFieldSpecs()
    {
        $formatter = $this->getFormatter();
        $results = $formatter->getRecordFieldSpec();
        $expected = [
            'cleanDOI' => [
                'description' => 'First valid DOI',
                'type' => 'string'
            ],
            'dedupIds' => [
                'description' => 'IDs of all records deduplicated',
                'type' => 'array',
                'items' => ['type' => 'string']
            ],
            'fullRecord' => [],
            'rawData' => [],
            'buildings' => [],
            'recordPage' => [],
            'subjectsExtended' => [],
        ];
        $this->assertEquals($expected, $results);
    }
}
