<?php

/**
 * Unit tests for record formatter.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
class RecordFormatterTest extends \PHPUnit\Framework\TestCase
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
                'type' => 'string',
            ],
            'dedupIds' => [
                'vufind.method' => 'Formatter::getDedupIds',
                'description' => 'IDs of all records deduplicated',
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'fullRecord' => ['vufind.method' => 'Formatter::getFullRecord'],
            'rawData' => ['vufind.method' => 'Formatter::getRawData'],
            'buildings' => ['vufind.method' => 'getBuildings'],
            'recordPage' => ['vufind.method' => 'Formatter::getRecordPage'],
            'subjectsExtended' => [
                'vufind.method' => 'Formatter::getExtendedSubjectHeadings',
            ],
            'authors' => ['vufind.method' => 'getDeduplicatedAuthors'],
        ];
    }

    /**
     * Get a helper plugin manager for the RecordFormatter.
     *
     * @return \Laminas\View\HelperPluginManager
     */
    protected function getHelperPluginManager()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $hm = new \Laminas\View\HelperPluginManager($container);
        $hm->setService('translate', new \VuFind\View\Helper\Root\Translate());
        $mockRecordLinker
            = $container->get(\VuFind\View\Helper\Root\RecordLinker::class);
        $mockRecordLinker->method('getUrl')->willReturn('/vufind/Record/12345');
        $hm->setService('recordLinker', $mockRecordLinker);
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
            $defs ?: $this->getDefaultDefs(),
            $this->getHelperPluginManager()
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
                'Buildings' => ['foo', new TranslatableString('bar', 'xyzzy')],
                'AllSubjectHeadings' => [['heading' => 'subject']],
                'DeduplicatedAuthors' => [
                    'primary' => ['Ms. A' => ['role' => ['Editor']]],
                    'secondary' => ['Mr. B' => [], 'Mr. C' => []],
                ],
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
            [$driver],
            array_keys($this->getDefaultDefs())
        );
        $expectedRaw = $driver->getRawData();
        unset($expectedRaw['spelling']);
        $expectedRaw['Buildings'] = [
            'foo', ['value' => 'bar', 'translated' => 'xyzzy'],
        ];
        $expected = [
            [
                'cleanDOI' => 'foo',
                'dedupIds' => ['bar'],
                'fullRecord' => 'xyzzy',
                'rawData' => $expectedRaw,
                'buildings' => ['foo', ['value' => 'bar', 'translated' => 'xyzzy']],
                'recordPage' => '/vufind/Record/12345',
                'subjectsExtended' => [['heading' => 'subject']],
                'authors' => [
                    'primary' => ['Ms. A' => ['role' => ['Editor']]],
                    'secondary' => ['Mr. B' => [], 'Mr. C' => []],
                ],
            ],
        ];
        $this->assertEquals($expected, $results);

        // Test filtered XML
        $filtered = '<filtered></filtered>';
        $driver->setFilteredXML($filtered);
        $results = $formatter->format(
            [$driver],
            array_keys($this->getDefaultDefs())
        );
        $expected[0]['fullRecord'] = $filtered;
        $expected[0]['rawData']['FilteredXML'] = $filtered;
        $this->assertEquals($expected, $results);
    }

    /**
     * Data provider to return in absolute link tests.
     *
     * @return array[]
     */
    public static function absoluteLinkProvider(): array
    {
        return [
            [
                '', 'somehost', 'http://somehost/vufind/Record/12345',
                '', 'somehost:8080', 'http://somehost:8080/vufind/Record/12345',
                'on', 'somehost', 'https://somehost/vufind/Record/12345',
            ],
        ];
    }

    /**
     * Test record formatter's absolute link field
     *
     * @param string $https    $_SERVER['HTTPS'] value
     * @param string $host     $_SERVER['HTTP_HOST'] value
     * @param string $expected Expected result of absolute link
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('absoluteLinkProvider')]
    public function testFormatterAbsoluteLink(string $https, string $host, string $expected)
    {
        $defs = $this->getDefaultDefs();
        $defs['recordPageAbsoluteLink'] = ['vufind.method' => 'Formatter::getRecordPageAbsoluteLink'];
        $formatter = $this->getFormatter($defs);

        $driver = $this->getDriver();

        $serverBackupFields = ['HTTPS', 'HTTP_HOST'];
        $serverBackup = array_map(fn ($field) => $_SERVER[$field] ?? null, $serverBackupFields);

        try {
            $_SERVER['HTTPS'] = $https;
            $_SERVER['HTTP_HOST'] = $host;

            $results = $formatter->format(
                [$driver],
                ['recordPageAbsoluteLink']
            );
            $expected = [
                [
                    'recordPageAbsoluteLink' => $expected,
                ],
            ];
            $this->assertEquals($expected, $results);
        } finally {
            foreach ($serverBackupFields as $field) {
                if ($backupValue = ($serverBackup[$field] ?? null)) {
                    $_SERVER[$field] = $backupValue;
                } else {
                    unset($_SERVER[$field]);
                }
            }
        }
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
                'type' => 'string',
            ],
            'dedupIds' => [
                'description' => 'IDs of all records deduplicated',
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'fullRecord' => [],
            'rawData' => [],
            'buildings' => [],
            'recordPage' => [],
            'subjectsExtended' => [],
            'authors' => [],
        ];
        $this->assertEquals($expected, $results);
    }
}
