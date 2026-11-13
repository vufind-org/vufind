<?php

/**
 * Abstract menu base class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2026.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Application;
use Laminas\Router\RouteMatch;
use VuFind\Exception\BadConfig;
use VuFind\Http\RouteHelper;
use VuFind\Role\PermissionManager;
use VuFind\Section\PageRegion;
use VuFind\Section\Plugin\AbstractBase;
use VuFind\Section\SectionServiceInterface;

/**
 * Abstract menu base class.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractMenu extends AbstractBase implements NavigationInterface
{
    /**
     * Menu group context key used with setting properties.
     */
    protected const GROUP_CONTEXT = 'group';

    /**
     * Menu item context key used with setting properties.
     */
    protected const ITEM_CONTEXT = 'item';

    /**
     * Route match.
     *
     * @var ?RouteMatch $routeMatch
     */
    protected ?RouteMatch $routeMatch;

    /**
     * Processed and filtered menu configuration returned by getMenu().
     *
     * @var ?array
     */
    protected ?array $menu;

    /**
     * Tabs for the current request.
     *
     * @var array
     */
    protected array $currentTabs;

    /**
     * Parent path for the currently active item.
     *
     * @var array
     */
    protected array $activeItemParentPath;

    /**
     * Constructor.
     *
     * @param SectionServiceInterface $sectionService    Section service
     * @param array                   $sectionConfig     Section configuration
     * @param array                   $config            Main configuration
     * @param Request                 $request           Request
     * @param Application             $application       Application
     * @param RouteHelper             $routeHelper       Route helper
     * @param PermissionManager       $permissionManager Permission manager
     */
    public function __construct(
        protected SectionServiceInterface $sectionService,
        array $sectionConfig,
        protected array $config,
        protected Request $request,
        Application $application,
        protected RouteHelper $routeHelper,
        protected PermissionManager $permissionManager
    ) {
        $this->requiredSettings[self::GROUP_CONTEXT] ??= [];
        $this->requiredSettings[self::ITEM_CONTEXT] ??= [];
        $this->localizableSettings[self::GROUP_CONTEXT] ??= [];
        $this->localizableSettings[self::ITEM_CONTEXT] ??= [];
        $this->routeMatch = $application->getMvcEvent()->getRouteMatch();
        $this->setSectionService($sectionService);
        $this->setSectionConfig($sectionConfig);
        $this->localizeSectionConfig();
    }

    /**
     * Set section configuration.
     *
     * @param array $sectionConfig Section configuration
     *
     * @return $this
     */
    public function setSectionConfig(array $sectionConfig): static
    {
        parent::setSectionConfig($sectionConfig);
        $this->menu = null;
        return $this;
    }

    /**
     * Get section service.
     *
     * @return SectionServiceInterface
     */
    public function getSectionService(): SectionServiceInterface
    {
        return $this->sectionService;
    }

    /**
     * Set section service.
     *
     * @param SectionServiceInterface $sectionService Section service
     *
     * @return static
     */
    public function setSectionService(SectionServiceInterface $sectionService): static
    {
        $this->sectionService = $sectionService;
        return $this;
    }

    /**
     * Localize section configuration.
     *
     * @return static
     */
    public function localizeSectionConfig(): static
    {
        $sectionService = $this->getSectionService();
        $config = $sectionService->localizeSettings($this);
        foreach ($config as $group => $settings) {
            $config[$group] = $sectionService->localizeSettings(
                $this,
                $settings,
                self::GROUP_CONTEXT
            );
            foreach ($settings['MenuItems'] ?? [] as $i => $menuItem) {
                $config[$group]['MenuItems'][$i] = $sectionService->localizeSettings(
                    $this,
                    $menuItem,
                    self::ITEM_CONTEXT
                );
            }
        }
        $this->setSectionConfig($config);
        return $this;
    }

    /**
     * Validate settings.
     *
     * @param array  $settings   Settings
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return array
     * @throws BadConfig
     */
    public function validateSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        parent::validateSettings($settings, $contextKey);
        if ($contextKey === self::DEFAULT_CONTEXT) {
            foreach ($settings as $group) {
                parent::validateSettings($group, self::GROUP_CONTEXT);
                foreach ($group['MenuItems'] ?? [] as $menuItem) {
                    parent::validateSettings($menuItem, self::ITEM_CONTEXT);
                }
            }
        }
        return $settings;
    }

    /**
     * Return context variables that can be used to render the section.
     *
     * @param ?PageRegion $pageRegion Page region in which the section is used (optional)
     *
     * @return ?array Context variables, or null if section should not be rendered
     */
    public function getSectionContext(?PageRegion $pageRegion = null): ?array
    {
        if (!$this->isActive()) {
            return null;
        }
        $context = [
            'requestUri' => $this->request->getRequestUri(),
            'activeItemParentPath' => $this->getActiveItemParentPath(),
        ];
        if ($pageRegion === PageRegion::MenuTabs) {
            return array_merge($context, $this->getCurrentTabs());
        }
        $context['menu'] = $this->getMenu();
        return $context;
    }

    /**
     * Get processed and filtered menu configuration with groups and items to
     * display.
     *
     * @return array
     */
    public function getMenu(): array
    {
        if (!isset($this->menu)) {
            $config = $this->getSectionConfig() ?: static::getDefaultMenuConfig();
            $this->menu = $this->processGroups($config);
        }
        return $this->menu;
    }

    /**
     * Get menu tabs for the current request.
     *
     * @return array Tab items under 'submenuItems' key, or empty array if no tabs
     */
    public function getCurrentTabs(): array
    {
        return $this->currentTabs ?? [];
    }

    /**
     * Get parent path for the currently active item.
     *
     * @return array URIs of menu items in the path
     */
    public function getActiveItemParentPath(): array
    {
        return $this->activeItemParentPath ?? [];
    }

    /**
     * Will the menu render items in the specified region?
     *
     * @param PageRegion $pageRegion Page region
     *
     * @return bool
     */
    public function hasPageRegion(PageRegion $pageRegion): bool
    {
        return null !== $this->getSectionContext($pageRegion);
    }

    /**
     * Helper method for global sidebar.
     *
     * @return bool
     */
    public function hasGlobalSidebar(): bool
    {
        return $this->hasPageRegion(PageRegion::GlobalSidebar);
    }

    /**
     * Helper method for menu tabs.
     *
     * @return bool
     */
    public function hasMenuTabs(): bool
    {
        return $this->hasPageRegion(PageRegion::MenuTabs);
    }

    /**
     * Helper method for sidebar.
     *
     * @return bool
     */
    public function hasSidebar(): bool
    {
        return $this->hasPageRegion(PageRegion::Sidebar);
    }

    /**
     * Process and filter groups.
     *
     * @param array $groups Groups to process and filter
     *
     * @return array
     */
    protected function processGroups(array $groups): array
    {
        $availableGroups = [];
        foreach ($this->filterAvailable($groups) as $groupName => $group) {
            if ($group = $this->processGroup($group)) {
                $availableGroups[$groupName] = $group;
            }
        }
        return $availableGroups;
    }

    /**
     * Process or filter group.
     *
     * @param array $group Group to process
     *
     * @return array|false Processed group or false if group should be filtered
     */
    protected function processGroup(array $group): array|false
    {
        $items = $this->processItems($group['MenuItems'] ?? []);
        // Skip groups without items to display.
        if (!empty($items)) {
            $group['MenuItems'] = $items;
            return $group;
        }
        return false;
    }

    /**
     * Process menu items.
     *
     * @param array $items Items to process
     *
     * @return array
     */
    protected function processItems(array $items): array
    {
        $items = $this->processItemSubmenuItemsKey($items);
        return $this->filterAvailable($items);
    }

    /**
     * Process any items with a 'submenuItems' key.
     *
     * @param array $items Items to process
     *
     * @return array
     */
    protected function processItemSubmenuItemsKey(array $items): array
    {
        foreach ($items as $i => $item) {
            if (isset($item['submenuItems'])) {
                $items[$i]['submenuItems'] = $this->filterAvailable($item['submenuItems']);
                if (empty($items[$i]['submenuItems'])) {
                    // Filter items with 'submenuItems' key but without submenu items to display.
                    unset($items[$i]);
                } else {
                    // Recursive check for nested submenuItems.
                    $items[$i]['submenuItems']
                        = $this->processItemSubmenuItemsKey($items[$i]['submenuItems']);
                }
            }
        }
        return $items;
    }

    /**
     * Get default menu configuration.
     *
     * @return array
     */
    abstract public static function getDefaultMenuConfig(): array;

    /**
     * Check whether to show site map page item.
     *
     * @return bool
     */
    public function checkSiteMapPage(): bool
    {
        return $this->config['Site']['siteMapPageEnabled'] ?? false;
    }

    /**
     * Check whether the global sidebar is enabled.
     *
     * @return bool
     */
    public function checkGlobalSidebarEnabled(): bool
    {
        return $this->config['Site']['globalSidebarEnabled'] ?? false;
    }

    /**
     * Check whether Admin module is enabled and user has admin module permission.
     *
     * @return bool
     */
    public function checkAdminEnabled(): bool
    {
        return ($this->config['Site']['admin_enabled'] ?? false)
            && $this->permissionManager->isAuthorized('access.AdminModule');
    }

    /**
     * Check whether on an admin route.
     *
     * @return bool
     */
    public function checkAdminRoute(): bool
    {
        return $this->routeMatch && $this->routeMatch->getParam('admin_route');
    }

    /**
     * Check whether admin menu should be shown on all pages.
     *
     * @return bool
     */
    public function checkAdminMenuOnAllPages(): bool
    {
        return $this->config['Site']['adminMenuOnAllPages'] ?? false;
    }
}
