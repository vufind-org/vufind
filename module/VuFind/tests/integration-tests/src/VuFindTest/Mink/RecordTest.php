<?php

/**
 * Mink test class for basic record functionality.
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

/**
 * Mink test class for basic record functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test record tabs for a particular ID.
     *
     * @param string $id       ID to load
     * @param bool   $encodeId Should we URL encode the ID?
     *
     * @return void
     */
    protected function tryRecordTabsOnId($id, $encodeId = true)
    {
        $url = $this->getVuFindUrl(
            '/Record/' . ($encodeId ? rawurlencode($id) : $id)
        );
        $session = $this->getMinkSession();
        $session->visit($url);
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $staffViewTab = $this->findCss($page, '.record-tabs .details a');
        $this->assertEquals('Staff View', $staffViewTab->getText());
        $staffViewTab->click();
        $this->assertEqualsWithTimeout(
            $url . '#details',
            [$session, 'getCurrentUrl']
        );
        $staffViewTable = $this->findCss($page, '.record-tabs .details-tab table.staff-view--marc');
        $this->assertEquals('LEADER', substr($staffViewTable->getText(), 0, 6));
    }

    /**
     * Test that we can start on a hashed URL and then move back to the default
     * tab from there.
     *
     * @param string $id       ID to load
     * @param bool   $encodeId Should we URL encode the ID?
     *
     * @return void
     */
    protected function tryLoadingTabHashAndReturningToDefault($id, $encodeId = true)
    {
        // special test for going back to default tab from non-default URL
        $url = $this->getVuFindUrl(
            '/Record/' . ($encodeId ? rawurlencode($id) : $id) . '/Holdings#details'
        );
        $session = $this->getMinkSession();
        $session->visit($url);
        $page = $session->getPage();
        $this->assertStringStartsWith(
            'LEADER',
            $this->findCssAndGetText($page, '.record-tabs .details-tab table.staff-view--marc')
        );
        $page = $session->getPage();
        $staffViewTab = $this->findCss($page, '.record-tabs .holdings a');
        $this->assertEquals('Holdings', $staffViewTab->getText());
        $staffViewTab->click();
        $this->assertEquals(
            '3rd Floor Main Library',
            $this->findCssAndGetText($page, '.record-tabs .holdings-tab h2')
        );
        [$baseUrl] = explode('#', $url);
        $this->assertEquals($baseUrl, $session->getCurrentUrl());
    }

    /**
     * Test that record tabs work with a "normal" ID.
     *
     * @return void
     */
    public function testRecordTabsOnNormalId()
    {
        $this->tryRecordTabsOnId('testsample1');
        $this->tryLoadingTabHashAndReturningToDefault('testsample2');
    }

    /**
     * Test that record tabs work with an ID with a space in it.
     *
     * @return void
     */
    public function testRecordTabsOnSpacedId()
    {
        $this->tryRecordTabsOnId('dot.dash-underscore__3.space suffix');
        $this->tryLoadingTabHashAndReturningToDefault(
            'dot.dash-underscore__3.space suffix'
        );
    }

    /**
     * Test that record tabs work with an ID with a plus in it.
     *
     * @return void
     */
    public function testRecordTabsOnPlusId()
    {
        // Skip encoding on this one, because Laminas doesn't URL encode
        // plus signs in route segments!
        $this->tryRecordTabsOnId('theplus+andtheminus-', false);
        $this->tryLoadingTabHashAndReturningToDefault(
            'theplus+andtheminus-',
            false
        );
    }

    /**
     * Test that tabs work correctly with loadInitialTabWithAjax turned on.
     *
     * @return void
     */
    public function testLoadInitialTabWithAjax()
    {
        $this->changeConfigs(
            ['config' => ['Site' => ['loadInitialTabWithAjax' => 1]]]
        );
        $this->tryRecordTabsOnId('testsample1');
        $this->tryLoadingTabHashAndReturningToDefault('testsample2');
    }
}
