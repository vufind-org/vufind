<?php

/**
 * HighwirePress Test Class
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

use VuFind\MetadataVocabulary\HighwirePress;

/**
 * HighwirePress Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HighwirePressTest extends \PHPUnit\Framework\TestCase
{
    use FakeDriverTrait;

    /**
     * Test basic functionality of the class.
     *
     * @return void
     */
    public function testMappings()
    {
        $meta = new HighwirePress();
        $this->assertEquals(
            [
                'citation_title' => ['Fake Title'],
                'citation_doi' => ['FakeDOI'],
                'citation_lastpage' => [10],
                'citation_isbn' => ['FakeISBN'],
                'citation_issn' => ['FakeISSN'],
                'citation_firstpage' => [1],
                'citation_volume' => [7],
                'citation_author' => ['Mr. Person'],
                'citation_issue' => [5],
                'citation_journal_title' => ['My Journal'],
                'citation_publisher' => ['Fake Publisher'],
                'citation_date' => [2020],
                'citation_language' => ['English'],
            ],
            $meta->getMappedData($this->getDriver())
        );
    }
}
