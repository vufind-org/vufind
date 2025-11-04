<?php

/**
 * SolrForward Test Class
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

use Finna\RecordDriver\SolrForward;

use function is_callable;

/**
 * SolrForward Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrForwardTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test primary authors.
     *
     * @return void
     */
    public function testGetPrimaryAuthors()
    {
        $driver = $this->getDriver();
        $this->assertEquals(
            [
                [
                    'tag' => 'elotekija',
                    'name' => 'Juha Kuoma',
                    'role' => 'drt',
                    'id' => 'elonet_henkilo_1',
                    'type' => 'elonet_henkilo',
                    'roleName' => '',
                    'description' => '',
                    'uncredited' => '',
                    'idx' => 1,
                    'tehtava' => 'ohjaus',
                    'finna-activity-code' => 'D02',
                    'relator' => 'D02',
                ],
            ],
            $driver->getNonPresenterPrimaryAuthors()
        );
    }

    /**
     * Test producers.
     *
     * @return void
     */
    public function testGetProducers()
    {
        $driver = $this->getDriver();
        $this->assertEquals(
            [
                [
                    'tag' => 'elotuotantoyhtio',
                    'name' => 'Finna-filmi Oy',
                    'role' => 'pro',
                    'id' => 'elonet_yhtio_218057',
                    'type' => 'elonet_yhtio',
                    'roleName' => '',
                    'description' => '',
                    'uncredited' => '',
                    'idx' => 70000,
                    'finna-activity-code' => 'E10',
                    'relator' => 'E10',
                ],
            ],
            $driver->getProducers()
        );
    }

    /**
     * Function to get testGetPresenters data.
     *
     * @return array
     */
    public static function getPresentersData(): array
    {
        return [
            'creditedPresentersTest' => [
                'credited',
                [
                    'presenters' => [
                        [
                            'tag' => 'elonayttelija',
                            'name' => 'Ami Kunkka',
                            'role' => '',
                            'id' => 'elonet_henkilo_255464',
                            'type' => 'elonet_henkilo',
                            'roleName' => 'Debug Duck',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 120000,
                            'finna-activity-code' => 'E01',
                            'relator' => 'E01',
                            'elokuva-elonayttelija-rooli' => 'Debug Duck',
                        ],
                    ],
                ],
            ],
            'uncreditedPresentersTest' => [
                'uncredited',
                [
                    'presenters' => [
                        [
                            'tag' => 'elokreditoimatonnayttelija',
                            'name' => 'Kreditoimaton näyttelijä',
                            'role' => '',
                            'id' => 'elonet_henkilo_164393',
                            'type' => 'elonet_henkilo',
                            'roleName' => 'vankilavieras',
                            'description' => '',
                            'uncredited' => true,
                            'idx' => 130000,
                            'finna-activity-code' => 'E01',
                            'finna-activity-text' => 'kreditoimatonnäyttelijä',
                            'relator' => 'E01',
                            'elokuva-elokreditoimatonnayttelija-nimi'
                                => 'Kreditoimaton näyttelijä',
                            'elokuva-elokreditoimatonnayttelija-rooli'
                                => 'vankilavieras',
                        ],
                    ],
                ],
            ],
            'actingEnsemblesTest' => [
                'actingEnsemble',
                [
                    'presenters' => [
                        [
                            'tag' => 'elonayttelijakokoonpano',
                            'name' => 'Celsiukset miinuksella ja soitellaan.',
                            'role' => 'Esitti yhtyettä',
                            'id' => 'elonet_kokoonpano_1417980',
                            'type' => 'elonet_kokoonpano',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 140000,
                            'tehtava' => 'Esitti yhtyettä',
                            'finna-activity-code' => 'A99',
                            'finna-activity-text' => 'Esitti yhtyettä',
                            'elokuva-elonayttelijakokoonpano-tehtava'
                                => 'Esitti yhtyettä',
                            'relator' => 'A99',
                        ],
                    ],
                ],
            ],
            'performingEnsemblesTest' => [
                'performingEnsemble',
                [
                    'presenters' => [
                        [
                            'tag' => 'elonayttelijakokoonpano',
                            'name' => 'Soittokunta.',
                            'role' => '',
                            'id' => 'elonet_kokoonpano_1417980',
                            'type' => 'elonet_kokoonpano',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 180000,
                            'tehtava' => 'Esitti yhtyettä',
                            'finna-activity-code' => 'A99',
                            'relator' => 'A99',
                        ],
                    ],
                ],
            ],
            'performersTest' => [
                'performer',
                [
                    'presenters' => [
                        [
                            'tag' => 'eloesiintyja',
                            'name' => 'Esiin Tyjä',
                            'role' => '',
                            'id' => 'elonet_henkilo_1312480',
                            'type' => 'elonet_henkilo',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 50000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text' => 'dokumentti-esiintyjä',
                            'relator' => 'E99',
                        ],
                        [
                            'tag' => 'eloesiintyja',
                            'name' => 'Ääre M. Es',
                            'role' => '',
                            'id' => 'elonet_henkilo_1320375',
                            'type' => 'elonet_henkilo',
                            'roleName' => 'Tämä on määre',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 60000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text' => 'dokumentti-esiintyjä',
                            'relator' => 'E99',
                            'elokuva-eloesiintyja-maare' => 'Tämä on määre',
                        ],
                        [
                            'tag' => 'eloesiintyja',
                            'name' => 'Ei Roolia',
                            'role' => '',
                            'id' => 'elonet_henkilo_55113344',
                            'type' => 'elonet_henkilo',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 190000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text' => 'esiintyjä',
                            'relator' => 'E99',
                        ],
                    ],
                ],
            ],
            'uncreditedPerformersTest' => [
                'uncreditedPerformer',
                [
                    'presenters' =>
                    [
                        [
                            'tag' => 'elokreditoimatonesiintyja',
                            'name' => 'Doku M. Entti',
                            'role' => '',
                            'id' => 'elonet_henkilo_1344654',
                            'type' => 'elonet_henkilo',
                            'roleName' => 'Kreditöimätön esiintyjä',
                            'description' => '',
                            'uncredited' => true,
                            'idx' => 100000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text'
                                => 'kreditoimaton-dokumentti-esiintyjä',
                            'relator' => 'E99',
                            'elokuva-elokreditoimatonesiintyja-nimi'
                                => 'Doku M. Entti',
                            'elokuva-elokreditoimatonesiintyja-maare'
                                => 'Kreditöimätön esiintyjä',
                        ],
                        [
                            'tag' => 'elokreditoimatonesiintyja',
                            'name' => 'Doku M. Entti II',
                            'role' => '',
                            'id' => 'elonet_henkilo_1486496',
                            'type' => 'elonet_henkilo',
                            'roleName' => 'Kreditöimätön esiintyjä nr. 2',
                            'description' => '',
                            'uncredited' => true,
                            'idx' => 110000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text'
                                => 'kreditoimaton-dokumentti-esiintyjä',
                            'relator' => 'E99',
                            'elokuva-elokreditoimatonesiintyja-nimi'
                                => 'Doku M. Entti II',
                            'elokuva-elokreditoimatonesiintyja-maare'
                                => 'Kreditöimätön esiintyjä nr. 2',
                        ],
                    ],
                ],
            ],
            'assistantsTest' => [
                'assistant',
                [
                    'presenters' => [
                        [
                            'tag' => 'avustajat',
                            'name' => 'Matti, Miia, Mietos, Miro, Maria. (Sulkeet)',
                            'role' => 'avustajat',
                            'id' => '',
                            'type' => '',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 170000,
                            'finna-activity-code' => 'A99',
                            'finna-activity-text' => 'avustajat',
                            'elokuva-avustajat' => 'avustajat',
                            'relator' => 'A99',
                        ],
                    ],
                ],
            ],
            'othersTest' => [
                'other',
                [
                    'presenters' => [
                        [
                            'tag' => 'muutesiintyjat',
                            'name' => 'Kolme tuhisevaa siiliä!',
                            'role' => '',
                            'id' => '',
                            'type' => '',
                            'roleName' => '',
                            'description' => '',
                            'uncredited' => '',
                            'idx' => 200000,
                            'finna-activity-code' => 'E99',
                            'finna-activity-text' => 'dokumentti-muutesiintyjät',
                            'relator' => 'E99',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Function to get testGetNonPresenterSecondaryAuthors data.
     *
     * @return array
     */
    public static function getNonPresenterSecondaryAuthorsData(): array
    {
        return [
            'creditedTests' =>
            [
                'credited',
                [
                    [
                        'tag' => 'elotekija',
                        'name' => 'Juha Kuoma',
                        'role' => 'drt',
                        'id' => 'elonet_henkilo_1',
                        'type' => 'elonet_henkilo',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 1,
                        'tehtava' => 'ohjaus',
                        'finna-activity-code' => 'D02',
                        'relator' => 'D02',
                    ],
                    [
                        'tag' => 'elotekija',
                        'name' => 'Kuha Luoma',
                        'role' => 'aus',
                        'id' => 'elonet_henkilo_2',
                        'type' => 'elonet_henkilo',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 20000,
                        'tehtava' => 'käsikirjoitus',
                        'finna-activity-code' => 'aus',
                        'relator' => 'aus',
                    ],
                    [
                        'tag' => 'elotekijayhtio',
                        'name' => 'Tekevä Yhtiö Oy',
                        'role' => 'Yhtiön tehtävä',
                        'id' => 'elonet_yhtio_956916',
                        'type' => 'elonet_yhtio',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 40000,
                        'tehtava' => 'Yhtiön tehtävä',
                        'finna-activity-code' => 'A99',
                        'finna-activity-text' => 'Yhtiön tehtävä',
                        'elokuva-elotekijayhtio-tehtava' => 'Yhtiön tehtävä',
                        'relator' => 'A99',
                    ],
                    [
                        'tag' => 'elolevittaja',
                        'name' => 'Levittäjä Oy',
                        'role' => 'fds',
                        'id' => 'elonet_yhtio_210941',
                        'type' => 'elonet_yhtio',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 150000,
                        'finna-activity-code' => 'fds',
                        'relator' => 'fds',
                        'elokuva-elolevittaja-vuosi' => '2001',
                        'elokuva-elolevittaja-levitystapa' => 'teatterilevitys',
                    ],
                    [
                        'tag' => 'muuttekijat',
                        'name' => 'Paavo Pöllö, Martti Mäyrä,'
                            . ' Kalle Kissa, Seppo Siili',
                        'role' => '',
                        'id' => '',
                        'type' => '',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 210000,
                        'finna-activity-code' => 'oth',
                        'relator' => 'oth',
                    ],
                ],
            ],
            'ensemblesTests' =>
            [
                'ensembles',
                [
                    [
                        'tag' => 'elotekijakokoonpano',
                        'name' => 'Joku kuoro',
                        'role' => 'kuoro',
                        'id' => 'elonet_kokoonpano_1480640',
                        'type' => 'elonet_kokoonpano',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => '',
                        'idx' => 30000,
                        'tehtava' => 'kuoro',
                        'finna-activity-code' => 'A99',
                        'finna-activity-text' => 'kuoro',
                        'elokuva-elotekijakokoonpano-tehtava' => 'kuoro',
                        'relator' => 'A99',
                    ],
                ],
            ],
            'uncreditedTests' =>
            [
                'uncredited',
                [
                    [
                        'tag' => 'elokreditoimatontekija',
                        'name' => 'Valo K. Uvaus',
                        'role' => 'valokuvat',
                        'id' => 'elonet_henkilo_107674',
                        'type' => 'elonet_henkilo',
                        'roleName' => '',
                        'description' => '',
                        'uncredited' => true,
                        'idx' => 160000,
                        'tehtava' => 'valokuvat',
                        'finna-activity-code' => 'A99',
                        'finna-activity-text' => 'valokuvat',
                        'elokuva-elokreditoimatontekija-tehtava' => 'valokuvat',
                        'relator' => 'A99',
                        'elokuva-elokreditoimatontekija-nimi' => 'Valo K. Uvaus',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test presenters.
     *
     * @param string $key      Key of the array to test.
     * @param array  $expected Result to be expected.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getPresentersData')]
    public function testGetPresenters(string $key, array $expected): void
    {
        $driver = $this->getDriver();
        $this->assertTrue(is_callable([$driver, 'getPresenters'], true));
        $authors = $driver->getPresenters();
        $this->assertTrue(isset($authors[$key]));
        $this->assertEquals(
            $expected,
            $authors[$key]
        );
    }

    /**
     * Test funders.
     *
     * @return void
     */
    public function testGetFunders(): void
    {
        $driver = $this->getDriver();
        $this->assertEquals(
            [
                [
                  'tag' => 'elorahoitusyhtio',
                  'name' => 'Rahoitus tuotantotuki',
                  'role' => 'fnd',
                  'id' => 'elonet_yhtio_11',
                  'type' => 'elonet_yhtio',
                  'roleName' => '',
                  'description' => '',
                  'uncredited' => '',
                  'idx' => 80000,
                  'finna-activity-code' => 'fnd',
                  'relator' => 'fnd',
                  'elokuva-elorahoitusyhtio-rahoitustapa' => 'tuotantotuki',
                  'elokuva-elorahoitusyhtio-summa' => '159 779 €',
                  'amount' => '159 779 €',
                  'fundingType' => 'tuotantotuki',
                ],
                [
                  'tag' => 'elorahoitusyhtio',
                  'name' => 'Rahoitus yhteistyö',
                  'role' => 'fnd',
                  'id' => 'elonet_yhtio_710074',
                  'type' => 'elonet_yhtio',
                  'roleName' => '',
                  'description' => '',
                  'uncredited' => '',
                  'idx' => 90000,
                  'finna-activity-code' => 'fnd',
                  'relator' => 'fnd',
                  'elokuva-elorahoitusyhtio-henkilo' => 'Raho Ittaja',
                  'elokuva-elorahoitusyhtio-rahoitustapa' => 'yhteistyö',
                  'amount' => '',
                  'fundingType' => 'yhteistyö',
                ],
            ],
            $driver->getFunders()
        );
    }

    /**
     * Test funders.
     *
     * @return void
     */
    public function testGetDistributors(): void
    {
        $driver = $this->getDriver();
        $this->assertEquals(
            [
                [
                  'tag' => 'elolevittaja',
                  'name' => 'Levittäjä Oy',
                  'role' => 'fds',
                  'id' => 'elonet_yhtio_210941',
                  'type' => 'elonet_yhtio',
                  'roleName' => '',
                  'description' => '',
                  'uncredited' => '',
                  'idx' => 150000,
                  'finna-activity-code' => 'fds',
                  'relator' => 'fds',
                  'elokuva-elolevittaja-vuosi' => '2001',
                  'elokuva-elolevittaja-levitystapa' => 'teatterilevitys',
                  'date' => '2001',
                  'method' => 'teatterilevitys',
                ],
            ],
            $driver->getDistributors()
        );
    }

    /**
     * Test nonpresenter secondaryauthors.
     *
     * @param string $key      Key of the array to test.
     * @param array  $expected Result to be expected.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getNonPresenterSecondaryAuthorsData')]
    public function testGetNonPresenterSecondaryAuthors(
        string $key,
        array $expected
    ): void {
        $driver = $this->getDriver();
        $function = 'getNonPresenterSecondaryAuthors';
        $this->assertTrue(is_callable([$driver, $function], true));
        $authors = $driver->getNonPresenterSecondaryAuthors();
        $this->assertTrue(isset($authors[$key]));
        $this->assertEquals(
            $expected,
            $authors[$key]
        );
    }

    /**
     * Function to get testEvents data with array expected.
     *
     * @return array
     */
    public static function getEventsArrayData(): array
    {
        return [
            [
                'getAccessRestrictions',
                [],
            ],
            [
                'getDescription',
                [
                    'Tämä on sisällön kuvaus.',
                ],
            ],
            [
                'getGeneralNotes',
                [
                    'Tässä on huomautukset.',
                ],
            ],
            [
                'getAllSubjectHeadings',
                [
                    ['Testi'],
                    ['Unit'],
                    ['Forward'],
                ],
            ],
            [
                'getAlternativeTitles',
                [
                    'Zoo (swe)',
                    'Animals (language name)',
                    'Animals Working (working title)',
                    'Park (test name)',
                ],
            ],
            [
                'getAwards',
                [
                    'Paras elokuva.',
                    'Best movie.',
                    'Good movie.',
                ],
            ],
            [
                'getPlayingTimes',
                [
                    '1 min',
                ],
            ],
            [
                'getPremiereTheaters',
                [
                    'Leppävaara: Sellosali 1',
                    'Karjaa: Bio Pallas',
                ],
            ],
            [
                'getBroadcastingInfo',
                [
                    [
                        'time' => '7.05.1995',
                        'place' => 'Kanava 1',
                        'viewers' => '1 000 (mediaani)',
                    ],
                    [
                        'time' => '15.05.2011',
                        'place' => 'Kanava 2',
                        'viewers' => '5 000',
                    ],
                ],
            ],
            [
                'getFestivalInfo',
                [
                    [
                        'name' => 'Ensimmäinen festivaaliosallistuminen',
                        'region' => 'Leppävaara, Suomi',
                        'date' => '1990',
                    ],
                    [
                        'name' => 'Toinen festivaaliosallistuminen',
                        'region' => 'Lahti, Suomi',
                        'date' => '1991',
                    ],
                ],
            ],
            [
                'getForeignDistribution',
                [
                    [
                        'name' => 'Mat',
                        'region' => 'Ruotsi',
                    ],
                    [
                        'name' => 'Pat',
                        'region' => 'Norja',
                    ],
                    [
                        'name' => 'Tanska',
                        'region' => '',
                    ],
                ],
            ],
            [
                'getOtherScreenings',
                [
                    [
                        'name' => 'ennakkoesitys',
                        'region' => 'Mordor, Keskimaa',
                        'date' => '03.03.2000',
                    ],
                ],
            ],
            [
                'getInspectionDetails',
                [
                    [
                        'inspector' => 'T',
                        'number' => 'A-5',
                        'format' => '1 mm',
                        'length' => '1 m',
                        'runningtime' => '1 min',
                        'agerestriction' => 'S',
                        'additional' => 'Tarkastajat: Tarkastajat OY',
                        'office' => 'Finna-filmit Oy',
                        'date' => '15.02.2001',
                    ],
                ],
            ],
            [
                'getLocationNotes',
                [
                    'Tässä on tietoa kuvauspaikkahuomautuksista.',
                ],
            ],
            [
                'getMovieThanks',
                [
                    'Kiitos, thanks, tack.',
                ],
            ],
        ];
    }

    /**
     * Function to get testEvents data with string expected.
     *
     * @return array
     */
    public static function getEventsStringData(): array
    {
        return [
            [
                'getColor',
                'väri',
            ],
            [
                'getColorSystem',
                'rgb',
            ],
            [
                'getType',
                'kauhu, draama',
            ],
            [
                'getAspectRatio',
                '1,75:1',
            ],
            [
                'getMusicInfo',
                'Tästä musiikki-infosta poistuu br merkki alusta.',
            ],
            [
                'getOriginalWork',
                'lotr',
            ],
            [
                'getPressReview',
                'Tässä on lehdistöarvio.',
            ],
            [
                'getSound',
                'ääni',
            ],
            [
                'getSoundSystem',
                '6+1',
            ],
            [
                'getProductionCost',
                '5 €',
            ],
            [
                'getPremiereTime',
                '01.01.2001',
            ],
            [
                'getNumberOfCopies',
                '1',
            ],
            [
                'getAmountOfViewers',
                '1 100',
            ],
            [
                'getAgeLimit',
                'S',
            ],
            [
                'getFilmingDate',
                '10.6.1996 - syksy 2000 (Lähde: ctrl+c 22.2.2010).',
            ],
            [
                'getArchiveFilms',
                'Infoa arkistoaineistosta.',
            ],
        ];
    }

    /**
     * Test events with string values as return types.
     *
     * @param string $function Function of the driver to test.
     * @param string $expected Result to be expected.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getEventsStringData')]
    public function testEvents(
        string $function,
        string $expected
    ): void {
        $driver = $this->getDriver();
        $this->assertTrue(is_callable([$driver, $function], true));
        $this->assertEquals(
            $expected,
            $driver->$function()
        );
    }

    /**
     * Test events with array values as return types.
     *
     * @param string $function Function of the driver to test.
     * @param array  $expected Result to be expected.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getEventsArrayData')]
    public function testEventsWithArrayExpected(
        string $function,
        array $expected
    ): void {
        $driver = $this->getDriver();
        $this->assertTrue(is_callable([$driver, $function], true));
        $this->assertEquals(
            $expected,
            $driver->$function()
        );
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides    Fixture fields to override.
     * @param array $searchConfig Search configuration.
     *
     * @return SolrForward
     */
    protected function getDriver($overrides = [], $searchConfig = []): SolrForward
    {
        $fixture = $this->getFixture('forward/forward_test.xml', 'Finna');
        $record = new SolrForward(
            null,
            null,
            new \VuFind\Config\Config($searchConfig)
        );
        $record->setRawData(['fullrecord' => $fixture]);
        return $record;
    }
}
