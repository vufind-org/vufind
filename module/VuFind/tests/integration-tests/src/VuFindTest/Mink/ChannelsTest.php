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
     * Get a reference to a standard search results page.
     *
     * @param string $q Search to perform on Channels page
     *
     * @return Element
     */
    protected function getChannelsPage(string $q = 'building:"weird_ids.mrc"'): Element
    {
        $session = $this->getMinkSession();
        $path = '/Channels/Search?lookfor=' . urlencode($q);
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Make sure the page works, channels exists, search
     *
     * @return void
     */
    public function testBasic(): void
    {
        $page = $this->getChannelsPage();
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
        $page = $this->getChannelsPage();
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
    }

    /**
     * Switch to search
     *
     * @return void
     */
    public function testSwitchToSearch(): void
    {
        $page = $this->getChannelsPage();
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
        $page = $this->getChannelsPage($query);
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
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Channels/Home');
        $page = $session->getPage();
        $this->assertEquals($expectedTitle, $this->findCssAndGetText($page, 'h2.channel-title'));
        $this->assertCount(2, $page->findAll('css', 'li.channel-item'));
        $this->assertCount(1, $page->findAll('css', 'li.channel-item[data-record-id="' . $bibId1 . '"]'));
        $this->assertCount(1, $page->findAll('css', 'li.channel-item[data-record-id="' . $bibId2 . '"]'));
    }
}
