<?php

/**
 * EDS Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\EDS;

/**
 * EDS Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EDSTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'General' => [
            'default_sort' => 'relevance',
        ],
        'ItemGlobalOrder' => [],
    ];

    /**
     * Test getUniqueID for a record.
     *
     * @return void
     */
    public function testGetUniqueID(): void
    {
        $overrides = [
            'Header' => ['DbId' => 'TDB123', 'An' => 'TAn456'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals('TDB123,TAn456', $driver->getUniqueID());
    }

    /**
     * Test getShortTitle for a record.
     *
     * @return void
     */
    public function testGetShortTitle(): void
    {
        $this->assertEquals('', $this->getDriver()->getShortTitle());
    }

    /**
     * Test getItemsAbstract for a record.
     *
     * @return void
     */
    public function testGetItemsAbstract(): void
    {
        $this->assertEquals('', $this->getDriver()->getItemsAbstract());
    }

    /**
     * Test getAccessLevel for a record.
     *
     * @return void
     */
    public function testGetAccessLevel(): void
    {
        $this->assertEquals('', $this->getDriver()->getAccessLevel());
    }

    /**
     * Test getItemsAuthors for a record.
     *
     * @return void
     */
    public function testGetItemsAuthors(): void
    {
        $this->assertEquals('', $this->getDriver()->getItemsAuthors());
    }

    /**
     * Test getCustomLinks for a record.
     *
     * @return void
     */
    public function testGetCustomLinks(): void
    {
        $this->assertEquals([], $this->getDriver()->getCustomLinks());
    }

    /**
     * Test getFTCustomLinks for a record.
     *
     * @return void
     */
    public function testGetFTCustomLinks(): void
    {
        $this->assertEquals([], $this->getDriver()->getFTCustomLinks());
    }

    /**
     * Test getDbLabel for a record.
     *
     * @return void
     */
    public function testGetDbLabel(): void
    {
        $this->assertEquals('', $this->getDriver()->getDbLabel());
    }

    /**
     * Test getHTMLFullText for a record.
     *
     * @return void
     */
    public function testGetHTMLFullText(): void
    {
        $this->assertEquals('', $this->getDriver()->getHTMLFullText());
    }

    /**
     * Test hasHTMLFullTextAvailable for a record.
     *
     * @return void
     */
    public function testHasHTMLFullTextAvailable(): void
    {
        $this->assertEquals(false, $this->getDriver()->hasHTMLFullTextAvailable());
    }

    /**
     * Test getItems for a record.
     *
     * @return void
     */
    public function testGetItems(): void
    {
        $this->assertEquals([], $this->getDriver()->getItems());
    }

    /**
     * Test getItems for a record.
     *
     * @return void
     */
    public function testGetItemsWithData(): void
    {
        $driver = $this->getDriverWithItemData();
        $items = [
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'MEOW! Welcome to CAT ISLAND.',
            ],
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => 'Lusted, Marcia Amidon (AUTHOR)',
            ],
            [
                'Name' => 'TitleSource',
                'Label' => 'Source',
                'Group' => 'Src',
                'Data' => 'Feb2022, Vol. 38 Issue 5, p32-33. 2p. 4 Color Photographs.',
            ],
        ];
        $this->assertEquals($items, $driver->getItems());
    }

    /**
     * Test getItems sorting the data for a record.
     *
     * @return void
     */
    public function testGetItemsWithDataSorted(): void
    {
        // Change the default order the array data is in and exclude one of the items
        // to ensure it appears at the end
        $driverConfig = [
            'ItemGlobalOrder' => [
                '1' => 'Authors', // note that we used the 'Label' and not the 'Name'
                '2' => 'Title',
            ],
        ];

        $driver = $this->getDriverWithItemData($driverConfig);
        $items = [
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => 'Lusted, Marcia Amidon (AUTHOR)',
            ],
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'MEOW! Welcome to CAT ISLAND.',
            ],
            [
                'Name' => 'TitleSource',
                'Label' => 'Source',
                'Group' => 'Src',
                'Data' => 'Feb2022, Vol. 38 Issue 5, p32-33. 2p. 4 Color Photographs.',
            ],
        ];
        $this->assertEquals($items, $driver->getItems());
    }

    /**
     * Test getItems when invalid data is returned from EDS (i.e. not in the structure
     * VuFind expected)
     *
     * @return void
     */
    public function testGetItemsWithInvalidConfig(): void
    {
        $driverConfig = [
            'ItemGlobalOrder' => 'invalid',
        ];

        $driver = $this->getDriverWithItemData($driverConfig);
        // items in original order are returned when the config can't be parsed
        $items = [
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'MEOW! Welcome to CAT ISLAND.',
            ],
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => 'Lusted, Marcia Amidon (AUTHOR)',
            ],
            [
                'Name' => 'TitleSource',
                'Label' => 'Source',
                'Group' => 'Src',
                'Data' => 'Feb2022, Vol. 38 Issue 5, p32-33. 2p. 4 Color Photographs.',
            ],
        ];
        $this->assertEquals($items, $driver->getItems());
    }

    /**
     * Test getPLink for a record.
     *
     * @return void
     */
    public function testGetPLink(): void
    {
        $this->assertEquals('', $this->getDriver()->getPLink());
    }

    /**
     * Test getPubType for a record.
     *
     * @return void
     */
    public function testGetPubType(): void
    {
        $this->assertEquals('', $this->getDriver()->getPubType());
    }

    /**
     * Test getPubTypeId for a record.
     *
     * @return void
     */
    public function testGetPubTypeId(): void
    {
        $this->assertEquals('', $this->getDriver()->getPubTypeId());
    }

    /**
     * Test hasPdfAvailable for a record.
     *
     * @return void
     */
    public function testHasPdfAvailable(): void
    {
        $this->assertEquals(false, $this->getDriver()->hasPdfAvailable());
    }

    /**
     * Test getPdfLink for a record.
     *
     * @return void
     */
    public function testGetPdfLink(): void
    {
        $this->assertEquals(false, $this->getDriver()->getPdfLink());
    }

    /**
     * Test getItemsSubjects for a record.
     *
     * @return void
     */
    public function testGetItemsSubjects(): void
    {
        $this->assertEquals('', $this->getDriver()->getItemsSubjects());
    }

    /**
     * Test getThumbnail for a record.
     *
     * @return void
     */
    public function testGetThumbnail(): void
    {
        $this->assertEquals(false, $this->getDriver()->getThumbnail());
    }

    /**
     * Test getItemsTitle for a record.
     *
     * @return void
     */
    public function testGetItemsTitle(): void
    {
        $this->assertEquals('', $this->getDriver()->getItemsTitle());
    }

    /**
     * Test getTitle for a record.
     *
     * @return void
     */
    public function testGetTitle(): void
    {
        $this->assertEquals('', $this->getDriver()->getTitle());
    }

    /**
     * Test getPrimaryAuthors for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthors(): void
    {
        $this->assertEquals([], $this->getDriver()->getPrimaryAuthors());
    }

    /**
     * Test getItemsTitleSource for a record.
     *
     * @return void
     */
    public function testGetItemsTitleSource(): void
    {
        $this->assertEquals('', $this->getDriver()->getItemsTitleSource());
    }

    /**
     * Data provider for testLinkUrls
     *
     * @return array
     */
    public function getLinkUrlsProvider(): array
    {
        return [
            [
                'http://localhost/sample1',
                '<a href=\'http://localhost/sample1\'>http://localhost/sample1</a>',
            ],
            [
                '<link linkTarget="URL" linkTerm="https://localhost/sample"'
                    . ' linkWindow="_blank">https://localhost/sample</link>',
                '<a href=\'https://localhost/sample\'>https://localhost/sample</a>',
            ],
        ];
    }

    /**
     * Test linkUrls for a record.
     *
     * @param string $url      Input URL
     * @param string $expected Expected value
     *
     * @dataProvider getLinkUrlsProvider
     *
     * @return void
     */
    public function testLinkUrls(string $url, string $expected): void
    {
        $this->assertEquals($expected, $this->getDriver()->linkUrls($url));
    }

    /**
     * Test getISSNs.
     *
     * @return void
     */
    public function testGetISSNs(): void
    {
        $driver = $this->getDriverWithIdentifierData();
        $this->assertEquals(
            ['1234-5678', '5678-1234'],
            $driver->getISSNs()
        );
    }

    /**
     * Test getISBNs.
     *
     * @return void
     */
    public function testGetISBNs(): void
    {
        $driver = $this->getDriverWithIdentifierData();
        $this->assertEquals(
            ['0123456789X', 'fakeisbnxxx'],
            $driver->getISBNs()
        );
    }

    /**
     * Get a record driver with fake item data.
     *
     * @param array $config overrides the EDS config for testing
     *
     * @return EDS
     */
    protected function getDriverWithItemData(array $config = null): EDS
    {
        return $this->getDriver(
            [
                'Items' => [
                    [
                        'Name' => 'Title',
                        'Label' => 'Title',
                        'Group' => 'Ti',
                        'Data' => 'MEOW! Welcome to CAT ISLAND.',
                    ],
                    [
                        'Name' => 'Author',
                        'Label' => 'Authors',
                        'Group' => 'Au',
                        'Data' => 'Lusted, Marcia Amidon (AUTHOR)',
                    ],
                    [
                        'Name' => 'TitleSource',
                        'Label' => 'Source',
                        'Group' => 'Src',
                        'Data' => 'Feb2022, Vol. 38 Issue 5, p32-33. 2p. 4 Color Photographs.',
                    ],
                ],
            ],
            $config
        );
    }

    /**
     * Get a record driver with fake identifier data.
     *
     * @param array $config overrides the EDS config for testing
     *
     * @return EDS
     */
    protected function getDriverWithIdentifierData(array $config = null): EDS
    {
        return $this->getDriver(
            [
                'RecordInfo' => [
                    'BibRecord' => [
                        'BibRelationships' => [
                            'IsPartOfRelationships' => [
                                [
                                    'BibEntity' => [
                                        'Identifiers' => [
                                            [
                                                'Type' => 'issn-electronic',
                                                'Value' => '1234-5678',
                                            ],
                                            [
                                                'Type' => 'issn-print',
                                                'Value' => '5678-1234',
                                            ],
                                            [
                                                'Type' => 'isbn-electronic',
                                                'Value' => '0123456789X',
                                            ],
                                            [
                                                'Type' => 'isbn-print',
                                                'Value' => 'fakeisbnxxx',
                                            ],
                                            [
                                                'Type' => 'meaningless-noise',
                                                'Value' => 'should never be seen',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $config
        );
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Raw data for testing
     * @param array $config    overrides the EDS config for testing
     *
     * @return EDS
     */
    protected function getDriver($overrides = [], array $config = null): EDS
    {
        $record = new EDS();
        $record->setRawData($overrides);

        // Set the private recordConfig property
        // TODO -- is there a better way to set this config?
        $reflection = new \ReflectionProperty($record::class, 'recordConfig');
        $reflection->setAccessible(true);
        $reflection->setValue($record, (object)($config ?? $this->defaultDriverConfig));

        return $record;
    }
}
