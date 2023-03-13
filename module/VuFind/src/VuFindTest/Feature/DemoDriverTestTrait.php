<?php

/**
 * Trait with utility methods for configuring the demo driver in a test
 *
 * PHP version 7
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
        $rawDueDate = strtotime("now +5 days");
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
                ]
            ]
        );
    }

    /**
     * Get Demo.ini override settings for testing ILS functions.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return array
     */
    protected function getDemoIniOverrides($bibId = 'testsample1')
    {
        return [
            'Records' => [
                'transactions' => $this->getFakeTransactions($bibId),
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
            ],
            'StaticHoldings' => [$bibId => json_encode([$this->getFakeItem()])],
            'Users' => ['catuser' => 'catpass'],
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
            "__electronic__" => false,
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
        $this->findCss($page, '#profile_cat_username')->setValue($username);
        $this->findCss($page, '#profile_cat_password')->setValue($password);
        $this->clickCss($page, 'input.btn.btn-primary');
    }
}
