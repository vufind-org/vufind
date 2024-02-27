<?php

/**
 * Mink cart test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * Mink cart test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ChannelsTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Get a reference to a standard search results page.
     *
     * @return Element
     */
    protected function getChannelsPage()
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
    public function testBasic()
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
            $this->findCssAndGetValue($page, '[action*="Channels/Search"] .form-control')
        );
    }

    /**
     * Add channels button
     *
     * @return void
     */
    public function testAddChannels()
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
    public function testSwitchToSearch()
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
}
