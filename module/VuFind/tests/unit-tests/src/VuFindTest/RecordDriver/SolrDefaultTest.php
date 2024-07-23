<?php

/**
 * SolrDefault Record Driver Test Class
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
        $this->assertEquals('', $this->getDriver()->getContainerRecordID());
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
        $this->assertEquals('', $this->getDriver()->getHighlightedTitle());
    }

    /**
     * Test getHighlightedSnippet for an empty record.
     *
     * @return void
     */
    public function testEmptyGetHighlightedSnippet()
    {
        $this->assertEquals(false, $this->getDriver()->getHighlightedSnippet());
    }

    /**
     * Test getHighlightedSnippet for a record when empty snippet data is given.
     *
     * @return void
     */
    public function testGetHighlightedSnippetAllEmpty()
    {
        $overrides = [
            'General' => ['snippets' => true],
        ];
        $driver = $this->getDriver([], $overrides);
        $details = ['topic' => ['']];
        $driver->setHighlightDetails($details);
        $this->assertEquals(false, $driver->getHighlightedSnippet());
    }

    /**
     * Test getHighlightedSnippet for a record when the first snippet is empty.
     *
     * @return void
     */
    public function testGetHighlightedSnippetFirstEmpty()
    {
        $overrides = [
            'General' => ['snippets' => true],
        ];
        $driver = $this->getDriver([], $overrides);
        // Note that the first topic result is empty, it should return the first non-empty one
        $details = ['topic' => ['', 'Testing {{{{START_HILITE}}}}Snippets{{{{END_HILITE}}}} highlighting']];
        $driver->setHighlightDetails($details);
        $this->assertEquals(
            ['snippet' => 'Testing {{{{START_HILITE}}}}Snippets{{{{END_HILITE}}}} highlighting', 'caption' => false],
            $driver->getHighlightedSnippet()
        );
    }

    /**
     * Test getHighlightedSnippet for a record when multiple preferred snippet fields exist.
     *
     * @return void
     */
    public function testGetHighlightedSnippetInPreferredFieldOrder()
    {
        $overrides = [
            'General' => ['snippets' => true],
        ];
        $driver = $this->getDriver([], $overrides);
        $details = [
            'topic' => ['', 'Testing topic {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}'],
            'contents' => ['Testing content {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}'],
        ];
        $driver->setHighlightDetails($details);
        // Should return the snippet from contents since that is the first item in preferredSnippetFields
        $this->assertEquals(
            ['snippet' => 'Testing content {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}', 'caption' => false],
            $driver->getHighlightedSnippet()
        );
    }

    /**
     * Test getHighlightedSnippet for a record when no preferred snippet fields exist.
     *
     * @return void
     */
    public function testGetHighlightedSnippetNonForbiddenField()
    {
        $overrides = [
            'General' => ['snippets' => true],
        ];
        $driver = $this->getDriver([], $overrides);
        $details = [
            'author' => ['', 'Testing author {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}'],
            'toast' => ['Testing toast {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}'],
        ];
        $driver->setHighlightDetails($details);
        // Should ignore the 'author' snippet since that is forbidden, and return 'toast' instead
        $this->assertEquals(
            ['snippet' => 'Testing toast {{{{START_HILITE}}}}snippet{{{{END_HILITE}}}}', 'caption' => false],
            $driver->getHighlightedSnippet()
        );
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
