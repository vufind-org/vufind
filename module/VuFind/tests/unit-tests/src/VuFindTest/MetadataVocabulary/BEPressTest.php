<?php

/**
 * BEPress Test Class
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

use VuFind\MetadataVocabulary\BEPress;

/**
 * BEPress Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BEPressTest extends \PHPUnit\Framework\TestCase
{
    use FakeDriverTrait;

    /**
     * Test basic functionality of the class.
     *
     * @return void
     */
    public function testMappings()
    {
        $meta = new BEPress();
        $this->assertEquals(
            [
                'bepress_citation_title' => ['Fake Title'],
                'bepress_citation_doi' => ['FakeDOI'],
                'bepress_citation_lastpage' => [10],
                'bepress_citation_isbn' => ['FakeISBN'],
                'bepress_citation_issn' => ['FakeISSN'],
                'bepress_citation_firstpage' => [1],
                'bepress_citation_volume' => [7],
                'bepress_citation_author' => ['Mr. Person'],
                'bepress_citation_issue' => [5],
                'bepress_citation_journal_title' => ['My Journal'],
                'bepress_citation_publisher' => ['Fake Publisher'],
                'bepress_citation_date' => [2020],
            ],
            $meta->getMappedData($this->getDriver())
        );
    }
}
