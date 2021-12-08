<?php
/**
 * EDS Record Driver Test Class
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EDSTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getUniqueID for a record.
     *
     * @return void
     */
    public function testGetUniqueID()
    {
        $overrides = [
            'Header' => ['DbId' => 'TDB123', 'An' => 'TAn456']
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals('TDB123,TAn456', $driver->getUniqueID());
    }

    /**
     * Test getShortTitle for a record.
     *
     * @return void
     */
    public function testGetShortTitle()
    {
        $this->assertEquals('', $this->getDriver()->getShortTitle());
    }

    /**
     * Test getItemsAbstract for a record.
     *
     * @return void
     */
    public function testGetItemsAbstract()
    {
        $this->assertEquals('', $this->getDriver()->getItemsAbstract());
    }

    /**
     * Test getAccessLevel for a record.
     *
     * @return void
     */
    public function testGetAccessLevel()
    {
        $this->assertEquals('', $this->getDriver()->getAccessLevel());
    }

    /**
     * Test getItemsAuthors for a record.
     *
     * @return void
     */
    public function testGetItemsAuthors()
    {
        $this->assertEquals('', $this->getDriver()->getItemsAuthors());
    }

    /**
     * Test getCustomLinks for a record.
     *
     * @return void
     */
    public function testGetCustomLinks()
    {
        $this->assertEquals([], $this->getDriver()->getCustomLinks());
    }

    /**
     * Test getFTCustomLinks for a record.
     *
     * @return void
     */
    public function testGetFTCustomLinks()
    {
        $this->assertEquals([], $this->getDriver()->getFTCustomLinks());
    }

    /**
     * Test getDbLabel for a record.
     *
     * @return void
     */
    public function testGetDbLabel()
    {
        $this->assertEquals('', $this->getDriver()->getDbLabel());
    }

    /**
     * Test getHTMLFullText for a record.
     *
     * @return void
     */
    public function testGetHTMLFullText()
    {
        $this->assertEquals('', $this->getDriver()->getHTMLFullText());
    }

    /**
     * Test hasHTMLFullTextAvailable for a record.
     *
     * @return void
     */
    public function testHasHTMLFullTextAvailable()
    {
        $this->assertEquals(false, $this->getDriver()->hasHTMLFullTextAvailable());
    }

    /**
     * Test getItems for a record.
     *
     * @return void
     */
    public function testGetItems()
    {
        $this->assertEquals([], $this->getDriver()->getItems());
    }

    /**
     * Test getPLink for a record.
     *
     * @return void
     */
    public function testGetPLink()
    {
        $this->assertEquals('', $this->getDriver()->getPLink());
    }

    /**
     * Test getPubType for a record.
     *
     * @return void
     */
    public function testGetPubType()
    {
        $this->assertEquals('', $this->getDriver()->getPubType());
    }

    /**
     * Test getPubTypeId for a record.
     *
     * @return void
     */
    public function testGetPubTypeId()
    {
        $this->assertEquals('', $this->getDriver()->getPubTypeId());
    }

    /**
     * Test hasPdfAvailable for a record.
     *
     * @return void
     */
    public function testHasPdfAvailable()
    {
        $this->assertEquals(false, $this->getDriver()->hasPdfAvailable());
    }

    /**
     * Test getPdfLink for a record.
     *
     * @return void
     */
    public function testGetPdfLink()
    {
        $this->assertEquals(false, $this->getDriver()->getPdfLink());
    }

    /**
     * Test getItemsSubjects for a record.
     *
     * @return void
     */
    public function testGetItemsSubjects()
    {
        $this->assertEquals('', $this->getDriver()->getItemsSubjects());
    }

    /**
     * Test getThumbnail for a record.
     *
     * @return void
     */
    public function testGetThumbnail()
    {
        $this->assertEquals(false, $this->getDriver()->getThumbnail());
    }

    /**
     * Test getItemsTitle for a record.
     *
     * @return void
     */
    public function testGetItemsTitle()
    {
        $this->assertEquals('', $this->getDriver()->getItemsTitle());
    }

    /**
     * Test getTitle for a record.
     *
     * @return void
     */
    public function testGetTitle()
    {
        $this->assertEquals('', $this->getDriver()->getTitle());
    }

    /**
     * Test getPrimaryAuthors for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthors()
    {
        $this->assertEquals([], $this->getDriver()->getPrimaryAuthors());
    }

    /**
     * Test getItemsTitleSource for a record.
     *
     * @return void
     */
    public function testGetItemsTitleSource()
    {
        $this->assertEquals('', $this->getDriver()->getItemsTitleSource());
    }

    /**
     * Test linkUrls for a record.
     *
     * @return void
     */
    public function testLinkUrls()
    {
        $str = "http://fictional.com/sample/url";
        $this->assertEquals("<a href='" . $str . "'>" . $str . "</a>", $this->getDriver()->linkUrls($str));
    }

    /**
     * Test getISSNs.
     *
     * @return void
     */
    public function testGetISSNs()
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
    public function testGetISBNs()
    {
        $driver = $this->getDriverWithIdentifierData();
        $this->assertEquals(
            ['0123456789X', 'fakeisbnxxx'],
            $driver->getISBNs()
        );
    }

    /**
     * Get a record driver with fake identifier data.
     *
     * @return EDS
     */
    protected function getDriverWithIdentifierData()
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
                                                'Value' => '1234-5678'
                                            ],
                                            [
                                                'Type' => 'issn-print',
                                                'Value' => '5678-1234'
                                            ],
                                            [
                                                'Type' => 'isbn-electronic',
                                                'Value' => '0123456789X'
                                            ],
                                            [
                                                'Type' => 'isbn-print',
                                                'Value' => 'fakeisbnxxx'
                                            ],
                                            [
                                                'Type' => 'meaningless-noise',
                                                'Value' => 'should never be seen'
                                            ],
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Raw data for testing
     *
     * @return EDS
     */
    protected function getDriver($overrides = [])
    {
        $record = new EDS();
        $record->setRawData($overrides);
        return $record;
    }
}
