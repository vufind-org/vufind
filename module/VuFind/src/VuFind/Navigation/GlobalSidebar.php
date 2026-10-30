<?php

/**
 * GlobalSidebar section plugin.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
use VuFind\Cookie\CookieManager;
use VuFind\Http\RouteHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Navigation\Feature\SectionKeyTrait;
use VuFind\Role\PermissionManager;
use VuFind\Section\PageRegion;
use VuFind\Section\SectionServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function in_array;

/**
 * GlobalSidebar section plugin.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GlobalSidebar extends AbstractMenu implements
    NavigationInterface,
    TranslatorAwareInterface
{
    use SectionKeyTrait;
    use TranslatorAwareTrait;

    public const GLOBAL_SIDEBAR_COLLAPSED_COOKIE = 'vufind_global_sidebar_collapsed';

    /**
     * Default collapsed state from configuration if a user cookie is not found.
     *
     * @var bool
     */
    protected bool $defaultCollapsedState;

    /**
     * Default icon to use if missing from configuration.
     *
     * @var string
     */
    protected string $defaultIcon;

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
     * @param CookieManager           $cookieManager     Cookie manager
     */
    public function __construct(
        SectionServiceInterface $sectionService,
        #[Autowire(config: 'GlobalSidebar')]
        array $sectionConfig,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(service: 'Request')]
        Request $request,
        #[Autowire(service: 'Application')]
        Application $application,
        RouteHelper $routeHelper,
        PermissionManager $permissionManager,
        protected CookieManager $cookieManager
    ) {
        parent::__construct(
            $sectionService,
            $sectionConfig,
            $config,
            $request,
            $application,
            $routeHelper,
            $permissionManager
        );
        $this->defaultCollapsedState = match ($config['Site']['globalSidebarDefaultAppearance'] ?? 'expanded') {
            'collapsed' => true,
            default => false,
        };
        $this->defaultIcon = $config['Site']['globalSidebarDefaultIcon'] ?? 'format-unknown';
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
        return $this->checkGlobalSidebarEnabled();
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
        if (
            !in_array($pageRegion, [PageRegion::GlobalSidebar, PageRegion::MenuTabs])
            || null === ($context = parent::getSectionContext($pageRegion))
        ) {
            return null;
        }
        if ($pageRegion === PageRegion::MenuTabs) {
            return $context;
        }
        if (empty($context['menu'])) {
            return null;
        }
        foreach ($context['menu'] as &$menuGroup) {
            foreach ($menuGroup['MenuItems'] as &$menuItem) {
                // Set default icon if icon and iconMethod are not set.
                if (!isset($menuItem['icon']) && !isset($menuItem['iconMethod'])) {
                    $menuItem['icon'] = $this->defaultIcon;
                }
                // Add attributes for tooltips shown when sidebar is collapsed.
                if (!isset($menuItem['label'])) {
                    continue;
                }
                $menuItem['linkAttributes'] = array_merge(
                    ($menuItem['linkAttributes'] ?? []),
                    [
                        'data-bs-toggle' => 'tooltip',
                        'data-bs-placement' => 'right',
                        'data-bs-title' => $this->translate($menuItem['label']),
                    ]
                );
            }
        }
        $cookies = $this->cookieManager->getCookies();
        $cookieValue = $cookies[self::GLOBAL_SIDEBAR_COLLAPSED_COOKIE] ?? $this->defaultCollapsedState;
        $context['collapsed'] = filter_var($cookieValue, FILTER_VALIDATE_BOOLEAN);
        return $context;
    }

    /**
     * Get default menu configuration.
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            AdminMenu:
              section: AdminMenu
            YAML;
        return Yaml::parse($yaml);
    }
}
