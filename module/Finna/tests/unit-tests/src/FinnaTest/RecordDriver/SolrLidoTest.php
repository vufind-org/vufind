<?php

/**
 * SolrLido Test Class
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

use Finna\RecordDriver\SolrLido;

use function is_callable;

/**
 * SolrLido Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrLidoTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Function to get expected representations data
     *
     * @return array
     */
    public static function getRepresentationsData(): array
    {
        return [
            [
                'getModels',
                [
                    2 => [
                        'models' => [
                            [
                                'url' => 'https://gltfmalli.gltf',
                                'format' => 'gltf',
                                'type' => 'preview',
                                'data' => [
                                    'size' => [
                                        'unit' => 'byte',
                                        'value' => '60840000',
                                    ],
                                ],
                            ],
                            [
                                'url' => 'https://glbmalli.glb',
                                'format' => 'glb',
                                'type' => 'preview',
                            ],
                        ],
                        'rights' => [
                            'copyright' => 'InC',
                            'description' => [
                                'Tässä on mallien copyright.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'getAllImages',
                [
                    [
                        'urls' => [
                            'large' => 'https://largekuvanlinkki.com',
                            'small' => 'https://largekuvanlinkki.com',
                            'medium' => 'https://largekuvanlinkki.com',
                        ],
                        'description' => '',
                        'rights' => [
                            'copyright' => 'CC BY 4.0',
                            'description' => [
                                0 => 'Tässä on kuvien copyright.',
                            ],
                        ],
                        'highResolution' => [
                            'original' => [
                                0 => [
                                    'data' => [
                                        'size' => [
                                            'unit' => 'byte',
                                            'value' => '123',
                                        ],
                                        'width' => [
                                            'unit' => 'pixel',
                                            'value' => '123',
                                        ],
                                        'height' => [
                                            'unit' => 'pixel',
                                            'value' => '123',
                                        ],
                                    ],
                                    'url' => 'https://originalKuvanLinkkiTif.com',
                                    'format' => 'tif',
                                    'resourceID' => '607642',
                                ],
                            ],
                        ],
                        'identifier' => '607642',
                        'downloadable' => true,
                        'resourceDescription' => 'Kuvan selitys',
                        'cacheSizes' => [
                            'small' => 'large',
                            'medium' => 'large',
                        ],
                    ],
                    [
                        'urls' => [
                            'large' => 'https://largekuvanlinkki2.com',
                            'small' => 'https://thumbkuvanlinkki2.com',
                            'medium' => 'https://thumbkuvanlinkki2.com',
                            'master' => 'https://masterkuvanlinkki2.com',
                        ],
                        'description' => '',
                        'rights' => [
                            'copyright' => 'InC',
                            'description' => [
                                0 => 'Tässä on kuvien copyright.',
                            ],
                        ],
                        'highResolution' => [
                            'original' => [
                                0 => [
                                    'data' => [
                                        'size' => [
                                            'unit' => 'byte',
                                            'value' => '5',
                                        ],
                                        'width' => [
                                            'unit' => 'pixel',
                                            'value' => '5',
                                        ],
                                        'height' => [
                                            'unit' => 'pixel',
                                            'value' => '5',
                                        ],
                                    ],
                                    'url' => 'https://originalKuvanLinkkiTif.com',
                                    'format' => 'tif',
                                    'resourceID' => '607643',
                                ],
                            ],
                            'master' => [
                                [
                                    'url' => 'https://masterkuvanlinkki2.com',
                                    'data' => [],
                                    'format' => 'jpg',
                                    'resourceID' => '607643',
                                ],
                            ],
                        ],
                        'identifier' => '607643',
                        'downloadable' => false,
                        'resourceName' => 'Kuvan nimi',
                        'cacheSizes' => [
                            'medium' => 'small',
                        ],
                    ],
                    2 => [
                        'urls' => [
                            'large' => 'https://kaikkilinkit.com',
                            'small' => 'https://kaikkilinkit.com',
                            'medium' => 'https://kaikkilinkit.com',
                        ],
                        'description' => '',
                        'rights' => [
                            'copyright' => 'CC BY 4.0',
                            'description' => [
                                0 => 'Tässä on kuvien copyright.',
                                1 => 'Tässä on mallien copyright.',
                                2 => 'Tekstitiedoston tarkempi käyttöoikeuskuvaus',
                            ],
                        ],
                        'highResolution' => [],
                        'identifier' => '607644',
                        'downloadable' => true,
                        'cacheSizes' => [
                            'small' => 'large',
                            'medium' => 'large',
                        ],
                    ],
                ],
            ],
            [
                'getURLs',
                [
                    [
                        'desc' => 'AudioTesti.mp3',
                        'url' => 'https://linkkiaudioon.fi',
                        'codec' => 'mp3',
                        'type' => 'audio',
                        'embed' => 'audio',
                        'resourceName' => 'AudioTesti.mp3',
                    ],
                    [
                        'desc' => 'VideoTesti.mp4',
                        'url' => 'https://linkkivideoon.fi',
                        'embed' => 'video',
                        'format' => 'mp4',
                        'videoSources' => [
                            'src' => 'https://linkkivideoon.fi',
                            'type' => 'video/mp4',
                        ],
                        'resourceName' => 'VideoTesti.mp4',
                        'data' => [
                            'size' => [
                                'unit' => 'byte',
                                'value' => '74576596',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'getDocuments',
                [
                    0 => [
                        'description' => 'external_sketchfab.com',
                        'url' => 'https://sketchfab.com/test',
                        'format' => '',
                        'rights' => [
                            'copyright' => 'InC',
                            'description' => [
                                0 => 'Tässä on mallien copyright.',
                            ],
                        ],
                        'linkType' => 'external-link',
                        'label' => '3D',
                    ],
                    1 => [
                        'description' => 'PDFTesti.pdf',
                        'url' => 'https://linkkiPDF.fi',
                        'format' => 'pdf',
                        'rights' => [],
                        'linkType' => 'proxy-link',
                        'label' => '',
                    ],
                    2 => [
                        'description' => 'DocxTesti.docx',
                        'url' => 'https://linkkiDocx.fi',
                        'format' => 'docx',
                        'rights' => [
                            'copyright' => 'CC BY 4.0',
                            'description' => [
                                0 => 'Tekstitiedoston tarkempi käyttöoikeuskuvaus',
                            ],
                        ],
                        'linkType' => 'proxy-link',
                        'label' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * Function to get expected format classifications data
     *
     * @return array
     */
    public static function getFormatClassificationsData(): array
    {
        return [
            [
                'getFormatClassifications',
                [
                    'lido_test.xml' => [
                        'näkyy (testimittari)',
                    ],
                    'lido_test2.xml' => [
                        'uno (testimittari)',
                        'dos',
                        'one (testimittari)',
                        'two',
                    ],
                ],
            ],
        ];
    }

    /**
     * Function to get expected other classifications data
     *
     * @return array
     */
    public static function getOtherClassificationsData(): array
    {
        return [
            [
                'getOtherClassifications',
                [
                    'lido_test.xml' => [
                        'näkyy',
                    ],
                    'lido_test2.xml' => [
                        [
                            'term' => 'uno',
                            'label' => 'testimittari',
                        ],
                        [
                            'term' => 'one',
                            'label' => 'testimittari',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test representations
     *
     * @param string $function Function of the driver to test
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRepresentationsData')]
    public function testRepresentations(
        string $function,
        array $expected
    ): void {
        $driver = $this->getDriver('lido_test.xml');
        $this->assertTrue(is_callable([$driver, $function], true));
        $this->assertEquals(
            $expected,
            $driver->$function()
        );
    }

    /**
     * Test getFormatClassifications
     *
     * @param string $function Function of the driver to test
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getFormatClassificationsData')]
    public function testGetFormatClassifications(
        string $function,
        array $expected
    ): void {
        foreach ($expected as $file => $result) {
            $driver = $this->getDriver($file);
            $this->assertTrue(is_callable([$driver, $function], true));
            $this->assertEquals(
                $result,
                $driver->$function()
            );
        }
    }

    /**
     * Test getOtherClassifications
     *
     * @param string $function Function of the driver to test
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getOtherClassificationsData')]
    public function testGetOtherClassifications(
        string $function,
        array $expected
    ): void {
        foreach ($expected as $file => $result) {
            $driver = $this->getDriver($file);
            $this->assertTrue(is_callable([$driver, $function], true));
            $this->assertEquals(
                $result,
                $driver->$function()
            );
        }
    }

    /**
     * Function to get expected measurements data
     *
     * @return array
     */
    public static function getMeasurementsByTypeData(): array
    {
        return [
            [
                'getMeasurements',
                'fi',
                [
                    'lido_test.xml' => [
                        'pituus 73.0 cm, leveys 14 cm (kohde 2, kohde 3)',
                    ],
                    'lido_test2.xml' => [
                        'syvyys 50 cm (kohde 1)',
                        'pituus 0.73 m',
                    ],
                ],
            ],
            [
                'getMeasurements',
                'sv',
                [
                    'lido_test.xml' => [
                        'pituus 73.0 cm, leveys 14 cm (kohde 2, kohde 3)',
                    ],
                    'lido_test2.xml' => [
                        'syvyys 50 cm (kohde 1)',
                        'pituus 0.73 m',
                    ],
                ],
            ],
            [
                'getMeasurements',
                'en',
                [
                    'lido_test.xml' => [
                        'height 73.0 cm, width 14 cm (subjects 2 and 3)',
                    ],
                    'lido_test2.xml' => [
                        'depth 50 cm (subject 1)',
                        'pituus 0.73 m',
                    ],
                ],
            ],
            [
                'getPhysicalDescriptions',
                'fi',
                [
                    'lido_test.xml' => [
                        '1001 neliömetriä',
                    ],
                    'lido_test2.xml' => [
                        '1200 kpl (kohde 1)',
                        '12 yksikköä (kohde 1)',
                        '100 hyllymetriä',
                    ],
                ],
            ],
            [
                'getPhysicalDescriptions',
                'sv',
                [
                    'lido_test.xml' => [
                        '1001 neliömetriä',
                    ],
                    'lido_test2.xml' => [
                        '1200 kpl (kohde 1)',
                        '12 yksikköä (kohde 1)',
                        '100 hyllymetriä',
                    ],
                ],
            ],
            [
                'getPhysicalDescriptions',
                'en',
                [
                    'lido_test.xml' => [
                        '1001 square meters',
                    ],
                    'lido_test2.xml' => [
                        '1200 pcs (subject 1)',
                        '12 yksikköä (subject 1)',
                        '100 hyllymetriä',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test getMeasurementsByType
     *
     * @param string $function Function of the driver to test
     * @param string $language Language
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getMeasurementsByTypeData')]
    public function testGetMeasurementsByType(
        string $function,
        string $language,
        array $expected
    ): void {
        foreach ($expected as $file => $result) {
            $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
            $translator->setLocale($language);
            $driver = $this->getDriver($file);
            $driver->setTranslator($translator);
            $this->assertTrue(is_callable([$driver, $function], true));
            $this->assertEquals(
                $result,
                $driver->$function()
            );
        }
    }

    /**
     * Function to get data for subject field
     *
     * @return array
     */
    public static function getAllSubjectHeadingsWithoutPlacesExtendedData(): array
    {
        return [
                    [
                        'fi',
                        'lido_test.xml',
                        [
                            [
                                'heading' => ['sohvat'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                            [
                                'heading' => ['maalaukset'],
                                'type' => 'topic',
                                'source' => '',
                                'id' => 'http://www.yso.fi/onto/koko/p31096',
                                'authType' => null,
                            ],
                            [
                                'heading' => ['maalaukset, ei pilkottu'],
                                'type' => 'topic',
                                'source' => '',
                                'id' => 'http://www.yso.fi/onto/koko/p31096',
                                'authType' => null,
                            ],
                            [
                                'heading' => ['maalaukset'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                            [
                                'heading' => ['pilkottuna'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                        ],
                    ],
                    [
                        'sv',
                        'lido_test2.xml',
                        [
                            [
                                'heading' => ['morot'],
                                'type' => 'topic',
                                'source' => 'yso',
                                'id' => 'http://www.yso.fi/onto/yso/p5066',
                                'authType' => null,
                            ],
                            [
                                'heading' => ['Jussi, Jänö'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                        ],
                    ],
                    [
                        'xy',
                        'lido_test2.xml',
                        [
                            [
                                'heading' => ['porkkana'],
                                'type' => 'topic',
                                'source' => 'yso',
                                'id' => 'http://www.yso.fi/onto/yso/p5066',
                                'authType' => null,
                            ],
                            [
                                'heading' => ['morot'],
                                'type' => 'topic',
                                'source' => 'yso',
                                'id' => 'http://www.yso.fi/onto/yso/p5066',
                                'authType' => null,
                            ],
                            [
                                'heading' => ['juures'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                            [
                                'heading' => ['Jussi, Jänö'],
                                'type' => 'topic',
                                'source' => '',
                            ],
                        ],
                    ],
        ];
    }

    /**
     * Test getAllSubjectHeadingsWithoutPlacesExtended
     *
     * @param string $language Language
     * @param string $xmlFile  Xml record to use for the test
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getAllSubjectHeadingsWithoutPlacesExtendedData')]
    public function testGetAllSubjectHeadingsWithoutPlacesExtended(
        string $language,
        string $xmlFile,
        array $expected
    ): void {
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $translator->setLocale($language);
        $driver = $this->getDriver($xmlFile);
        $driver->setTranslator($translator);
        $this->assertEquals(
            $expected,
            $driver->getAllSubjectHeadingsWithoutPlacesExtended()
        );
    }

    /**
     * Test getAllSubjectHeadings function
     *
     * @return void
     */
    public function testGetAllSubjectHeadings(): void
    {
        $driver = $this->getDriver('lido_test2.xml');
        $expected = [
            [
                'heading' => ['porkkana'],
                'type' => 'topic',
                'source' => 'yso',
                'id' => 'http://www.yso.fi/onto/yso/p5066',
                'authType' => null,
            ],
            [
                'heading' => ['morot'],
                'type' => 'topic',
                'source' => 'yso',
                'id' => 'http://www.yso.fi/onto/yso/p5066',
                'authType' => null,
            ],
            [
                'heading' => ['juures'],
                'type' => 'topic',
                'source' => '',
            ],
            [
                'heading' => ['Jussi, Jänö'],
                'type' => 'topic',
                'source' => '',
            ],
            [
                'heading' => ['Etelä-Suomi'],
                'type' => 'URI',
                'id' => 'http://www.yso.fi/onto/yso/p105917',
                'ids' => [
                    'http://www.yso.fi/onto/yso/p105917',
                ],
            ],
            [
                'heading' => ['Lohja'],
                'type' => 'mjr',
                'id' => '123456',
                'ids' => [
                    '123456',
                    'extraid',
                ],
            ],
            [
                'heading' => ['Kauppakatu 5, Lohja, Uusimaa, Suomi'],
            ],
        ];
        $this->assertEquals($expected, $driver->getAllSubjectHeadings(true));

        $expected = [
            ['porkkana'],
            ['morot'],
            ['juures'],
            ['Jussi, Jänö'],
            ['Etelä-Suomi'],
            ['Lohja'],
            ['Kauppakatu 5, Lohja, Uusimaa, Suomi'],
        ];
        $this->assertEquals($expected, $driver->getAllSubjectHeadings());
    }

    /**
     * Function to get expected physical locations data
     *
     * @return array
     */
    public static function getPhysicalLocationsData(): array
    {
        return [
            [
                'fi',
                [
                    'lido_test.xml' => [
                        'Kansalliskirjaston kupolisali, Unioninkatu 36, Helsinki',
                        'Teos on nähtävissä kirjaston aukioloaikoina.',
                    ],
                    'lido_test2.xml' => [
                        'Huonenumero 123, Auditorio, Mannerheimintie 999, Helsinki',
                    ],
                ],
            ],
            [
                'en-gb',
                [
                    'lido_test.xml' => [
                        'Kansalliskirjaston kupolisali, Unioninkatu 36, Helsinki',
                        'The object can be accessed when the library is open.',
                    ],
                    'lido_test2.xml' => [
                        'Huonenumero 123, Auditorio, Mannerheimintie 999, Helsinki',
                    ],
                ],
            ],
            [
                'xy',
                [
                    'lido_test.xml' => [
                        'Kansalliskirjaston kupolisali, Unioninkatu 36, Helsinki',
                        'Teos on nähtävissä kirjaston aukioloaikoina.',
                    ],
                    'lido_test2.xml' => [
                        'Huonenumero 123, Auditorio, Mannerheimintie 999, Helsinki',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test getPhysicalLocations
     *
     * @param string $language Language
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getPhysicalLocationsData')]
    public function testGetPhysicalLocations(
        string $language,
        array $expected
    ): void {
        foreach ($expected as $file => $result) {
            $translator = $this
                ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock();
            $translator->setLocale($language);
            $driver = $this->getDriver($file);
            $driver->setTranslator($translator);
            $this->assertEquals(
                $result,
                $driver->getPhysicalLocations()
            );
        }
    }

    /**
     * Test getNonPresenterAuthors.
     * Design event actors should always be before Production event actors.
     *
     * @return void
     */
    public function testGetNonPresenterAuthors(): void
    {
        $driver = $this->getDriver('lido_test.xml');
        $this->assertEquals(
            [
                [
                    'name' => 'Puu, Teisto',
                    'role' => 'suunnittelija',
                ],
                [
                    'name' => 'Mattilainen, Meikä',
                    'role' => 'haaveilija',
                ],
                [
                    'name' => 'Tiistai, Nietos',
                    'role' => 'Työntekijä',
                ],
            ],
            $driver->getNonPresenterAuthors()
        );
    }

    /**
     * Function to get expected date range data
     *
     * @return array
     */
    public static function getDateRangeData(): array
    {
        return [
            [
                '[2009-01-01 TO 2009-12-31]',
                ['2009'],
            ],
            [
                '[-2000-01-01 TO 0900-12-31]',
                ['-2000', '900'],
            ],
            [
                '1937-12-08',
                ['1937'],
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
                '[-0055-10-31 TO -0002-02-15]',
                ['-55', '-2'],
            ],
            [
                '',
                null,
            ],
        ];
    }

    /**
     * Test getDateRange
     *
     * @param string $indexValue Index value to test
     * @param ?array $expected   Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDateRangeData')]
    public function testGetDateRange(
        string $indexValue,
        ?array $expected
    ): void {
        $record = new SolrLido(
            [],
            [],
            new \VuFind\Config\Config([])
        );
        $record->setRawData(
            [
                'id' => 'knp-247394',
                'creation_daterange' => $indexValue,
            ]
        );
        $this->assertEquals(
            $expected,
            $record->getResultDateRange()
        );
    }

    /**
     * Function to get expected summary data
     *
     * @return array
     */
    public static function getSummaryData(): array
    {
        return [
            [
                'lido_test.xml',
                [
                    'Visible description.',
                    'Visible subject labeled.',
                ],
                [],
                'en-gb',

            ],
            [
                'lido_test2.xml',
                [
                    'näkyy partial.',
                    'Näkyy kokonaan.',
                    'Näkyy description untyped.',
                    'Näkyy subject unlabeled.',
                ],
                ['title' => 'Otsikko'],
                'fi',
            ],
            [
                'lido_test.xml',
                [
                    'Näkyy description typed.',
                    'Visible description.',
                    'Visible subject labeled.',
                    'Näkyy subject labeled.',
                    'Synas subject labeled.',
                ],
                [],
                'xy',
            ],
        ];
    }

    /**
     * Test getSummary()
     *
     * @param string $xmlFile  Xml record to use for the test
     * @param array  $expected Expected results from function
     * @param array  $rawData  The additional tested data
     * @param string $language Language
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSummaryData')]
    public function testGetSummary(
        $xmlFile,
        $expected,
        $rawData,
        $language
    ): void {
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $translator->setLocale($language);
        $driver = $this->getDriver($xmlFile, [], [], $rawData);
        $driver->setTranslator($translator);
        $this->assertEquals(
            $expected,
            $driver->getSummary()
        );
    }

    /**
     * Function to get expected events data
     *
     * @return array
     */
    public static function getEventsData(): array
    {
        return [
            [
                'fi',
                [
                    'valmistus' => [
                        0 => [
                            'type' => 'valmistus',
                            'name' => '',
                            'date' => 'valmistusaika noin 1910–1920',
                            'methods' => ['tekniikka: ompelu', 'technique: sewing'],
                            'methodsExtended' => [
                                [
                                    'data' => 'tekniikka: ompelu',
                                    'id' => 'http://www.yso.fi/onto/koko/p72845',
                                    'source' => 'koko',
                                ],
                            ],
                            'materials' => ['materiaali: villa', 'material: wool', 'vuori: sarka'],
                            'materialsExtended' => [
                                [
                                    'data' => 'materiaali: villa',
                                    'id' => 'http://www.yso.fi/onto/koko/p33150',
                                    'source' => '',
                                ],
                                [
                                    'data' => 'vuori: sarka',
                                    'id' => '',
                                    'source' => '',
                                ],
                            ],
                            'places' => [
                                [
                                    'placeName' => 'Bulevardi, Helsinki',
                                    'type' => 'URI',
                                    'id' => 'http://www.yso.fi/onto/yso/p202484',
                                    'ids' => [
                                        'http://www.yso.fi/onto/yso/p202484',
                                    ],
                                    'details' => ['place_id_type_URI'],
                                ],
                            ],
                            'actors' => [
                                [
                                    'name' => 'Mattilainen, Meikä',
                                    'role' => 'haaveilija',
                                    'birth' => '',
                                    'death' => '',
                                ],
                                [
                                    'name' => 'Tiistai, Nietos',
                                    'role' => 'Työntekijä',
                                    'birth' => '',
                                    'death' => '',
                                ],
                            ],
                            'culture' => 'kulttuuri',
                            'descriptions' => ['valmistusprosessin kuvaus'],
                            'description' => 'valmistusprosessin kuvaus',
                        ],
                    ],
                    'suunnittelu' => [
                        0 => [
                            'type' => 'suunnittelu',
                            'name' => '',
                            'date' => '',
                            'methods' => [],
                            'methodsExtended' => [],
                            'materials' => [],
                            'materialsExtended' => [],
                            'places' => [],
                            'actors' => [
                                [
                                    'name' => 'Puu, Teisto',
                                    'role' => 'suunnittelija',
                                    'birth' => '',
                                    'death' => '',
                                ],
                            ],
                            'culture' => '',
                            'descriptions' => [],
                            'description' => '',
                        ],
                    ],
                ],
            ],
            [
                'en',
                [
                    'valmistus' => [
                        0 => [
                            'type' => 'valmistus',
                            'name' => '',
                            'date' => 'created ca 1910–1920',
                            'methods' => ['tekniikka: ompelu', 'technique: sewing'],
                            'methodsExtended' => [
                                [
                                    'data' => 'technique: sewing',
                                    'id' => 'http://www.yso.fi/onto/koko/p72845',
                                    'source' => 'koko',
                                ],
                            ],
                            'materials' => ['materiaali: villa', 'material: wool', 'lining: frieze'],
                            'materialsExtended' => [
                                [
                                    'data' => 'material: wool',
                                    'id' => 'http://www.yso.fi/onto/koko/p33150',
                                    'source' => '',
                                ],
                                [
                                    'data' => 'lining: frieze',
                                    'id' => '',
                                    'source' => '',
                                ],
                            ],
                            'places' => [
                                [
                                    'placeName' => 'Bulevardi, Helsinki',
                                    'type' => 'URI',
                                    'id' => 'http://www.yso.fi/onto/yso/p202484',
                                    'ids' => [
                                        'http://www.yso.fi/onto/yso/p202484',
                                    ],
                                    'details' => ['place_id_type_URI'],
                                ],
                            ],
                            'actors' => [
                                [
                                    'name' => 'Mattilainen, Meikä',
                                    'role' => 'haaveilija',
                                    'birth' => '',
                                    'death' => '',
                                ],
                                [
                                    'name' => 'Tiistai, Nietos',
                                    'role' => 'Työntekijä',
                                    'birth' => '',
                                    'death' => '',
                                ],
                            ],
                            'culture' => 'kulttuuri',
                            'descriptions' => ['description of the production process'],
                            'description' => 'description of the production process',
                        ],
                    ],
                    'suunnittelu' => [
                        0 => [
                            'type' => 'suunnittelu',
                            'name' => '',
                            'date' => '',
                            'methods' => [],
                            'methodsExtended' => [],
                            'materials' => [],
                            'materialsExtended' => [],
                            'places' => [],
                            'actors' => [
                                [
                                    'name' => 'Puu, Teisto',
                                    'role' => 'suunnittelija',
                                    'birth' => '',
                                    'death' => '',
                                ],
                            ],
                            'culture' => '',
                            'descriptions' => [],
                            'description' => '',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test getEvents
     *
     * @param string $language Language
     * @param array  $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getEventsData')]
    public function testGetEvents(
        string $language,
        array $expected
    ): void {
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $translator->setLocale($language);
        $driver = $this->getDriver('lido_test.xml');
        $driver->setTranslator($translator);
        $this->assertEquals(
            $expected,
            $driver->getEvents()
        );
    }

    /**
     * Function to get expected related publications data
     *
     * @return array
     */
    public static function getRelatedPublicationsData(): array
    {
        return [
            [
                [
                    0 => [
                        'title' => 'Helsinki = Empirekaupungin synty 1550-1850, Helsinki, s. 89',
                        'searchTitle' => 'Helsinki = Empirekaupungin synty 1550-1850, Helsinki',
                        'label' => 'Julkaistu teoksessa',
                        'url' => '',
                        'isbn' => '951-746-543-2',
                    ],
                    1 => [
                        'title' => 'Multiple titles in one field; Should be discarded from search',
                        'searchTitle' => '',
                        'label' => '',
                        'url' => '',
                        'isbn' => '',
                    ],
                    2 => [
                        'title' => 'Online publication, discarded from search',
                        'searchTitle' => '',
                        'label' => 'Verkkojulkaisu',
                        'url' => '',
                        'isbn' => '',
                    ],
                    3 => [
                        'title' => 'This is a very long title and for better result, only the first 30 words'
                            . ' should be included in search title, which means that its last word should be this.'
                            . ' The rest of the title should be included only in display title.',
                        'searchTitle' => 'This is a very long title and for better result, only the first 30 words'
                            . ' should be included in search title, which means that its last word should be this.',
                        'label' => '',
                        'url' => '',
                        'isbn' => '951-772-866-2',
                    ],
                    4 => [
                        'title' => 'A publication with no valid ISBN',
                        'searchTitle' => 'A publication with no valid ISBN',
                        'label' => 'kirjallisuus',
                        'url' => '',
                        'isbn' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test getRelatedPublications
     *
     * @param array $expected Result to be expected
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRelatedPublicationsData')]
    public function testGetRelatedPublications(
        array $expected
    ): void {
        $translator = $this
            ->getMockBuilder(\Laminas\I18n\Translator\Translator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $driver = $this->getDriver('lido_test.xml');
        $this->assertEquals(
            $expected,
            $driver->getRelatedPublications()
        );
    }

    /**
     * Get a record driver with fake data
     *
     * @param string $recordXml    Xml record to use for the test
     * @param array  $overrides    Fixture fields to override
     * @param array  $searchConfig Search configuration
     * @param array  $rawData      Raw data for the record
     *
     * @return SolrLido
     */
    protected function getDriver(
        string $recordXml,
        $overrides = [],
        $searchConfig = [],
        $rawData = []
    ): SolrLido {
        $fixture = $this->getFixture("lido/$recordXml", 'Finna');
        $config = [
            'Record' => [
                'allowed_external_hosts_mode' => 'disable',
            ],
            'FileDownload' => [
                'excludeRights' => [
                    'INC',
                ],
            ],
        ];
        $config = new \VuFind\Config\Config($config);
        $record = new SolrLido(
            $config,
            $config,
            new \VuFind\Config\Config($searchConfig)
        );
        $defaultData = [
            'id' => 'knp-247394',
            'fullrecord' => $fixture,
            'usage_rights_str_mv' => [
                'usage_A',
            ],
        ];
        $record->setRawData(
            array_merge($defaultData, $rawData)
        );
        return $record;
    }
}
