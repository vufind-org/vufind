<?php

/**
 * Trait with utility methods for configuring the demo driver in a test
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;

/**
 * Trait with utility methods for configuring the demo driver in a test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait DemoDriverTestTrait
{
    /**
     * Get transaction JSON for Demo.ini.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return array
     */
    protected function getFakeTransactions($bibId)
    {
        $rawDueDate = strtotime('now +5 days');
        return json_encode(
            [
                [
                    'duedate' => $rawDueDate,
                    'rawduedate' => $rawDueDate,
                    'dueStatus' => 'due',
                    'barcode' => 1234567890,
                    'renew'   => 0,
                    'renewLimit' => 1,
                    'request' => 0,
                    'id' => $bibId,
                    'source' => 'Solr',
                    'item_id' => 0,
                    'renewable' => true,
                ],
            ]
        );
    }

    /**
     * Get historic transaction JSON for Demo.ini.
     *
     * @param string $bibId  Bibliographic record ID to create fake item info for.
     * @param string $bibId2 Another bibliographic record ID to create fake item info
     * for.
     *
     * @return array
     */
    protected function getFakeHistoricTransactions($bibId, $bibId2)
    {
        $checkoutDate = strtotime('now -30 days');
        $returnDate = strtotime('now -5 days');
        $dueDate = strtotime('now -2 days');
        $checkoutDate2 = strtotime('now -34 days');
        $returnDate2 = strtotime('now -3 days');
        $dueDate2 = strtotime('now -1 days');
        return json_encode(
            [
                [
                    'checkoutDate' => date('Y-m-d', $checkoutDate),
                    '_checkoutDate' => $checkoutDate,
                    'dueDate' => date('Y-m-d', $dueDate),
                    '_dueDate' => $dueDate,
                    'returnDate' => date('Y-m-d', $returnDate),
                    '_returnDate' => $returnDate,
                    'barcode' => 1234567890,
                    'id' => $bibId,
                    'source' => 'Solr',
                    'item_id' => 0,
                    'row_id' => 31313,
                ],
                [
                    'checkoutDate' => date('Y-m-d', $checkoutDate2),
                    '_checkoutDate' => $checkoutDate2,
                    'dueDate' => date('Y-m-d', $dueDate2),
                    '_dueDate' => $dueDate2,
                    'returnDate' => date('Y-m-d', $returnDate2),
                    '_returnDate' => $returnDate2,
                    'barcode' => 2345678901,
                    'id' => $bibId2,
                    'source' => 'Solr',
                    'item_id' => 0,
                    'row_id' => 21212,
                ],
            ]
        );
    }

    /**
     * Get Demo.ini override settings for testing ILS functions.
     *
     * @param string $bibId  Bibliographic record ID to create fake item info for.
     * @param string $bibId2 Bibliographic record ID for a second transaction history
     * row.
     *
     * @return array
     */
    protected function getDemoIniOverrides(
        $bibId = 'testsample1',
        $bibId2 = 'testsample2'
    ) {
        return [
            'Records' => [
                'transactions' => $this->getFakeTransactions($bibId),
                'historicTransactions'
                    => $this->getFakeHistoricTransactions($bibId, $bibId2),
            ],
            'Failure_Probabilities' => [
                'cancelHolds' => 0,
                'cancelILLRequests' => 0,
                'cancelStorageRetrievalRequests' => 0,
                'checkILLRequestIsValid' => 0,
                'checkRenewBlock' => 0,
                'checkRequestIsValid' => 0,
                'checkStorageRetrievalRequestIsValid' => 0,
                'getAccountBlocks' => 0,
                'getDefaultRequestGroup' => 0,
                'getHoldDefaultRequiredDate' => 0,
                'getRequestBlocks' => 0,
                'placeHold' => 0,
                'placeILLRequest' => 0,
                'placeStorageRetrievalRequest' => 0,
                'renewMyItems' => 0,
                'updateHolds' => 0,
                'purgeTransactionHistory' => 0,
            ],
            'StaticHoldings' => [
                $bibId => json_encode([$this->getFakeItem()]),
                $bibId2 => json_encode([$this->getFakeItem()]),
            ],
            'Users' => ['catuser' => 'catpass'],
            'TransactionHistory' => [
                'enabled' => true,
            ],
        ];
    }

    /**
     * Get a fake item record for inclusion in the Demo driver configuration.
     *
     * @return array
     */
    protected function getFakeItem()
    {
        return [
            'barcode'      => '12345678',
            'availability' => true,
            'status'       => 'Available',
            'location'     => 'Test Location',
            'locationhref' => false,
            'reserve'      => 'N',
            'callnumber'   => 'Test Call Number',
            'duedate'      => '',
            'is_holdable'  => true,
            'addLink'      => true,
            'addStorageRetrievalRequestLink' => 'check',
            'addILLRequestLink' => 'check',
            '__electronic__' => false,
        ];
    }

    /**
     * Fill in and submit the catalog login form with the provided credentials.
     *
     * @param Element $page     Page element.
     * @param string  $username Username
     * @param string  $password Password
     *
     * @return void
     */
    protected function submitCatalogLoginForm(
        Element $page,
        string $username,
        string $password
    ): void {
        $this->findCssAndSetValue($page, '#profile_cat_username', $username);
        $this->findCssAndSetValue($page, '#profile_cat_password', $password);
        $this->clickCss($page, 'input.btn.btn-primary');
    }
}
