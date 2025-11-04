<?php

/**
 * SolrLrmi Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Minna Rönkä <minna.ronka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\RecordDriver;

use Finna\RecordDriver\SolrLrmi;

use function is_callable;

/**
 * SolrLrmi Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Minna Rönkä <minna.ronka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrLrmiTest extends \PHPUnit\Framework\TestCase
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
                'getIdentifier',
                [
                    'urn:nbn:fi:oerfi-202402_00027263_1',
                ],
            ],
            [
                'getSummary',
                [
                    'Suomenkielinen kuvausteksti',
                    'Toinen suomenkielinen kuvausteksti',
                ],
            ],
            [
                'getNonPresenterAuthors',
                [
                    [
                        'name' => 'Suojellaan Lapsia ry, Protect Children',
                    ],
                ],
            ],
            [
                'getTopics',
                [
                    'digitaalinen media',
                    'digitalisaatio',
                ],
            ],
            [
                'getMaterials',
                [
                    [
                        'url' => 'https://materiaalilinkki1.pdf',
                        'pdfUrl' => null,
                        'title' => 'MyFriendToo-juliste 1',
                        'format' => 'pdf',
                        'filesize' => '146204',
                        'position' => 2,
                    ],
                    [
                        'url' => 'https://materiaalilinkki2.pdf',
                        'pdfUrl' => null,
                        'title' => 'MyFriendToo-juliste 2',
                        'format' => 'pdf',
                        'filesize' => '159732',
                        'position' => 3,
                    ],
                    [
                        'url' => 'https://materiaalilinkkienglanti.pdf',
                        'pdfUrl' => null,
                        'title' => 'MyFriendToo-poster 1',
                        'format' => 'pdf',
                        'filesize' => '157766',
                        'position' => 5,
                    ],
                    [
                        'url' => 'https://materiaalilinkkiruotsi.pdf',
                        'pdfUrl' => null,
                        'title' => 'MyFriendToo-affisch 1',
                        'format' => 'pdf',
                        'filesize' => '156683',
                        'position' => 7,
                    ],
                ],
            ],
            [
                'getEducationalUse',
                [
                    'Ohjeistus',
                ],
            ],
        ];
    }

    /**
     * Test functions
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
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $translator->setLocale('fi');
        $driver = $this->getDriver('lrmi_test.xml');
        $driver->setTranslator($translator);
        $this->assertTrue(is_callable([$driver, $function], true));
        $this->assertEquals(
            $expected,
            $driver->$function()
        );
    }

    /**
     * Function to get summary data
     *
     * @return array
     */
    public static function getSummaryData(): array
    {
        return [
            [
                'fi',
                [
                'Suomenkielinen kuvausteksti',
                'Toinen suomenkielinen kuvausteksti',
                ],
            ],
            [
                'en',
                [
                'Description in English',
                ],
            ],
            [
                'sv',
                [
                'Deskription på svenska',
                ],
            ],
            [
                'se',
                [
                'Suomenkielinen kuvausteksti',
                'Toinen suomenkielinen kuvausteksti',
                ],
            ],
        ];
    }

    /**
     * Test getSummary
     *
     * @param string $language Language
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSummaryData')]
    public function testSummary(
        string $language,
        array $expected
    ): void {
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $translator->setLocale($language);
        $driver = $this->getDriver('lrmi_test.xml');
        $driver->setTranslator($translator);
        $this->assertEquals(
            $expected,
            $driver->getSummary()
        );
    }

    /**
     * Function to get expected alternative titles data
     *
     * @return array
     */
    public static function getAlternativeTitlesData(): array
    {
        return [
            [
                'Pääotsikko',
                ['Pääotsikko', 'Vaihtoehtoinen otsikko 1', 'Vaihtoehtoinen otsikko 2'],
                ['Vaihtoehtoinen otsikko 1', 'Vaihtoehtoinen otsikko 2'],
            ],
        ];
    }

    /**
     * Test getAlternativeTitles
     *
     * @param string $title     Title index value to test
     * @param array  $altTitles Alternative title index values to test
     * @param ?array $expected  Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAlternativeTitlesData')]
    public function testGetAlternativeTitles(
        string $title,
        array $altTitles,
        ?array $expected
    ): void {
        $record = new SolrLrmi(
            [],
            [],
            new \VuFind\Config\Config([])
        );
        $record->setRawData(
            [
                'title' => $title,
                'title_alt' => $altTitles,
            ]
        );
        $this->assertEquals(
            $expected,
            $record->getAlternativeTitles()
        );
    }

    /**
     * Get a record driver with fake data.
     *
     * @param string $recordXml    Xml record to use for the test
     * @param array  $overrides    Fixture fields to override.
     * @param array  $searchConfig Search configuration.
     *
     * @return SolrLrmi
     */
    protected function getDriver(string $recordXml, $overrides = [], $searchConfig = []): SolrLrmi
    {
        $fixture = $this->getFixture("lrmi/$recordXml", 'Finna');
        $record = new SolrLrmi(
            null,
            null,
            new \VuFind\Config\Config($searchConfig)
        );
        $localeConfig = [
            'Site' => [
                'language' => 'fi',
                'fallback_languages' => 'fi,en',
                'browserDetectLanguage' => false,
            ],
            'Languages' => [
                'fi' => 'Finnish',
                'en' => 'English',
                'sv' => 'Swedish',
                'en-gb' => 'British English',
                'se' => 'Northern Sámi',
            ],
        ];
        $localeConfig = new \VuFind\Config\Config($localeConfig);
        $record->attachLocaleSettings(new \VuFind\I18n\Locale\LocaleSettings($localeConfig));
        $record->setRawData(['fullrecord' => $fixture]);
        return $record;
    }
}
