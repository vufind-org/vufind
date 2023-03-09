<?php
/**
 * SolrDefault Record Driver Test Class
 *
 * PHP version 7
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
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\SolrDefault;

/**
 * SolrDefault Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrDefaultTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test an OpenURL for a book.
     *
     * @return void
     */
    public function testBookOpenURL()
    {
        $driver = $this->getDriver();
        $this->assertEquals(
            $this->getFixture('openurl/book'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test a snippet caption.
     *
     * @return void
     */
    public function testGetSnippetCaption()
    {
        $config = ['Snippet_Captions' => ['foo' => 'bar']];
        $driver = $this->getDriver([], $config);
        $this->assertEquals('bar', $driver->getSnippetCaption('foo'));
    }

    /**
     * Test an OpenURL for an article.
     *
     * @return void
     */
    public function testArticleOpenURL()
    {
        $overrides = [
            'format' => ['Article'],
            'container_title' => 'Fake Container',
            'container_volume' => 'XVII',
            'container_issue' => '6',
            'container_start_page' => '12',
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals(
            $this->getFixture('openurl/article'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test an OpenURL for a journal.
     *
     * @return void
     */
    public function testJournalOpenURL()
    {
        $overrides = [
            'format' => ['Journal'],
            'issn' => ['1234-5678'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals(
            $this->getFixture('openurl/journal'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test an OpenURL for an unknown material type with no ISBN or ISSN
     *
     * @return void
     */
    public function testUnknownTypeOpenURL()
    {
        $overrides = [
            'format' => ['Thingie'],
            'isbn' => [],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals(
            $this->getFixture('openurl/unknown'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test an OpenURL for an unknown material type with only ISBNs
     *
     * @return void
     */
    public function testUnknownTypeOnlyISBNsOpenURL()
    {
        $overrides = [
            'format' => ['Thingie'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals(
            $this->getFixture('openurl/unknown-isbn'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test an OpenURL for an unknown material type with both ISBN and ISSN
     *
     * @return void
     */
    public function testUnknownTypeBothISBNsandISSNsOpenURL()
    {
        $overrides = [
            'format' => ['Thingie'],
            'issn' => ['1234-5678'],
        ];
        $driver = $this->getDriver($overrides);
        $this->assertEquals(
            $this->getFixture('openurl/unknown-isbn-issn'),
            $driver->getOpenUrl()
        );
    }

    /**
     * Test Dublin Core conversion.
     *
     * @return void
     */
    public function testDublinCore()
    {
        $expected = $this->getFixture('oai/dc.xml');
        $xml = $this->getDriver()->getXML('oai_dc');
        $this->assertEquals($expected, $xml);
    }

    /**
     * Test getContainerRecordID for a record.
     *
     * @return void
     */
    public function testGetContainerRecordID()
    {
        $this->assertEquals("", $this->getDriver()->getContainerRecordID());
    }

    /**
     * Test getChildRecordCount for a record.
     *
     * @return void
     */
    public function testGetChildRecordCount()
    {
        $this->assertEquals(0, $this->getDriver()->getChildRecordCount());
    }

    /**
     * Test getHighlightedTitle for a record.
     *
     * @return void
     */
    public function testGetHighlightedTitle()
    {
        $this->assertEquals("", $this->getDriver()->getHighlightedTitle());
    }

    /**
     * Test getHighlightedSnippet for a record.
     *
     * @return void
     */
    public function testGetHighlightedSnippet()
    {
        $this->assertEquals(false, $this->getDriver()->getHighlightedSnippet());
    }

    /**
     * Test HighlightDetails for a record.
     *
     * @return void
     */
    public function testHighlightDetails()
    {
        $details = ['author' => 'test'];
        $driver = $this->getDriver();
        $driver->setHighlightDetails($details);
        $this->assertEquals($details, $driver->getHighlightDetails());
    }

    /**
     * Test getRawAuthorHighlights for a record.
     *
     * @return void
     */
    public function testGetRawAuthorHighlights()
    {
        $this->assertEquals([], $this->getDriver()->getRawAuthorHighlights());
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides    Fixture fields to override.
     * @param array $searchConfig Search configuration.
     *
     * @return SolrDefault
     */
    protected function getDriver($overrides = [], $searchConfig = [])
    {
        $fixture = $this->getJsonFixture('misc/testbug2.json');
        $record = new SolrDefault(null, null, new \Laminas\Config\Config($searchConfig));
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }
}
