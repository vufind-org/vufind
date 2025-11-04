<?php

/**
 * GetCheckoutHistory test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\AjaxHandler;

use Finna\AjaxHandler\GetCheckoutHistory;
use Finna\AjaxHandler\GetCheckoutHistoryFactory;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Config\Config;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\ILS\Connection;

/**
 * GetCheckoutHistory test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetCheckoutHistoryTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    /**
     * Set up a GetCheckoutHistory handler for testing.
     *
     * @param ?UserEntityInterface $user       Return value for getUserObject() in auth manager
     * @param Config               $testConfig Default values for testing config settings
     *
     * @return GetCheckoutHistory
     */
    protected function getHandler(
        ?UserEntityInterface $user = null,
        Config $testConfig = new Config([])
    ): GetCheckoutHistory {
        // Set up auth manager with user:
        $this->container->set(Manager::class, $this->getMockAuthManager($user));
        $mockConfigManager = $this->container->createMock(\VuFind\Config\PluginManager::class, ['get']);
        $mockConfigManager->expects($this->once())->method('get')->with('config')->willReturn($testConfig);
        $this->container->set(\VuFind\Config\PluginManager::class, $mockConfigManager);
        // Build the handler:
        $factory = new GetCheckoutHistoryFactory();
        return $factory($this->container, GetCheckoutHistory::class);
    }

    /**
     * Data provider for testSuccess
     *
     * @return array
     */
    public static function getSuccessfulData(): array
    {
        return [
            'batch limit is higher' => [
                50,
                1000,
                [
                    'success' => true,
                    'transactions' => [[]],
                    'count' => 10000,
                ],
                ['parts' => 10],
            ],
            'batch limit is same' => [
                50,
                50,
                [
                    'success' => true,
                    'transactions' => [[]],
                    'count' => 10000,
                ],
                ['parts' => 200],
            ],
            'batch limit is lower' => [
                50,
                10,
                [
                    'success' => true,
                    'transactions' => [[]],
                    'count' => 10000,
                ],
                ['parts' => 200],
            ],
            'results lower than batch limit' => [
                50,
                1000,
                [
                    'success' => true,
                    'transactions' => [[]],
                    'count' => 21,
                ],
                ['parts' => 1],
            ],
            'no history' => [
                50,
                10,
                [
                    'success' => true,
                    'transactions' => [],
                    'count' => 0,
                ],
                ['parts' => 0],
            ],
            'different default than usual' => [
                15,
                1000,
                [
                    'success' => true,
                    'transactions' => [],
                    'count' => 10000,
                ],
                ['parts' => 10],
            ],
            'test with very small limits' => [
                3,
                2,
                [
                    'success' => true,
                    'transactions' => [],
                    'count' => 7,
                ],
                ['parts' => 3],
            ],
            'test with nothing set as limits' => [
                0,
                1000,
                [
                    'success' => true,
                    'transactions' => [],
                    'count' => 10000,
                ],
                ['parts' => 10],
            ],
        ];
    }

    /**
     * Data provider for testSuccess
     *
     * @return array
     */
    public static function getFailuresData(): array
    {
        return [
            'failure from getMyTransactions' => [
                50,
                1000,
                [
                    'success' => false,
                    'transactions' => [[]],
                    'count' => 10000,
                ],
                ['An error has occurred',  500],
            ],
        ];
    }

    /**
     * Test successful response
     *
     * @param int   $defaultPageSize   Default page size to set in config
     * @param int   $batchLimit        Default batch limit to set in config
     * @param array $transactionResult Array containing success, transactions and count of all transactions
     * @param array $expected          What is the expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSuccessfulData')]
    public function testSuccess(int $defaultPageSize, int $batchLimit, array $transactionResult, array $expected)
    {
        $this->assertEquals(
            [$expected],
            $this->runSuccessfulTest($defaultPageSize, $batchLimit, $transactionResult)
        );
    }

    /**
     * Test failures
     *
     * @param int   $defaultPageSize   Default page size to set in config
     * @param int   $batchLimit        Default batch limit to set in config
     * @param array $transactionResult Array containing success, transactions and count of all transactions
     * @param array $expected          What is the expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getFailuresData')]
    public function testFailures(int $defaultPageSize, int $batchLimit, array $transactionResult, array $expected)
    {
        $this->assertEquals(
            $expected,
            $this->runSuccessfulTest($defaultPageSize, $batchLimit, $transactionResult)
        );
    }

    /**
     * Test the AJAX handler's response when no one is logged in.
     *
     * @return void
     */
    public function testLoggedOutUser(): void
    {
        $handler = $this->getHandler();
        $this->assertEquals(
            ['You must be logged in first', 401],
            $handler->handleRequest($this->getParamsHelper([]))
        );
    }

    /**
     * Generic support function for successful request tests.
     *
     * @param int   $limit             Default page limit
     * @param int   $batchLimit        Default batch limit
     * @param array $transactionResult Result from getMyTransactionHistory
     *
     * @return array
     */
    protected function runSuccessfulTest($limit, $batchLimit, $transactionResult = []): array
    {
        /**
         * Create a wrapper class for connection as it is little bit difficult to mock
         */
        $wrapperClass = new class ($transactionResult) extends Connection {
            /**
             * Override constructor
             *
             * @param array $transactionResult Result from getMyTransactionHistory
             *
             * @return void
             */
            public function __construct(protected array $transactionResult = [])
            {
            }

            /**
             * Override checkFunction
             *
             * @param string $function Function to check
             * @param ?array $params   Params to use or null
             *
             * @return array
             */
            public function checkFunction($function, $params = null)
            {
                return [
                    'max_results' => 50,
                ];
            }

            /**
             * GetMyTransactionHistory mock
             *
             * @param array $patron Mock patron array
             * @param array $params Contains info about ils specified limits
             *
             * @return array
             */
            public function getMyTransactionHistory($patron, $params): array
            {
                return $this->transactionResult ?: [
                    'success' => true,
                    'transactions' => [[]],
                    'count' => 10000,
                ];
            }
        };
        $ilsAuth = $this->container
            ->createMock(ILSAuthenticator::class, ['storedCatalogLogin']);
        $ilsAuth->expects($this->any())->method('storedCatalogLogin')->willReturn([3]);
        $this->container->set(Connection::class, $wrapperClass);
        $this->container->set(ILSAuthenticator::class, $ilsAuth);
        $config = new Config([
          'Catalog' => [
            'historic_loan_page_size' => $limit,
            'loan_history_download_batch_limit' => $batchLimit,
          ],
        ]);
        $handler = $this->getHandler($this->getMockUser(), $config);
        return $handler->handleRequest($this->getParamsHelper([]));
    }
}
