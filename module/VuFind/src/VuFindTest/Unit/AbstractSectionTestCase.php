<?php

/**
 * Abstract base class for section plugin tests.
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

use Laminas\Http\Request;
use Laminas\Mvc\View\Http\ViewManager;
use Laminas\View\Model\ViewModel;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Cart;
use VuFind\Config\AccountCapabilities;
use VuFind\Config\YamlReader;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\ILS\Connection;
use VuFind\Navigation\AbstractMenu;
use VuFind\Navigation\AccountMenu;
use VuFind\Navigation\AdminMenu;
use VuFind\Navigation\FooterMenu;
use VuFind\Navigation\HeaderBar;
use VuFind\Section\Plugin\PluginManager as SectionManager;
use VuFind\Section\Plugin\SectionInterface;
use VuFind\Section\SectionService;
use VuFind\Section\SectionServiceInterface;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * Abstract base class for section plugin tests.
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
        $service = new SectionService(
            $container->get(YamlReader::class),
            $container->get(SectionManager::class),
            $userLocale,
            $fallbackLocales,
        );
        $container->set(SectionServiceInterface::class, $service);
        $this->getAccountMenu($container);
        $this->getAdminMenu($container);
        $this->getFooterMenu($container);
        $this->getHeaderBar($container);
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
        $pluginManager = $container->get(SectionManager::class);
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
        $container->set(AccountCapabilities::class, $mockAccountCapabilities);

        $mockManager = $this->createMock(Manager::class);
        $mockManager->method('getUserObject')
            ->willReturn(($checkMethods['checkLogout'] ?? true) ? $this->createMock(UserEntityInterface::class) : null);
        $container->set(Manager::class, $mockManager);

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
        $container->set(Connection::class, $mockConnection);

        $mockOverdriveConnector = $this->createMock(OverdriveConnector::class);
        $mockOverdriveConnector->method('isContentActive')
            ->willReturn($checkMethods['checkOverdrive'] ?? true);
        $container->set(OverdriveConnector::class, $mockOverdriveConnector);

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

    /**
     * Get a mock FooterMenu.
     *
     * @param MockContainer $container    Mock container
     * @param array         $config       Configuration to use
     * @param array         $checkMethods Values to return for specific check methods
     *
     * @return FooterMenu
     */
    protected function getFooterMenu(
        MockContainer $container,
        array $config = [],
        array $checkMethods = []
    ): FooterMenu {
        $checkCookieSettings = $checkMethods['checkCookieSettings'] ?? true;

        $footer = new FooterMenu(
            $config,
            ['Cookies' => ['consent' => $checkCookieSettings]],
        );
        $this->setSectionPlugin($container, $footer, 'footer');
        return $footer;
    }

    /**
     * Get all FooterMenu check methods.
     *
     * @param bool $value Value for the check methods to return
     *
     * @return array
     */
    protected function getFooterMenuCheckMethods(bool $value = true): array
    {
        return [
            'checkCookieSettings' => $value,
        ];
    }

    /**
     * Get a mock HeaderBar.
     *
     * @param MockContainer $container    Mock container
     * @param array         $config       Configuration to use
     * @param array         $checkMethods Values to return for specific check methods
     *
     * @return HeaderBar
     */
    protected function getHeaderBar(
        MockContainer $container,
        array $config = [],
        array $checkMethods = []
    ): HeaderBar {
        $mockCart = $this->createMock(Cart::class);
        $mockCart->method('isActive')
            ->willReturn($checkMethods['checkCart'] ?? true);
        $container->set(Cart::class, $mockCart);

        $mockAuthManager = $this->createMock(Manager::class);
        $mockAuthManager->method('loginEnabled')
            ->willReturn($checkMethods['checkAccount'] ?? true);
        $container->set(Manager::class, $mockAuthManager);

        $checkThemeOptions = $checkMethods['checkThemeOptions'] ?? true;
        $mockViewModel = $this->createMock(ViewModel::class);
        $mockViewModel->method('getVariable')->with('themeOptions')
            ->willReturn($checkThemeOptions ? [[], []] : []);
        $mockViewManager = $this->createMock(ViewManager::class);
        $mockViewManager->method('getViewModel')
            ->willReturn($mockViewModel);
        $container->set('ViewManager', $mockViewManager);

        $checkAllLangs = $checkMethods['checkAllLangs'] ?? true;
        $mockLocaleSettings = $this->createMock(LocaleSettings::class);
        $mockLocaleSettings->method('getEnabledLocales')
            ->willReturn($checkAllLangs ? [[], []] : []);
        $container->set(LocaleSettings::class, $mockLocaleSettings);

        $mockRequest = $this->createMock(Request::class);
        $container->set('Request', $mockRequest);

        $header = new HeaderBar(
            $config,
            ['Feedback' => ['tab_enabled' => $checkMethods['checkFeedback'] ?? true]],
            $mockCart,
            $mockAuthManager,
            $mockViewModel,
            $mockLocaleSettings,
            $mockRequest,
        );
        $this->setSectionPlugin($container, $header, 'header');
        return $header;
    }

    /**
     * Get all HeaderBar check methods.
     *
     * @param bool $value Value for the check methods to return
     *
     * @return array
     */
    protected function getHeaderBarCheckMethods(bool $value = true): array
    {
        return [
            'checkFeedback' => $value,
            'checkCart' => $value,
            'checkAccount' => $value,
            'checkThemeOptions' => $value,
            'checkAllLangs' => $value,
        ];
    }
}
