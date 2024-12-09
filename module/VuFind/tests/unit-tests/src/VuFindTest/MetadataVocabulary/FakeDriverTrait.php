<?php

/**
 * Trait containing method to generate fake drivers for metadata testing.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\MetadataVocabulary;

use VuFindTest\RecordDriver\TestHarness;

/**
 * Trait containing method to generate fake drivers for metadata testing.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait FakeDriverTrait
{
    /**
     * Get a fake record driver
     *
     * @return TestHarness
     */
    protected function getDriver()
    {
        $data = [
            'Title' => 'Fake Title',
            'PrimaryAuthors' => ['Mr. Person'],
            'ContainerTitle' => 'My Journal',
            'PublicationDates' => [2020],
            'CleanDOI' => 'FakeDOI',
            'ContainerEndPage' => 10,
            'CleanISBN' => 'FakeISBN',
            'CleanISSN' => 'FakeISSN',
            'ContainerIssue' => 5,
            'Languages' => ['English'],
            'Publishers' => ['Fake Publisher'],
            'ContainerStartPage' => 1,
            'ContainerVolume' => 7,
        ];
        $driver = new TestHarness();
        $driver->setRawData($data);
        return $driver;
    }
}
