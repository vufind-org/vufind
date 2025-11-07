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

namespace VuFindTest\Section;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Config\AccountCapabilities;
use VuFind\Config\YamlReader;
use VuFind\ILS\Connection;
use VuFind\Navigation\AbstractMenu;
use VuFind\Navigation\AccountMenu;
use VuFind\Navigation\AdminMenu;
use VuFind\Navigation\NavigationInterface;
use VuFind\Navigation\PluginManager as NavigationPluginManager;
use VuFind\Section\PluginManager as SectionPluginManager;
use VuFind\Section\SectionInterface;
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
     * @return MockContainer
     */
    protected function getContainerWithSectionRelatedServices(): MockContainer
    {
        $container = new MockContainer($this);
        $this->addSectionRelatedServicesToContainer($container);
        return $container;
    }

    /**
     * Add section related services to a mock container.
     *
     * @param MockContainer $container Mock container
     *
     * @return void
     */
    protected function addSectionRelatedServicesToContainer(MockContainer $container)
    {
        $container->set(YamlReader::class, new YAMLReader($this->getPathResolver()));
        $sectionPm = new SectionPluginManager($container);
        $container->set(SectionPluginManager::class, $sectionPm);
        $navigationPm = new NavigationPluginManager($container);
        $container->set(NavigationPluginManager::class, $navigationPm);
        $service = new SectionService(
            $container->get(YamlReader::class),
            $sectionPm,
            $navigationPm,
            'en',
            ['en', 'fi']
        );
        $container->set(SectionServiceInterface::class, $service);
        $this->getAccountMenu([], [], $container);
        $this->getAdminMenu([], [], $container);
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
     * @param SectionInterface $plugin    Section plugin
     * @param string           $alias     Plugin alias
     * @param ?MockContainer   $container Mock container with section related
     *                                    services (optional)
     *
     * @return MockContainer
     */
    protected function setSectionPlugin(
        SectionInterface $plugin,
        string $alias,
        ?MockContainer $container = null
    ): MockContainer {
        $container ??= $this->getContainerWithSectionRelatedServices();
        $pluginManager = $plugin instanceof NavigationInterface
            ? $container->get(NavigationPluginManager::class)
            : $container->get(SectionPluginManager::class);
        if ($plugin instanceof AbstractMenu) {
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
     * @param array          $config       Configuration to use
     * @param array          $checkMethods Values to return for specific check methods
     * @param ?MockContainer $container    Mock container (optional)
     *
     * @return AccountMenu
     */
    protected function getAccountMenu(
        array $config = [],
        array $checkMethods = [],
        ?MockContainer $container = null
    ): AccountMenu {
        $accountMenu = $this->getMockBuilder(AccountMenu::class)
            ->setConstructorArgs(
                [
                    $config,
                    $this->createMock(AccountCapabilities::class),
                    $this->createMock(Manager::class),
                    $this->createMock(Connection::class),
                    $this->createMock(ILSAuthenticator::class),
                    null,
                ]
            )
            ->onlyMethods(array_keys($this->getAccountMenuCheckMethods()))
            ->getMock();
        foreach ($this->getAccountMenuCheckMethods() as $checkMethod => $default) {
            $accountMenu->method($checkMethod)->willReturn($checkMethods[$checkMethod] ?? $default);
        }
        $this->setSectionPlugin($accountMenu, 'accountMenu', $container);
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
        ];
    }

    /**
     * Get a mock AdminMenu.
     *
     * @param array          $config       Configuration to use
     * @param array          $checkMethods Values to return for specific check methods
     * @param ?MockContainer $container    Mock container (optional)
     *
     * @return AdminMenu
     */
    protected function getAdminMenu(
        array $config = [],
        array $checkMethods = [],
        ?MockContainer $container = null
    ): AdminMenu {
        $adminMenu = $this->getMockBuilder(AdminMenu::class)
            ->setConstructorArgs(
                [
                    $config,
                    false,
                ]
            )
            ->onlyMethods(array_keys($this->getAdminMenuCheckMethods()))
            ->getMock();
        foreach ($this->getAdminMenuCheckMethods() as $checkMethod => $default) {
            $adminMenu->method($checkMethod)->willReturn($checkMethods[$checkMethod] ?? $default);
        }
        $this->setSectionPlugin($adminMenu, 'adminMenu', $container);
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
