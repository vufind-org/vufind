<?php

/**
 * SolrMarc Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\ILS\Connection;
use VuFind\ILS\Logic\Holds;
use VuFind\ILS\Logic\TitleHolds;

use function count;
use function in_array;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrMarcTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test a record that used to be known to cause problems because of the way
     * its linking fields are set up.
     *
     * Note: while Bug2 below is named for consistency with VuFind 1.x, this is
     * named Bug1 simply to fill the gap. It's related to a problem that was
     * discovered later. See VUFIND-1034 in JIRA.
     *
     * @return void
     */
    public function testBug1(): void
    {
        $configArr = ['Record' => ['marc_links' => '760,765,770,772,774,773,775,777,780,785']];
        $config = new \Laminas\Config\Config($configArr);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $fixture = $this->getJsonFixture('misc/testbug1.json');
        $record->setRawData($fixture['response']['docs'][0]);
        $expected = [
            [
                'title' => 'A',
                'value' => 'Bollettino della Unione matematica italiana',
                'link' => ['type' => 'bib', 'value' => '000343528'],
            ],
            [
                'title' => 'B',
                'value' => 'Bollettino della Unione matematica',
                'link' => ['type' => 'bib', 'value' => '000343529'],
            ],
            [
                'title' => 'note_785_8',
                'value' => 'Bollettino della Unione matematica italiana',
                'link' => ['type' => 'bib', 'value' => '000394898'],
            ],
        ];
        $this->assertEquals($expected, $record->getAllRecordLinks());
    }

    /**
     * Test a record that used to be known to cause problems because of the way
     * series name was handled (the old "Bug2" test from VuFind 1.x).
     *
     * @return void
     */
    public function testBug2(): void
    {
        $record = new \VuFind\RecordDriver\SolrMarc();
        $fixture = $this->getJsonFixture('misc/testbug2.json');
        $record->setRawData($fixture['response']['docs'][0]);

        $this->assertEquals(
            $record->getPrimaryAuthor(),
            'Vico, Giambattista, 1668-1744.'
        );
        $secondary = $record->getSecondaryAuthors();
        $this->assertEquals(count($secondary), 1);
        $this->assertTrue(in_array('Pandolfi, Claudia.', $secondary));
        $series = $record->getSeries();
        $this->assertEquals(count($series), 1);
        $this->assertEquals(
            'Vico, Giambattista, 1668-1744. Works. 1982 ;',
            $series[0]['name']
        );
        $this->assertEquals('2, pt. 1.', $series[0]['number']);
    }

    /**
     * Test regular and extended subject heading support.
     *
     * @return void
     */
    public function testSubjectHeadings(): void
    {
        $config = new \Laminas\Config\Config([]);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $fixture = $this->getJsonFixture('misc/testbug1.json');
        $record->setRawData($fixture['response']['docs'][0]);
        $this->assertEquals(
            [['Matematica', 'Periodici.']],
            $record->getAllSubjectHeadings()
        );
        $this->assertEquals(
            [
                [
                    'heading' => ['Matematica', 'Periodici.'],
                    'type' => '',
                    'source' => '',
                    'id' => '',
                ],
            ],
            $record->getAllSubjectHeadings(true)
        );
    }

    /**
     * Test regular and extended subject heading support for different possible config options.
     *
     * @param ?string $marcSubjectHeadingsSortConfig The config value for
     * $this->mainConfig->Record->marcSubjectHeadingsSort
     * @param array   $expectedResults               Array of the expected values returned from
     * $record->getAllSubjectHeadings()
     *
     * @return void
     *
     * @dataProvider marcSubjectHeadingsSortOptionsProvider
     */
    public function testSubjectHeadingsOrder(?string $marcSubjectHeadingsSortConfig, array $expectedResults): void
    {
        $configArray = [
            'Record' => [
                'marcSubjectHeadingsSort' => $marcSubjectHeadingsSortConfig,
            ],
        ];
        $marc = $this->getFixture('marc/subjectheadingsorder.xml');
        $config = new \Laminas\Config\Config($configArray);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $record->setRawData(['fullrecord' => $marc]);
        $this->assertEquals($expectedResults, $record->getAllSubjectHeadings());
    }

    /**
     * Config and data for assertion of Subject Headings Order (testSubjectHeadingsOrder)
     *
     * @return array[]
     */
    public static function marcSubjectHeadingsSortOptionsProvider(): array
    {
        // Record order is the default; save it to a variable so we
        // can test both explicit and default configuration behaviors
        // using the same values.
        $recordOrderResults = [
            [
                'Guerrero (Mexico : State)',
                'Social life and customs',
                'Pictorial works.',
            ],
            [
                'Street photography',
                'Mexico',
                'Guerrero (State)',
            ],
            [
                'Photobooks.',
            ],
        ];
        return [
            'field config' => [
                'numerical',
                [
                    [
                        'Street photography',
                        'Mexico',
                        'Guerrero (State)',
                    ],
                    [
                        'Guerrero (Mexico : State)',
                        'Social life and customs',
                        'Pictorial works.',
                    ],
                    [
                        'Photobooks.',
                    ],
                ],
            ],
            'record config' => [
                'record',
                $recordOrderResults,
            ],
            'default config' => [
                null,
                $recordOrderResults,
            ],
        ];
    }

    /**
     * Test table of contents support.
     *
     * @return void
     */
    public function testTOC(): void
    {
        $marc = $this->getFixture('marc/toc1.xml');
        $config = new \Laminas\Config\Config([]);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $record->setRawData(['fullrecord' => $marc]);
        $this->assertEquals(
            [
                'About the Association of Professors of Missions / Robert Danielson',
                'Foreword / Angel Santiago-Vendrell',
                'Conference theme',
                'Plenary Papers',
                'Teaching missiology in and for world Christianity content and method / Peter C. Phan',
                'The bodies we teach by: (en) gendering mission for global Christianities / Mai-Ahn Le',
                'Teaching Christian mission in an age of world Christianity: a reflection on the centenary of the '
                . '1916 Panama Congress / Philip Wingeier-Rayo',
                'Conference Papers',
                'Theological metaphors of teaching mission in an age of world Christianity in the North American '
                . 'context / David Thang Moe',
                'Mission shifts from Pope Benedict XVI to Pope Francis / William P. Gregory',
                'The elephant in the room: towards a paradigm shift in missiological education / Sarita D. Gallagher',
                'Historic models of teaching Christian mission: case studies informing an age of world Christianity '
                . '/ Robert L. Gallagher',
                'How the West was won: world Christianity as historic reality / Matt Friedman',
                'The world\'s Christians: strategies for teaching international graduate students in Kenya\'s '
                . 'Christian universities / Janice Horsager Rasmussen',
                'Gendered mission: educational work or itinerating preaching? The mission practice of the Presbyterian'
                . ' Church USA in Barranquilla, Colombia, 1880-1920 / Angel Santiago-Vendrell',
                'Mary McLeod Bethune: Christ did not designate any particular color to go / Mary Cloutier',
                'Teaching mission in an age of world Christianity: history, theology, anthropology, and gender in the '
                . 'classroom / Angel Santiago-Vendrell',
                'Conference Proceedings',
                'First Fruits report for the APM',
                'Minutes of 2016 meeting',
                'Secretary\'s treasury report',
                'Conference program.',
            ],
            $record->getTOC()
        );
        $marc2 = $this->getFixture('marc/toc2.xml');
        $record2 = new \VuFind\RecordDriver\SolrMarc($config);
        $record2->setRawData(['fullrecord' => $marc2]);
        $this->assertEquals(
            [
                'Don\'t split the unspaced--separator.',
                'Do split the spaced one.',
                'Respect pre-AACR2-style separation',
                'Even though it\'s old.',
            ],
            $record2->getTOC()
        );
    }

    /**
     * Data provider for testGetSchemaOrgFormatsArray().
     *
     * @return array[]
     */
    public static function getSchemaOrgFormatsArrayProvider(): array
    {
        return [
            'with ILS' => [true, ['CreativeWork', 'Product']],
            'without ILS' => [false, ['CreativeWork']],
        ];
    }

    /**
     * Test getSchemaOrgFormatsArray().
     *
     * @param bool  $useIls          Should we attach an ILS to the record driver?
     * @param array $expectedFormats The expected method output
     *
     * @return void
     *
     * @dataProvider getSchemaOrgFormatsArrayProvider
     */
    public function testGetSchemaOrgFormatsArray(bool $useIls, array $expectedFormats): void
    {
        // Set up record driver:
        $config = new \Laminas\Config\Config([]);
        $record = new \VuFind\RecordDriver\SolrMarc($config);

        // Load data:
        $fixture = $this->getJsonFixture('misc/testbug1.json');
        $record->setRawData($fixture['response']['docs'][0]);

        // Set up and activate ILS if requested:
        if ($useIls) {
            $record->attachILS(
                $this->createMock(Connection::class),
                $this->createMock(Holds::class),
                $this->createMock(TitleHolds::class)
            );
            $record->setIlsBackends(['Solr']);
        }

        $this->assertEquals($expectedFormats, $record->getSchemaOrgFormatsArray());
    }

    /**
     * Test getFormattedMarcDetails() method.
     *
     * @return void
     */
    public function testGetFormattedMarcDetails(): void
    {
        $config = new \Laminas\Config\Config([]);
        $record = new \VuFind\RecordDriver\SolrMarc($config);
        $fixture = $this->getJsonFixture('misc/testbug1.json');
        $record->setRawData($fixture['response']['docs'][0]);
        $input = [
            'foo' => 'msg|true',
            'bar' => 'msg|false',
            'baz' => 'msg|xyzzy',
            'null' => 'msg',
            'title' => 'marc|a',
            'default' => 'marc',
            'emptySubfield' => 'marc|c',
            'pub' => 'marc|abc|260',
        ];
        $this->assertEquals(
            [
                [
                    'id' => '000105196',
                    'foo' => true,
                    'bar' => false,
                    'null' => null,
                    'baz' => 'xyzzy',
                    'title' => 'Bollettino della Unione matematica italiana.',
                    'default' => 'Bollettino della Unione matematica italiana.',
                    'emptySubfield' => '',
                    'pub' => 'Bologna : Zanichelli, 1922-1975.',
                ],
            ],
            $record->getFormattedMarcDetails('245', $input)
        );
    }

    /**
     * Test methods in MarcReaderTrait.
     *
     * @return void
     */
    public function testMarcReaderTrait(): void
    {
        $xml = $this->getFixture('marc/marctraits.xml');
        $record = new \VuFind\Marc\MarcReader($xml);
        $obj = $this->getMockBuilder(\VuFind\RecordDriver\SolrMarc::class)
            ->onlyMethods(['getMarcReader'])->getMock();
        $obj->expects($this->any())
            ->method('getMarcReader')
            ->willReturn($record);

        $reflection = new \ReflectionObject($obj);

        $getFieldArray = $reflection->getMethod('getFieldArray');
        $getFieldArray->setAccessible(true);
        $this->assertEquals(
            ['Author, Test (1800-)'],
            $getFieldArray->invokeArgs($obj, [100, ['a', 'd']])
        );

        $getSubfieldArray = $reflection->getMethod('getSubfieldArray');
        $getSubfieldArray->setAccessible(true);
        $this->assertEquals(
            ['Author, Test (1800-)'],
            $getSubfieldArray
                ->invokeArgs($obj, [$record->getField('100'), ['a', 'd']])
        );
    }
}
