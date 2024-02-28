<?php

/**
 * DublinCore Test Class
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

use VuFind\MetadataVocabulary\DublinCore;

/**
 * DublinCore Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DublinCoreTest extends \PHPUnit\Framework\TestCase
{
    use FakeDriverTrait;

    /**
     * Test basic functionality of the class.
     *
     * @return void
     */
    public function testMappings()
    {
        $meta = new DublinCore();
        $this->assertEquals(
            [
                'DC.title' => ['Fake Title'],
                'DC.language' => ['English'],
                'DC.citation.epage' => [10],
                'DC.identifier' => ['FakeDOI', 'FakeISBN', 'FakeISSN'],
                'DC.citation.spage' => [1],
                'DC.citation.volume' => [7],
                'DC.creator' => ['Mr. Person'],
                'DC.citation.issue' => [5],
                'DC.relation.ispartof' => ['My Journal'],
                'DC.publisher' => ['Fake Publisher'],
                'DC.issued' => [2020],
            ],
            $meta->getMappedData($this->getDriver())
        );
    }
}
