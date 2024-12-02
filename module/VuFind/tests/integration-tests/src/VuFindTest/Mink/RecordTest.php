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
    protected function tryRecordTabsOnId(string $id, bool $encodeId = true): void
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
    protected function tryLoadingTabHashAndReturningToDefault(string $id, bool $encodeId = true): void
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
    public function testRecordTabsOnNormalId(): void
    {
        $this->tryRecordTabsOnId('testsample1');
        $this->tryLoadingTabHashAndReturningToDefault('testsample2');
    }

    /**
     * Test that record tabs work with an ID with a space in it.
     *
     * @return void
     */
    public function testRecordTabsOnSpacedId(): void
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
    public function testRecordTabsOnPlusId(): void
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
    public function testLoadInitialTabWithAjax(): void
    {
        $this->changeConfigs(
            ['config' => ['Site' => ['loadInitialTabWithAjax' => 1]]]
        );
        $this->tryRecordTabsOnId('testsample1');
        $this->tryLoadingTabHashAndReturningToDefault('testsample2');
    }

    /**
     * Data provider for testPermalink().
     *
     * @return array[]
     */
    public static function permalinkProvider(): array
    {
        return [
            'default' => [null],
            'enabled' => [true],
            'disabled' => [false],
        ];
    }

    /**
     * Test permalink display.
     *
     * @param ?bool  $enabled Are permalinks enabled? (Null = use default config)
     * @param string $id      Record ID to test with
     *
     * @return void
     *
     * @dataProvider permalinkProvider
     */
    public function testPermalink(?bool $enabled, $id = 'testbug1'): void
    {
        // Change configuration, unless we're using the default value:
        if (null !== $enabled) {
            $this->changeConfigs(
                ['config' => ['Record' => ['permanent_link' => $enabled]]]
            );
        }
        $url = $this->getVuFindUrl('/Record/' . rawurlencode($id));
        $session = $this->getMinkSession();
        $session->visit($url);
        $page = $session->getPage();
        // We expect to find a permanent link in default and true modes; not in false mode.
        $selector = '.permalink-record';
        if ($enabled === false) {
            $this->unFindCss($page, $selector);
        } else {
            $link = $this->findCss($page, $selector);
            $this->assertStringContainsString($link->getAttribute('href'), $url . '/Permalink');
        }
    }
}
