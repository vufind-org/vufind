<?php

/**
 * EIT Record Driver Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
 * EIT Record Driver Test Class.
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
    public function testGetAllSubjectHeadings(): void
    {
        $this->assertEquals([], $this->getDriver()->getAllSubjectHeadings());
    }

    /**
     * Test getBreadcrumb for a record.
     *
     * @return void
     */
    public function testGetBreadcrumb(): void
    {
        $driver = $this->getDriver();
        $this->assertSame('', $driver->getBreadcrumb());
    }

    /**
     * Test getCleanISSN for a record.
     *
     * @return void
     */
    public function testGetCleanISSN(): void
    {
        $this->assertEquals(false, $this->getDriver()->getCleanISSN());
    }

    /**
     * Test getFormats for a record.
     *
     * @return void
     */
    public function testGetFormats(): void
    {
        $this->assertEquals([], $this->getDriver()->getFormats());
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
     * Test getPublicationDates for a record.
     *
     * @return void
     */
    public function testGetPublicationDates(): void
    {
        $this->assertEquals([], $this->getDriver()->getPublicationDates());
    }

    /**
     * Test getPublishers for a record.
     *
     * @return void
     */
    public function testGetPublishers(): void
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
    protected function getDriver(array $overrides = []): EIT
    {
        // Simulate empty response for now:
        $fixture = ['response' => ['docs' => [[]]]];

        $record = new EIT();
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }
}
