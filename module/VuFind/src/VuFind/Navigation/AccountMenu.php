<?php

/**
 * Account menu
 *
 * PHP version 8
 *
 * Copyright (C) Moravian library 2024.
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
 * @package  Navigation
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Navigation;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;

/**
 * Account menu
 *
 * @category VuFind
 * @package  Navigation
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccountMenu extends AbstractMenu
{
    /**
     * Constructor.
     *
     * @param array               $config              Menu configuration
     * @param AccountCapabilities $accountCapabilities Account capabilities
     * @param Manager             $authManager         Authentication manager
     * @param Connection          $ilsConnection       ILS connection
     * @param ILSAuthenticator    $ilsAuthenticator    ILS authenticator
     * @param ?OverdriveConnector $overdriveConnector  Overdrive connector
     */
    public function __construct(
        array $config,
        protected AccountCapabilities $accountCapabilities,
        protected Manager $authManager,
        protected Connection $ilsConnection,
        protected ILSAuthenticator $ilsAuthenticator,
        protected ?OverdriveConnector $overdriveConnector,
    ) {
        if (isset($config['MenuItems'])) {
            // backward compatibility for outdated AccountMenu configurations
            $default = static::getDefaultMenuConfig();
            $default['Account']['MenuItems'] = $config['MenuItems'];
            $config = $default;
        }
        parent::__construct($config);
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        return [
            'Account' => [
                'name' => 'acc',
                'label' => 'Your Account',
                'id' => 'acc-menu-acc-header',
                'class' => 'account-menu',
                'MenuItems' => [
                    [
                        'name' => 'favorites',
                        'label' => 'saved_items',
                        'route' => 'myresearch-favorites',
                        'icon' => 'user-favorites',
                        'checkMethod' => 'checkFavorites',
                    ],
                    [
                        'name' => 'checkedout',
                        'label' => 'Checked Out Items',
                        'route' => 'myresearch-checkedout',
                        'icon' => 'user-checked-out',
                        'status' => true,
                        'checkMethod' => 'checkCheckedout',
                    ],
                    [
                        'name' => 'historicloans',
                        'label' => 'Loan History',
                        'route' => 'checkouts-history',
                        'icon' => 'user-loan-history',
                        'checkMethod' => 'checkHistoricloans',
                    ],
                    [
                        'name' => 'holds',
                        'label' => 'Holds and Recalls',
                        'route' => 'holds-list',
                        'icon' => 'user-holds',
                        'status' => true,
                        'checkMethod' => 'checkHolds',
                    ],
                    [
                        'name' => 'storageRetrievalRequests',
                        'label' => 'Storage Retrieval Requests',
                        'route' => 'myresearch-storageretrievalrequests',
                        'icon' => 'user-storage-retrievals',
                        'status' => true,
                        'checkMethod' => 'checkStorageRetrievalRequests',
                    ],
                    [
                        'name' => 'ILLRequests',
                        'label' => 'Interlibrary Loan Requests',
                        'route' => 'myresearch-illrequests',
                        'icon' => 'user-ill-requests',
                        'status' => true,
                        'checkMethod' => 'checkILLRequests',
                    ],
                    [
                        'name' => 'fines',
                        'label' => 'Fines',
                        'route' => 'myresearch-fines',
                        'status' => true,
                        'checkMethod' => 'checkFines',
                        'iconMethod' => 'finesIcon',
                    ],
                    [
                        'name' => 'profile',
                        'label' => 'Profile',
                        'route' => 'myresearch-profile',
                        'icon' => 'profile',
                    ],
                    [
                        'name' => 'librarycards',
                        'label' => 'Library Cards',
                        'route' => 'librarycards-home',
                        'icon' => 'barcode',
                        'checkMethod' => 'checkLibraryCards',
                    ],
                    [
                        'name' => 'dgcontent',
                        'label' => 'Overdrive Content',
                        'route' => 'overdrive-mycontent',
                        'icon' => 'overdrive',
                        'checkMethod' => 'checkOverdrive',
                    ],
                    [
                        'name' => 'history',
                        'label' => 'Search History',
                        'route' => 'search-history',
                        'icon' => 'search',
                        'checkMethod' => 'checkHistory',
                    ],
                    [
                        'name' => 'logout',
                        'label' => 'Log Out',
                        'route' => 'myresearch-logout',
                        'icon' => 'sign-out',
                        'checkMethod' => 'checkLogout',
                    ],
                ],
            ],
            'Lists' => [
                'label' => 'Your Lists',
                'id' => 'acc-menu-lists-header',
                'checkMethod' => 'checkUserlistMode',
                'MenuItems' => [
                    [
                        'template' => 'myresearch/menu-mylists.phtml',
                        'icon' => 'user-list',
                    ],
                    [
                        'name' => 'newlist',
                        'label' => 'Create a List',
                        'route' => 'editList',
                        'routeParams' => [
                            'id' => 'NEW',
                        ],
                        'icon' => 'ui-add',
                    ],
                ],
            ],
        ];
    }

    /**
     * Check whether to show favorites item
     *
     * @return bool
     */
    public function checkFavorites(): bool
    {
        return $this->accountCapabilities->getListSetting();
    }

    /**
     * Check whether to show checkedout item
     *
     * @return bool
     */
    public function checkCheckedout(): bool
    {
        return $this->checkIlsCapability('getMyTransactions');
    }

    /**
     * Check whether to show historicloans item
     *
     * @return bool
     */
    public function checkHistoricloans(): bool
    {
        return $this->checkIlsFunction('getMyTransactionHistory');
    }

    /**
     * Check whether to show holds item
     *
     * @return bool
     */
    public function checkHolds(): bool
    {
        return $this->checkIlsCapability('getMyHolds');
    }

    /**
     * Check whether to show storageRetrievalRequests item
     *
     * @return bool
     */
    public function checkStorageRetrievalRequests(): bool
    {
        return $this->checkIlsFunction('StorageRetrievalRequests');
    }

    /**
     * Check whether to show ILLRequests item
     *
     * @return bool
     */
    public function checkILLRequests(): bool
    {
        return $this->checkIlsFunction('ILLRequests');
    }

    /**
     * Check whether to show fines item
     *
     * @return bool
     */
    public function checkFines(): bool
    {
        return $this->checkIlsCapability('getMyFines');
    }

    /**
     * Check whether to show librarycards item
     *
     * @return bool
     */
    public function checkLibraryCards(): bool
    {
        return $this->isIlsOnline() && $this->getUser()
            && $this->accountCapabilities->libraryCardsEnabled();
    }

    /**
     * Check whether to show overdrive item
     *
     * @return bool
     */
    public function checkOverdrive(): bool
    {
        return $this->overdriveConnector?->isContentActive() ?? false;
    }

    /**
     * Check whether to show searchhistory item
     *
     * @return bool
     */
    public function checkHistory(): bool
    {
        return $this->accountCapabilities->getSavedSearchSetting() === 'enabled';
    }

    /**
     * Check whether to show logout item
     *
     * @return bool
     */
    public function checkLogout(): bool
    {
        return (bool)$this->getUser();
    }

    /**
     * Check whether to show user lists.
     *
     * @return bool
     */
    public function checkUserlistMode(): bool
    {
        return $this->authManager->getUserObject()
            && ($this->accountCapabilities->getListSetting() !== 'disabled');
    }

    /**
     * Check ILS connection capability
     *
     * @param string $capability Name of then ILS method to check
     *
     * @return bool
     */
    protected function checkIlsCapability(string $capability): bool
    {
        return $this->isIlsOnline()
            && $this->ilsConnection->checkCapability($capability, $this->getCapabilityParams());
    }

    /**
     * Check ILS function capability
     *
     * @param string $function The name of the ILS function to check.
     *
     * @return bool
     */
    protected function checkIlsFunction(string $function): bool
    {
        return $this->isIlsOnline()
            && $this->ilsConnection->checkFunction($function, $this->getCapabilityParams());
    }

    /**
     * Check whether the ILS connection is available
     *
     * @return bool
     */
    protected function isIlsOnline(): bool
    {
        return 'ils-none' !== $this->ilsConnection->getOfflineMode();
    }

    /**
     * Get params for checking ILS capability/function
     *
     * @return array
     */
    protected function getCapabilityParams(): array
    {
        try {
            $patron = $this->getUser() ? $this->ilsAuthenticator->storedCatalogLogin() : false;
        } catch (ILSException) {
            $patron = false;
        }
        return $patron ? compact('patron') : [];
    }

    /**
     * Get authenticated user
     *
     * @return ?UserEntityInterface Object if user is logged in, null otherwise.
     */
    protected function getUser(): ?UserEntityInterface
    {
        return $this->authManager->getUserObject();
    }
}
