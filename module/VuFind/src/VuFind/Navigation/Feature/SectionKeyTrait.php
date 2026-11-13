<?php

/**
 * Section key trait.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation\Feature;

use VuFind\Exception\BadConfig;
use VuFind\Navigation\NavigationInterface;

use function in_array;
use function is_array;

/**
 * Section key trait.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait SectionKeyTrait
{
    /**
     * Process and filter groups.
     *
     * @param array $groups Groups to process and filter
     *
     * @return array
     */
    protected function processGroups(array $groups): array
    {
        $availableGroups = parent::processGroups($groups);
        return $this->processGroupSectionKey($availableGroups);
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
        // Groups containing a section key will be processed in a separate step.
        if (isset($group['section'])) {
            return $group;
        }
        return parent::processGroup($group);
    }

    /**
     * Process any groups with a section key.
     *
     * @param array $groups Groups
     *
     * @return array
     * @throws BadConfig
     */
    protected function processGroupSectionKey(array $groups): array
    {
        $processedGroups = [];
        foreach ($groups as $groupName => $group) {
            if (!$section = $group['section'] ?? false) {
                $processedGroups[$groupName] = $group;
                continue;
            }
            if (empty($this->filterAvailable([$group]))) {
                continue;
            }
            if (is_array($section)) {
                $groups = $section['groups'] ?? [];
                $section = $section['section'];
            } else {
                $groups = [];
            }
            $plugin = $this->sectionService->getSection($section);
            if ($plugin::class === $this::class) {
                throw new BadConfig('Specifying ' . $plugin::class . ' plugin sections is not possible');
            }
            if (!$plugin instanceof NavigationInterface) {
                throw new BadConfig($plugin::class . ' does not implement NavigationInterface');
            }
            if (!$plugin->isActive()) {
                continue;
            }
            foreach ($plugin->getMenu() as $pluginGroupName => $pluginGroup) {
                if (!empty($groups) && !in_array($pluginGroupName, $groups)) {
                    continue;
                }
                if (isset($processedGroups[$pluginGroupName])) {
                    throw new BadConfig('Group key clash in configuration: ' . $pluginGroupName);
                }
                $pluginGroup['__sectionPlugin'] = $plugin;
                $processedGroups[$pluginGroupName] = $pluginGroup;
            }
            if (!empty($currentTabs = $plugin->getCurrentTabs())) {
                $this->currentTabs = $currentTabs;
            }
            if (!empty($activeItemParentPath = $plugin->getActiveItemParentPath())) {
                $this->activeItemParentPath = $activeItemParentPath;
            }
        }
        return $processedGroups;
    }

    /**
     * Get menu tabs for the current request.
     *
     * @return array Tab items under 'submenuItems' key, or empty array if no tabs
     */
    public function getCurrentTabs(): array
    {
        // Menu processing will set possible current tabs from included sections.
        $this->getMenu();
        return parent::getCurrentTabs();
    }

    /**
     * Get parent path for the currently active item.
     *
     * @return array URIs of menu items in the path
     */
    public function getActiveItemParentPath(): array
    {
        // Menu processing will set possible parent path from included sections.
        $this->getMenu();
        return parent::getActiveItemParentPath();
    }
}
