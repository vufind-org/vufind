<?php

/**
 * EDS Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\EPF;

/**
 * EPF Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EPFTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getThumbnail for a record.
     *
     * @return void
     */
    public function testGetThumbnail(): void
    {
        $thumbnail = $this->getDriverWithIdentifierData()->getThumbnail();
        $this->assertEquals('1234-5678', $thumbnail['recordid']);
    }

    /**
     * Test getFullTextHoldings for a record.
     *
     * @return void
     */
    public function testGetFullTextHoldings(): void
    {
        $holdings = $this->getDriverWithIdentifierData()->getFullTextHoldings();
        $this->assertEquals(
            'https://foo.bar',
            $holdings[0]['FullTextHolding']['URL']
        );
    }

    /**
     * Test getIssns for a record.
     *
     * @return void
     */
    public function testGetIssns(): void
    {
        $issns = $this->getDriverWithIdentifierData()->getISSNs();
        $this->assertEquals(
            ['19494998', '19495005'],
            $issns
        );
    }

    /**
     * Get a record driver with fake identifier data.
     *
     * @return EPF
     */
    protected function getDriverWithIdentifierData(): EPF
    {
        return $this->getDriver(
            [
                'Header' => [
                    'PublicationId' => '1234-5678',
                ],
                'RecordInfo' => [
                    'BibRecord' => [
                        'BibEntity' => [
                            'Identifiers' => [
                                [
                                    'Type' => 'issn-print',
                                    'Value' => '19494998',
                                ],
                                [
                                    'Type' => 'issn-online',
                                    'Value' => '19495005',
                                ],
                                [
                                    'Type' => 'ejsid',
                                    'Value' => '723124',
                                ],
                            ],
                        ],
                    ],
                ],
                'FullTextHoldings' => [
                    [
                        'FullTextHolding' => [
                            'URL' => 'https://foo.bar',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Raw data for testing
     *
     * @return EPF
     */
    protected function getDriver($overrides = []): EPF
    {
        $record = new EPF();
        $record->setRawData($overrides);
        return $record;
    }
}
