<?php

/**
 * Mink channels test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use Exception;
use PHPUnit\Framework\ExpectationFailedException;
use VuFindTest\Feature\DemoDriverTestTrait;

/**
 * Mink channels test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ChannelsTest extends \VuFindTest\Integration\MinkTestCase
{
    use DemoDriverTestTrait;

    /**
     * Selector for finding a complete channel.
     *
     * @var string
     */
    protected $channelSelector = 'div.channel';

    /**
     * Get a reference to a standard channels home page.
     *
     * @return Element
     */
    protected function getChannelsHomePage(): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Channels/Home');
        return $session->getPage();
    }

    /**
     * Get a reference to a standard record-based results page.
     *
     * @param string $id Record to look up
     *
     * @return Element
     */
    protected function getChannelsRecordPage(string $id = 'testsample1'): Element
    {
        $session = $this->getMinkSession();
        $path = '/Channels/Record?id=' . urlencode($id);
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Get a reference to a standard search results page.
     *
     * @param string $q Search to perform on Channels page
     *
     * @return Element
     */
    protected function getChannelsSearchPage(string $q = 'building:"weird_ids.mrc"'): Element
    {
        $session = $this->getMinkSession();
        $path = '/Channels/Search?lookfor=' . urlencode($q);
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Make sure the record page works, channels exists, search
     *
     * @return void
     */
    public function testBasicRecord(): void
    {
        $id = 'testsample1';
        $page = $this->getChannelsRecordPage($id);
        // Channels are here
        $this->findCss($page, $this->channelSelector);
        // Check number of channels
        $channels = $page->findAll('css', $this->channelSelector);
        $this->assertCount(4, $channels);
        // Make sure appropriate similar records are displayed:
        $this->assertEquals(
            'Similar Items: Journal of rational emotive therapy :',
            $this->findCssAndGetText($page, 'h2.channel-title')
        );
        // Similar record drop-down menu contains appropriate view record link:
        $link = $this->findCss($page, '.channel-options a');
        $this->assertEquals('View Record', $link->getText());
        $this->assertStringEndsWith("/$id", $link->getAttribute('href'));
    }

    /**
     * Make sure the search page works, channels exists, search
     *
     * @return void
     */
    public function testBasicSearch(): void
    {
        $page = $this->getChannelsSearchPage();
        // Channels are here
        $this->findCss($page, $this->channelSelector);
        // Check number of channels
        $channels = $page->findAll('css', $this->channelSelector);
        $this->assertCount(6, $channels);
        // Make sure search input matches url
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCssAndGetValue($page, '[action*="Channels/Search"] .form-control')
        );
    }

    /**
     * Add channels button
     *
     * @return void
     */
    public function testAddChannels(): void
    {
        $page = $this->getChannelsSearchPage();
        $channel = $this->findCss($page, $this->channelSelector);
        // Initial counts
        $this->assertCount(6, $page->findAll('css', $this->channelSelector));
        $this->assertCount(8, $channel->findAll('css', '.channel-add-link'));
        // Click first add button
        $this->clickCss($channel, '.channel-add-more-btn');
        // Post count
        $this->waitStatement('$("div.channel").length === 8');
        $this->waitStatement('$(".channel-add-menu:first .channel-add-link").length === 6');
        $this->assertCount(8, $page->findAll('css', $this->channelSelector));
        $this->assertCount(6, $channel->findAll('css', '.channel-add-link'));
        // Click last add button (and assert that it's the one we expect it to be)
        $lastChannel = $this->findCss($page, 'div.channel', index: 7);
        $this->assertEquals('Similar Items: Movie Quotes Thru The Ages', $lastChannel->find('css', 'h2')->getText());
        $this->clickCss($lastChannel, '.channel-add-more-btn');
        // Post count
        $this->waitStatement('$("div.channel").length === 10');
        $this->assertCount(10, $page->findAll('css', $this->channelSelector));
    }

    /**
     * Switch to search
     *
     * @return void
     */
    public function testSwitchToSearch(): void
    {
        $page = $this->getChannelsSearchPage();
        $channel = $this->findCss($page, $this->channelSelector);
        // Click options dropdown to display links
        $this->clickCss($channel, '.channel-options');
        // Click link to go to search results
        $this->clickCss($channel, '.channel-options .fa-search');
        // Make sure the search translated
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCssAndGetValue($page, '#searchForm_lookfor')
        );
        // Check facet
        $this->assertEquals(
            'Suggested Topics:',
            $this->findCssAndGetText($page, '.filters .filters-title')
        );
        $this->assertEquals(
            'Remove Filter Adult children of aging parents',
            $this->findCssAndGetText($page, '.filters .filter-value')
        );
    }

    /**
     * Data provider for testPopovers
     *
     * @return array
     */
    public static function popoversProvider(): array
    {
        return [
            'different records (weird IDs)' => [
                'building:"weird_ids.mrc"',
                'hashes#coming@ya',
                'Octothorpes: Why not?',
                'dollar$ign/slashcombo',
                'Of Money and Slashes',
                null,
            ],
            'same record in two channels' => [
                'id:017791359-1',
                '017791359-1',
                'Fake Record 1 with multiple relators/',
                '017791359-1',
                'Fake Record 1 with multiple relators/',
                1,
            ],
        ];
    }

    /**
     * Assert that the popover contents contain the expected title and description; return the
     * contents string.
     *
     * @param Element $page          Page element
     * @param string  $expectedTitle Expected title for popover
     *
     * @return string
     * @throws Exception
     * @throws ExpectationFailedException
     */
    protected function assertPopoverTitleAndDescription(Element $page, string $expectedTitle): string
    {
        // Ensure that any "Loading..." popover is not being displayed:
        $this->waitForPageLoad($page);
        $popoverContents = $this->findCssAndGetText($page, '.channels-quick-look');
        // The popover should contain an appropriate title and metadata:
        $this->assertStringContainsString($expectedTitle, $popoverContents);
        $this->assertStringContainsString('Description', $popoverContents);
        // Click outside of channels to move the focus away:
        $this->clickCss($page, 'li.active');
        return $popoverContents;
    }

    /**
     * Test popover behavior by clicking back and forth between two records
     *
     * @param string $query               Search query
     * @param string $record1             ID of first record
     * @param string $title1              Title of first record
     * @param string $record2             ID of second record
     * @param string $title2              Title of second record
     * @param ?int   $record2ChannelIndex Index of channel containing second record (needed when $record1 === $record2)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('popoversProvider')]
    public function testQuickLookPopovers(
        string $query,
        string $record1,
        string $title1,
        string $record2,
        string $title2,
        ?int $record2ChannelIndex
    ): void {
        $page = $this->getChannelsSearchPage($query);
        // Click a record to open the popover:
        $this->clickCss($page, '.channel-item[data-record-id="' . $record1 . '"] .channel-quick-look-btn');
        // The popover should contain an appropriate title and metadata:
        $popoverContents = $this->assertPopoverTitleAndDescription($page, $title1);
        // Click a different record (or the second instance of the same record, if that's what we're testing):
        $title2Target = $record2ChannelIndex === null
            ? $page : $this->findCss($page, '.channel', index: $record2ChannelIndex);
        $this->clickCss($title2Target, '.channel-item[data-record-id="' . $record2 . '"] .channel-quick-look-btn');
        $this->assertPopoverTitleAndDescription($page, $title2);
        // Now click back to the original record; the popover should contain the same contents.
        $this->clickCss($page, '.channel-item[data-record-id="' . $record1 . '"] .channel-quick-look-btn');
        $popoverContents3 = $this->findCssAndGetText($page, '.channels-quick-look');
        $this->assertEquals($popoverContents, $popoverContents3);
        // Finally, click through to the record page.
        $link = $this->findCss($page, '.ql-view-record-btn');
        $this->assertEquals('View Record', $link->getText());
        $link->click();
        $this->waitForPageLoad($page);
        $this->assertEquals($title1, $this->findCssAndGetText($page, 'h1'));
    }

    /**
     * Data provider for testILSChannel().
     *
     * @return array[]
     */
    public static function ilsChannelProvider(): array
    {
        return [
            'New ILS Items' => ['newilsitems', 'New Items'],
            'Recently Returned' => ['recentlyreturned', 'Recently Returned'],
            'Trending ILS Items' => ['trendingilsitems', 'Trending Items'],
        ];
    }

    /**
     * Test ILS-powered channels
     *
     * @param string $channel       Name of channel to test
     * @param string $expectedTitle Expected channel title
     * @param string $bibId1        First test record to include in channel
     * @param string $bibId2        Second test record to include in channel
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ilsChannelProvider')]
    public function testILSChannel(
        string $channel,
        string $expectedTitle,
        string $bibId1 = 'testsample1',
        string $bibId2 = 'testsample2'
    ): void {
        $this->changeConfigs(
            [
                'channels' => [
                    'General' => [
                        'cache_home_channels' => false,
                    ],
                    'source.Solr' => [
                        'home' => [$channel],
                    ],
                ],
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                ],
                'Demo' => $this->getDemoIniOverrides($bibId1, $bibId2),
            ],
        );
        $page = $this->getChannelsHomePage();
        $this->assertEquals($expectedTitle, $this->findCssAndGetText($page, 'h2.channel-title'));
        $this->assertCount(2, $page->findAll('css', 'li.channel-item'));
        $this->assertCount(1, $page->findAll('css', 'li.channel-item[data-record-id="' . $bibId1 . '"]'));
        $this->assertCount(1, $page->findAll('css', 'li.channel-item[data-record-id="' . $bibId2 . '"]'));
    }

    /**
     * Test Solr-powered new items channel
     *
     * @return void
     */
    public function testNewItemsChannel(): void
    {
        $this->changeConfigs(
            [
                'channels' => [
                    'General' => [
                        'cache_home_channels' => false,
                    ],
                    'source.Solr' => [
                        'home' => ['newsearchitems'],
                    ],
                ],
            ],
        );
        $page = $this->getChannelsHomePage();
        $this->assertEquals('New Items', $this->findCssAndGetText($page, 'h2.channel-title'));
        // In case test data changes, we won't make specific assertions about specific records,
        // but we can assume that we'll get at least two pages worth of them!
        $this->assertCount(6, $page->findAll('css', 'li.channel-item:not(.hidden-batch-item)'));
        $this->clickCss($page, '.channel-load-more-btn');
        $this->assertCount(12, $page->findAll('css', 'li.channel-item:not(.hidden-batch-item)'));
    }

    /**
     * Test Random channel
     *
     * @return void
     */
    public function testRandomChannel(): void
    {
        $this->changeConfigs(
            [
                'channels' => [
                    'General' => [
                        'cache_home_channels' => false,
                    ],
                    'source.Solr' => [
                        'home' => ['random'],
                    ],
                ],
            ],
        );
        $page = $this->getChannelsHomePage();
        $this->assertEquals('Random items from your results', $this->findCssAndGetText($page, 'h2.channel-title'));
        // Since selected records are random, we can't make specific assertions about specific records,
        // but we can assume that we'll get at least four pages worth of them!
        $this->assertCount(6, $page->findAll('css', 'li.channel-item:not(.hidden-batch-item)'));
        $this->clickCss($page, '.channel-load-more-btn');
        $this->clickCss($page, '.channel-load-more-btn');
        $this->clickCss($page, '.channel-load-more-btn');
        $this->assertCount(24, $page->findAll('css', 'li.channel-item:not(.hidden-batch-item)'));
    }

    /**
     * Test deep pagination of Facets channel
     *
     * @return void
     */
    public function testDeepPaginationOfFacetsChannel(): void
    {
        $this->changeConfigs(
            [
                'channels' => [
                    'General' => [
                        'cache_home_channels' => false,
                    ],
                    'source.Solr' => [
                        'home' => ['facets:provider.facets.home'],
                    ],
                    'provider.facets.home' => [
                        'maxFieldsToSuggest' => 2,
                        'maxValuesToSuggestPerField' => 1,
                    ],
                ],
                'searches' => [
                    // Filter to specific records to ensure predictable results:
                    'RawHiddenFilters' => ['building:geo.mrc'],
                ],
            ],
        );
        $page = $this->getChannelsHomePage();
        $channel = $this->findCss($page, 'div.channel', index: 1);
        $this->assertEquals('Format: Book Chapter', $this->findCssAndGetText($channel, 'h2.channel-title'));
        // Let's get more than 48 items on the page to ensure that we call back to the server for more results:
        $this->assertCount(6, $channel->findAll('css', 'li.channel-item:not(.hidden-batch-item)'));
        for ($i = 0; $i < 8; $i++) {
            $button = $this->findCss($channel, '.channel-load-more-btn');
            $button->click();
            $this->waitForPageLoad($page);
            // Confirm that the button's labels remain appropriate after clicks:
            $dataHref = $button->getAttribute('data-href');
            $selector = 'button[data-href="' . $dataHref . '"]';
            $js = "false === document.querySelector('$selector').textContent.startsWith('Loading')";
            $this->waitStatement($js);
            $this->assertEquals('Load more items', $button->getText());
            $this->assertEquals('Load more items into Format: Book Chapter', $button->getAttribute('aria-label'));
        }
        // Make sure that we not only have the expected number of items but also that they all have different
        // IDs. (This prevents regression of a bug where the same page of results got loaded multiple times).
        $allItems = $channel->findAll('css', 'li.channel-item:not(.hidden-batch-item)');
        $allIds = array_unique(array_map(fn ($item) => $item->getAttribute('data-record-id'), $allItems));
        $this->assertCount(54, $allItems);
        $this->assertCount(54, $allIds);
    }
}
