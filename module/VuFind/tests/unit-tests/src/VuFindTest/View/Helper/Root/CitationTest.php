<?php

/**
 * CitationBuilder Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\Citation;

/**
 * CitationBuilder Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CitationTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Sample citations -- each element of this array contains three elements --
     * the raw input data and the expected apa/mla output citations.
     *
     * @var array
     */
    protected $citations = [
        // @codingStandardsIgnoreStart
        [
            'raw' => [
                'SecondaryAuthors' => ['Shafer, Kathleen Newton'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['St. Louis'],
                'Publishers' => ['Mosby'],
                'PublicationDates' => ['1958'],
            ],
            'apa' => 'Shafer, K. N. (1958). <i>Medical-surgical nursing</i>. Mosby.',
            'mla' => 'Shafer, Kathleen Newton. <i>Medical-surgical Nursing</i>. Mosby, 1958.',
            'chicago' => 'Shafer, Kathleen Newton. <i>Medical-surgical Nursing</i>. St. Louis: Mosby, 1958.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'assessment and management of clinical problems.',
                'Edition' => '7th ed. /',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007'],
            ],
            'apa' => 'Lewis, S. (2007). <i>Medical-surgical nursing: Assessment and management of clinical problems</i> (7th ed.). Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <i>Medical-surgical Nursing: Assessment and Management of Clinical Problems</i>. 7th ed. Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <i>Medical-surgical Nursing: Assessment and Management of Clinical Problems</i>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [  // subtitle embedded in title, with multi-word uncapped phrase, quoted word, and DOI added
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'Title' => 'Even if you "test" Medical-surgical nursing: assessment and management of clinical problems on top of crazy capitalization.',
                'Edition' => '7th ed. /',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007'],
                'CleanDOI' => 'myDOI',
            ],
            'apa' => 'Lewis, S. (2007). <i>Even if you &quot;test&quot; Medical-surgical nursing: Assessment and management of clinical problems on top of crazy capitalization</i> (7th ed.). Mosby Elsevier. https://doi.org/myDOI',
            'mla' => 'Lewis, S.M. <i>Even if You &quot;Test&quot; Medical-surgical Nursing: Assessment and Management of Clinical Problems on top of Crazy Capitalization</i>. 7th ed. Mosby Elsevier, 2007. https://doi.org/myDOI.',
            'chicago' => 'Lewis, S.M. <i>Even if You &quot;Test&quot; Medical-surgical Nursing: Assessment and Management of Clinical Problems on top of Crazy Capitalization</i>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007. https://doi.org/myDOI.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'assessment and management of clinical problems.',
                'Edition' => '1st ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007'],
            ],
            'apa' => 'Lewis, S. (2007). <i>Medical-surgical nursing: Assessment and management of clinical problems</i>. Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <i>Medical-surgical Nursing: Assessment and Management of Clinical Problems</i>. Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <i>Medical-surgical Nursing: Assessment and Management of Clinical Problems</i>. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M., Weirdlynamed'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'why?',
                'Edition' => '7th ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007'],
            ],
            'apa' => 'Lewis, S. (2007). <i>Medical-surgical nursing: Why?</i> (7th ed.). Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <i>Medical-surgical Nursing: Why?</i> 7th ed. Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <i>Medical-surgical Nursing: Why?</i> 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M., IV'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'why?',
                'Edition' => '1st ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007'],
            ],
            'apa' => 'Lewis, S., IV. (2007). <i>Medical-surgical nursing: Why?</i> Mosby Elsevier.',
            'mla' => 'Lewis, S.M., IV. <i>Medical-surgical Nursing: Why?</i> Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M., IV. <i>Medical-surgical Nursing: Why?</i> St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York :'],
                'Publishers' => ['Holmes & Meier,'],
                'PublicationDates' => ['1980.'],
            ],
            'apa' => 'Burch, P. H., Jr. (1980). <i>The New Deal to the Carter administration</i>. Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr. <i>The New Deal to the Carter Administration</i>. Holmes &amp; Meier, 1980.',
            'chicago' => 'Burch, Philip H., Jr. <i>The New Deal to the Carter Administration</i>. New York: Holmes &amp; Meier, 1980.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Coauthor, Fictional', 'Fakeperson, Third, III'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York :'],
                'Publishers' => ['Holmes & Meier,'],
                'PublicationDates' => ['1980.'],
            ],
            'apa' => 'Burch, P. H., Jr., Coauthor, F., &amp; Fakeperson, T., III. (1980). <i>The New Deal to the Carter administration</i>. Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr., et al. <i>The New Deal to the Carter Administration</i>. Holmes &amp; Meier, 1980.',
            'chicago' => 'Burch, Philip H., Jr., Fictional Coauthor, and Third Fakeperson, III. <i>The New Deal to the Carter Administration</i>. New York: Holmes &amp; Meier, 1980.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Coauthor, Fictional', 'Fakeperson, Third, III', 'Mob, Writing', 'Manypeople, Letsmakeup'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => '',
                'Publishers' => '',
                'PublicationDates' => '',
            ],
            'apa' => 'Burch, P. H., Jr., Coauthor, F., Fakeperson, T., III, Mob, W., &amp; Manypeople, L. <i>The New Deal to the Carter administration</i>.',
            'mla' => 'Burch, Philip H., Jr., et al. <i>The New Deal to the Carter Administration</i>.',
            'chicago' => 'Burch, Philip H., Jr., Fictional Coauthor, Third Fakeperson, III, Writing Mob, and Letsmakeup Manypeople. <i>The New Deal to the Carter Administration</i>.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Anonymous, 1971-1973', 'Elseperson, Firstnamery, 1971-1973'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York'],
                'Publishers' => ['Holmes & Meier'],
            ],
            'apa' => 'Burch, P. H., Jr., Anonymous, &amp; Elseperson, F. <i>The New Deal to the Carter administration</i>. Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr., et al. <i>The New Deal to the Carter Administration</i>. Holmes &amp; Meier.',
            'chicago' => 'Burch, Philip H., Jr., Anonymous, and Firstnamery Elseperson. <i>The New Deal to the Carter Administration</i>. New York: Holmes &amp; Meier.',
        ],
        [  // eight authors, with a blend of formatting and extra punctuation/malformed dates
            'raw' => [
                'SecondaryAuthors' => ['One, Person.', 'Person Two', 'Three, Person', 'Person Four.', 'Five, Person, 1900-1950', 'Six, Person 1910-1963', 'Person Seven', 'Person Eight 1900-1999'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Eight, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, Person Seven, and Person Eight. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // eight authors
            'raw' => [
                'SecondaryAuthors' => ['One, Person', 'Two, Person', 'Three, Person', 'Four, Person', 'Five, Person', 'Six, Person', 'Seven, Person', 'Eight, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Eight, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, Person Seven, and Person Eight. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // seven authors
            'raw' => [
                'SecondaryAuthors' => ['One, Person', 'Two, Person', 'Three, Person', 'Four, Person', 'Five, Person', 'Six, Person', 'Seven, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., &amp; Seven, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, and Person Seven. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // six authors
            'raw' => [
                'SecondaryAuthors' => ['One, Person', 'Two, Person', 'Three, Person', 'Four, Person', 'Five, Person', 'Six, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., &amp; Six, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, and Person Six. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // three authors, including one with a random trailing comma
            'raw' => [
                'SecondaryAuthors' => ['One, Person,', 'Two, Person', 'Three, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., &amp; Three, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, and Person Three. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // two authors with birth dates in different formats, single-page article
            'raw' => [
                'SecondaryAuthors' => ['One, Person, b. 1960', 'Two, Person, 1970-'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 19,
            ],
            'apa' => 'One, P., &amp; Two, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19.',
            'mla' => 'One, Person, and Person Two. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, p. 19.',
            'chicago' => 'One, Person, and Person Two. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19.',
        ],
        [  // two authors with no comma in first author's name (test no comma before and)
            // and parenthetical note on second author (test it is removed)
            'raw' => [
                'SecondaryAuthors' => ['IBM', 'Two, Person (Director), 1970-'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 19,
            ],
            'apa' => 'IBM &amp; Two, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19.',
            'mla' => 'IBM and Person Two. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, p. 19.',
            'chicago' => 'IBM and Person Two. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19.',
        ],
        [  // one author
            'raw' => [
                'SecondaryAuthors' => ['One, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // eight authors in "first name first" format.
            'raw' => [
                'SecondaryAuthors' => ['Person One b. 1960', 'Person Two 1869-', 'Person Three', 'Person Four', 'Person Five', 'Person Six', 'Person Seven', 'Person Eight'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Eight, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, Person Seven, and Person Eight. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // ten authors in "first name first" format.
            'raw' => [
                'SecondaryAuthors' => ['Person One', 'Person Two', 'Person Three', 'Person Four', 'Person Five', 'Person Six', 'Person Seven', 'Person Eight', 'Person Nine', 'Person Ten'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Ten, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21.',
            'chicago' => 'One, Person, et al. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21.',
        ],
        [  // DOI
            'raw' => [
                'SecondaryAuthors' => ['One, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21,
                'CleanDOI' => 'testDOI',
            ],
            'apa' => 'One, P. (1999). Test Article. <i>Test Journal, 1</i>(7), 19-21. https://doi.org/testDOI',
            'mla' => 'One, Person. &quot;Test Article.&quot; <i>Test Journal</i>, vol. 1, no. 7, 1999, pp. 19-21, https://doi.org/testDOI.',
            'chicago' => 'One, Person. &quot;Test Article.&quot; <i>Test Journal</i> 1, no. 7 (1999): 19-21. https://doi.org/testDOI.',
        ],
        // @codingStandardsIgnoreEnd
    ];

    /**
     * Test citation generation
     *
     * @return void
     */
    public function testCitations()
    {
        $citation = new Citation(new \VuFind\Date\Converter());
        $citation->setView($this->getPhpRenderer());
        $driver = new \VuFindTest\RecordDriver\TestHarness();
        foreach ($this->citations as $current) {
            $driver->setRawData($current['raw']);
            $cb = $citation($driver);

            // Normalize whitespace:
            $apa = trim(preg_replace("/\s+/", ' ', $cb->getCitation('APA')));
            $this->assertEquals($current['apa'], $apa);

            // Normalize whitespace:
            $mla = trim(preg_replace("/\s+/", ' ', $cb->getCitation('MLA')));
            $this->assertEquals($current['mla'], $mla);

            // Normalize whitespace:
            $chicago = trim(preg_replace("/\s+/", ' ', $cb->getCitation('Chicago')));
            $this->assertEquals($current['chicago'], $chicago);
        }

        $cb = $citation($driver);
        // Test a couple of illegal citation formats:
        $this->assertEquals('', $cb->getCitation(''));
        $this->assertEquals('', $cb->getCitation('Citation'));
        $this->assertEquals('', $cb->getCitation('SupportedCitationFormats'));
        $this->assertEquals('', $cb->getCitation('badgarbage'));
    }
}
