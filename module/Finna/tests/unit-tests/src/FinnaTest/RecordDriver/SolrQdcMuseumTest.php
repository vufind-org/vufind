<?php

/**
 * SolrQdc Museum Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\RecordDriver;

use Finna\RecordDriver\SolrQdc;

use function is_callable;

/**
 * SolrQdc Museum Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrQdcMuseumTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Function to get expected function data
     *
     * @return array
     */
    public static function getTestFunctionsData(): array
    {
        return [
            [
                'getAllImages',
                [
                    0 => [
                        'urls' => [
                            'large' => 'https://www.savanni.art.collection.org/large/ducksinharmony.jpg',
                            'small' => 'https://www.savanni.art.collection.org/square/ducksinharmony.jpg',
                            'medium' => 'https://www.savanni.art.collection.org/medium/ducksinharmony.jpg',
                            'original' => 'https://www.savanni.art.collection.org/original/ducksinharmony.jpg',
                        ],
                        'description' => '',
                        'rights' => [
                            'copyright' => 'In CopyRight',
                            'link' => false,
                            'description' => [
                                '2023 Finna qa',
                            ],
                        ],
                        'highResolution' => [
                            'original' => [
                                0 => [
                                    'data' => [],
                                    'url' => 'https://www.savanni.art.collection.org/original/ducksinharmony.jpg',
                                    'format' => 'jpg',
                                ],
                            ],
                        ],
                        'downloadable' => false,
                        'cacheSizes' => [],
                    ],
                ],
            ],
            [
                'getAllRecordLinks',
                [
                    0 => [
                        'value' => 'Ducks in the universe',
                        'link' => [
                            'value' => 'Ducks in the universe',
                            'type' => 'allFields',
                        ],
                    ],
                ],
            ],
            [
                'getSeries',
                [],
            ],
            [
                'getIdentifier',
                [
                    0 => 'TM 1234',
                ],
            ],
            [
                'getKeywords',
                [],
            ],
            [
                'getISBNs',
                [],
            ],
            [
                'getOtherIdentifiers',
                [
                    0 => [
                        'data' => 'Q123456789',
                        'detail' => 'wikidata',
                    ],
                    1 => [
                        'data' => 'TM 1234',
                        'detail' => 'wikidata:P217',
                    ],
                ],
            ],
            [
                'getURLs',
                [],
            ],
            [
                'getEducationPrograms',
                [],
            ],
            [
                'getPhysicalDescriptions',
                [
                    0 => '2.1 cm x 2.3 cm',
                ],
            ],
            [
                'getPhysicalMediums',
                [
                    0 => 'Akryyli',
                    1 => 'Kangas',
                ],
            ],
            [
                'getDescriptions',
                [
                    0 => 'painting by Juha Kuoma',
                    1 => 'abstract should be removed',
                ],
            ],
            [
                'getAbstracts',
                [],
            ],
            [
                'getDescriptionURL',
                false,
            ],
        ];
    }

    /**
     * Test functions with return value array
     *
     * @param string $function Function of the driver to test
     * @param mixed  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestFunctionsData')]
    public function testFunctions(
        string $function,
        $expected
    ): void {
        $driver = $this->getDriver();
        $this->assertTrue(is_callable([$driver, $function], true));
        $this->assertEquals(
            $expected,
            $driver->$function()
        );
    }

    /**
     * Function to get expected publication date range data
     *
     * @return array
     */
    public static function getPublicationDateRangeData(): array
    {
        return [
            [
                '[2001-01-01 TO 2001-12-31]',
                ['2001'],
            ],
            [
                '[1998-01-01 TO 2012-12-31]',
                ['1998', '2012'],
            ],
            [
                '[-2002-01-01 TO 0100-12-31]',
                ['-2002', '100'],
            ],
            [
                '[-0099-10-31 TO -0001-05-01]',
                ['-99', '-1'],
            ],
            [
                '[0000-01-01 TO 0000-12-31]',
                ['0'],
            ],
            [
                '[0999-06-02 TO 9999-12-31]',
                ['999', ''],
            ],
            [
                '[-9999-01-01 TO 9998-12-31]',
                ['-9999', '9998'],
            ],
            [
                '1937-12-08',
                ['1937'],
            ],
            [
                '',
                null,
            ],
        ];
    }

    /**
     * Test getPublicationDateRange
     *
     * @param string $indexValue Index value to test
     * @param ?array $expected   Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getPublicationDateRangeData')]
    public function testGetPublicationDateRange(
        string $indexValue,
        ?array $expected
    ): void {
        $record = new SolrQdc(
            [],
            [],
            new \VuFind\Config\Config([])
        );
        $record->setRawData(
            [
                'id' => 'knp-247394',
                'publication_daterange' => $indexValue,
            ]
        );
        $this->assertEquals(
            $expected,
            $record->getPublicationDateRange()
        );
    }

    /**
     * Simple function to test element filtering works properly
     *
     * @return void
     */
    public function testXmlElementFilter(): void
    {
        $driver = $this->getDriver();
        $filtered = $this->getFixture('qdc/qdc_museum_test_filtered.xml', 'Finna');
        $this->assertEquals($filtered, $driver->getFilteredXML());
    }

    /**
     * Function to get expected human readable publication dates data
     *
     * @return array
     */
    public static function getHumanReadablePublicationDatesData(): array
    {
        return [
            [
                '[2001-01-01 TO 2001-12-31]',
                ['2001'],
            ],
            [
                '[1998-01-01 TO 2012-12-31]',
                ['1998–2012'],
            ],
            [
                '[-2002-01-01 TO 0100-12-31]',
                ['-2002–100'],
            ],
            [
                '[-0099-10-31 TO -0001-05-01]',
                ['-99–-1'],
            ],
            [
                '[0000-01-01 TO 0000-12-31]',
                ['0'],
            ],
            [
                '[0999-06-02 TO 9999-12-31]',
                ['999–'],
            ],
            [
                '[-9999-01-01 TO 9998-12-31]',
                ['-9999–9998'],
            ],
            [
                '1937-12-08',
                ['1937'],
            ],
            [
                '',
                [],
            ],
        ];
    }

    /**
     * Test getHumanReadablePublicationDates
     *
     * @param string $indexValue Index value to test
     * @param ?array $expected   Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getHumanReadablePublicationDatesData')]
    public function testGetHumanReadablePublicationDates(
        string $indexValue,
        ?array $expected
    ): void {
        $record = new SolrQdc(
            [],
            [],
            new \VuFind\Config\Config([])
        );
        $record->setRawData(
            [
                'id' => 'knp-247394',
                'publication_daterange' => $indexValue,
            ]
        );
        $this->assertEquals(
            $expected,
            $record->getHumanReadablePublicationDates()
        );
    }

    /**
     * Get a record driver with fake data
     *
     * @param array $overrides    Fixture fields to override
     * @param array $searchConfig Search configuration
     *
     * @return SolrQdc
     */
    protected function getDriver($overrides = [], $searchConfig = []): SolrQdc
    {
        $fixture = $this->getFixture('qdc/qdc_museum_test.xml', 'Finna');
        $config = [
            'Record' => [
                'allowed_external_hosts_mode' => 'disable',
            ],
            'ImageRights' => [
                'fi' => [
                    'CC BY 4.0' => 'http://creativecommons.org/licenses/by/4.0/deed.fi',
                ],
                'en-gb' => [
                    'CC BY 4.0' => 'http://creativecommons.org/licenses/by/4.0/deed.en',
                ],
                'sv' => [
                    'CC BY 4.0' => 'http://creativecommons.org/licenses/by/4.0/deed.sv',
                ],
            ],
        ];
        $record = new SolrQdc(
            $config,
            $config,
            new \VuFind\Config\Config($searchConfig)
        );
        $localeConfig = [
            'Site' => [
                'language' => 'fi',
                'fallback_languages' => 'en-gb,sv',
                'browserDetectLanguage' => false,
            ],
            'Languages' => [
                'fi' => 'Finnish',
                'en' => 'English',
                'sv' => 'Swedish',
                'en-gb' => 'British English',
            ],
        ];
        $localeConfig = new \VuFind\Config\Config($localeConfig);
        $record->attachLocaleSettings(new \VuFind\I18n\Locale\LocaleSettings($localeConfig));
        $record->setRawData(['id' => 'knp-247394', 'fullrecord' => $fixture]);
        return $record;
    }
}
