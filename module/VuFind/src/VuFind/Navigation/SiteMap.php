<?php

/**
 * SiteMap section plugin.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Application;
use Symfony\Component\Yaml\Yaml;
use VuFind\Http\RouteHelper;
use VuFind\Navigation\Feature\SectionKeyTrait;
use VuFind\Role\PermissionManager;
use VuFind\Section\SectionServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function array_key_exists;
use function count;

/**
 * SiteMap section plugin.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SiteMap extends AbstractMenu
{
    use SectionKeyTrait;

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
        SectionServiceInterface $sectionService,
        #[Autowire(config: 'SiteMap.yaml')]
        array $sectionConfig,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(service: 'Request')]
        Request $request,
        #[Autowire(service: 'Application')]
        Application $application,
        RouteHelper $routeHelper,
        PermissionManager $permissionManager
    ) {
        $this->addRequiredSettings(
            [
                'MenuItems',
            ],
            self::GROUP_CONTEXT
        );
        $this->addRequiredSettings(
            [
                'label',
                'route',
                'url',
                'submenuItems',
            ],
            self::ITEM_CONTEXT
        );
        $this->addLocalizableSettings(
            [
                'url',
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
    }

    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string $setting    Setting
     * @param array  $context    Settings to be used in evaluation (optional)
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return bool
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        if (
            $contextKey === self::GROUP_CONTEXT
            && array_key_exists('section', $context)
        ) {
            // If a section setting exists, no other settings are required.
            return false;
        }
        if ($contextKey === self::ITEM_CONTEXT) {
            // Conditional requirement checks.
            $diff = array_diff(['route', 'url', 'submenuItems'], [$setting]);
            if (count($diff) === 2) {
                // Setting is one of the three. If one of the two other settings
                // exists then this setting is optional.
                return count(array_intersect($diff, array_keys($context))) === 0;
            }
        }
        return parent::isRequiredSetting($setting, $context, $contextKey);
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
            $menu = [];
            foreach (parent::getMenu() as $key => $group) {
                if (!empty($filtered = $this->filterAvailableForSiteMap($group))) {
                    $menu[$key] = $filtered;
                }
            }
            $this->menu = $menu;
        }
        return $this->menu;
    }

    /**
     * Get available items for the site map page.
     *
     * @param array $menuArray Items or item to filter
     *
     * @return array
     */
    protected function filterAvailableForSiteMap(array $menuArray): array
    {
        if ($menuArray['excludeFromSiteMapPage'] ?? false) {
            return [];
        }
        foreach (['MenuItems', 'submenuItems'] as $itemsKey) {
            if (isset($menuArray[$itemsKey])) {
                foreach ($menuArray[$itemsKey] as $i => $item) {
                    $menuArray[$itemsKey][$i] = $this->filterAvailableForSiteMap($item);
                    if (empty($menuArray[$itemsKey][$i])) {
                        unset($menuArray[$itemsKey][$i]);
                    }
                }
                if (empty($menuArray[$itemsKey])) {
                    // Filter items without menu or submenu items to display.
                    return [];
                }
            }
        }
        return $menuArray;
    }

    /**
     * Get default menu configuration.
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            HomePage:
              MenuItems:
                - label: 'Home Page'
                  route: home
            
            HeaderBar:
              section: HeaderBar
            
            FooterMenu:
              section: FooterMenu
            YAML;
        return Yaml::parse($yaml);
    }
}
