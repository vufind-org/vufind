<?php

/**
 * SolrMarc Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\RecordDriver;

use Finna\RecordDriver\SolrMarc;
use Generator;

/**
 * SolrMarc Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrMarcTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Data provider for testTitlePunctuation
     *
     * @return array
     */
    public static function getTestTitlePunctuationData(): array
    {
        return [
            [
                'Title',
                'Title /:',
            ],
            [
                'Title',
                'Title /  ',
            ],
            [
                'Title',
                'Title (((',
            ],
            [
                '(((',
                '(((',
            ],
        ];
    }

    /**
     * Test title trailing punctuation handling
     *
     * @param string $expected Expected result
     * @param string $title    Record title
     *
     * @dataProvider getTestTitlePunctuationData
     *
     * @return void
     */
    public function testTitlePunctuation(string $expected, string $title): void
    {
        $marc = [
            'leader' => '',
            'fields' => [
                [
                    '245' => [
                        'ind1' => ' ',
                        'ind2' => ' ',
                        'subfields' => [
                            ['a' => $title],
                        ],
                    ],
                ],
            ],
        ];

        $record = new SolrMarc();
        $record->setRawData(
            [
                'fullrecord' => json_encode($marc),
            ]
        );

        $this->assertEquals($expected, $record->getTitle());
    }

    /**
     * Data provider for testHostRecordsData
     *
     * @return Generator
     */
    public static function getTestHostRecordsData(): Generator
    {
        yield 'legacy host record links' => [
            'marc/legacy_linking_ids.xml',
            [
                'test' => [
                    'legacy_settings' => [
                        'linking_id' => true,
                    ],
                ],
            ],
            [
                [
                    'id' => 'test.123456',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
            ],
        ];
        yield 'host record link with prefix' => [
            'marc/linking_ids.xml',
            [
                'test' => [
                    'prefixIn003' => true,
                ],
            ],
            [
                [
                    'id' => '',
                    'linkingId' => '(FI-MELINDA)123456789',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '',
                    'linkingId' => '(FI-MELINDA)555',
                    'sourceId' => 'Solr',
                    'title' => 'United records Top',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '',
                    'linkingId' => '(FI-MELINDA)019172566',
                    'sourceId' => 'Solr',
                    'title' => 'Art Research',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => 'Included in collections',
                ],
            ],
        ];
        yield 'host record link with prefix mismatch' => [
            'marc/linking_ids_prefix_mismatch.xml',
            [
                'test' => [
                    'prefixIn003' => true,
                ],
            ],
            [
                [
                    'id' => '',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records Top',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'Art Research',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => 'Included in collections',
                ],
            ],
        ];
        yield 'host record link with dots' => [
            'marc/linking_ids_with_dots.xml',
            [
                'test' => [
                    'prefixIn003' => true,
                ],
            ],
            [
                [
                    'id' => 'link.withdot1',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => 'link.withdot2',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
            ],
        ];
        yield 'host record link with no prefix' => [
            'marc/linking_ids_no_prefix.xml',
            [
                'test' => [
                    'prefixIn003' => false,
                ],
            ],
            [
                [
                    'id' => '123456789',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records parent',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '555',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'United records Top',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => '',
                ],
                [
                    'id' => '019172566',
                    'linkingId' => '',
                    'sourceId' => 'Solr',
                    'title' => 'Art Research',
                    'reference' => '',
                    'publishingInfo' => '',
                    'mainHeading' => '',
                    'relation' => 'Included in collections',
                ],
            ],
        ];
    }

    /**
     * Test record linking with Legacy and new way
     *
     * @param string $fixture  Fixture path to test file
     * @param array  $dsConfig Datasource configuration
     * @param array  $expected Array of expected results
     *
     * @dataProvider getTestHostRecordsData
     *
     * @return void
     */
    public function testGetHostRecords(string $fixture, array $dsConfig, array $expected): void
    {
        $xml = $this->getFixture($fixture, 'Finna');
        $config = new \VuFind\Config\Config([
            'Record' => [
                'marc_links' => '760,762,765,767,770,772,773,775,776,780,785',
                'marc_links_link_types' => 'linkingId,id,oclc,dlc,isbn,issn,title',
            ],
        ]);

        $obj = new SolrMarc($config);
        $obj->setRawData(
            [
                'datasource_str_mv' => ['test'],
                'fullrecord' => $xml,
            ],
        );
        $obj->attachDatasourceSettings($dsConfig);

        $this->assertEquals($expected, $obj->getHostRecords());
    }

    /**
     * Data provider for testRecordLinking
     *
     * @return Generator
     */
    public static function getTestAllRecordLinksData(): Generator
    {
        yield 'legacy record links' => [
            'marc/legacy_linking_ids.xml',
            [
                'test' => [
                    'legacy_settings' => [
                        'linking_id' => true,
                    ],
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'bib',
                        'value' => 'test.123456',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'title',
                        'value' => 'United records parent',
                    ],
                    'isCollection' => false,
                ],
            ],
        ];
        yield 'record link with prefixes' => [
            'marc/linking_ids.xml',
            [
                'test' => [
                    'prefixIn003' => true,
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'linkingId',
                        'value' => '(FI-MELINDA)123456789',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records Top',
                    'title' => 'Another United',
                    'link' => [
                        'type' => 'linkingId',
                        'value' => '(FI-MELINDA)555',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'Art Research',
                    'title' => 'Included in collections',
                    'link' => [
                        'type' => 'linkingId',
                        'value' => '(FI-MELINDA)019172566',
                    ],
                    'isCollection' => true,
                ],
            ],
        ];
        yield 'record link without prefixes' => [
            'marc/linking_ids_no_prefix.xml',
            [
                'test' => [
                    'prefixIn003' => false,
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'bib',
                        'value' => '123456789',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records Top',
                    'title' => 'Another United',
                    'link' => [
                        'type' => 'bib',
                        'value' => '555',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'Art Research',
                    'title' => 'Included in collections',
                    'link' => [
                        'type' => 'bib',
                        'value' => '019172566',
                    ],
                    'isCollection' => true,
                ],
            ],
        ];
        yield 'record link check linking id with multiple prefixes' => [
            'marc/linking_ids_prefix_mismatch.xml',
            [
                'test' => [
                    'link_prefixes' => 'FI-MELINDA,FI-NL',
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'linkingId',
                        'value' => '(FI-MELINDA)123456789',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records Top',
                    'title' => 'Another United',
                    'link' => [
                        'type' => 'linkingId',
                        'value' => '(FI-NL)555',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'Art Research',
                    'title' => 'Included in collections',
                    'link' => [
                        'type' => 'title',
                        'value' => 'Art Research',
                    ],
                    'isCollection' => true,
                ],
            ],
        ];
        yield 'record link check linking id with a dot' => [
            'marc/linking_ids_with_dots.xml',
            [
                'test' => [
                    'prefixIn003' => true,
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'bib',
                        'value' => 'link.withdot1',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'bib',
                        'value' => 'link.withdot2',
                    ],
                    'isCollection' => false,
                ],
            ],
        ];
        yield 'record link force checking legacy ids' => [
            'marc/linking_ids.xml',
            [
                'test' => [
                    'prefixIn003' => false,
                    'legacy_settings' => [
                        'linking_id' => true,
                    ],
                ],
            ],
            [
                [
                    'value' => 'United records parent',
                    'title' => 'United',
                    'link' => [
                        'type' => 'title',
                        'value' => 'United records parent',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'United records Top',
                    'title' => 'Another United',
                    'link' => [
                        'type' => 'title',
                        'value' => 'United records Top',
                    ],
                    'isCollection' => false,
                ],
                [
                    'value' => 'Art Research',
                    'title' => 'Included in collections',
                    'link' => [
                        'type' => 'title',
                        'value' => 'Art Research',
                    ],
                    'isCollection' => true,
                ],
            ],
        ];
    }

    /**
     * Test getAllRecordLinks
     *
     * @param string $fixture  Fixture path to test file
     * @param array  $dsConfig Datasource configuration
     * @param array  $expected Array of expected results
     *
     * @dataProvider getTestAllRecordLinksData
     *
     * @return void
     */
    public function testGetAllRecordLinks(string $fixture, array $dsConfig, array $expected): void
    {
        $xml = $this->getFixture($fixture, 'Finna');
        $config = new \VuFind\Config\Config([
            'Record' => [
                'marc_links' => '760,762,765,767,770,772,773,775,776,780,785',
                'marc_links_link_types' => 'linkingId,id,oclc,dlc,isbn,issn,title',
            ],
        ]);

        $obj = new SolrMarc($config);
        $obj->setRawData(
            [
                'datasource_str_mv' => ['test'],
                'fullrecord' => $xml,
            ],
        );
        $obj->attachDatasourceSettings($dsConfig);

        $this->assertEquals($expected, $obj->getAllRecordLinks());
    }
}
