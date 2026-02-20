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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Navigation;

use Symfony\Component\Yaml\Yaml;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Config\AccountCapabilities;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;

use function array_key_exists;
use function count;
use function in_array;

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
     * @param array               $sectionConfig       Menu configuration
     * @param AccountCapabilities $accountCapabilities Account capabilities
     * @param Manager             $authManager         Authentication manager
     * @param Connection          $ilsConnection       ILS connection
     * @param ILSAuthenticator    $ilsAuthenticator    ILS authenticator
     * @param ?OverdriveConnector $overdriveConnector  Overdrive connector
     * @param array               $config              Main configuration
     */
    public function __construct(
        array $sectionConfig,
        protected AccountCapabilities $accountCapabilities,
        protected Manager $authManager,
        protected Connection $ilsConnection,
        protected ILSAuthenticator $ilsAuthenticator,
        protected ?OverdriveConnector $overdriveConnector,
        array $config = []
    ) {
        if (isset($sectionConfig['MenuItems'])) {
            // backward compatibility for outdated legacy AccountMenu configurations
            $default = static::getDefaultMenuConfig();
            $default['Account']['MenuItems'] = $sectionConfig['MenuItems'];
            $sectionConfig = $default;
        }
        $this->addRequiredSettings(
            [
                'label',
                'MenuItems',
            ],
            self::GROUP_CONTEXT
        );
        $this->addRequiredSettings(
            [
                'label',
                'route',
                'url',
                'template',
            ],
            self::ITEM_CONTEXT
        );
        $this->addLocalizableSettings(
            [
                'url',
            ],
            self::ITEM_CONTEXT
        );
        parent::__construct($sectionConfig, $config);
    }

    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string               $setting    Setting key
     * @param array<string, mixed> $context    Setting keys and values to be used in evaluation (optional)
     * @param string               $contextKey Key identifying the context (optional)
     *
     * @return bool
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        if ($contextKey === self::ITEM_CONTEXT) {
            // Conditional requirement checks.
            $diff = array_diff(['route', 'url', 'template'], [$setting]);
            if (count($diff) === 2) {
                // Setting is one of the three. If one of the two other settings
                // exists then this setting is optional.
                return count(array_intersect($diff, array_keys($context))) === 0;
            }
            if ($setting === 'label' && array_key_exists('template', $context)) {
                // Label is not required when a template setting exists.
                return false;
            }
        }
        return parent::isRequiredSetting($setting, $context, $contextKey);
    }

    /**
     * Return context variables that can be used to render the section.
     *
     * @return array
     */
    public function getSectionContext(): array
    {
        $context = parent::getSectionContext();
        // set items for legacy backward compatibility, might be removed in future releases
        $context['items'] = $this->getMenu()['Account']['MenuItems'] ?? [];
        return $context;
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            Account:
              label: Your Account
              id: acc-menu-acc-header
              class: account-menu
              MenuItems:
                - name: favorites
                  label: saved_items
                  route: myresearch-favorites
                  icon: user-favorites
                  checkMethod: checkFavorites
            
                - name: checkedout
                  label: Checked Out Items
                  route: myresearch-checkedout
                  icon: user-checked-out
                  status: true
                  checkMethod: checkCheckedout
            
                - name: historicloans
                  label: Loan History
                  route: checkouts-history
                  icon: user-loan-history
                  checkMethod: checkHistoricloans
            
                - name: holds
                  label: Holds and Recalls
                  route: holds-list
                  icon: user-holds
                  status: true
                  checkMethod: checkHolds
            
                - name: storageRetrievalRequests
                  label: Storage Retrieval Requests
                  route: myresearch-storageretrievalrequests
                  icon: user-storage-retrievals
                  status: true
                  checkMethod: checkStorageRetrievalRequests
            
                - name: ILLRequests
                  label: Interlibrary Loan Requests
                  route: myresearch-illrequests
                  icon: user-ill-requests
                  status: true
                  checkMethod: checkILLRequests
            
                - name: fines
                  label: Fines
                  route: myresearch-fines
                  status: true
                  checkMethod: checkFines
                  iconMethod: finesIcon
            
                - name: profile
                  label: Profile
                  route: myresearch-profile
                  icon: profile
            
                - name: librarycards
                  label: Library Cards
                  route: librarycards-home
                  icon: barcode
                  checkMethod: checkLibraryCards
            
                - name: dgcontent
                  label: Overdrive Content
                  route: overdrive-mycontent
                  icon: overdrive
                  checkMethod: checkOverdrive
            
                - name: history
                  label: Search History
                  route: search-history
                  icon: search
                  checkMethod: checkHistory
            
                - name: usercontent
                  label: user_content
                  route: myresearch-usercontent
                  icon: user-content
                  checkMethod: checkUserContent
            
                - name: logout
                  label: Log Out
                  route: myresearch-logout
                  icon: sign-out
                  checkMethod: checkLogout
            
            Lists:
              label: Your Lists
              id: acc-menu-lists-header
              checkMethod: checkUserlistMode
              MenuItems:
                - template: myresearch/menu-mylists.phtml
                  icon: user-list
            
                - name: newlist
                  label: Create a List
                  route: editList
                  routeParams:
                    id: NEW
                  icon: ui-add
            YAML;
        return Yaml::parse($yaml);
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
     * Check whether to show user content (comments, ratings, tags)
     *
     * @return bool
     */
    public function checkUserContent(): bool
    {
        return in_array(
            'enabled',
            [
                $this->accountCapabilities->getCommentSetting(),
                $this->accountCapabilities->getRatingSetting(),
                $this->accountCapabilities->getTagSetting(),
            ],
            true
        );
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

    /**
     * Create icon name for fines item
     *
     * @return string
     */
    public function finesIcon(): string
    {
        return 'currency-' . strtolower($this->config['Site']['defaultCurrency'] ?? 'usd');
    }
}
