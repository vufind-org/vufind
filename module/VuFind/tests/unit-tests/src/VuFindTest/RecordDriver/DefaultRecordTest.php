<?php

/**
 * DefaultRecord Record Driver Test Class
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

use Laminas\Config\Config;
use VuFind\RecordDriver\DefaultRecord;
use VuFind\RecordDriver\Response\PublicationDetails;

/**
 * DefaultRecord Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DefaultRecordTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test getPublicationDates for a record.
     *
     * @return void
     */
    public function testGetPublicationDates()
    {
        $pubDates = ['1992'];
        $this->assertEquals($pubDates, $this->getDriver()->getPublicationDates());
    }

    /**
     * Test getCoordinateLabels for a record.
     *
     * @return void
     */
    public function testGetCoordinateLabels()
    {
        $coordinateLabels = [];
        $this->assertEquals($coordinateLabels, $this->getDriver()->getCoordinateLabels());
    }

    /**
     * Test getDisplayCoordinates for a record.
     *
     * @return void
     */
    public function testGetDisplayCoordinates()
    {
        $displayCoordinates = [];
        $this->assertEquals($displayCoordinates, $this->getDriver()->getDisplayCoordinates());
    }

    /**
     * Test getDisplayCoordinates for a record.
     *
     * @return void
     */
    public function testGetGeoLocation()
    {
        $geoLoc = [];
        $this->assertEquals($geoLoc, $this->getDriver()->getGeoLocation());
    }

    /**
     * Test getSchemaOrgFormatsArray for a record.
     *
     * @return void
     */
    public function testGetSchemaOrgFormatsArray()
    {
        $formats = ['Book'];
        $this->assertEquals($formats, $this->getDriver()->getSchemaOrgFormatsArray());
    }

    /**
     * Test getSchemaOrgFormats for a record.
     *
     * @return void
     */
    public function testGetSchemaOrgFormats()
    {
        $formats = 'Book';
        $this->assertEquals($formats, $this->getDriver()->getSchemaOrgFormats());
    }

    /**
     * Test getSortTitle for a record.
     *
     * @return void
     */
    public function testGetSortTitle()
    {
        $this->assertEquals(
            'congiura dei principi napoletani 1701 :(prima e seconda stesura)',
            $this->getDriver()->getSortTitle()
        );
    }

    /**
     * Test getContainerReference for a record.
     *
     * @return void
     */
    public function testGetContainerReference()
    {
        $this->assertEquals('', $this->getDriver()->getContainerReference());
    }

    /**
     * Test getThumbnail for a record.
     *
     * @return void
     */
    public function testGetThumbnail()
    {
        $thumbnail = [
          'author' => 'Vico, Giambattista, 1668-1744.',
          'callnumber' => '',
          'size' => 'small',
          'title' => 'La congiura dei Principi Napoletani 1701 : (prima e seconda stesura) /',
          'recordid' => 'testbug2',
          'source' => '',
          'oclc' => '30585539',
          'isbns' => ['8820737493', '8072815563', '9798644293513'],
        ];
        $this->assertEquals($thumbnail, $this->getDriver()->getThumbnail());
    }

    /**
     * Test getURLs for a record.
     *
     * @return void
     */
    public function testGetURLs()
    {
        $testURL = [['url' => 'http://fictional.com/sample/url']];
        $this->assertEquals($testURL, $this->getDriver()->getURLs());
    }

    /**
     * Test getURLs for a record.
     *
     * @return void
     */
    public function testGetTOC()
    {
        $this->assertEquals([], $this->getDriver()->getTOC());
    }

    /**
     * Test getSummary for a record.
     *
     * @return void
     */
    public function testGetSummary()
    {
        $this->assertEquals([], $this->getDriver()->getSummary());
    }

    /**
     * Test getSubtitle for a record.
     *
     * @return void
     */
    public function testGetSubtitle()
    {
        $this->assertEquals('(prima e seconda stesura) /', $this->getDriver()->getSubtitle());
    }

    /**
     * Test getSecondaryAuthorsRoles for a record.
     *
     * @return void
     */
    public function testGetSecondaryAuthorsRoles()
    {
        $this->assertEquals([], $this->getDriver()->getSecondaryAuthorsRoles());
    }

    /**
     * Test getSecondaryAuthors for a record.
     *
     * @return void
     */
    public function testGetSecondaryAuthors()
    {
        $author2 = ['Pandolfi, Claudia.'];
        $this->assertEquals($author2, $this->getDriver()->getSecondaryAuthors());
    }

    /**
     * Test getPublicationDetails for a record.
     *
     * @return void
     */
    public function testGetPublicationDetails()
    {
        $pubDetails = [new PublicationDetails('', 'Centro di Studi Vichiani,', '1992')];
        $this->assertEquals($pubDetails, $this->getDriver()->getPublicationDetails());
    }

    /**
     * Test getPrimaryAuthorsRoles for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthorsRoles()
    {
        $this->assertEquals([], $this->getDriver()->getPrimaryAuthorsRoles());
    }

    /**
     * Test getPrimaryAuthor for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthor()
    {
        $this->assertEquals('Vico, Giambattista, 1668-1744.', $this->getDriver()->getPrimaryAuthor());
    }

    /**
     * Test getPreviousTitles for a record.
     *
     * @return void
     */
    public function testGetPreviousTitles()
    {
        $this->assertEquals([], $this->getDriver()->getPreviousTitles());
    }

    /**
     * Test getPhysicalDescriptions for a record.
     *
     * @return void
     */
    public function testGetPhysicalDescriptions()
    {
        $physical = ['296 p. : ill. ; 24 cm.'];
        $this->assertEquals($physical, $this->getDriver()->getPhysicalDescriptions());
    }

    /**
     * Test getCoinsOpenUrl for a record.
     *
     * @return void
     */
    public function testGetCoinsOpenUrl()
    {
        $coinsOpenUrl = 'url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3A'
            . 'UTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.title=La+co'
            . 'ngiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.da'
            . 'te=1992&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Abook&rft.genre=book&rft.btitl'
            . 'e=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F'
            . '&rft.series=Vico%2C+Giambattista%2C+1668-1744.+Works.+1982+%3B&rft.au=Vico%2C+Gi'
            . 'ambattista%2C+1668-1744.&rft.pub=Centro+di+Studi+Vichiani%2C&rft.edition=Fiction'
            . 'al+edition.&rft.isbn=8820737493';
        $this->assertEquals($coinsOpenUrl, $this->getDriver()->getCoinsOpenUrl());
    }

    /**
     * Test getOpenUrl for a record.
     *
     * @return void
     */
    public function testGetOpenUrl()
    {
        $openUrl = 'url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3A'
            . 'UTF-8&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator&rft.title=La+co'
            . 'ngiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F&rft.da'
            . 'te=1992&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Abook&rft.genre=book&rft.btitl'
            . 'e=La+congiura+dei+Principi+Napoletani+1701+%3A+%28prima+e+seconda+stesura%29+%2F'
            . '&rft.series=Vico%2C+Giambattista%2C+1668-1744.+Works.+1982+%3B&rft.au=Vico%2C+Gi'
            . 'ambattista%2C+1668-1744.&rft.pub=Centro+di+Studi+Vichiani%2C&rft.place=Morano+%3A'
            . '&rft.edition=Fictional+edition.&rft.isbn=8820737493&rft_id=info%3Adoi%2Fxxx'
            . '&rft_id=pmid%3Ayyy';

        // Parameters returned by getBookOpenUrlParams with rft_id added
        $openUrlParams = [
            'url_ver' => 'Z39.88-2004',
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'rfr_id' => 'info:sid/vufind.svn.sourceforge.net:generator',
            'rft.title' => 'La congiura dei Principi Napoletani 1701 : (prima e seconda stesura) /',
            'rft.date' => '1992',
            'rft_val_fmt' => 'info:ofi/fmt:kev:mtx:book',
            'rft.genre' => 'book',
            'rft.btitle' => 'La congiura dei Principi Napoletani 1701 : (prima e seconda stesura) /',
            'rft.series' => 'Vico, Giambattista, 1668-1744. Works. 1982 ;',
            'rft.au' => 'Vico, Giambattista, 1668-1744.',
            'rft.pub' => 'Centro di Studi Vichiani,',
            'rft.place' => 'Morano :',
            'rft.edition' => 'Fictional edition.',
            'rft.isbn' => '8820737493',
            'rft_id' => [
                'info:doi/xxx',
                'pmid:yyy',
            ],
        ];

        $fixture = $this->getJsonFixture('misc/testbug2.json');
        $fields = $fixture['response']['docs'][0];
        $mock = $this->getMockBuilder(\VuFind\RecordDriver\DefaultRecord::class)
            ->onlyMethods(['getBookOpenUrlParams'])
            ->getMock();
        $mock->setRawData($fields);
        $mock->expects($this->any())
            ->method('getBookOpenUrlParams')
            ->will($this->returnValue($openUrlParams));

        $this->assertEquals($openUrl, $mock->getOpenUrl());
    }

    /**
     * Test getNewerTitles for a record.
     *
     * @return void
     */
    public function testGetNewerTitles()
    {
        $this->assertEquals([], $this->getDriver()->getNewerTitles());
    }

    /**
     * Test getLCCN for a record.
     *
     * @return void
     */
    public function testGetLCCN()
    {
        $this->assertEquals('', $this->getDriver()->getLCCN());
    }

    /**
     * Test getInstitutions for a record.
     *
     * @return void
     */
    public function testGetInstitutions()
    {
        $institution = ['MyInstitution'];
        $this->assertEquals($institution, $this->getDriver()->getInstitutions());
    }

    /**
     * Test getLastIndexed for a record.
     *
     * @return void
     */
    public function testGetLastIndexed()
    {
        $this->assertEquals('', $this->getDriver()->getLastIndexed());
    }

    /**
     * Test getPrimaryAuthorsWithHighlighting for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthorsWithHighlighting()
    {
        $primAuthHighlight = ['Vico, Giambattista, 1668-1744.'];
        $this->assertEquals($primAuthHighlight, $this->getDriver()->getPrimaryAuthorsWithHighlighting());
    }

    /**
     * Test getDateSpan for a record.
     *
     * @return void
     */
    public function testGetDateSpan()
    {
        $this->assertEquals([], $this->getDriver()->getDateSpan());
    }

    /**
     * Test getCorporateAuthorsRoles for a record.
     *
     * @return void
     */
    public function testGetCorporateAuthorsRoles()
    {
        $this->assertEquals([], $this->getDriver()->getCorporateAuthorsRoles());
    }

    /**
     * Test getCorporateAuthors for a record.
     *
     * @return void
     */
    public function testGetCorporateAuthors()
    {
        $this->assertEquals([], $this->getDriver()->getCorporateAuthors());
    }

    /**
     * Test getCleanDOI for a record.
     *
     * @return void
     */
    public function testGetCleanDOI()
    {
        $cleanDOI = false;
        $this->assertEquals($cleanDOI, $this->getDriver()->getCleanDOI());
    }

    /**
     * Test getCallNumber for a record.
     *
     * @return void
     */
    public function testGetCallNumber()
    {
        $this->assertEquals('', $this->getDriver()->getCallNumber());
    }

    /**
     * Test getBreadcrumb for a record.
     *
     * @return void
     */
    public function testGetBreadcrumb()
    {
        $breadcrumb = 'La congiura dei Principi Napoletani 1701 :';
        $this->assertEquals($breadcrumb, $this->getDriver()->getBreadcrumb());
    }

    /**
     * Test citation behavior.
     *
     * @return void
     */
    public function testCitationBehavior()
    {
        // The DefaultRecord driver should have some supported formats:
        $driver = $this->getDriver();
        $supported = $this->callMethod($driver, 'getSupportedCitationFormats');
        $this->assertNotEmpty($supported);

        // By default, all supported formats should be enabled:
        $this->assertEquals($supported, $driver->getCitationFormats());

        // Data table (citation_formats config, expected result):
        $tests = [
            // No results:
            [false, []],
            ['false', []],
            // All results:
            [true, $supported],
            ['true', $supported],
            // Filtered results:
            ['MLA,foo', ['MLA']],
            ['bar ,     APA,MLA', ['APA', 'MLA']],
        ];
        foreach ($tests as $current) {
            [$input, $output] = $current;
            $cfg = new Config(['Record' => ['citation_formats' => $input]]);
            $this->assertEquals(
                $output,
                array_values($this->getDriver([], $cfg)->getCitationFormats())
            );
        }
    }

    /**
     * Data provider for testGetCleanISBNs
     *
     * @return array
     */
    public static function getCleanISBNsProvider(): array
    {
        return [
            [
                ['8820737493', '8072815563'],
                'only10',
                true,
            ],
            [
                ['8820737493', '8072815563', 'invalid-isbn'],
                'only10',
                false,
            ],
            [
                ['8820737493', '8072815563', '9798644293513'],
                'prefer10',
                true,
            ],
            [
                ['8820737493', '8072815563', '9798644293513', 'invalid-isbn'],
                'prefer10',
                false,
            ],
            [
                ['9798644293513', '9788820737498', '9788072815562'],
                'normalize13',
                true,
            ],
            [
                ['9798644293513', '9788820737498', '9788072815562', 'invalid-isbn'],
                'normalize13',
                false,
            ],
        ];
    }

    /**
     * Test getCleanISBNs for a record.
     *
     * @param array  $result        Expected result
     * @param string $mode          Retrieval mode
     * @param bool   $filterInvalid Should we filter invalid ISBNs?
     *
     * @dataProvider getCleanISBNsProvider
     *
     * @return void
     */
    public function testGetCleanISBNs($result, $mode, $filterInvalid)
    {
        $this->assertEquals($result, $this->getDriver()->getCleanISBNs($mode, $filterInvalid));
    }

    /**
     * Test whether author deduplication works correctly.
     *
     * @return void
     */
    public function testGetDeduplicatedAuthors()
    {
        $authorName = 'Tester, Marc';

        $overrides = [
            'author' => $authorName,
            'author_role' => 'aut',
            'author2' => [$authorName],
            'author2_role' => ['trl'],
            'author_corporate' => [],
            'author_corporate_role' => [],
        ];

        $expectedResult = [
            'primary' => [
                $authorName => [
                    'role' => ['aut', 'trl'],
                ],
            ],
            'secondary' => [],
            'corporate' => [],
        ];

        $driver = $this->getDriver($overrides);
        $deduplicatedAuthors = $driver->getDeduplicatedAuthors();

        $this->assertEquals($deduplicatedAuthors, $expectedResult);
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array  $overrides  Fixture fields to override.
     * @param Config $mainConfig Main configuration (optional).
     *
     * @return SolrDefault
     */
    protected function getDriver($overrides = [], Config $mainConfig = null)
    {
        $fixture = $this->getJsonFixture('misc/testbug2.json');
        $record = new DefaultRecord($mainConfig);
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }
}
