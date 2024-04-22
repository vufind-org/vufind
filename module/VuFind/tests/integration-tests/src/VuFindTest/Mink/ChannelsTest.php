<?php

/**
 * Mink channels test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011-2024.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink channels test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class ChannelsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get a reference to a standard search results page.
     *
     * @return Element
     */
    protected function getChannelsPage(): Element
    {
        $session = $this->getMinkSession();
        $path = '/Channels/Search?lookfor=building%3A%22weird_ids.mrc%22';
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
        $this->findCss($page, 'div.channel-wrapper');
        // Check number of channels
        $channels = $page->findAll('css', 'div.channel-wrapper');
        $this->assertCount(6, $channels);
        // Make sure search input matches url
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCss($page, '[action*="Channels/Search"] .form-control')->getValue()
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
        $channel = $this->findCss($page, 'div.channel-wrapper');
        // Initial counts
        $this->assertCount(6, $page->findAll('css', 'div.channel-wrapper'));
        $this->assertCount(8, $channel->findAll('css', '.channel-add-menu .dropdown-menu li'));
        // Click first add button
        $this->clickCss($channel, '.add-btn');
        // Post count
        $this->waitStatement('$("div.channel-wrapper").length === 8');
        $this->waitStatement('$(".channel-add-menu:first .dropdown-menu li").length === 6');
        $this->assertCount(8, $page->findAll('css', 'div.channel-wrapper'));
        $this->assertCount(6, $channel->findAll('css', '.channel-add-menu .dropdown-menu li'));
    }

    /**
     * Switch to search
     *
     * @return void
     */
    public function testSwitchToSearch(): void
    {
        $page = $this->getChannelsPage();
        $channel = $this->findCss($page, 'div.channel-wrapper');
        // Click dropdown to display links
        $this->clickCss($channel, '.dropdown');
        // Click link to go to search results
        $this->clickCss($channel, '.channel_search');
        // Make sure the search translated
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCss($page, '#searchForm_lookfor')->getValue()
        );
        // Check facet
        $this->assertEquals(
            'Suggested Topics:',
            $this->findCss($page, '.filters .filters-title')->getText()
        );
        $this->assertEquals(
            'Remove Filter Adult children of aging parents',
            $this->findCss($page, '.filters .filter-value')->getText()
        );
    }

    /**
     * Test popover behavior
     *
     * @return void
     */
    public function testPopovers(): void
    {
        $page = $this->getChannelsPage();
        // Click a record to open the popover:
        $this->clickCss($page, '.channel-record[data-record-id="hashes#coming@ya"]');
        $popoverContents = $this->findCssAndGetText($page, '.popover');
        // The popover should contain an appropriate title and metadata:
        $this->assertStringContainsString('Octothorpes: Why not?', $popoverContents);
        $this->assertStringContainsString('Physical Description', $popoverContents);
        // Click a different record:
        $this->clickCss($page, '.channel-record[data-record-id="dollar$ign/slashcombo"]');
        $popoverContents2 = $this->findCssAndGetText($page, '.popover');
        // The popover should contain an appropriate title and metadata:
        $this->assertStringContainsString('Of Money and Slashes', $popoverContents2);
        $this->assertStringContainsString('Physical Description', $popoverContents2);
        // Click outside of channels to move the focus away:
        $this->clickCss($page, 'li.active');
        // Now click back to the original record; the popover should contain the same contents.
        $this->clickCss($page, '.channel-record[data-record-id="hashes#coming@ya"]');
        $popoverContents3 = $this->findCssAndGetText($page, '.popover');
        $this->assertEquals($popoverContents, $popoverContents3);
        // Finally, click through to the record page.
        $link = $this->findCss($page, '.popover a', null, 1);
        $this->assertEquals('View Record', $link->getText());
        $link->click();
        $this->waitForPageLoad($page);
        $this->assertEquals('Octothorpes: Why not?', $this->findCssAndGetText($page, 'h1'));
    }
}
