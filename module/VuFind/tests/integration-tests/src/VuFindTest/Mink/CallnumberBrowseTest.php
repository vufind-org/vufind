<?php

/**
 * Mink search actions test class.
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
 * Mink search actions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CallnumberBrowseTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Record ID to use in testing.
     *
     * @var string
     */
    protected $id = 'testdeweybrowse';

    /**
     * Set config for callnumber tests
     * Sets callnumber_handler to false
     *
     * @param string $nos  multiple_call_nos setting
     * @param string $locs multiple_locations setting
     * @param bool   $full Show full status setting
     *
     * @return void
     */
    protected function changeCallnumberSettings($nos, $locs, $full = false)
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Catalog' => ['driver' => 'Sample'],
                    'Item_Status' => [
                        'multiple_call_nos' => $nos,
                        'multiple_locations' => $locs,
                        'callnumber_handler' => false,
                        'show_full_status' => $full,
                    ],
                ],
            ]
        );
    }

    /**
     * Checks if a link has the correct callnumber and setting
     *
     * @param \Behat\Mink\Element\Element $link link element
     * @param string                      $type dewey or lcc
     *
     * @return void
     */
    protected function checkLink($link, $type)
    {
        $this->assertIsObject($link);
        $href = $link->getAttribute('href');
        $this->assertStringContainsString($type, $href);
        $this->assertNotEquals('', $link->getText());
        $hrefCallnum = explode('&from=', $href)[1];
        $this->assertStringEndsWith($hrefCallnum, $link->getText());
    }

    /**
     * Set up configuration for testing with multiple call numbers.
     *
     * @return void
     */
    protected function setupMultipleCallnumbers()
    {
        $this->changeConfigs(
            [
            'config' => [
                'Catalog' => ['driver' => 'Demo'],
            ],
            'Demo' => [
                'StaticHoldings' => [
                    $this->id => json_encode(
                        [
                        ['callnumber' => 'CallNumberOne', 'location' => 'Villanova'],
                        ['callnumber' => 'CallNumberTwo', 'location' => 'Villanova'],
                        ['callnumber' => 'CallNumberThree', 'location' => 'Phobos'],
                        ['callnumber' => 'CallNumberFour', 'location' => 'Phobos'],
                        ]
                    ),
                ],
            ],
            ]
        );
    }

    /**
     * Sets callnumber_handler to true
     *
     * @param string                      $type        dewey or lcc
     * @param \Behat\Mink\Element\Element $page        page element
     * @param bool                        $expectLinks links on multiple?
     *
     * @return void
     */
    protected function activateAndTestLinks($type, $page, $expectLinks)
    {
        // Single callnumbers (Sample)
        $this->changeConfigs(
            [
                'config' => [
                    'Catalog' => ['driver' => 'Sample'],
                    'Item_Status' => ['callnumber_handler' => $type],
                ],
            ]
        );
        $callnumberSelector = '.callnumber a,.groupCallnumber a,.fullCallnumber a';
        $session = $this->getMinkSession();
        $session->reload();
        $this->waitForPageLoad($page);
        $link = $this->findCss($page, $callnumberSelector);
        $this->checkLink($link, $type);

        // Multiple callnumbers
        $this->setupMultipleCallnumbers();
        $session->reload();
        $this->waitForPageLoad($page);
        if ($expectLinks) {
            $link = $this->findCss($page, $callnumberSelector);
            $this->checkLink($link, $type);
        } else {
            $link = $page->find('css', $callnumberSelector);
            $this->assertNull($link);
        }
    }

    /**
     * Sets callnumber_handler to true
     *
     * @param string $nos         multiple_call_nos setting
     * @param string $locs        multiple_locations setting
     * @param bool   $expectLinks whether or not links are expected for multiple callnumbers in this config
     * @param bool   $full        Show full status setting
     *
     * @return void
     */
    protected function validateSetting($nos = 'first', $locs = 'msg', $expectLinks = true, $full = false)
    {
        $this->changeCallnumberSettings($nos, $locs, $full);
        $page = $this->performSearch('id:' . $this->id);
        // No link
        $link = $page->find('css', '.callnumber a,.groupCallnumber a,.fullCallnumber a');
        $this->assertTrue(null === $link);
        // With dewey links
        $this->activateAndTestLinks('dewey', $page, $expectLinks);
        // With lcc links
        $this->activateAndTestLinks('lcc', $page, $expectLinks);
    }

    /**
     * Test with multiple_call_nos set to first
     * and multiple_locations set to msg
     *
     * @return void
     */
    public function testFirstAndMsg()
    {
        $this->validateSetting('first');
    }

    /**
     * Test with multiple_call_nos set to first
     * and multiple_locations set to msg
     *
     * @return void
     */
    public function testAllAndMsg()
    {
        $this->validateSetting('all');
    }

    /**
     * Test with multiple_call_nos set to first
     * and multiple_locations set to msg
     *
     * @return void
     */
    public function testMsgAndMsg()
    {
        $this->validateSetting('msg', 'msg', false);
    }

    /**
     * Test with multiple_call_nos set to first
     * and multiple_locations set to group
     *
     * @return void
     */
    public function testFirstAndGroup()
    {
        $this->validateSetting('first', 'group');
    }

    /**
     * Test with multiple_call_nos set to all
     * and multiple_locations set to msg
     *
     * @return void
     */
    public function testAllAndGroup()
    {
        $this->validateSetting('all', 'group');
    }

    /**
     * Test with multiple_call_nos set to msg
     * and multiple_locations set to group
     *
     * @return void
     */
    public function testMsgAndGroup()
    {
        $this->validateSetting('msg', 'group', false);
    }

    /**
     * Test with show_full_status set to true
     *
     * @return void
     */
    public function testStatusFull()
    {
        $this->validateSetting('first', 'msg', true, true);
    }
}
