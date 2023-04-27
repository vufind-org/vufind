<?php

/**
 * Test class for holdings and item statuses.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\DocumentElement;
use VuFind\ILS\Connection;

/**
 * Test class for holdings and item statuses.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class HoldingsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\DemoDriverTestTrait;

    /**
     * Data provider for test methods
     *
     * @return array
     */
    public function itemStatusAndHoldingsProvider(): array
    {
        return [
            [true, 'On Shelf', 'Available', 'success'],
            [false, 'Checked Out', 'Checked Out', 'danger'],
            [Connection::ITEM_STATUS_AVAILABLE, 'On Shelf', 'On Shelf', 'success'],
            [Connection::ITEM_STATUS_UNAVAILABLE, 'Checked Out', 'Checked Out', 'danger'],
            [Connection::ITEM_STATUS_UNCERTAIN, 'Check with Staff', 'Check with Staff', 'warning'],
        ];
    }

    /**
     * Test basic item status display in search results
     *
     * @param mixed  $availability Item availability status
     * @param string $status       Status display string
     * @param string $expected     Expected availability display status
     * @param string $expectedType Expected status type (e.g. 'success')
     *
     * @dataProvider itemStatusAndHoldingsProvider
     *
     * @return void
     */
    public function testItemStatus($availability, $status, $expected, $expectedType): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(false),
                'Demo' => $this->getDemoIniOverrides($availability, $status, true),
            ]
        );

        $page = $this->goToSearchResults();

        // The simple availability display will only show Available/Unavailable/Uncertain:
        $expectedMap = [
            'success' => 'Available',
            'danger' => 'Checked Out',
            'warning' => 'Uncertain',
        ];
        $label = $this->findCss($page, ".result-body .status .label.label-$expectedType");
        $this->assertEquals($expectedMap[$expectedType], $label->getText());
    }

    /**
     * Test full item status display in search results
     *
     * @param mixed  $availability Item availability status
     * @param string $status       Status display string
     * @param string $expected     Expected availability display status
     * @param string $expectedType Expected status type (e.g. 'success')
     *
     * @dataProvider itemStatusAndHoldingsProvider
     *
     * @return void
     */
    public function testItemStatusFull($availability, $status, $expected, $expectedType): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(true),
                'Demo' => $this->getDemoIniOverrides($availability, $status, true),
            ]
        );

        $page = $this->goToSearchResults();

        $label = $this->findCss($page, ".result-body .fullAvailability .text-$expectedType");
        $this->assertEquals($expected, $label->getText());
    }

    /**
     * Test holdings tab
     *
     * @param mixed  $availability Item availability status
     * @param string $status       Status display string
     * @param string $expected     Expected availability display status
     * @param string $expectedType Expected status type (e.g. 'success')
     *
     * @dataProvider itemStatusAndHoldingsProvider
     *
     * @return void
     */
    public function testHoldings($availability, $status, $expected, $expectedType): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(false),
                'Demo' => $this->getDemoIniOverrides($availability, $status),
            ]
        );

        $page = $this->goToRecord();

        $label = $this->findCss($page, ".holdings-tab span.text-$expectedType");
        $this->assertEquals($expected, $label->getText());
    }

    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @param bool $fullStatus Whether to show full item status in results
     *
     * @return array
     */
    protected function getConfigIniOverrides(bool $fullStatus): array
    {
        return [
            'Catalog' => [
                'driver' => 'Demo',
            ],
            'Item_Status' => [
                'show_full_status' => $fullStatus,
            ],
        ];
    }

    /**
     * Get Demo.ini override settings for testing ILS functions.
     *
     * @param mixed  $availability  Item availability status
     * @param string $statusMsg     Status display string
     * @param bool   $addExtraItems Whether to add extra items to ensure the status
     * logic works properly
     *
     * @return array
     */
    protected function getDemoIniOverrides(
        $availability,
        string $statusMsg,
        bool $addExtraItems = false
    ): array {
        $items = [];
        // If the requested item is available or uncertain, add other items before
        // (if allowed) to test that the correct status prevails:
        if ($addExtraItems && $availability) {
            $item = $this->getFakeItem();
            $item['availability'] = Connection::ITEM_STATUS_UNAVAILABLE;
            $item['status'] = 'Foo';
            $items[] = $item;
            if (Connection::ITEM_STATUS_UNCERTAIN !== $availability) {
                $item = $this->getFakeItem();
                $item['availability'] = Connection::ITEM_STATUS_UNCERTAIN;
                $item['status'] = 'Foo';
                $items[] = $item;
            }
        }
        $item = $this->getFakeItem();
        $item['availability'] = $availability;
        $item['status'] = $statusMsg;
        $items[] = $item;
        return [
            'Records' => [
                'services' => [],
            ],
            'Failure_Probabilities' => [
                'getHolding' => 0,
                'getStatuses' => 0,
            ],
            'StaticHoldings' => ['testsample1' => json_encode($items)],
            'Users' => ['catuser' => 'catpass'],
        ];
    }

    /**
     * Get search results page
     *
     * @return DocumentElement
     */
    protected function goToSearchResults(): DocumentElement
    {
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . '/Search/Results?lookfor='
            . urlencode("id:(testsample1)")
        );
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Get record page
     *
     * @return DocumentElement
     */
    protected function goToRecord(): DocumentElement
    {
        $session = $this->getMinkSession();
        $session->visit(
            $this->getVuFindUrl() . '/Record/testsample1'
        );
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }
}
