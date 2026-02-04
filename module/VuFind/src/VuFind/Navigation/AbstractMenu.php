<?php

/**
 * Abstract menu base class
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
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

use Exception;
use VuFind\Exception\BadConfig;
use VuFind\Section\Plugin\AbstractBase;
use VuFind\Section\SectionServiceInterface;

/**
 * Abstract menu base class
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
     * Section service.
     *
     * @var SectionServiceInterface
     */
    protected SectionServiceInterface $sectionService;

    /**
     * Processed and filtered menu configuration returned by getMenu().
     *
     * @var ?array
     */
    protected ?array $menu;

    /**
     * Constructor.
     *
     * @param array $sectionConfig Section configuration
     * @param array $config        Main configuration
     */
    public function __construct(
        array $sectionConfig,
        protected array $config = []
    ) {
        $this->requiredSettings[self::GROUP_CONTEXT] ??= [];
        $this->requiredSettings[self::ITEM_CONTEXT] ??= [];
        $this->localizableSettings[self::GROUP_CONTEXT] ??= [];
        $this->localizableSettings[self::ITEM_CONTEXT] ??= [];
        $this->setSectionConfig($sectionConfig);
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
     * @throws Exception if section service has not been set
     */
    public function getSectionService(): SectionServiceInterface
    {
        // Section service must be set after constructing the object. This
        // requirement will be removed in VuFind version 12.
        if (!isset($this->sectionService)) {
            throw new Exception('Section service not set');
        }
        return $this->sectionService;
    }

    /**
     * Set section service.
     *
     * This method must be called after constructing the object. This
     * requirement will be removed in VuFind version 12.
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
     * This method should be called after setting the section service and if
     * setting the configuration outside the constructor. This requirement will
     * be removed in VuFind version 12.
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
     * @return array
     */
    public function getSectionContext(): array
    {
        return [
            'menu' => $this->getMenu(),
        ];
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
     * Get default menu configuration
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
}
