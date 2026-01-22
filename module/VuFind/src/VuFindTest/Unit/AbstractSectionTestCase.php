<?php

/**
 * Abstract base class for navigation plugin tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Unit;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Config\AccountCapabilities;
use VuFind\Config\YamlReader;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\ILS\Connection;
use VuFind\Navigation\AbstractMenu;
use VuFind\Navigation\AccountMenu;
use VuFind\Navigation\AdminMenu;
use VuFind\Navigation\NavigationInterface;
use VuFind\Navigation\PluginManager as NavigationManager;
use VuFind\Section\Plugin\PluginManager as SectionManager;
use VuFind\Section\Plugin\SectionInterface;
use VuFind\Section\SectionService;
use VuFind\Section\SectionServiceInterface;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * Abstract base class for navigation plugin tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class AbstractSectionTestCase extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Get a container with section related services.
     *
     * @param string $userLocale      User locale (optional)
     * @param array  $fallbackLocales Fallback locale(s) (optional)
     *
     * @return MockContainer
     */
    protected function getContainerWithSectionRelatedServices(
        string $userLocale = 'en',
        array $fallbackLocales = ['en', 'fi']
    ): MockContainer {
        $container = new MockContainer($this);
        $this->addSectionRelatedServicesToContainer($container, $userLocale, $fallbackLocales);
        return $container;
    }

    /**
     * Add section related services to a mock container.
     *
     * @param MockContainer $container       Mock container
     * @param string        $userLocale      User locale (optional)
     * @param array         $fallbackLocales Fallback locale(s) (optional)
     *
     * @return void
     */
    protected function addSectionRelatedServicesToContainer(
        MockContainer $container,
        string $userLocale = 'en',
        array $fallbackLocales = ['en', 'fi']
    ): void {
        $container->set(YamlReader::class, new YamlReader($this->getPathResolver()));
        $sectionManager = new SectionManager($container);
        $container->set(SectionManager::class, $sectionManager);
        $navigationManager = new NavigationManager($container);
        $container->set(NavigationManager::class, $navigationManager);
        $service = new SectionService(
            $container->get(YamlReader::class),
            $container->get(SectionManager::class),
            $userLocale,
            $fallbackLocales,
        );
        $container->set(SectionServiceInterface::class, $service);
        $this->getAccountMenu($container);
        $this->getAdminMenu($container);
    }

    /**
     * Get a mock section service.
     *
     * @return \VuFind\Section\SectionServiceInterface
     */
    protected function getSectionService(): SectionServiceInterface
    {
        return $this->getContainerWithSectionRelatedServices()
            ->get(SectionServiceInterface::class);
    }

    /**
     * Set section plugin to a mock container.
     *
     * @param MockContainer    $container Mock container with section related services
     * @param SectionInterface $plugin    Section plugin
     * @param string           $alias     Plugin alias
     *
     * @return MockContainer
     */
    protected function setSectionPlugin(
        MockContainer $container,
        SectionInterface $plugin,
        string $alias,
    ): MockContainer {
        $pluginManager = $plugin instanceof NavigationInterface
            ? $container->get(NavigationManager::class)
            : $container->get(SectionManager::class);
        if ($plugin instanceof AbstractMenu) {
            // These will be added to the constructor in VuFind version 12.
            $sectionService = $container->get(SectionServiceInterface::class);
            $plugin->setSectionService($sectionService);
            $plugin->localizeSectionConfig();
        }
        if (!$allowOverride = $pluginManager->getAllowOverride()) {
            $pluginManager->setAllowOverride(true);
        }
        $pluginManager->setService($plugin::class, $plugin);
        $pluginManager->setAlias($alias, $plugin::class);
        $pluginManager->setAllowOverride($allowOverride);
        return $container;
    }

    /**
     * Get a mock AccountMenu.
     *
     * @param MockContainer $container    Mock container
     * @param array         $config       Configuration to use
     * @param array         $checkMethods Values to return for specific check methods
     *
     * @return AccountMenu
     */
    protected function getAccountMenu(
        MockContainer $container,
        array $config = [],
        array $checkMethods = [],
    ): AccountMenu {
        $mockAccountCapabilities = $this->createMock(AccountCapabilities::class);
        $mockAccountCapabilities->method('getListSetting')
            ->willReturn($checkMethods['checkFavorites'] ?? true);
        $mockAccountCapabilities->method('libraryCardsEnabled')
            ->willReturn($checkMethods['checkLibraryCards'] ?? true);
        $mockAccountCapabilities->method('getSavedSearchSetting')
            ->willReturn(($checkMethods['checkHistory'] ?? true) ? 'enabled' : 'disabled');
        $mockAccountCapabilities->method('getListSetting')
            ->willReturn(($checkMethods['checkUserlistMode'] ?? true) ? 'enabled' : 'disabled');
        $mockAccountCapabilities->method('getCommentSetting')
            ->willReturn(($checkMethods['checkUserContent'] ?? true) ? 'enabled' : 'disabled');

        $mockManager = $this->createMock(Manager::class);
        $mockManager->method('getUserObject')
            ->willReturn(($checkMethods['checkLogout'] ?? true) ? $this->createMock(UserEntityInterface::class) : null);

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->method('checkCapability')
            ->willReturnCallback(function ($method) use ($checkMethods) {
                return match ($method) {
                    'getMyTransactions' => $checkMethods['checkCheckedout'] ?? true,
                    'getMyHolds' => $checkMethods['checkHolds'] ?? true,
                    'getMyFines' => $checkMethods['checkFines'] ?? true,
                };
            });
        $mockConnection->method('checkFunction')
            ->willReturnCallback(function (string $function) use ($checkMethods) {
                return match ($function) {
                    'getMyTransactionHistory' => $checkMethods['checkHistoricloans'] ?? true,
                    'StorageRetrievalRequests' => $checkMethods['checkStorageRetrievalRequests'] ?? true,
                    'ILLRequests' => $checkMethods['checkILLRequests'] ?? true,
                };
            });

        $mockOverdriveConnector = $this->createMock(OverdriveConnector::class);
        $mockOverdriveConnector->method('isContentActive')
            ->willReturn($checkMethods['checkOverdrive'] ?? true);

        $accountMenu = new AccountMenu(
            $config,
            $mockAccountCapabilities,
            $mockManager,
            $mockConnection,
            $this->createMock(ILSAuthenticator::class),
            $mockOverdriveConnector
        );
        $this->setSectionPlugin($container, $accountMenu, 'accountMenu');
        return $accountMenu;
    }

    /**
     * Get all AccountMenu check methods.
     *
     * @param bool $value Value for the check methods to return
     *
     * @return array
     */
    protected function getAccountMenuCheckMethods(bool $value = true): array
    {
        return [
            'checkFavorites' => $value,
            'checkCheckedout' => $value,
            'checkHistoricloans' => $value,
            'checkHolds' => $value,
            'checkStorageRetrievalRequests' => $value,
            'checkILLRequests' => $value,
            'checkFines' => $value,
            'checkLibraryCards' => $value,
            'checkOverdrive' => $value,
            'checkHistory' => $value,
            'checkLogout' => $value,
            'checkUserlistMode' => $value,
            'checkUserContent' => $value,
        ];
    }

    /**
     * Get a mock AdminMenu.
     *
     * @param MockContainer $container    Mock container
     * @param array         $config       Configuration to use
     * @param array         $checkMethods Values to return for specific check methods
     *
     * @return AdminMenu
     */
    protected function getAdminMenu(
        MockContainer $container,
        array $config = [],
        array $checkMethods = [],
    ): AdminMenu {
        $adminMenu = new AdminMenu(
            $config,
            $checkMethods['checkShowOverdrive'] ?? true,
        );
        $this->setSectionPlugin($container, $adminMenu, 'adminMenu');
        return $adminMenu;
    }

    /**
     * Get all AdminMenu check methods.
     *
     * @param bool $value Value for the check methods to return
     *
     * @return array
     */
    protected function getAdminMenuCheckMethods(bool $value = true): array
    {
        return [
            'checkShowOverdrive' => $value,
        ];
    }
}
