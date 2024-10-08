<?php

/**
 * EDS Record Driver Test Class
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\EDS;

use function array_slice;

/**
 * EDS Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sravanthi Adusumilli <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EDSTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Default test configuration
     *
     * @var array
     */
    protected $defaultDriverConfig = [
        'General' => [
            'default_sort' => 'relevance',
        ],
        'ItemGlobalOrder' => [],
    ];

    /**
     * Generate a new Eds driver to return responses set in a json fixture
     *
     * Overwrites $this->driver
     * Uses session cache
     *
     * @param string $test   Name of test fixture to load
     * @param array  $config Driver configuration (null to use default)
     *
     * @return EDS
     */
    protected function getDriver(string $test = null, array $config = null): EDS
    {
        $record = new EDS(null, new \Laminas\Config\Config($config ?? $this->defaultDriverConfig));
        if (null !== $test) {
            $json = $this->getJsonFixture('eds/' . $test . '.json');
            $record->setRawData($json);
        }
        return $record;
    }

    /**
     * Test getUniqueID for a record.
     *
     * @return void
     */
    public function testGetUniqueID(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('edsgob,edsgob.14707011', $driver->getUniqueID());
    }

    /**
     * Test getShortTitle for a record.
     *
     * @return void
     */
    public function testGetShortTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('METAPHOR IN PRACTICE', $driver->getShortTitle());
    }

    /**
     * Test getShortTitle for a record with no title.
     *
     * @return void
     */
    public function testGetShortTitleWhenNoTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('', $driver->getShortTitle());
    }

    /**
     * Test getSubtitle for a record.
     *
     * @return void
     */
    public function testGetSubtitle(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.', $driver->getSubtitle());
    }

    /**
     * Test getSubtitle for a record when there is no title field.
     *
     * @return void
     */
    public function testGetSubtitleWhenNoTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('', $driver->getSubtitle());
    }

    /**
     * Test getItemsAbstract for a record.
     *
     * @return void
     */
    public function testGetItemsAbstract(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('unit test abstract', $driver->getItemsAbstract());
    }

    /**
     * Test getAccessLevel for a record.
     *
     * @return void
     */
    public function testGetAccessLevel(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('', $driver->getAccessLevel());
    }

    /**
     * Test getItemsAuthors for a record.
     *
     * @return void
     */
    public function testGetItemsAuthors(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            $driver->getItemsAuthors()
        );
    }

    /**
     * Test getCustomLinks for a record.
     *
     * @return void
     */
    public function testGetCustomLinks(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $record = [
            [
                'Url' => 'customlink-unittest/edsgob.14707011',
                'Name' => 'Custom Link (s8364774)',
                'Category' => 'custom link',
                'Text' => 'Unit test custom link',
                'Icon' => 'https://imageserver.ebscohost.com/branding/images/FTF.gif',
                'MouseOverText' => 'Exciting text',
            ],
        ];
        $this->assertEquals($record, $driver->getCustomLinks());
    }

    /**
     * Test getFTCustomLinks for a record.
     *
     * @return void
     */
    public function testGetFTCustomLinks(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $record = [
            [
                'Url' => 'customlink-fulltext-unittest/edsgob.14707011/&pages=1-10/',
                'Name' => 'Full Text Finder (s8364774)',
                'Category' => 'fullText',
                'Text' => 'Full Text Finder',
                'Icon' => 'https://imageserver.ebscohost.com/branding/images/FTF.gif',
                'MouseOverText' => 'Full Text Finder',
            ],
        ];
        $this->assertEquals($record, $driver->getFTCustomLinks());
    }

    /**
     * Test getDbLabel for a record.
     *
     * @return void
     */
    public function testGetDbLabel(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('GOBI E-books', $driver->getDbLabel());
    }

    /**
     * Test getHTMLFullText for a record.
     *
     * @return void
     */
    public function testGetHTMLFullText(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('This is some wonderful full text', $driver->getHTMLFullText());
    }

    /**
     * Test hasHTMLFullTextAvailable for a record.
     *
     * @return void
     */
    public function testHasHTMLFullTextAvailable(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertTrue($driver->hasHTMLFullTextAvailable());
    }

    /**
     * Test getItems for a record.
     *
     * @return void
     */
    public function testGetItems(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $items = [
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'METAPHOR IN PRACTICE: A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.',
            ],
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            ],
            [
                'Name' => 'Publisher',
                'Label' => 'Publisher Information',
                'Group' => 'PubInfo',
                'Data' => 'OAKLAND: NEW HARBINGER PUB, 2017.',
            ],
        ];
        $results = $driver->getItems();

        // Verify total number of metadata elements
        $this->assertCount(11, $results);
        // Verify contents of the first 3 elements
        $this->assertEquals($items, array_slice($results, 0, 3));
    }

    /**
     * Test getItems sorting the data for a record.
     *
     * @return void
     */
    public function testGetItemsSorted(): void
    {
        // Change the default order the array data is in and exclude one of the items
        // to ensure it appears at the end
        $config = $this->defaultDriverConfig;
        $config['ItemGlobalOrder']['1'] = 'Authors';
        $config['ItemGlobalOrder']['2'] = 'Title';

        $driver = $this->getDriver('valid-eds-record', $config);
        $items = [
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            ],
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'METAPHOR IN PRACTICE: A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.',
            ],
            [
                'Name' => 'Publisher',
                'Label' => 'Publisher Information',
                'Group' => 'PubInfo',
                'Data' => 'OAKLAND: NEW HARBINGER PUB, 2017.',
            ],
        ];
        $results = $driver->getItems();

        // Verify total number of metadata elements
        $this->assertCount(11, $results);
        // Verify contents of the first 3 elements
        $this->assertEquals($items, array_slice($results, 0, 3));
    }

    /**
     * Test getItems filtering the data for a record.
     *
     * @return void
     */
    public function testGetItemsFilteredCore(): void
    {
        // Change the default order the array data is in and exclude one of the items
        // to ensure it appears at the end
        $config = $this->defaultDriverConfig;
        $config['ItemCoreFilter']['excludeLabel'][] = 'Title';

        $driver = $this->getDriver('valid-eds-record', $config);
        $items = [
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            ],
            [
                'Name' => 'Publisher',
                'Label' => 'Publisher Information',
                'Group' => 'PubInfo',
                'Data' => 'OAKLAND: NEW HARBINGER PUB, 2017.',
            ],
        ];
        $results = $driver->getItems('core');

        // Verify total number of metadata elements
        // (Note one is removed from the fixture file since it has been filtered)
        $this->assertCount(10, $results);
        // Verify contents of the first 2 elements
        $this->assertEquals($items, array_slice($results, 0, 2));
    }

    /**
     * Test getItems filtering the data for a record.
     *
     * @return void
     */
    public function testGetItemsFilteredResultList(): void
    {
        // Change the default order the array data is in and exclude one of the items
        // to ensure it appears at the end
        $config = $this->defaultDriverConfig;
        $config['ItemResultListFilter']['excludeLabel'][] = 'Title';

        $driver = $this->getDriver('valid-eds-record', $config);
        $items = [
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            ],
            [
                'Name' => 'Publisher',
                'Label' => 'Publisher Information',
                'Group' => 'PubInfo',
                'Data' => 'OAKLAND: NEW HARBINGER PUB, 2017.',
            ],
        ];
        $results = $driver->getItems('result-list');

        // Verify total number of metadata elements
        // (Note one is removed from the fixture file since it has been filtered)
        $this->assertCount(10, $results);
        // Verify contents of the first 2 elements
        $this->assertEquals($items, array_slice($results, 0, 2));
    }

    /**
     * Test getItems when invalid data is returned from EDS (i.e. not in the structure
     * VuFind expected)
     *
     * @return void
     */
    public function testGetItemsWithInvalidConfig(): void
    {
        $config = $this->defaultDriverConfig;
        $config['ItemGlobalOrder']['invalid'] = null;

        $driver = $this->getDriver('valid-eds-record', $config);

        // items in original order are returned when the config can't be parsed
        $items = [
            [
                'Name' => 'Title',
                'Label' => 'Title',
                'Group' => 'Ti',
                'Data' => 'METAPHOR IN PRACTICE: A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.',
            ],
            [
                'Name' => 'Author',
                'Label' => 'Authors',
                'Group' => 'Au',
                'Data' => '<a href="../EDS/Search?lookfor=%22TORNEKE%2C+NIKLAS%2E%22&amp;type=AU">TORNEKE, NIKLAS.</a>',
            ],
            [
                'Name' => 'Publisher',
                'Label' => 'Publisher Information',
                'Group' => 'PubInfo',
                'Data' => 'OAKLAND: NEW HARBINGER PUB, 2017.',
            ],
        ];
        $results = $driver->getItems();

        // Verify total number of metadata elements
        $this->assertCount(11, $results);
        // Verify contents of the first 3 elements
        $this->assertEquals($items, array_slice($results, 0, 3));
    }

    /**
     * Test getPLink for a record.
     *
     * @return void
     */
    public function testGetPLink(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('plink-unittest/edsgob.14707011', $driver->getPLink());
    }

    /**
     * Test getPubType for a record.
     *
     * @return void
     */
    public function testGetPubType(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('eBook', $driver->getPubType());
    }

    /**
     * Test getPubTypeId for a record.
     *
     * @return void
     */
    public function testGetPubTypeId(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('ebook', $driver->getPubTypeId());
    }

    /**
     * Test hasPdfAvailable for a record.
     *
     * @return void
     */
    public function testHasPdfAvailable(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertTrue($driver->hasPdfAvailable());
    }

    /**
     * Test hasPdfAvailable for a record when none is.
     *
     * @return void
     */
    public function testHasPdfAvailableReturningFalse(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertFalse($driver->hasPdfAvailable());
    }

    /**
     * Test hasEpubAvailable for a record.
     *
     * @return void
     */
    public function testHasEpubAvailable(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertTrue($driver->hasEpubAvailable());
    }

    /**
     * Test hasLinkedFullTextAvailable for a record.
     *
     * @return void
     */
    public function testHasLinkedFullTextAvailable(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertTrue($driver->hasLinkedFullTextAvailable());
    }

    /**
     * Test getPdfLink for a record.
     *
     * @return void
     */
    public function testGetPdfLink(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('pdf ebook url test', $driver->getPdfLink());
    }

    /**
     * Test getEbookLink for a record.
     *
     * @return void
     */
    public function testGetEbookLinkNoData(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertFalse($driver->getEbookLink(['ebook-pdf']));
    }

    /**
     * Test getEpubLink for a record.
     *
     * @return void
     */
    public function testGetEpubLink(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('epub url test', $driver->getEpubLink());
    }

    /**
     * Test getLinkedFullTextLink for a record.
     *
     * @return void
     */
    public function testGetLinkedFullTextLink(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('linked full text url test', $driver->getLinkedFullTextLink());
    }

    /**
     * Test getAllSubjectHeadingsFlattened for a record.
     *
     * @return void
     */
    public function testGetAllSubjectHeadingsFlattened(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            [
                'PSYCHOTHERAPY',
                'METAPHOR',
            ],
            $driver->getAllSubjectHeadingsFlattened()
        );
    }

    /**
     * Test getThumbnail for a record.
     *
     * @return void
     */
    public function testGetThumbnail(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('thumbnail link', $driver->getThumbnail());
    }

    /**
     * Test getThumbnail for a record that has no image data.
     *
     * @return void
     */
    public function testGetThumbnailWhenNoneReturned(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertFalse($driver->getThumbnail());
    }

    /**
     * Test getItemsTitle for a record.
     *
     * @return void
     */
    public function testGetItemsTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            'METAPHOR IN PRACTICE: A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.',
            $driver->getItemsTitle()
        );
    }

    /**
     * Test getTitle for a record.
     *
     * @return void
     */
    public function testGetTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            'METAPHOR IN PRACTICE: A PROFESSIONAL\'S GUIDE TO USING THE SCIENCE OF LANGUAGE.',
            $driver->getTitle()
        );
    }

    /**
     * Test getPrimaryAuthors for a record.
     *
     * @return void
     */
    public function testGetPrimaryAuthors(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(['TORNEKE, NIKLAS.'], $driver->getPrimaryAuthors());
    }

    /**
     * Test getItemsTitleSource for a record.
     *
     * @return void
     */
    public function testGetItemsTitleSource(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('unit test source', $driver->getItemsTitleSource());
    }

    /**
     * Data provider for testLinkUrls
     *
     * @return array
     */
    public static function getLinkUrlsProvider(): array
    {
        return [
            [
                'http://localhost/sample1',
                '<a href=\'http://localhost/sample1\'>http://localhost/sample1</a>',
            ],
            [
                '<link linkTarget="URL" linkTerm="https://localhost/sample"'
                    . ' linkWindow="_blank">https://localhost/sample</link>',
                '<a href=\'https://localhost/sample\'>https://localhost/sample</a>',
            ],
        ];
    }

    /**
     * Test linkUrls for a record.
     *
     * @param string $url      Input URL
     * @param string $expected Expected value
     *
     * @dataProvider getLinkUrlsProvider
     *
     * @return void
     */
    public function testLinkUrls(string $url, string $expected): void
    {
        $driver = $this->getDriver();
        $this->assertEquals($expected, $driver->linkUrls($url));
    }

    /**
     * Test getCleanDOI for a record.
     *
     * @return void
     */
    public function testGetCleanDOI(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('unit test DOI', $driver->getCleanDOI());
    }

    /**
     * Test getCleanDOI for a record when DOI is in bib data.
     *
     * @return void
     */
    public function testGetCleanDOIFromBibData(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('doi-test', $driver->getCleanDOI());
    }

    /**
     * Test getLanguages for a record.
     *
     * @return void
     */
    public function testGetLanguages(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(['English'], $driver->getLanguages());
    }

    /**
     * Test getISSNs.
     *
     * @return void
     */
    public function testGetISSNs(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            ['123456789'],
            $driver->getISSNs()
        );
    }

    /**
     * Test getISBNs.
     *
     * @return void
     */
    public function testGetISBNs(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(
            ['9781626259027'],
            $driver->getISBNs()
        );
    }

    /**
     * Test getContainerTitle for a record.
     *
     * @return void
     */
    public function testGetContainerTitleNoContainer(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('', $driver->getContainerTitle());
    }

    /**
     * Test getContainerTitle for a record.
     *
     * @return void
     */
    public function testGetContainerTitle(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('A really cool collection', $driver->getContainerTitle());
    }

    /**
     * Test getContainerIssue for a record when there is no data.
     *
     * @return void
     */
    public function testGetContainerIssueWhenEmpty(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('', $driver->getContainerIssue());
    }

    /**
     * Test getContainerIssue for a record.
     *
     * @return void
     */
    public function testGetContainerIssue(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('1', $driver->getContainerIssue());
    }

    /**
     * Test getContainerVolume for a record when there is none.
     *
     * @return void
     */
    public function testGetContainerVolumeWhenEmpty(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('', $driver->getContainerVolume());
    }

    /**
     * Test getContainerVolume for a record.
     *
     * @return void
     */
    public function testGetContainerVolume(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals('2', $driver->getContainerVolume());
    }

    /**
     * Test getPublicationDates for a record.
     *
     * @return void
     */
    public function testGetPublicationDates(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals(['2017'], $driver->getPublicationDates());
    }

    /**
     * Test getContainerStartPage for a record.
     *
     * @return void
     */
    public function testGetContainerStartPage(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('1', $driver->getContainerStartPage());
    }

    /**
     * Test getContainerEndPage for a record.
     *
     * @return void
     */
    public function testGetContainerEndPage(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals('10', $driver->getContainerEndPage());
    }

    /**
     * Test getContainerEndPage for a record with page data available.
     *
     * @return void
     */
    public function testGetContainerEndPageNoData(): void
    {
        $driver = $this->getDriver('no-container-end-page');
        $this->assertEquals('', $driver->getContainerEndPage());
    }

    /**
     * Test getFormats for an ebook record.
     *
     * @return void
     */
    public function testGetFormatsEbook(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $this->assertEquals(['Book', 'Electronic'], $driver->getFormats());
    }

    /**
     * Test getFormats for an article record.
     *
     * @return void
     */
    public function testGetFormatsArticle(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals(['report', 'Article'], $driver->getFormats());
    }

    /**
     * Test getFormats for a dissertation record.
     *
     * @return void
     */
    public function testGetFormatsDissertation(): void
    {
        $driver = $this->getDriver('dissertation-record');
        $this->assertEquals(['Thesis'], $driver->getFormats());
    }

    /**
     * Test getFormats for a unidentified format record.
     *
     * @return void
     */
    public function testGetFormatsOtherFormat(): void
    {
        $driver = $this->getDriver('invalid-pubformat-record');
        $this->assertEquals(['unit-test-format'], $driver->getFormats());
    }

    /**
     * Test getPublishers for a record.
     *
     * @return void
     */
    public function testGetPublishers(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals(['Here'], $driver->getPublishers());
    }

    /**
     * Test getPlacesOfPublication for a record.
     *
     * @return void
     */
    public function testGetPlacesOfPublication(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $this->assertEquals(['US'], $driver->getPlacesOfPublication());
    }

    /**
     * Test getPublicationDetails for a record.
     *
     * @return void
     */
    public function testGetPublicationDetails(): void
    {
        $driver = $this->getDriver('valid-eds-record-2');
        $details = new \VuFind\RecordDriver\Response\PublicationDetails('US', 'Here', 2017);
        $this->assertEquals([$details], $driver->getPublicationDetails());
    }

    /**
     * Test getPublicationDetails for a record with another data format.
     *
     * @return void
     */
    public function testGetPublicationDetailsNoDate(): void
    {
        $driver = $this->getDriver('publication-details-no-date');
        $details = new \VuFind\RecordDriver\Response\PublicationDetails('USA', 'Test', '');
        $this->assertEquals([$details], $driver->getPublicationDetails());
    }

    /**
     * Test getPublicationDetails for a record with an unmatched format.
     *
     * @return void
     */
    public function testGetPublicationDetailsUnMatchedFormat(): void
    {
        $driver = $this->getDriver('publication-details-unmatched-format');
        $details = new \VuFind\RecordDriver\Response\PublicationDetails('', 'Test Information', '');
        $this->assertEquals([$details], $driver->getPublicationDetails());
    }

    /**
     * Test getPublicationDetails for a record from the bib record.
     *
     * @return void
     */
    public function testGetPublicationDetailsFromBib(): void
    {
        $driver = $this->getDriver('valid-eds-record');
        $details = new \VuFind\RecordDriver\Response\PublicationDetails('', '', 2017);
        $this->assertEquals([$details], $driver->getPublicationDetails());
    }

    /**
     * Test extractEbscoData for an undefined method.
     *
     * @return void
     */
    public function testExtractEbscoDataUndefinedMethod(): void
    {
        $driver = $this->getDriver('valid-eds-record');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Undefined method: ');
        $this->assertFalse($this->callMethod($driver, 'extractEbscoData', [['invalid-method:invalid-path']]));
    }
}
