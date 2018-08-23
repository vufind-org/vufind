<?php
/**
 * Mink cart test class.
 *
 * PHP version 7
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
class ChannelsTest extends \VuFindTest\Unit\MinkTestCase
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
     */
    public function testBasic()
    {
        $page = $this->getChannelsPage();
        // Channels are here
        $this->findCss($page, 'div.channel-wrapper');
        // Check number of channels
        $channels = $page->findAll('css', 'div.channel-wrapper');
        $this->assertEquals(6, count($channels));
        // Make sure search input matches url
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCss($page, '[action*="Channels/Search"] .form-control')->getValue()
        );
    }

    /**
     * Add channels button
     */
    public function testAddChannels()
    {
        $page = $this->getChannelsPage();
        $channel = $this->findCss($page, 'div.channel-wrapper');
        // Initial counts
        $this->assertEquals(6, count($page->findAll('css', 'div.channel-wrapper')));
        $this->assertEquals(8, count($channel->findAll('css', '.channel-add-menu .dropdown-menu li')));
        // Click first add button
        $this->findCss($channel, '.add-btn')->click();
        $this->snooze();
        // Post count
        $this->assertEquals(8, count($page->findAll('css', 'div.channel-wrapper')));
        $this->assertEquals(6, count($channel->findAll('css', '.channel-add-menu .dropdown-menu li')));
    }

    /**
     * Switch to search
     */
    public function testSwitchToSearch()
    {
        $page = $this->getChannelsPage();
        $channel = $this->findCss($page, 'div.channel-wrapper');
        // Click dropdown to display links
        $this->findCss($channel, '.dropdown')->click();
        $this->snooze();
        // Click link to go to search results
        $this->findCss($channel, '.channel_search')->click();
        $this->snooze();
        // Make sure the search translated
        $this->assertEquals(
            'building:"weird_ids.mrc"',
            $this->findCss($page, '#searchForm_lookfor')->getValue()
        );
        // Check facet
        $this->assertEquals(
            'Clear Filter Suggested Topics: Adult children of aging parents',
            $this->findCss($page, '.active-filters .facet')->getText()
        );
    }
}
