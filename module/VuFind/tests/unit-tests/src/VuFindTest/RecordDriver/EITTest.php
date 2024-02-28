<?php

/**
 * EIT Record Driver Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\EIT;

/**
 * EIT Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EITTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getAllSubjectHeadings for a record.
     *
     * @return void
     */
    public function testGetAllSubjectHeadings()
    {
        $this->assertEquals([], $this->getDriver()->getAllSubjectHeadings());
    }

    /**
     * Test getBreadcrumb for a record.
     *
     * @return void
     */
    public function testGetBreadcrumb()
    {
        $driver = $this->getDriver();
        $this->assertEquals('', $driver->getBreadcrumb());
    }

    /**
     * Test getCleanISSN for a record.
     *
     * @return void
     */
    public function testGetCleanISSN()
    {
        $this->assertEquals(false, $this->getDriver()->getCleanISSN());
    }

    /**
     * Test getFormats for a record.
     *
     * @return void
     */
    public function testGetFormats()
    {
        $this->assertEquals([], $this->getDriver()->getFormats());
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
     * Test getPublicationDates for a record.
     *
     * @return void
     */
    public function testGetPublicationDates()
    {
        $this->assertEquals([], $this->getDriver()->getPublicationDates());
    }

    /**
     * Test getPublishers for a record.
     *
     * @return void
     */
    public function testGetPublishers()
    {
        $overrides = [
            'header' => [
                'controlInfo' => [
                    'pubinfo' => ['pub' => ['TestPublisher']],
                ],
            ],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals([['TestPublisher']], $driver->getPublishers());
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Fixture fields to override.
     *
     * @return EIT
     */
    protected function getDriver($overrides = []): EIT
    {
        // Simulate empty response for now:
        $fixture = ['response' => ['docs' => [[]]]];

        $record = new EIT();
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }
}
