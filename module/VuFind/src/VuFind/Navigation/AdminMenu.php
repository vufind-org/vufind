<?php

/**
 * Admin menu.
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Navigation;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Application;
use Symfony\Component\Yaml\Yaml;
use VuFind\Http\RouteHelper;
use VuFind\Role\PermissionManager;
use VuFind\Section\PageRegion;
use VuFind\Section\SectionServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function in_array;

/**
 * Admin menu.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdminMenu extends AbstractMenu
{
    /**
     * Show Overdrive admin menu item?
     *
     * @var bool
     */
    protected bool $showOverdriveAdminMenu;

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
     * @param array                   $overdriveConfig   Overdrive configuration
     */
    public function __construct(
        SectionServiceInterface $sectionService,
        #[Autowire(config: 'AdminMenu')]
        array $sectionConfig,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(service: 'Request')]
        Request $request,
        #[Autowire(service: 'Application')]
        Application $application,
        RouteHelper $routeHelper,
        PermissionManager $permissionManager,
        #[Autowire(config: 'Overdrive')]
        array $overdriveConfig,
    ) {
        $this->addRequiredSettings(
            [
                'label',
                'route',
            ],
            self::ITEM_CONTEXT
        );
        parent::__construct(
            $sectionService,
            $sectionConfig,
            $config,
            $request,
            $application,
            $routeHelper,
            $permissionManager
        );
        $this->showOverdriveAdminMenu
            = $overdriveConfig['Overdrive']['showOverdriveAdminMenu'] ?? false;
    }

    /**
     * Is the section active?
     *
     * An inactive section does not need to be rendered. An active section may
     * be rendered, but may still not be visible.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->checkAdminRoute()
            || ($this->checkAdminMenuOnAllPages() && $this->checkGlobalSidebarEnabled());
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
        $showInRegion = match ($pageRegion) {
            PageRegion::Sidebar => !$this->checkGlobalSidebarEnabled(),
            default => true
        };
        if (
            !$showInRegion
            || null === ($context = parent::getSectionContext($pageRegion))
        ) {
            return null;
        }
        if ($pageRegion !== PageRegion::MenuTabs) {
            $context['items'] = $this->getMenu()['Admin']['MenuItems'] ?? [];
        }
        return $context;
    }

    /**
     * Get menu tabs for the current request.
     *
     * @return array Tab items under 'submenuItems' key, or empty array if no tabs
     *
     * @todo Implement tabs as submenuItems in AdminMenu.yaml
     */
    public function getCurrentTabs(): array
    {
        if (!isset($this->currentTabs)) {
            $this->currentTabs = ('admin/tags' === $this->routeMatch->getMatchedRouteName())
                ? [
                    'submenuItems' => [
                        [
                            'name' => 'list',
                            'label' => 'List Tags',
                            'route' => 'admin/tags',
                            'routeParams' => ['action' => 'List'],
                        ],
                        [
                            'name' => 'manage',
                            'label' => 'Manage Tags',
                            'route' => 'admin/tags',
                            'routeParams' => ['action' => 'Manage'],
                        ],
                    ],
                ]
                : [];
        }
        return $this->currentTabs;
    }

    /**
     * Get parent path for the currently active item.
     *
     * @return array URIs of menu items in the path
     *
     * @todo Refactor after tabs implemented as submenuItems in AdminMenu.yaml
     */
    public function getActiveItemParentPath(): array
    {
        if (
            !isset($this->activeItemParentPath)
            && 'admin/tags' === $this->routeMatch->getMatchedRouteName()
            && in_array($this->routeMatch->getParam('action'), ['List', 'Manage'])
        ) {
            $this->activeItemParentPath = [$this->routeHelper->getUrlFromRoute('admin/tags')];
        }
        return $this->activeItemParentPath ?? [];
    }

    /**
     * Get default menu configuration.
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            Admin:
              label: 'VuFind Administration'
              id: admin-menu-header
              MenuItems:
                - name: home
                  label: Home
                  route: admin
                  icon: home

                - name: socialstats
                  label: Social Statistics
                  route: admin/social
                  icon: statistics

                - name: config
                  label: Configuration
                  route: admin/config
                  icon: config

                - name: maintenance
                  label: System Maintenance
                  route: admin/maintenance
                  icon: maintenance

                - name: tags
                  label: Tag Maintenance
                  route: admin/tags
                  icon: tags

                - name: feedback
                  label: Feedback Management
                  route: admin/feedback
                  icon: feedback

                - name: overdrive
                  label: od_admin_menu
                  route: admin/overdrive
                  icon: overdrive
                  checkMethod: checkShowOverdrive

                - name: payment
                  label: Online Payment
                  route: admin/payment
                  icon: payment

                - name: notices
                  label: Notices
                  route: admin/notices
                  icon: notification
            YAML;
        return Yaml::parse($yaml);
    }

    /**
     * Check whether to show Overdrive admin menu item.
     *
     * @return bool
     */
    public function checkShowOverdrive(): bool
    {
        return $this->showOverdriveAdminMenu;
    }
}
