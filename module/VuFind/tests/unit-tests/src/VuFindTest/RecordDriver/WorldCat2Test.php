<?php

/**
 * WorldCat v2 Record Driver Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\RecordDriver;

use Laminas\Config\Config;
use VuFind\RecordDriver\WorldCat2;

/**
 * WorldCat v2 Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class WorldCat2Test extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Data provider for testMethod().
     *
     * @return array[]
     */
    public static function methodTests(): array
    {
        return [
            'default call numbers' => ['getCallNumbers', []],
            'default dewey call number' => ['getDeweyCallNumber', ''],
            'default raw LCCN' => ['getLCCN', ''],
            'default formats' => ['getFormats', []],
            'default ISBNs' => ['getISBNs', []],
            'default ISSNs' => ['getISSNs', []],
            'default languages' => ['getLanguages', []],
            'default places of publication' => ['getPlacesOfPublication', []],
            'default primary authors' => ['getPrimaryAuthors', []],
            'default secondary authors' => ['getSecondaryAuthors', []],
            'default corporate authors' => ['getCorporateAuthors', []],
            'default date span' => ['getDateSpan', []],
            'default publication dates' => ['getPublicationDates', []],
            'default human-readable dates' => ['getHumanReadablePublicationDates', []],
            'default publishers' => ['getPublishers', []],
            'default newer titles' => ['getNewerTitles', []],
            'default previous titles' => ['getPreviousTitles', []],
            'default summary' => ['getSummary', []],
            'default title' => ['getTitle', ''],
            'default short title' => ['getShortTitle', ''],
            'default subtitle' => ['getSubtitle', ''],
            'default edition' => ['getEdition', ''],
            'default physical description' => ['getPhysicalDescriptions', ''],
            'default subject headings' => ['getAllSubjectHeadings', []],
            'default awards' => ['getAwards', []],
            'default general notes' => ['getGeneralNotes', []],
            'default bibliography notes' => ['getBibliographyNotes', []],
            'default production credits' => ['getProductionCredits', []],
            'default publication frequency' => ['getPublicationFrequency', []],
            'default series' => ['getSeries', []],
            'default table of contents' => ['getTOC', []],
            'default URLs (no config)' => ['getURLs', []],
            'default URLs (config disabled)' => ['getURLs', [], null, ['Record' => ['show_urls' => false]]],
            'default URLs (config enabled)' => ['getURLs', [], null, ['Record' => ['show_urls' => true]]],

            'non-default call numbers' => ['getCallNumbers', ['PR4034 .P7 1990eb', '823/.7'], 'worldcat2/pride.json'],
            'non-default dewey call number' => ['getDeweyCallNumber', '823/.7', 'worldcat2/pride.json'],
            'non-default raw LCCN' => ['getLCCN', 'PR4034.P71990eb', 'worldcat2/pride.json'],
            'non-default formats' => ['getFormats', ['Book', 'Digital'], 'worldcat2/pride.json'],
            'non-default ISBNs' => [
                'getISBNs',
                ['9780191592539', '0191592536', '0585377618', '9780585377612'],
                'worldcat2/pride.json',
            ],
            'non-default languages' => ['getLanguages', ['eng'], 'worldcat2/pride.json'],
            'non-default places of publication' => ['getPlacesOfPublication', ['Oxford :'], 'worldcat2/pride.json'],
            'non-default primary authors' =>
                ['getPrimaryAuthors', ['Austen, Jane, 1775-1817.'], 'worldcat2/pride.json'],
            'non-default secondary authors' => ['getSecondaryAuthors', ['Kinsley, James'], 'worldcat2/pride.json'],
            'non-default publication dates' => ['getPublicationDates', ['1990'], 'worldcat2/pride.json'],
            'non-default human-readable dates' =>
                ['getHumanReadablePublicationDates', ['1990'], 'worldcat2/pride.json'],
            'non-default publishers' => ['getPublishers', ['Oxford University Press'], 'worldcat2/pride.json'],
            'non-default title' => ['getTitle', 'Pride and prejudice', 'worldcat2/pride.json'],
            'non-default physical description' => [
                'getPhysicalDescriptions',
                '1 online resource (xxxii, 303 pages)',
                'worldcat2/pride.json',
            ],
            'non-default subject headings' => [
                'getAllSubjectHeadings',
                [
                   ['Young women Fiction'],
                   ['Courtship Fiction'],
                   ['Sisters Fiction'],
                   ['Jeunes femmes Romans, nouvelles, etc'],
                   ['Amours Romans, nouvelles, etc'],
                   ['Sœurs Romans, nouvelles, etc'],
                   ['FICTION Romance General'],
                   ['Courtship'],
                   ['Sisters'],
                   ['Young women'],
                   ['England Fiction'],
                   ['Angleterre Romans, nouvelles, etc'],
                   ['England'],
                   ['Domestic fiction'],
                   ['Fiction'],
                   ['Love stories'],
                ],
                'worldcat2/pride.json',
            ],
            'non-default general notes' => [
                'getGeneralNotes',
                ['Reprint. Originally published: Oxford University Press, 1970'],
                'worldcat2/pride.json',
            ],
            'non-default series' => [
                'getSeries',
                [
                    ['name' => 'Oxford world\'s classics (Oxford University Press)', 'number' => ''],
                    ['name' => 'World\'s classics', 'number' => ''],
                ],
                'worldcat2/pride.json',
            ],
            'non-default URLs (no config)' => ['getURLs', [], 'worldcat2/pride.json'],
            'non-default URLs (config disabled)' => [
                'getURLs',
                [],
                'worldcat2/pride.json',
                ['Record' => ['show_urls' => false]],
            ],
            'non-default URLs (config enabled)' => [
                'getURLs',
                [
                    [
                        'url' =>
                            'https://search.ebscohost.com/login.aspx?direct=true&scope=site&db=nlebk&db=nlabk&AN=55923',
                    ],
                    ['url' => 'http://www.netlibrary.com/UrlApi.aspx?action=browse&v=1&bookid=1085113'],
                    ['url' => 'https://archive.org/details/prideprejudice100aust'],
                    ['url' => 'https://openlibrary.org/books/OL1875263M'],
                ],
                'worldcat2/pride.json',
                ['Record' => ['show_urls' => true]],
            ],
            'non-default ID' => ['getUniqueID', '49569228', 'worldcat2/pride.json'],
            'non-default OCLC numbers' => [
                'getOCLC',
                ['49569228', '530699569', '702096151', '1036823755', '1332980563'],
                'worldcat2/pride.json',
            ],

            //'non-default ISSNs' => ['getISSNs', [], 'worldcat2/pride.json'],
            //'non-default corporate authors' => ['getCorporateAuthors', [], 'worldcat2/pride.json'],
            //'non-default date span' => ['getDateSpan', [], 'worldcat2/pride.json'],
            //'non-default newer titles' => ['getNewerTitles', [], 'worldcat2/pride.json'],
            //'non-default previous titles' => ['getPreviousTitles', [], 'worldcat2/pride.json'],
            //'non-default summary' => ['getSummary', [], 'worldcat2/pride.json'],
            //'non-default short title' => ['getShortTitle', '', 'worldcat2/pride.json'],
            //'non-default subtitle' => ['getSubtitle', '', 'worldcat2/pride.json'],
            //'non-default edition' => ['getEdition', '', 'worldcat2/pride.json'],
            //'non-default awards' => ['getAwards', [], 'worldcat2/pride.json'],
            //'non-default bibliography notes' => ['getBibliographyNotes', [], 'worldcat2/pride.json'],
            //'non-default production credits' => ['getProductionCredits', [], 'worldcat2/pride.json'],
            //'non-default publication frequency' => ['getPublicationFrequency', [], 'worldcat2/pride.json'],
            //'non-default table of contents' => ['getTOC', [], 'worldcat2/pride.json'],
        ];
    }

    /**
     * Test that a method returns an expected value when given a particular fixture.
     *
     * @param string  $method   Method to test
     * @param mixed   $expected Expected return value
     * @param ?string $fixture  Fixture to use (null for empty data)
     * @param array   $config   Configuration to apply
     *
     * @return void
     *
     * @dataProvider methodTests
     */
    public function testMethod(string $method, $expected, ?string $fixture = null, array $config = [])
    {
        $configObj = new Config($config);
        $driver = new WorldCat2($configObj, $configObj, $configObj);
        if ($fixture) {
            $driver->setRawData(
                json_decode($this->getFixture($fixture), true)
            );
        }
        $this->assertEquals($expected, $driver->$method());
    }

    /**
     * Test that an exception is thrown if the OCLC number is missing.
     *
     * @return void
     */
    public function testMissingIdentifier(): void
    {
        $configObj = new Config([]);
        $driver = new WorldCat2($configObj, $configObj, $configObj);
        $this->expectExceptionMessage('ID not set!');
        $driver->getOCLC();
    }
}
