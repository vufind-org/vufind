<?php
/**
 * CitationBuilder Test Class
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\Citation;

/**
 * CitationBuilder Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CitationTest extends \VuFindTest\Unit\ViewHelperTestCase
{
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
                'PublicationDates' => ['1958']
            ],
            'apa' => 'Shafer, K. N. (1958). <span style="font-style:italic;">Medical-surgical nursing</span>. St. Louis: Mosby.',
            'mla' => 'Shafer, Kathleen Newton. <span style="font-style:italic;">Medical-surgical Nursing</span>. St. Louis: Mosby, 1958.',
            'chicago' => 'Shafer, Kathleen Newton. <span style="font-style:italic;">Medical-surgical Nursing</span>. St. Louis: Mosby, 1958.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'assessment and management of clinical problems.',
                'Edition' => '7th ed. /',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007']
            ],
            'apa' => 'Lewis, S. (2007). <span style="font-style:italic;">Medical-surgical nursing: Assessment and management of clinical problems</span> (7th ed.). St. Louis, Mo: Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [  // subtitle embedded in title
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'Title' => 'Medical-surgical nursing: assessment and management of clinical problems.',
                'Edition' => '7th ed. /',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007']
            ],
            'apa' => 'Lewis, S. (2007). <span style="font-style:italic;">Medical-surgical nursing: Assessment and management of clinical problems</span> (7th ed.). St. Louis, Mo: Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M.'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'assessment and management of clinical problems.',
                'Edition' => '1st ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007']
            ],
            'apa' => 'Lewis, S. (2007). <span style="font-style:italic;">Medical-surgical nursing: Assessment and management of clinical problems</span>. St. Louis, Mo: Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. St. Louis, Mo: Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Assessment and Management of Clinical Problems</span>. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M., Weirdlynamed'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'why?',
                'Edition' => '7th ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007']
            ],
            'apa' => 'Lewis, S. (2007). <span style="font-style:italic;">Medical-surgical nursing: Why?</span> (7th ed.). St. Louis, Mo: Mosby Elsevier.',
            'mla' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Why?</span> 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M. <span style="font-style:italic;">Medical-surgical Nursing: Why?</span> 7th ed. St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Lewis, S.M., IV'],
                'ShortTitle' => 'Medical-surgical nursing',
                'Subtitle' => 'why?',
                'Edition' => '1st ed.',
                'PlacesOfPublication' => ['St. Louis, Mo.'],
                'Publishers' => ['Mosby Elsevier'],
                'PublicationDates' => ['2007']
            ],
            'apa' => 'Lewis, S., IV. (2007). <span style="font-style:italic;">Medical-surgical nursing: Why?</span> St. Louis, Mo: Mosby Elsevier.',
            'mla' => 'Lewis, S.M., IV. <span style="font-style:italic;">Medical-surgical Nursing: Why?</span> St. Louis, Mo: Mosby Elsevier, 2007.',
            'chicago' => 'Lewis, S.M., IV. <span style="font-style:italic;">Medical-surgical Nursing: Why?</span> St. Louis, Mo: Mosby Elsevier, 2007.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York :'],
                'Publishers' => ['Holmes & Meier,'],
                'PublicationDates' => ['1980.']
            ],
            'apa' => 'Burch, P. H., Jr. (1980). <span style="font-style:italic;">The New Deal to the Carter administration</span>. New York: Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier, 1980.',
            'chicago' => 'Burch, Philip H., Jr. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier, 1980.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Coauthor, Fictional', 'Fakeperson, Third, III'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York :'],
                'Publishers' => ['Holmes & Meier,'],
                'PublicationDates' => ['1980.']
            ],
            'apa' => 'Burch, P. H., Jr., Coauthor, F., &amp; Fakeperson, T., III. (1980). <span style="font-style:italic;">The New Deal to the Carter administration</span>. New York: Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr., Fictional Coauthor, and Third Fakeperson, III. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier, 1980.',
            'chicago' => 'Burch, Philip H., Jr., Fictional Coauthor, and Third Fakeperson, III. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier, 1980.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Coauthor, Fictional', 'Fakeperson, Third, III', 'Mob, Writing', 'Manypeople, Letsmakeup'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => '',
                'Publishers' => '',
                'PublicationDates' => ''
            ],
            'apa' => 'Burch, P. H., Jr., Coauthor, F., Fakeperson, T., III, Mob, W., &amp; Manypeople, L. <span style="font-style:italic;">The New Deal to the Carter administration</span>.',
            'mla' => 'Burch, Philip H., Jr., et al. <span style="font-style:italic;">The New Deal to the Carter Administration</span>.',
            'chicago' => 'Burch, Philip H., Jr., Fictional Coauthor, Third Fakeperson, III, Writing Mob, and Letsmakeup Manypeople. <span style="font-style:italic;">The New Deal to the Carter Administration</span>.',
        ],
        [
            'raw' => [
                'SecondaryAuthors' => ['Burch, Philip H., Jr.', 'Anonymous, 1971-1973', 'Elseperson, Firstnamery, 1971-1973'],
                'ShortTitle' => 'The New Deal to the Carter administration',
                'Subtitle' => '',
                'Edition' => '',
                'PlacesOfPublication' => ['New York'],
                'Publishers' => ['Holmes & Meier']
            ],
            'apa' => 'Burch, P. H., Jr., Anonymous, &amp; Elseperson, F. <span style="font-style:italic;">The New Deal to the Carter administration</span>. New York: Holmes &amp; Meier.',
            'mla' => 'Burch, Philip H., Jr., Anonymous, and Firstnamery Elseperson. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier.',
            'chicago' => 'Burch, Philip H., Jr., Anonymous, and Firstnamery Elseperson. <span style="font-style:italic;">The New Deal to the Carter Administration</span>. New York: Holmes &amp; Meier.',
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
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Eight, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, Person Seven, and Person Eight. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
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
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., &amp; Seven, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, and Person Seven. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
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
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., &amp; Six, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, and Person Six. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
        ],
        [  // two authors
            'raw' => [
                'SecondaryAuthors' => ['One, Person', 'Two, Person'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., &amp; Two, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, and Person Two. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, and Person Two. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
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
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
        ],
        [  // eight authors in "first name first" format.
            'raw' => [
                'SecondaryAuthors' => ['Person One', 'Person Two', 'Person Three', 'Person Four', 'Person Five', 'Person Six', 'Person Seven', 'Person Eight'],
                'ShortTitle' => 'Test Article',
                'ContainerTitle' => 'Test Journal',
                'ContainerVolume' => 1,
                'ContainerIssue' => 7,
                'PublicationDates' => ['1999'],
                'ContainerStartPage' => 19,
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Eight, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, Person Two, Person Three, Person Four, Person Five, Person Six, Person Seven, and Person Eight. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
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
                'ContainerEndPage' => 21
            ],
            'apa' => 'One, P., Two, P., Three, P., Four, P., Five, P., Six, P., . . . Ten, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21.',
            'mla' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person, et al. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
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
                'CleanDOI' => 'testDOI'
            ],
            'apa' => 'One, P. (1999). Test Article. <span style="font-style:italic;">Test Journal, 1</span>(7), pp. 19-21. doi:testDOI',
            'mla' => 'One, Person. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1.7 (1999): 19-21.',
            'chicago' => 'One, Person. &quot;Test Article.&quot; <span style="font-style:italic;">Test Journal</span> 1, no. 7 (1999): 19-21.',
        ]
        // @codingStandardsIgnoreEnd
    ];

    /**
     * Setup test case.
     *
     * Mark test skipped if short_open_tag is not enabled. The partial
     * uses short open tags. This directive is PHP_INI_PERDIR,
     * i.e. can only be changed via php.ini or a per-directory
     * equivalent. The testCitations() will fail if the test is run on
     * a system with short_open_tag disabled in the system-wide php
     * ini-file.
     *
     * @return void
     */
    protected function setup()
    {
        parent::setup();
        if (!ini_get('short_open_tag')) {
            $this->markTestSkipped('Test requires short_open_tag to be enabled');
        }
    }

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
            $cb = $citation->__invoke($driver);

            // Normalize whitespace:
            $apa = trim(preg_replace("/\s+/", " ", $cb->getCitation('APA')));
            $this->assertEquals($current['apa'], $apa);

            // Normalize whitespace:
            $mla = trim(preg_replace("/\s+/", " ", $cb->getCitation('MLA')));
            $this->assertEquals($current['mla'], $mla);

            // Normalize whitespace:
            $chicago = trim(preg_replace("/\s+/", " ", $cb->getCitation('Chicago')));
            $this->assertEquals($current['chicago'], $chicago);
        }

        // Test a couple of illegal citation formats:
        $this->assertEquals('', $cb->getCitation(''));
        $this->assertEquals('', $cb->getCitation('Citation'));
        $this->assertEquals('', $cb->getCitation('SupportedCitationFormats'));
        $this->assertEquals('', $cb->getCitation('badgarbage'));
    }
}